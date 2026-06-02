require('dotenv').config();
const mysql = require('mysql2/promise');
const { Pool } = require('pg');

const mysqlPoolDefaults = {
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0,
  dateStrings: true,
  // 15s: a longer-than-default timeout rides out brief event-loop stalls (which
  // delay mysql2's connect-timeout timer and trip spurious ETIMEDOUTs even when RDS
  // is healthy), but not so long that a genuinely stuck connect hangs the request.
  // Clustering (multiple workers) is the primary mitigation for the stalls.
  connectTimeout: 15000,
  enableKeepAlive: true,
  keepAliveInitialDelay: 10000,
};

const TRANSIENT_CONNECT_ERRORS = new Set(['ETIMEDOUT', 'ECONNREFUSED']);
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

// Wrap a pool's query() so a transient CONNECTION-ESTABLISHMENT failure is retried
// rather than surfacing as a 500. We only retry ETIMEDOUT/ECONNREFUSED — errors that
// occur while acquiring/opening a connection, before the query is sent — so a retry
// cannot double-execute a statement. SQL errors and mid-flight errors pass through
// untouched. This absorbs the spurious connect ETIMEDOUTs caused by event-loop stalls
// (e.g. a burst of metadata requests behind a heavy stream).
function addQueryRetry(pool, { retries = 2, baseDelayMs = 250 } = {}) {
  const original = pool.query.bind(pool);
  pool.query = async (...args) => {
    for (let attempt = 0; ; attempt++) {
      try {
        return await original(...args);
      } catch (err) {
        if (attempt >= retries || !TRANSIENT_CONNECT_ERRORS.has(err && err.code)) throw err;
        await sleep(baseDelayMs * (attempt + 1));
      }
    }
  };
  return pool;
}

const npnConfig = {
  host: process.env.OPS_USANPN_HOST,
  user: process.env.OPS_USANPN_USER,
  password: process.env.OPS_USANPN_PASSWORD,
  database: process.env.OPS_USANPN_DATABASE,
};

// Main pool for the fast metadata/CRUD endpoints. PER-WORKER limit; with 2 cluster
// workers this is ~40 total, well under RDS max_connections (~1284, peak ~90).
// Kept modest per worker so a burst opens fewer simultaneous connections at once.
const npnPool = addQueryRetry(mysql.createPool({
  ...npnConfig,
  ...mysqlPoolDefaults,
  connectionLimit: 20,
}));

// Dedicated pool for the heavy streaming download endpoints (get_observations,
// get_summarized_data, get_site_level_data, get_magnitude_data,
// get_observation_group_details). The small limit is a deliberate concurrency cap:
// the scarce resource is DB CPU/IO, not connection slots, so this bounds how many
// heavy queries run at once. A bounded queue makes excess download requests fail
// fast instead of starving npnPool or hanging the whole API.
const npnDownloadPool = mysql.createPool({
  ...npnConfig,
  ...mysqlPoolDefaults,
  connectionLimit: 5,
  queueLimit: 10,
});

const drupalPool = addQueryRetry(mysql.createPool({
  host: process.env.OPS_DRUPAL_HOST,
  user: process.env.OPS_DRUPAL_USER,
  password: process.env.OPS_DRUPAL_PASSWORD,
  database: process.env.OPS_DRUPAL_DATABASE,
  ...mysqlPoolDefaults,
}));

const gisPool = new Pool({
  host: process.env.PGHOST,
  user: process.env.PGUSER,
  password: process.env.PGPASSWORD,
  database: process.env.PGDATABASE,
  port: process.env.PGPORT,
  max: 10,
  idleTimeoutMillis: 30000,
  connectionTimeoutMillis: 2000,
});

module.exports = { npnPool, npnDownloadPool, drupalPool, gisPool };
