require('dotenv').config();
const mysql = require('mysql2/promise');
const { Pool } = require('pg');

const mysqlPoolDefaults = {
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0,
  dateStrings: true,
  connectTimeout: 10000,
  enableKeepAlive: true,
  keepAliveInitialDelay: 10000,
};

const npnConfig = {
  host: process.env.OPS_USANPN_HOST,
  user: process.env.OPS_USANPN_USER,
  password: process.env.OPS_USANPN_PASSWORD,
  database: process.env.OPS_USANPN_DATABASE,
};

// Main pool for the fast metadata/CRUD endpoints. Generous limit (RDS max_connections
// is ~1284 with peak usage ~90, so there is ample headroom) so these short queries
// effectively never queue behind anything.
const npnPool = mysql.createPool({
  ...npnConfig,
  ...mysqlPoolDefaults,
  connectionLimit: 40,
});

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

const drupalPool = mysql.createPool({
  host: process.env.OPS_DRUPAL_HOST,
  user: process.env.OPS_DRUPAL_USER,
  password: process.env.OPS_DRUPAL_PASSWORD,
  database: process.env.OPS_DRUPAL_DATABASE,
  ...mysqlPoolDefaults,
});

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
