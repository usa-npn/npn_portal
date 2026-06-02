require('dotenv').config();
const mysql = require('mysql2/promise');
const { Pool } = require('pg');

const mysqlPoolDefaults = {
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0,
  dateStrings: true,
  // 30s (was 10s): the single Node event loop can stall under concurrent heavy
  // processing, which delays mysql2's connect-timeout timer and trips spurious
  // ETIMEDOUTs even though RDS is healthy. A longer timeout rides out brief stalls.
  connectTimeout: 30000,
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

// Main pool for the fast metadata/CRUD endpoints. Generous limit (RDS max_connections
// is ~1284 with peak usage ~90, so there is ample headroom) so these short queries
// effectively never queue behind anything.
const npnPool = addQueryRetry(mysql.createPool({
  ...npnConfig,
  ...mysqlPoolDefaults,
  connectionLimit: 40,
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
  connectionLimit: 10,
  queueLimit: 20,
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
