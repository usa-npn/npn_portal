const express = require('express');
const router = express.Router();
const axios = require('axios');
const { npnPool } = require('../config/db');
const { verifyUser } = require('../utils/validateUser');
const { isNotSecure } = require('../utils/httpsCheck');
const checkProperty = require('../utils/checkProperty');
const cleanText = require('../utils/cleanText');

/**
 * Call Google Elevation API and return elevation in meters.
 */
async function getElevation(lat, lng) {
  const key = process.env.GOOGLE_ELEVATION_KEY;
  if (!key) return null;

  const url = `https://maps.googleapis.com/maps/api/elevation/json?locations=${lat},${lng}&key=${key}`;
  const response = await axios.get(url, { timeout: 10000 });
  const data = response.data;

  if (data.status === 'OK' && data.results && data.results.length > 0) {
    return data.results[0].elevation;
  }
  return null;
}

/**
 * Call Google Geocoding API and extract the US state abbreviation.
 */
async function getStateFromGeocoding(lat, lng) {
  const key = process.env.GOOGLE_GEOCODE_KEY;
  if (!key) return null;

  const url = `https://maps.googleapis.com/maps/api/geocode/json?latlng=${lat},${lng}&key=${key}`;
  const response = await axios.get(url, { timeout: 10000 });
  const data = response.data;

  if (data.status !== 'OK' || !data.results || data.results.length === 0) return null;

  for (const result of data.results) {
    for (const component of result.address_components) {
      if (component.types.includes('administrative_area_level_1')) {
        return component.short_name;
      }
    }
  }
  return null;
}

/**
 * Call the NPN timezone service and return the IANA timezone string.
 */
async function getTimezone(lat, lng) {
  const tzUrl = process.env.NPN_TIMEZONE_URL;
  if (!tzUrl) return null;

  const url = `${tzUrl}?latitude=${lat}&longitude=${lng}`;
  const response = await axios.get(url, { timeout: 10000 });
  const data = response.data;

  if (data && data.timeZone) return data.timeZone;
  if (typeof data === 'string') return data.trim();
  return null;
}

// POST /create_station
router.post('/create_station', async (req, res) => {
  try {
    if (isNotSecure(req)) {
      return res.status(403).json({
        response_code: 'failure',
        response_messages: ['HTTPS required'],
        station_id: null,
      });
    }

    const body = req.body;

    // Validate required fields
    if (
      !checkProperty(body, 'station_name') ||
      !checkProperty(body, 'latitude') ||
      !checkProperty(body, 'longitude')
    ) {
      return res.status(400).json({
        response_code: 'failure',
        response_messages: ['station_name, latitude, and longitude are required'],
        station_id: null,
      });
    }

    // Authenticate user
    const { user_id, user_pw, access_token, consumer_key } = body;
    const personId = await verifyUser(user_id, user_pw, access_token, consumer_key);

    if (!personId) {
      return res.status(401).json({
        response_code: 'failure',
        response_messages: ['Invalid credentials'],
        station_id: null,
      });
    }

    const stationName = cleanText(body.station_name.trim());
    const lat = parseFloat(body.latitude);
    const lng = parseFloat(body.longitude);

    // Validate lat/lng ranges
    if (lat < -90 || lat > 90) {
      return res.status(400).json({
        response_code: 'failure',
        response_messages: ['Latitude must be between -90 and 90'],
        station_id: null,
      });
    }

    if (lng < -180 || lng > 180) {
      return res.status(400).json({
        response_code: 'failure',
        response_messages: ['Longitude must be between -180 and 180'],
        station_id: null,
      });
    }

    // Check station name uniqueness for this user
    const [dupRows] = await npnPool.query(
      `SELECT Station_ID FROM usanpn2.Station
       WHERE Station_Name = ? AND Observer_ID = ?
       LIMIT 1`,
      [stationName, personId]
    );

    if (dupRows && dupRows.length > 0) {
      return res.status(409).json({
        response_code: 'failure',
        response_messages: ['You already have a station with that name'],
        station_id: null,
      });
    }

    // External API calls (non-fatal if they fail)
    let elevation = null;
    let state = null;
    let timezone = null;

    try {
      elevation = await getElevation(lat, lng);
    } catch (e) {
      console.warn('Elevation lookup failed:', e.message);
    }

    try {
      state = await getStateFromGeocoding(lat, lng);
    } catch (e) {
      console.warn('Geocoding lookup failed:', e.message);
    }

    try {
      timezone = await getTimezone(lat, lng);
    } catch (e) {
      console.warn('Timezone lookup failed:', e.message);
    }

    // Validate network_id if provided and check user admin role
    const networkId = checkProperty(body, 'network_id') ? parseInt(body.network_id, 10) : null;

    if (networkId) {
      const [roleRows] = await npnPool.query(
        `SELECT Role_ID FROM usanpn2.Network_Person
         WHERE Network_ID = ? AND Person_ID = ?
         LIMIT 1`,
        [networkId, personId]
      );

      if (!roleRows || roleRows.length === 0 || roleRows[0].Role_ID >= 2) {
        return res.status(403).json({
          response_code: 'failure',
          response_messages: ['You do not have admin access to that network'],
          station_id: null,
        });
      }
    }

    // Insert station
    const createDate = new Date();

    const [insertResult] = await npnPool.query(
      `INSERT INTO usanpn2.Station
         (Station_Name, Latitude, Longitude, Elevation_m, State, Time_Zone, Observer_ID, Create_Date)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
      [stationName, lat, lng, elevation, state, timezone, personId, createDate]
    );

    const newStationId = insertResult.insertId;

    // Link to network if provided
    if (networkId) {
      await npnPool.query(
        `INSERT INTO usanpn2.Network_Station (Network_ID, Station_ID) VALUES (?, ?)`,
        [networkId, newStationId]
      );
    }

    res.json({
      station_id: newStationId,
      response_messages: ['Station created successfully'],
      response_code: 'success',
    });
  } catch (err) {
    console.error('create_station error:', err.message);
    res.status(500).json({
      response_code: 'failure',
      response_messages: [err.message],
      station_id: null,
    });
  }
});

module.exports = router;
