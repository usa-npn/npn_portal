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

const npnPool = mysql.createPool({
  host: process.env.OPS_USANPN_HOST,
  user: process.env.OPS_USANPN_USER,
  password: process.env.OPS_USANPN_PASSWORD,
  database: process.env.OPS_USANPN_DATABASE,
  ...mysqlPoolDefaults,
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

module.exports = { npnPool, drupalPool, gisPool };
