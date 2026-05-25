const express = require('express');
const router = express.Router();
const { npnPool } = require('../config/db');
const { verifyUser } = require('../utils/validateUser');
const { isNotSecure } = require('../utils/httpsCheck');
const checkProperty = require('../utils/checkProperty');
const cleanText = require('../utils/cleanText');

// POST /create_individual
router.post('/create_individual', async (req, res) => {
  try {
    if (isNotSecure(req)) {
      return res.status(403).json({
        response_code: 'failure',
        response_messages: ['HTTPS required'],
        individual_id: null,
      });
    }

    const body = req.body;

    // Authenticate user
    const { user_id, user_pw, access_token, consumer_key } = body;
    const personId = await verifyUser(user_id, user_pw, access_token, consumer_key);

    if (!personId) {
      return res.status(401).json({
        response_code: 'failure',
        response_messages: ['Invalid credentials'],
        individual_id: null,
      });
    }

    // Validate required fields
    if (!checkProperty(body, 'station_id')) {
      return res.status(400).json({
        response_code: 'failure',
        response_messages: ['station_id is required'],
        individual_id: null,
      });
    }

    if (!checkProperty(body, 'individual_name')) {
      return res.status(400).json({
        response_code: 'failure',
        response_messages: ['individual_name is required'],
        individual_id: null,
      });
    }

    // Resolve species_id from species_id or species_num (ITIS)
    let speciesId = null;

    if (checkProperty(body, 'species_id')) {
      speciesId = parseInt(body.species_id, 10);
    } else if (checkProperty(body, 'species_num')) {
      const [spRows] = await npnPool.query(
        `SELECT Species_ID FROM usanpn2.Species WHERE ITIS_Taxonomic_SN = ? AND Active = 1 LIMIT 1`,
        [body.species_num]
      );
      if (!spRows || spRows.length === 0) {
        return res.status(400).json({
          response_code: 'failure',
          response_messages: ['Species not found by ITIS number'],
          individual_id: null,
        });
      }
      speciesId = spRows[0].Species_ID;
    } else {
      return res.status(400).json({
        response_code: 'failure',
        response_messages: ['species_id or species_num is required'],
        individual_id: null,
      });
    }

    const stationId = parseInt(body.station_id, 10);
    const individualName = cleanText(body.individual_name.trim());

    // Verify station belongs to user or user has network admin role
    const [stationRows] = await npnPool.query(
      `SELECT s.Station_ID, s.Observer_ID
       FROM usanpn2.Station s
       WHERE s.Station_ID = ? LIMIT 1`,
      [stationId]
    );

    if (!stationRows || stationRows.length === 0) {
      return res.status(404).json({
        response_code: 'failure',
        response_messages: ['Station not found'],
        individual_id: null,
      });
    }

    const station = stationRows[0];
    let authorized = station.Observer_ID === personId;

    if (!authorized) {
      // Check if user is admin of any network that contains this station
      const [adminRows] = await npnPool.query(
        `SELECT np.Network_ID
         FROM usanpn2.Network_Person np
         LEFT JOIN usanpn2.Network_Station ns ON ns.Network_ID = np.Network_ID
         WHERE np.Person_ID = ? AND ns.Station_ID = ? AND np.Role_ID < 2
         LIMIT 1`,
        [personId, stationId]
      );
      authorized = adminRows && adminRows.length > 0;
    }

    if (!authorized) {
      return res.status(403).json({
        response_code: 'failure',
        response_messages: ['You do not have permission to add individuals to this station'],
        individual_id: null,
      });
    }

    // Verify species exists
    const [spCheckRows] = await npnPool.query(
      `SELECT Species_ID, Kingdom FROM usanpn2.Species WHERE Species_ID = ? AND Active = 1 LIMIT 1`,
      [speciesId]
    );

    if (!spCheckRows || spCheckRows.length === 0) {
      return res.status(404).json({
        response_code: 'failure',
        response_messages: ['Species not found or inactive'],
        individual_id: null,
      });
    }

    const species = spCheckRows[0];

    // Check individual name uniqueness at station
    const [dupNameRows] = await npnPool.query(
      `SELECT Individual_ID FROM usanpn2.Station_Species_Individual
       WHERE Station_ID = ? AND Individual_UserStr = ?
       LIMIT 1`,
      [stationId, individualName]
    );

    if (dupNameRows && dupNameRows.length > 0) {
      return res.status(409).json({
        response_code: 'failure',
        response_messages: ['An individual with that name already exists at this station'],
        individual_id: null,
      });
    }

    // For animal species, check uniqueness of species at station
    if (species.Kingdom === 'Animalia') {
      const [animalDupRows] = await npnPool.query(
        `SELECT Individual_ID FROM usanpn2.Station_Species_Individual
         WHERE Station_ID = ? AND Species_ID = ?
         LIMIT 1`,
        [stationId, speciesId]
      );

      if (animalDupRows && animalDupRows.length > 0) {
        return res.status(409).json({
          response_code: 'failure',
          response_messages: ['An individual of this animal species already exists at this station'],
          individual_id: null,
        });
      }
    }

    // Validate shade_status if provided
    if (checkProperty(body, 'shade_status')) {
      const [shadeRows] = await npnPool.query(
        `SELECT Allowed_Value FROM usanpn2.Lookup
         WHERE Table_Name = 'Station_Species_Individual'
           AND Column_Name = 'Shade_Status'
           AND Allowed_Value = ?
         LIMIT 1`,
        [body.shade_status]
      );

      if (!shadeRows || shadeRows.length === 0) {
        return res.status(400).json({
          response_code: 'failure',
          response_messages: ['Invalid shade_status value'],
          individual_id: null,
        });
      }
    }

    // Get next seq_num for this station
    const [seqRows] = await npnPool.query(
      `SELECT COALESCE(MAX(Seq_Num), 0) + 1 AS next_seq
       FROM usanpn2.Station_Species_Individual
       WHERE Station_ID = ?`,
      [stationId]
    );

    const nextSeqNum = seqRows[0].next_seq;
    const shadeStatus = checkProperty(body, 'shade_status') ? body.shade_status : null;
    const createDate = new Date();

    // Insert individual
    const [insertResult] = await npnPool.query(
      `INSERT INTO usanpn2.Station_Species_Individual
         (Station_ID, Species_ID, Individual_UserStr, Shade_Status, Seq_Num, Active, Create_Date)
       VALUES (?, ?, ?, ?, ?, 1, ?)`,
      [stationId, speciesId, individualName, shadeStatus, nextSeqNum, createDate]
    );

    const newIndividualId = insertResult.insertId;

    res.json({
      individual_id: newIndividualId,
      response_messages: ['Individual created successfully'],
      response_code: 'success',
    });
  } catch (err) {
    console.error('create_individual error:', err.message);
    res.status(500).json({
      response_code: 'failure',
      response_messages: [err.message],
      individual_id: null,
    });
  }
});

module.exports = router;
