require('dotenv').config();
const mysql = require('mysql2/promise');
const { Pool } = require('pg');

const npnPool = mysql.createPool({
  host: process.env.OPS_USANPN_HOST,
  user: process.env.OPS_USANPN_USER,
  password: process.env.OPS_USANPN_PASSWORD,
  database: process.env.OPS_USANPN_DATABASE,
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0,
  dateStrings: true,
});

const drupalPool = mysql.createPool({
  host: process.env.OPS_DRUPAL_HOST,
  user: process.env.OPS_DRUPAL_USER,
  password: process.env.OPS_DRUPAL_PASSWORD,
  database: process.env.OPS_DRUPAL_DATABASE,
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0,
  dateStrings: true,
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
