const express = require('express');
const router = express.Router();
const { npnPool } = require('../config/db');
const checkProperty = require('../utils/checkProperty');
const arrayWrap = require('../utils/arrayWrap');

// GET /get_individuals_of_species_at_stations
router.get('/get_individuals_of_species_at_stations', async (req, res) => {
  try {
    const p = req.query;

    const stationParam = p.station_ids || p.station_id;
    if (!checkProperty(p, 'species_id') || !stationParam) {
      return res.status(400).json({ error: 'species_id and station_id are required' });
    }

    const speciesId = parseInt(p.species_id, 10);
    const stationIds = arrayWrap(stationParam).map(id => parseInt(id, 10)).filter(id => !isNaN(id));

    if (stationIds.length === 0) {
      return res.status(400).json({ error: 'No valid station_ids provided' });
    }

    const conditions = [
      'ssi.Species_ID = ?',
      'ssi.Station_ID IN (?)',
      '(o.Deleted IS NULL OR o.Deleted <> 1)',
    ];
    const params = [speciesId, stationIds];

    if (checkProperty(p, 'year')) {
      conditions.push('YEAR(o.Observation_Date) = ?');
      params.push(parseInt(p.year, 10));
    }

    const sql = `
      SELECT
        ssi.Individual_UserStr,
        ssi.Individual_ID,
        COUNT(DISTINCT o.Observation_Date) AS cnt
      FROM usanpn2.Observation o
      LEFT JOIN usanpn2.Station_Species_Individual ssi
        ON ssi.Individual_ID = o.Individual_ID
      WHERE ${conditions.join(' AND ')}
      GROUP BY o.Individual_ID
    `;

    const [rows] = await npnPool.query(sql, params);

    const result = rows.map(r => ({
      individual_id: r.Individual_ID,
      individual_name: r.Individual_UserStr,
      number_observations: r.cnt,
    }));

    res.json(result);
  } catch (err) {
    console.error('get_individuals_of_species_at_stations error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_individuals_at_stations
router.get('/get_individuals_at_stations', async (req, res) => {
  try {
    const p = req.query;

    const stationParam = p.station_ids || p.station_id;
    if (!stationParam) {
      return res.status(400).json({ error: 'station_id is required' });
    }

    const stationIds = arrayWrap(stationParam).map(id => parseInt(id, 10)).filter(id => !isNaN(id));

    if (stationIds.length === 0) {
      return res.status(400).json({ error: 'No valid station_ids provided' });
    }

    const sql = `
      SELECT
        ssi.Individual_UserStr,
        ssi.Individual_ID,
        ssi.Species_ID,
        sp.Kingdom,
        ssi.Active,
        ssi.Seq_Num,
        iis.File_URL
      FROM usanpn2.Station_Species_Individual ssi
      LEFT JOIN usanpn2.Species sp ON sp.Species_ID = ssi.Species_ID
      LEFT JOIN usanpn2.Image_Station_Species_Individual issi
        ON issi.Individual_ID = ssi.Individual_ID
      LEFT JOIN usanpn2.Image_Image_Source iis
        ON iis.Image_Source_ID = 1 AND iis.Image_ID = issi.Image_ID
      WHERE ssi.Station_ID IN (?)
    `;

    const [rows] = await npnPool.query(sql, [stationIds]);

    const result = rows.map(r => ({
      individual_id: r.Individual_ID,
      individual_name: r.Individual_UserStr,
      species_id: r.Species_ID,
      kingdom: r.Kingdom,
      active: r.Active,
      seq_num: r.Seq_Num,
      file_url: r.File_URL,
    }));

    res.json(result);
  } catch (err) {
    console.error('get_individuals_at_stations error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_individual_by_id
router.get('/get_individual_by_id', async (req, res) => {
  try {
    if (!checkProperty(req.query, 'individual_id')) {
      return res.status(400).json({ error: 'individual_id is required' });
    }

    const { individual_id } = req.query;

    const [rows] = await npnPool.query(
      `SELECT ssi.Individual_UserStr, ssi.Species_ID, sp.Kingdom
       FROM usanpn2.Station_Species_Individual ssi
       LEFT JOIN usanpn2.Species sp ON sp.Species_ID = ssi.Species_ID
       WHERE ssi.Individual_ID = ?
       LIMIT 1`,
      [individual_id]
    );

    if (!rows || rows.length === 0) {
      return res.json(null);
    }

    res.json({
      individual_name: rows[0].Individual_UserStr,
      kingdom: rows[0].Kingdom,
      species_id: rows[0].Species_ID,
    });
  } catch (err) {
    console.error('get_individual_by_id error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_shade_statuses
router.get('/get_shade_statuses', async (req, res) => {
  try {
    const [rows] = await npnPool.query(
      `SELECT Allowed_Value
       FROM usanpn2.Lookup
       WHERE Table_Name = 'Station_Species_Individual'
         AND Column_Name = 'Shade_Status'
       ORDER BY Seq_Num`
    );

    const result = rows.map(r => ({ status: r.Allowed_Value }));
    res.json(result);
  } catch (err) {
    console.error('get_shade_statuses error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_plant_details
router.get('/get_plant_details', async (req, res) => {
  const p = req.query;
  let ids = [];

  if (checkProperty(p, 'individual_id')) {
    ids = arrayWrap(p.individual_id);
  } else if (checkProperty(p, 'ids')) {
    ids = arrayWrap(p.ids);
  } else {
    return res.status(400).json({ error: 'individual_id or ids is required' });
  }

  ids = ids.map(id => parseInt(id, 10)).filter(id => !isNaN(id));
  if (ids.length === 0) return res.status(400).json({ error: 'No valid IDs provided' });

  const sql = `SELECT * FROM usanpn2.vw_Plant_Details WHERE Individual_ID IN (?)`;

  let conn;
  try {
    conn = await npnPool.getConnection();
  } catch (err) {
    console.error('get_plant_details error:', err.message);
    return res.status(500).json({ error: err.message });
  }

  const rawConn = conn.connection;
  let released = false;
  const release = () => { if (!released) { released = true; conn.release(); } };

  res.setHeader('Content-Type', 'application/json');
  res.write('[');
  let first = true;

  const q = rawConn.query(sql, [ids]);

  q.on('result', (row) => {
    const chunk = (first ? '' : ',') + JSON.stringify(row);
    first = false;
    if (!res.write(chunk)) rawConn.pause();
  });

  res.on('drain', () => rawConn.resume());
  req.on('close', () => { released = true; rawConn.destroy(); });
  q.on('end', () => { res.write(']'); res.end(); release(); });
  q.on('error', (err) => {
    console.error('get_plant_details stream error:', err.message);
    if (!res.headersSent) res.status(500).json({ error: err.message });
    else res.end(']');
    release();
  });
});

module.exports = router;
