const express = require('express');
const router = express.Router();
const { npnPool } = require('../config/db');
const { verifyUser } = require('../utils/validateUser');
const { isNotSecure } = require('../utils/httpsCheck');
const checkProperty = require('../utils/checkProperty');
const arrayWrap = require('../utils/arrayWrap');

// GET /get_person_id_from_drupal_id
router.all('/get_person_id_from_drupal_id', async (req, res) => {
  try {
    if (!checkProperty(req.query, 'drupal_id')) {
      return res.status(400).json({ error: 'drupal_id is required' });
    }

    const { drupal_id } = req.query;
    const loadKey = `Drupal_${drupal_id}`;

    const [rows] = await npnPool.query(
      `SELECT Person_ID FROM usanpn2.Person WHERE Load_Key LIKE ? LIMIT 1`,
      [loadKey]
    );

    if (!rows || rows.length === 0) {
      return res.json({ person_id: null });
    }

    res.json({ person_id: rows[0].Person_ID });
  } catch (err) {
    console.error('get_person_id_from_drupal_id error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_user_update
router.all('/get_user_update', async (req, res) => {
  try {
    if (isNotSecure(req)) {
      return res.status(403).json({ timestamp: -1, error: 'HTTPS required' });
    }

    const { user_id, user_pw, access_token, consumer_key } = req.query;
    const personId = await verifyUser(user_id, user_pw, access_token, consumer_key);

    if (!personId) {
      return res.status(401).json({ timestamp: -1, error: 'Invalid credentials' });
    }

    const [rows] = await npnPool.query(
      `SELECT Last_Update FROM usanpn2.Person WHERE Person_ID = ? LIMIT 1`,
      [personId]
    );

    if (!rows || rows.length === 0) {
      return res.json({ timestamp: -1 });
    }

    res.json({ timestamp: rows[0].Last_Update });
  } catch (err) {
    console.error('get_user_update error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_observer_details
router.all('/get_observer_details', async (req, res) => {
  const p = req.query;
  let ids = [];

  if (checkProperty(p, 'person_id')) {
    ids = arrayWrap(p.person_id);
  } else if (checkProperty(p, 'ids')) {
    ids = arrayWrap(p.ids);
  } else {
    return res.status(400).json({ error: 'person_id or ids is required' });
  }

  ids = ids.map(id => parseInt(id, 10)).filter(id => !isNaN(id));
  if (ids.length === 0) return res.status(400).json({ error: 'No valid IDs provided' });

  const sql = `SELECT * FROM usanpn2.vw_Observer_Details WHERE Person_ID IN (?)`;

  let conn;
  try {
    conn = await npnPool.getConnection();
  } catch (err) {
    console.error('get_observer_details error:', err.message);
    return res.status(500).json({ error: err.message });
  }

  const rawConn = conn.connection;
  let released = false;
  const release = () => { if (!released) { released = true; conn.release(); } };

  res.setHeader('Content-Type', 'application/json');
  res.write('[');
  let first = true;
  let ended = false;

  const q = rawConn.query(sql, [ids]);

  q.on('result', (row) => {
    if (ended) return;
    const chunk = (first ? '' : ',') + JSON.stringify(
      Object.fromEntries(Object.entries(row).map(([k, v]) => [k.toLowerCase(), v]))
    );
    first = false;
    if (!res.write(chunk)) rawConn.pause();
  });

  res.on('drain', () => rawConn.resume());
  req.on('close', () => { ended = true; released = true; rawConn.destroy(); });
  q.on('end', () => { if (ended) return; ended = true; res.write(']'); res.end(); release(); });
  q.on('error', (err) => {
    console.error('get_observer_details stream error:', err.message);
    if (ended) { release(); return; }
    ended = true;
    if (!res.headersSent) res.status(500).json({ error: err.message });
    else { try { res.end(']'); } catch (_) {} }
    release();
  });
});

module.exports = router;
