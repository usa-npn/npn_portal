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
    let boundaryJoin = '';

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
      // boundary_id is a row in the Boundary table; test each station's point against
      // its Simple_WKT polygon with ST_Contains (matches CakePHP getStationsForBoundary).
      const boundaryId = parseInt(p.boundary_id, 10);
      if (isNaN(boundaryId) || boundaryId <= 0) {
        return res.status(400).json({ error: 'Invalid boundary_id' });
      }
      boundaryJoin = 'JOIN usanpn2.Boundary b ON b.Boundary_ID = ?';
      params.unshift(boundaryId); // join placeholder precedes the WHERE params
      conditions.push(
        "ST_Contains(ST_GeomFromText(b.Simple_WKT), ST_GeomFromText(CONCAT('POINT(', s.Longitude, ' ', s.Latitude, ')'))) = 1"
      );
    }

    if (conditions.length === 0) {
      return res.status(400).json({ error: 'Boundary parameters required (bbox or boundary_id)' });
    }

    const sql = `
      SELECT DISTINCT s.Station_ID
      FROM usanpn2.Station s
      ${boundaryJoin}
      WHERE ${conditions.join(' AND ')}
      ORDER BY s.Station_ID ASC
    `;

    // Legacy CakePHP getStationsForBoundary returned a flat array of Station_IDs, not
    // station objects. Clients (e.g. the viz tool) pass this result straight into a
    // station_id[] filter, so the response must stay a bare ID list for parity — an
    // object array stringifies to "[object Object]" downstream and breaks those calls.
    const [rows] = await npnPool.query(sql, params);
    res.json(rows.map(r => r.Station_ID));
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

    // WKT polygon mode
    if (checkProperty(p, 'wkt')) {
      const conditions = [
        `ST_Contains(ST_GeomFromText(?), ST_GeomFromText(CONCAT('POINT(', Longitude, ' ', Latitude, ')')))`
      ];
      const params = [p.wkt];

      if (checkProperty(p, 'person_id')) {
        conditions.push('Observer_ID = ?');
        params.push(p.person_id);
      }

      const [rows] = await npnPool.query(
        `SELECT Station_ID, Station_Name, Latitude, Longitude
         FROM usanpn2.Station
         WHERE ${conditions.join(' AND ')}
         ORDER BY Station_Name ASC`,
        params
      );
      return res.json(rows.map(r => ({
        station_id: r.Station_ID,
        station_name: r.Station_Name,
        latitude: r.Latitude,
        longitude: r.Longitude,
      })));
    }

    return res.status(400).json({ error: 'Provide bounding box, latitude/longitude/radius_km, or wkt' });
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

// --- Daymet fetch-and-cache helpers ---

const DAYMET_VARS = 'tmin,tmax,dayl,prcp';
// https://daymet.ornl.gov/single-pixel — current single-pixel extraction API
const DAYMET_API = 'https://daymet.ornl.gov/single-pixel/api/data';

const FIRST_DAY_WINTER = 335, LAST_DAY_WINTER = 59;
const FIRST_DAY_SPRING = 60,  LAST_DAY_SPRING  = 151;
const FIRST_DAY_SUMMER = 152, LAST_DAY_SUMMER  = 243;
const FIRST_DAY_FALL   = 244, LAST_DAY_FALL    = 334;
const GDD_BASE_C = 0, GDD_BASE_F = 32;

function daymetIsLeapYear(year) {
  return (year % 4 === 0 && year % 100 !== 0) || year % 400 === 0;
}

// Fetch one year of daily data from the Daymet API; returns array of day objects or null.
async function fetchDaymetAPI(lat, lon, year) {
  try {
    const url = `${DAYMET_API}?lat=${lat}&lon=${lon}&vars=${DAYMET_VARS}&years=${year}`;
    const resp = await axios.get(url, { timeout: 30000, responseType: 'text' });
    const lines = resp.data.split(/\r?\n/);
    // Find the header line (contains both 'year' and 'yday')
    const headerIdx = lines.findIndex(l => l.includes('year') && l.includes('yday'));
    if (headerIdx < 0) return null;
    // Strip units like "(deg c)" from header tokens
    const headers = lines[headerIdx].split(',').map(h => h.trim().split(' ')[0]);
    const days = [];
    for (let i = headerIdx + 1; i < lines.length; i++) {
      const line = lines[i].trim();
      if (!line) continue;
      const vals = line.split(',');
      const day = {};
      headers.forEach((h, idx) => { day[h] = parseFloat(vals[idx]); });
      days.push(day);
    }
    return days.length ? days : null;
  } catch (err) {
    console.error(`fetchDaymetAPI error (lat=${lat} lon=${lon} year=${year}):`, err.message);
    return null;
  }
}

function daymetGetDays(data, firstDay, lastDay, leap) {
  const start = firstDay + leap - 1;
  const length = lastDay - firstDay + leap + 1;
  return data.slice(start, start + length);
}

function daymetGetWinterDays(data, lastYearData, year) {
  const leap = daymetIsLeapYear(year) ? 1 : 0;
  const prevLeap = daymetIsLeapYear(year - 1) ? 1 : 0;
  const winterLast = lastYearData ? lastYearData.slice(FIRST_DAY_WINTER + prevLeap - 1) : [];
  const winterThis = data.slice(0, LAST_DAY_WINTER + leap);
  return [...winterLast, ...winterThis];
}

function avg(days, key) {
  if (!days.length) return 0;
  return days.reduce((s, d) => s + d[key], 0) / days.length;
}

function sum(days, key) {
  return days.reduce((s, d) => s + d[key], 0);
}

// Fetch from Daymet API and write to usanpn2.Daymet + usanpn2.Daymet_Data.
async function cacheDaymet(lat, lon, year, data, lastYearData) {
  const leap = daymetIsLeapYear(year) ? 1 : 0;
  const prevLeap = daymetIsLeapYear(year - 1) ? 1 : 0;

  const winterDays = daymetGetWinterDays(data, lastYearData, year);
  const springDays = daymetGetDays(data, FIRST_DAY_SPRING, LAST_DAY_SPRING, leap);
  const summerDays = daymetGetDays(data, FIRST_DAY_SUMMER, LAST_DAY_SUMMER, leap);
  const fallDays   = lastYearData
    ? daymetGetDays(lastYearData, FIRST_DAY_FALL, LAST_DAY_FALL, prevLeap)
    : [];

  const daymetRow = {
    Latitude: lat, Longitude: lon, Year: year,
    tmax_winter: avg(winterDays, 'tmax'), tmax_spring: avg(springDays, 'tmax'),
    tmax_summer: avg(summerDays, 'tmax'), tmax_fall:   avg(fallDays,   'tmax'),
    tmin_winter: avg(winterDays, 'tmin'), tmin_spring: avg(springDays, 'tmin'),
    tmin_summer: avg(summerDays, 'tmin'), tmin_fall:   avg(fallDays,   'tmin'),
    prcp_winter: sum(winterDays, 'prcp'), prcp_spring: sum(springDays, 'prcp'),
    prcp_summer: sum(summerDays, 'prcp'), prcp_fall:   sum(fallDays,   'prcp'),
    Update_Date: new Date().toISOString().replace('T', ' ').slice(0, 19),
  };

  const [[existing]] = await npnPool.query(
    'SELECT Daymet_ID FROM usanpn2.Daymet WHERE Latitude = ? AND Longitude = ? AND Year = ?',
    [lat, lon, year]
  );

  let daymetId;
  if (existing) {
    daymetId = existing.Daymet_ID;
    const { Latitude, Longitude, Year, ...updateFields } = daymetRow;
    await npnPool.query('UPDATE usanpn2.Daymet SET ? WHERE Daymet_ID = ?', [updateFields, daymetId]);
  } else {
    const [result] = await npnPool.query('INSERT INTO usanpn2.Daymet SET ?', [daymetRow]);
    daymetId = result.insertId;
  }

  let gdd = 0, gddf = 0, totalPrcp = 0;
  for (let i = 0; i < data.length; i++) {
    const day = data[i];
    const tmaxf = (day.tmax * 1.8) + 32;
    const tminf = (day.tmin * 1.8) + 32;
    const todayGdd  = (day.tmax + day.tmin) / 2 - GDD_BASE_C;
    const todayGddf = (tmaxf + tminf) / 2 - GDD_BASE_F;
    gdd       += todayGdd  < 0 ? 0 : todayGdd;
    gddf      += todayGddf < 0 ? 0 : todayGddf;
    totalPrcp += day.prcp;

    await npnPool.query(
      `INSERT INTO usanpn2.Daymet_Data
         (Daymet_ID, doy, tmax, tmin, tmaxf, tminf, prcp, daylength, gdd, gddf, acc_prcp)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE
         tmax=VALUES(tmax), tmin=VALUES(tmin), tmaxf=VALUES(tmaxf), tminf=VALUES(tminf),
         prcp=VALUES(prcp), daylength=VALUES(daylength), gdd=VALUES(gdd),
         gddf=VALUES(gddf), acc_prcp=VALUES(acc_prcp)`,
      [daymetId, i + 1, day.tmax, day.tmin, tmaxf, tminf,
       day.prcp, day.dayl, gdd, gddf, totalPrcp]
    );
  }

  return daymetId;
}

// GET /get_daymet_data
router.all('/get_daymet_data', async (req, res) => {
  try {
    const p = req.query;

    if (!checkProperty(p, 'station_id') || !checkProperty(p, 'year')) {
      return res.status(400).json({ error: 'station_id and year are required' });
    }

    const stationId = parseInt(p.station_id, 10);
    const year = parseInt(p.year, 10);
    const doy = checkProperty(p, 'doy') ? parseInt(p.doy, 10) : 1;

    const sql = `
      SELECT
        d.Daymet_ID, d.Latitude, d.Longitude, d.Year,
        d.tmax_winter, d.tmax_spring, d.tmax_summer, d.tmax_fall,
        d.tmin_winter, d.tmin_spring, d.tmin_summer, d.tmin_fall,
        d.prcp_winter, d.prcp_spring, d.prcp_summer, d.prcp_fall,
        d.Update_Date,
        dd.Daymet_Data_ID, dd.doy, dd.tmax, dd.tmin, dd.tmaxf, dd.tminf,
        dd.prcp, dd.daylength, dd.gdd, dd.gddf, dd.acc_prcp
      FROM usanpn2.Station s
      JOIN usanpn2.Daymet d
        ON s.Short_Longitude = d.Longitude AND s.Short_Latitude = d.Latitude AND d.Year = ?
      LEFT JOIN usanpn2.Daymet_Data dd
        ON d.Daymet_ID = dd.Daymet_ID AND dd.doy = ?
      WHERE s.Station_ID = ?
    `;

    let [rows] = await npnPool.query(sql, [year, doy, stationId]);

    if (!rows.length) {
      // DB miss — fetch from Daymet API, cache, then re-query
      const [[station]] = await npnPool.query(
        'SELECT Short_Latitude AS lat, Short_Longitude AS lon FROM usanpn2.Station WHERE Station_ID = ?',
        [stationId]
      );
      if (!station) return res.json(null);

      const { lat, lon } = station;
      const data = await fetchDaymetAPI(lat, lon, year);
      if (!data) return res.json(null);

      const lastYearData = await fetchDaymetAPI(lat, lon, year - 1);
      await cacheDaymet(lat, lon, year, data, lastYearData);

      [rows] = await npnPool.query(sql, [year, doy, stationId]);
      if (!rows.length) return res.json(null);
    }

    const row = rows[0];
    const result = {
      Daymet: {
        Daymet_ID: row.Daymet_ID,
        Latitude: row.Latitude,
        Longitude: row.Longitude,
        Year: row.Year,
        tmax_winter: row.tmax_winter,
        tmax_spring: row.tmax_spring,
        tmax_summer: row.tmax_summer,
        tmax_fall: row.tmax_fall,
        tmin_winter: row.tmin_winter,
        tmin_spring: row.tmin_spring,
        tmin_summer: row.tmin_summer,
        tmin_fall: row.tmin_fall,
        prcp_winter: row.prcp_winter,
        prcp_spring: row.prcp_spring,
        prcp_summer: row.prcp_summer,
        prcp_fall: row.prcp_fall,
        Update_Date: row.Update_Date,
      },
      daymet2daymetdata: row.Daymet_Data_ID != null ? [{
        Daymet_Data_ID: row.Daymet_Data_ID,
        Daymet_ID: row.Daymet_ID,
        doy: row.doy,
        tmax: row.tmax,
        tmin: row.tmin,
        tmaxf: row.tmaxf,
        tminf: row.tminf,
        prcp: row.prcp,
        daylength: row.daylength,
        gdd: row.gdd,
        gddf: row.gddf,
        acc_prcp: row.acc_prcp,
      }] : [],
    };

    res.json(result);
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
