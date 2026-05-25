const express = require('express');
const router = express.Router();
const axios = require('axios');
const { npnPool } = require('../config/db');
const { verifyUser } = require('../utils/validateUser');
const { isNotSecure } = require('../utils/httpsCheck');
const checkProperty = require('../utils/checkProperty');
const arrayWrap = require('../utils/arrayWrap');
const resolveBooleanText = require('../utils/resolveBooleanText');

// GET /get_all_stations
router.all('/get_all_stations', async (req, res) => {
  try {
    const p = req.query;
    const conditions = ['1=1'];
    const params = [];

    const joins = [
      `LEFT JOIN usanpn2.Network_Station ns_img ON ns_img.Station_ID = s.Station_ID`,
      `LEFT JOIN usanpn2.Image_Station img_s ON img_s.Station_ID = s.Station_ID`,
      `LEFT JOIN usanpn2.Image_Image_Source img_src ON img_src.Image_ID = img_s.Image_ID AND img_src.Image_Source_ID = 1`,
    ];

    const networkIdParam = checkProperty(p, 'network_ids') ? p.network_ids : (checkProperty(p, 'network_id') ? p.network_id : null);
    if (networkIdParam) {
      const networkIds = arrayWrap(networkIdParam).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
      if (networkIds.length > 0) {
        conditions.push('ns_img.Network_ID IN (?)');
        params.push(networkIds);
      }
    }

    if (checkProperty(p, 'person_id')) {
      conditions.push('s.Observer_ID = ?');
      params.push(p.person_id);
    }

    if (checkProperty(p, 'state_code')) {
      conditions.push('s.State = ?');
      params.push(p.state_code);
    }

    if (
      checkProperty(p, 'bottom_left_x') &&
      checkProperty(p, 'bottom_left_y') &&
      checkProperty(p, 'top_right_x') &&
      checkProperty(p, 'top_right_y')
    ) {
      conditions.push('s.Longitude BETWEEN ? AND ?');
      conditions.push('s.Latitude BETWEEN ? AND ?');
      params.push(parseFloat(p.bottom_left_x), parseFloat(p.top_right_x));
      params.push(parseFloat(p.bottom_left_y), parseFloat(p.top_right_y));
    }

    const sql = `
      SELECT DISTINCT
        s.Station_ID,
        s.Station_Name,
        s.Latitude,
        s.Longitude,
        ns_img.Network_ID,
        img_src.File_URL
      FROM usanpn2.Station s
      ${joins.join(' ')}
      WHERE ${conditions.join(' AND ')}
      ORDER BY s.Station_Name ASC
    `;

    const [rows] = await npnPool.query(sql, params);

    res.json(rows.map(r => ({
      station_id: r.Station_ID,
      station_name: r.Station_Name,
      latitude: r.Latitude,
      longitude: r.Longitude,
      network_id: r.Network_ID || "",
      file_url: r.File_URL || null,
    })));
  } catch (err) {
    console.error('get_all_stations error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_stations_for_user
router.all('/get_stations_for_user', async (req, res) => {
  try {
    if (isNotSecure(req)) {
      return res.status(403).json({ error: 'HTTPS required' });
    }

    const { user_id, user_pw, access_token, consumer_key } = req.query;
    const personId = await verifyUser(user_id, user_pw, access_token, consumer_key);

    if (!personId) {
      return res.status(401).json({ error: 'Invalid credentials' });
    }

    const [rows] = await npnPool.query(
      `SELECT * FROM usanpn2.vw_Station_Details WHERE Observer_ID = ? ORDER BY Station_Name ASC`,
      [personId]
    );

    res.json(rows);
  } catch (err) {
    console.error('get_stations_for_user error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_stations_for_boundary
router.all('/get_stations_for_boundary', async (req, res) => {
  try {
    const p = req.query;
    const conditions = [];
    const params = [];

    if (
      checkProperty(p, 'bottom_left_x') &&
      checkProperty(p, 'bottom_left_y') &&
      checkProperty(p, 'top_right_x') &&
      checkProperty(p, 'top_right_y')
    ) {
      conditions.push('s.Longitude BETWEEN ? AND ?');
      conditions.push('s.Latitude BETWEEN ? AND ?');
      params.push(parseFloat(p.bottom_left_x), parseFloat(p.top_right_x));
      params.push(parseFloat(p.bottom_left_y), parseFloat(p.top_right_y));
    }

    if (checkProperty(p, 'boundary_id')) {
      // boundary_id references a predefined geographic boundary
      conditions.push('s.Boundary_ID = ?');
      params.push(p.boundary_id);
    }

    if (conditions.length === 0) {
      return res.status(400).json({ error: 'Boundary parameters required (bbox or boundary_id)' });
    }

    const sql = `
      SELECT DISTINCT
        s.Station_ID, s.Station_Name, s.Latitude, s.Longitude, s.Observer_ID, s.State
      FROM usanpn2.Station s
      WHERE ${conditions.join(' AND ')}
      ORDER BY s.Station_Name ASC
    `;

    const [rows] = await npnPool.query(sql, params);
    res.json(rows.map(r => ({
      station_id: r.Station_ID,
      station_name: r.Station_Name,
      latitude: r.Latitude,
      longitude: r.Longitude,
      observer_id: r.Observer_ID,
      state: r.State,
    })));
  } catch (err) {
    console.error('get_stations_for_boundary error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_stations_by_location
router.all('/get_stations_by_location', async (req, res) => {
  try {
    const p = req.query;

    // Bounding box mode
    if (
      checkProperty(p, 'bottom_left_x') &&
      checkProperty(p, 'bottom_left_y') &&
      checkProperty(p, 'top_right_x') &&
      checkProperty(p, 'top_right_y')
    ) {
      const [rows] = await npnPool.query(
        `SELECT * FROM usanpn2.vw_Station_Details
         WHERE Longitude BETWEEN ? AND ?
           AND Latitude BETWEEN ? AND ?
         ORDER BY Station_Name ASC`,
        [
          parseFloat(p.bottom_left_x),
          parseFloat(p.top_right_x),
          parseFloat(p.bottom_left_y),
          parseFloat(p.top_right_y),
        ]
      );
      return res.json(rows);
    }

    // Radius mode
    if (checkProperty(p, 'latitude') && checkProperty(p, 'longitude') && checkProperty(p, 'radius_km')) {
      const lat = parseFloat(p.latitude);
      const lng = parseFloat(p.longitude);
      const radius = parseFloat(p.radius_km);

      // Haversine formula approximation via bounding box first, then filter
      const latDelta = radius / 111.0;
      const lngDelta = radius / (111.0 * Math.cos((lat * Math.PI) / 180));

      const [rows] = await npnPool.query(
        `SELECT *,
           (6371 * ACOS(
             COS(RADIANS(?)) * COS(RADIANS(Latitude)) *
             COS(RADIANS(Longitude) - RADIANS(?)) +
             SIN(RADIANS(?)) * SIN(RADIANS(Latitude))
           )) AS distance_km
         FROM usanpn2.vw_Station_Details
         WHERE Latitude BETWEEN ? AND ?
           AND Longitude BETWEEN ? AND ?
         HAVING distance_km <= ?
         ORDER BY distance_km ASC`,
        [lat, lng, lat, lat - latDelta, lat + latDelta, lng - lngDelta, lng + lngDelta, radius]
      );
      return res.json(rows);
    }

    return res.status(400).json({ error: 'Provide bounding box or latitude/longitude/radius_km' });
  } catch (err) {
    console.error('get_stations_by_location error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_stations_by_id
router.all('/get_stations_by_id', async (req, res) => {
  try {
    const p = req.query;

    if (!checkProperty(p, 'station_id')) {
      return res.json({});
    }

    const ids = arrayWrap(p.station_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    if (ids.length === 0) return res.json({});

    const [rows] = await npnPool.query(
      `SELECT Station_Name, Latitude, Longitude, Station_ID
       FROM usanpn2.Station
       WHERE Station_ID IN (?)`,
      [ids]
    );

    res.json(rows.map(r => ({
      latitude: r.Latitude,
      longitude: r.Longitude,
      station_name: r.Station_Name,
      station_id: r.Station_ID,
    })));
  } catch (err) {
    console.error('get_stations_by_id error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_stations_with_species
router.all('/get_stations_with_species', async (req, res) => {
  try {
    if (!checkProperty(req.query, 'species_id')) {
      return res.status(400).json({ error: 'species_id is required' });
    }

    const speciesIds = arrayWrap(req.query.species_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));

    const [rows] = await npnPool.query(
      `SELECT DISTINCT
         s.Station_ID, s.Station_Name, s.Latitude, s.Longitude, s.State, s.Observer_ID
       FROM usanpn2.Station s
       LEFT JOIN usanpn2.Station_Species_Individual ssi ON ssi.Station_ID = s.Station_ID
       WHERE ssi.Species_ID IN (?)
       ORDER BY s.Station_Name ASC`,
      [speciesIds]
    );

    res.json(rows.map(r => ({
      station_id: r.Station_ID,
      station_name: r.Station_Name,
      latitude: r.Latitude,
      longitude: r.Longitude,
      state: r.State,
      observer_id: r.Observer_ID,
    })));
  } catch (err) {
    console.error('get_stations_with_species error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_station_count_by_state
router.all('/get_station_count_by_state', async (req, res) => {
  try {
    const [rows] = await npnPool.query(
      `SELECT State, COUNT(*) AS cnt FROM usanpn2.Station WHERE State IS NOT NULL GROUP BY State ORDER BY State ASC`
    );
    res.json(rows.map(r => ({ state: r.State, number_stations: r.cnt })));
  } catch (err) {
    console.error('get_station_count_by_state error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_stations_for_network
router.all('/get_stations_for_network', async (req, res) => {
  try {
    if (!checkProperty(req.query, 'network_id')) {
      return res.status(400).json({ error: 'network_id is required' });
    }

    const networkIds = arrayWrap(req.query.network_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));

    const [rows] = await npnPool.query(
      `SELECT DISTINCT
         s.Station_ID, s.Station_Name, s.Latitude, s.Longitude,
         ns.Network_ID,
         img_src.File_URL
       FROM usanpn2.Station s
       LEFT JOIN usanpn2.Network_Station ns ON ns.Station_ID = s.Station_ID
       LEFT JOIN usanpn2.Image_Station img_s ON img_s.Station_ID = s.Station_ID
       LEFT JOIN usanpn2.Image_Image_Source img_src ON img_src.Image_ID = img_s.Image_ID AND img_src.Image_Source_ID = 1
       WHERE ns.Network_ID IN (?)
       ORDER BY s.Station_Name ASC`,
      [networkIds]
    );

    res.json(rows.map(r => ({
      station_id: r.Station_ID,
      station_name: r.Station_Name,
      latitude: r.Latitude,
      longitude: r.Longitude,
      network_id: r.Network_ID || "",
      file_url: r.File_URL || null,
    })));
  } catch (err) {
    console.error('get_stations_for_network error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_states
router.all('/get_states', async (req, res) => {
  try {
    const [rows] = await npnPool.query(
      `SELECT State_ID, State_Code, State_Name FROM usanpn2.State_List ORDER BY State_Name ASC`
    );
    res.json(rows.map(r => ({
      state_code: r.State_Code,
      state_name: r.State_Name,
      state_id: r.State_ID,
    })));
  } catch (err) {
    console.error('get_states error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_station_details
router.all('/get_station_details', async (req, res) => {
  const p = req.query;
  let ids = [];

  if (checkProperty(p, 'ids')) {
    ids = arrayWrap(p.ids);
  } else if (checkProperty(p, 'station_id')) {
    ids = arrayWrap(p.station_id);
  } else {
    return res.status(400).json({ error: 'ids or station_id is required' });
  }

  ids = ids.map(id => parseInt(id, 10)).filter(id => !isNaN(id));
  if (ids.length === 0) return res.status(400).json({ error: 'No valid IDs provided' });

  const sql = `SELECT * FROM usanpn2.vw_Station_Details WHERE Site_ID IN (?)`;

  let conn;
  try {
    conn = await npnPool.getConnection();
  } catch (err) {
    console.error('get_station_details error:', err.message);
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
    const chunk = (first ? '' : ',') + JSON.stringify(row);
    first = false;
    if (!res.write(chunk)) rawConn.pause();
  });

  res.on('drain', () => rawConn.resume());
  req.on('close', () => { ended = true; released = true; rawConn.destroy(); });
  q.on('end', () => { if (ended) return; ended = true; res.write(']'); res.end(); release(); });
  q.on('error', (err) => {
    console.error('get_station_details stream error:', err.message);
    if (ended) { release(); return; }
    ended = true;
    if (!res.headersSent) res.status(500).json({ error: err.message });
    else { try { res.end(']'); } catch (_) {} }
    release();
  });
});

// GET /get_daymet_data
router.all('/get_daymet_data', async (req, res) => {
  try {
    const p = req.query;

    if (!checkProperty(p, 'station_id')) {
      return res.status(400).json({ error: 'station_id is required' });
    }

    const stationId = parseInt(p.station_id, 10);
    const conditions = ['Station_ID = ?'];
    const params = [stationId];

    if (checkProperty(p, 'start_date')) {
      conditions.push('Data_Date >= ?');
      params.push(p.start_date);
    }

    if (checkProperty(p, 'end_date')) {
      conditions.push('Data_Date <= ?');
      params.push(p.end_date);
    }

    const sql = `
      SELECT Station_ID, Data_Date, Tmax, Tmin, Prcp, Srad, Vp, Swe, Dayl
      FROM usanpn2.Daymet_Data
      WHERE ${conditions.join(' AND ')}
      ORDER BY Data_Date ASC
    `;

    const [rows] = await npnPool.query(sql, params);
    res.json(rows);
  } catch (err) {
    console.error('get_daymet_data error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_time_series
router.all('/get_time_series', async (req, res) => {
  try {
    const p = req.query;

    if (!checkProperty(p, 'station_id')) {
      return res.status(400).json({ error: 'station_id is required' });
    }

    const stationId = parseInt(p.station_id, 10);
    const conditions = ['Station_ID = ?'];
    const params = [stationId];

    if (checkProperty(p, 'start_date')) {
      conditions.push('Observation_Date >= ?');
      params.push(p.start_date);
    }

    if (checkProperty(p, 'end_date')) {
      conditions.push('Observation_Date <= ?');
      params.push(p.end_date);
    }

    const sql = `
      SELECT Station_ID, Observation_Date, Tmax, Tmin, Prcp
      FROM usanpn2.Station_Time_Series
      WHERE ${conditions.join(' AND ')}
      ORDER BY Observation_Date ASC
    `;

    const [rows] = await npnPool.query(sql, params);
    res.json(rows);
  } catch (err) {
    console.error('get_time_series error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

module.exports = router;
