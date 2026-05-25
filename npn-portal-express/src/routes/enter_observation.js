const express = require('express');
const router = express.Router();
const { npnPool } = require('../config/db');
const { verifyUser } = require('../utils/validateUser');
const { isNotSecure } = require('../utils/httpsCheck');
const checkProperty = require('../utils/checkProperty');

/**
 * Validate a single observation value object.
 * Returns an array of error strings (empty if valid).
 */
function validateObservationValue(obs) {
  const errors = [];
  if (!checkProperty(obs, 'individual_id')) errors.push('individual_id is required');
  if (!checkProperty(obs, 'phenophase_id')) errors.push('phenophase_id is required');
  if (!checkProperty(obs, 'observation_date')) errors.push('observation_date is required');
  if (!checkProperty(obs, 'day_of_year', true) && !checkProperty(obs, 'observation_date')) {
    errors.push('day_of_year or observation_date is required');
  }
  // observation_value can be -1 (no), 0 (uncertain), 1 (yes)
  if (!Object.prototype.hasOwnProperty.call(obs, 'observation_value')) {
    errors.push('observation_value is required');
  }
  return errors;
}

/**
 * Insert a single observation record.
 * Returns { observation_id, observation_group_id, submission_id }.
 */
async function insertSingleObservation(personId, obs, connection) {
  const obsDate = obs.observation_date;
  const obsValue = parseInt(obs.observation_value, 10);
  const individualId = parseInt(obs.individual_id, 10);
  const phenophaseId = parseInt(obs.phenophase_id, 10);
  const comment = obs.comment || null;
  const now = new Date();

  // Get the observation group or create one
  let observationGroupId = obs.observation_group_id
    ? parseInt(obs.observation_group_id, 10)
    : null;

  if (!observationGroupId) {
    const [grpResult] = await connection.query(
      `INSERT INTO usanpn2.Observation_Group (Observer_ID, Create_Date) VALUES (?, ?)`,
      [personId, now]
    );
    observationGroupId = grpResult.insertId;
  }

  // Insert observation
  const [obsResult] = await connection.query(
    `INSERT INTO usanpn2.Observation
       (Individual_ID, Phenophase_ID, Observation_Date, Observation_Value,
        Observer_ID, Comment, Observation_Group_ID, Create_Date)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
    [individualId, phenophaseId, obsDate, obsValue, personId, comment, observationGroupId, now]
  );

  const observationId = obsResult.insertId;

  // Insert submission record
  const [subResult] = await connection.query(
    `INSERT INTO usanpn2.Submission
       (Observation_ID, Submission_DateTime, Observer_ID)
     VALUES (?, ?, ?)`,
    [observationId, now, personId]
  );

  const submissionId = subResult.insertId;

  return { observation_id: observationId, observation_group_id: observationGroupId, submission_id: submissionId };
}

// POST /enter_observation_set
router.post('/enter_observation_set', async (req, res) => {
  try {
    if (isNotSecure(req)) {
      return res.status(403).json({
        response_code: 'failure',
        response_messages: ['HTTPS required'],
        observations: [],
      });
    }

    const body = req.body;
    const { user_id, user_pw, access_token, consumer_key } = body;
    const personId = await verifyUser(user_id, user_pw, access_token, consumer_key);

    if (!personId) {
      return res.status(401).json({
        response_code: 'failure',
        response_messages: ['Invalid credentials'],
        observations: [],
      });
    }

    // Expect an array of observations in body.observations
    const observations = body.observations;

    if (!Array.isArray(observations) || observations.length === 0) {
      return res.status(400).json({
        response_code: 'failure',
        response_messages: ['observations array is required and must not be empty'],
        observations: [],
      });
    }

    // Validate all observations first
    const allErrors = [];
    observations.forEach((obs, idx) => {
      const errors = validateObservationValue(obs);
      errors.forEach(e => allErrors.push(`Observation ${idx}: ${e}`));
    });

    if (allErrors.length > 0) {
      return res.status(400).json({
        response_code: 'failure',
        response_messages: allErrors,
        observations: [],
      });
    }

    // Use a connection with transaction for atomicity
    const connection = await npnPool.getConnection();
    const results = [];

    try {
      await connection.beginTransaction();

      for (const obs of observations) {
        const result = await insertSingleObservation(personId, obs, connection);
        results.push(result);
      }

      await connection.commit();
    } catch (txErr) {
      await connection.rollback();
      connection.release();
      throw txErr;
    }

    connection.release();

    res.json({
      response_code: 'success',
      response_messages: [`${results.length} observation(s) submitted`],
      observations: results,
    });
  } catch (err) {
    console.error('enter_observation_set error:', err.message);
    res.status(500).json({
      response_code: 'failure',
      response_messages: [err.message],
      observations: [],
    });
  }
});

// POST /enter_observation
router.post('/enter_observation', async (req, res) => {
  try {
    if (isNotSecure(req)) {
      return res.status(403).json({
        response_code: 'failure',
        response_messages: ['HTTPS required'],
        observation_id: null,
      });
    }

    const body = req.body;
    const { user_id, user_pw, access_token, consumer_key } = body;
    const personId = await verifyUser(user_id, user_pw, access_token, consumer_key);

    if (!personId) {
      return res.status(401).json({
        response_code: 'failure',
        response_messages: ['Invalid credentials'],
        observation_id: null,
      });
    }

    const errors = validateObservationValue(body);
    if (errors.length > 0) {
      return res.status(400).json({
        response_code: 'failure',
        response_messages: errors,
        observation_id: null,
      });
    }

    const connection = await npnPool.getConnection();
    let result;

    try {
      await connection.beginTransaction();
      result = await insertSingleObservation(personId, body, connection);
      await connection.commit();
    } catch (txErr) {
      await connection.rollback();
      connection.release();
      throw txErr;
    }

    connection.release();

    res.json({
      response_code: 'success',
      response_messages: ['Observation submitted'],
      observation_id: result.observation_id,
      observation_group_id: result.observation_group_id,
      submission_id: result.submission_id,
    });
  } catch (err) {
    console.error('enter_observation error:', err.message);
    res.status(500).json({
      response_code: 'failure',
      response_messages: [err.message],
      observation_id: null,
    });
  }
});

// POST /enter_observation_details
router.post('/enter_observation_details', async (req, res) => {
  try {
    if (isNotSecure(req)) {
      return res.status(403).json({
        response_code: 'failure',
        response_messages: ['HTTPS required'],
      });
    }

    const body = req.body;
    const { user_id, user_pw, access_token, consumer_key } = body;
    const personId = await verifyUser(user_id, user_pw, access_token, consumer_key);

    if (!personId) {
      return res.status(401).json({
        response_code: 'failure',
        response_messages: ['Invalid credentials'],
      });
    }

    if (!checkProperty(body, 'observation_id')) {
      return res.status(400).json({
        response_code: 'failure',
        response_messages: ['observation_id is required'],
      });
    }

    const observationId = parseInt(body.observation_id, 10);

    // Verify observation belongs to this person
    const [obsRows] = await npnPool.query(
      `SELECT Observation_ID FROM usanpn2.Observation
       WHERE Observation_ID = ? AND Observer_ID = ?
       LIMIT 1`,
      [observationId, personId]
    );

    if (!obsRows || obsRows.length === 0) {
      return res.status(403).json({
        response_code: 'failure',
        response_messages: ['Observation not found or does not belong to this user'],
      });
    }

    const now = new Date();
    const rows = [];

    // Support multiple detail entries as an array or single object
    const detailsRaw = body.details;
    const details = Array.isArray(detailsRaw) ? detailsRaw : [body];

    for (const detail of details) {
      if (!checkProperty(detail, 'abundance_category_id') && !checkProperty(detail, 'intensity_id')) {
        continue;
      }

      const abundanceCategoryId = checkProperty(detail, 'abundance_category_id')
        ? parseInt(detail.abundance_category_id, 10)
        : null;
      const intensityId = checkProperty(detail, 'intensity_id')
        ? parseInt(detail.intensity_id, 10)
        : null;
      const valueText = detail.value_text || null;

      const [detailResult] = await npnPool.query(
        `INSERT INTO usanpn2.Observation_Detail
           (Observation_ID, Abundance_Category_ID, Intensity_ID, Value_Text, Create_Date)
         VALUES (?, ?, ?, ?, ?)`,
        [observationId, abundanceCategoryId, intensityId, valueText, now]
      );

      rows.push({ detail_id: detailResult.insertId });
    }

    res.json({
      response_code: 'success',
      response_messages: [`${rows.length} detail(s) recorded`],
      details: rows,
    });
  } catch (err) {
    console.error('enter_observation_details error:', err.message);
    res.status(500).json({
      response_code: 'failure',
      response_messages: [err.message],
    });
  }
});

// GET /get_observation_details
router.all('/get_observation_details', async (req, res) => {
  try {
    const p = req.query;

    if (!checkProperty(p, 'observation_id')) {
      return res.status(400).json({ error: 'observation_id is required' });
    }

    const observationId = parseInt(p.observation_id, 10);

    const [rows] = await npnPool.query(
      `SELECT
         od.Detail_ID,
         od.Observation_ID,
         od.Abundance_Category_ID,
         ac.Name AS abundance_category_name,
         od.Intensity_ID,
         od.Value_Text,
         od.Create_Date
       FROM usanpn2.Observation_Detail od
       LEFT JOIN usanpn2.Abundance_Category ac
         ON ac.Abundance_Category_ID = od.Abundance_Category_ID
       WHERE od.Observation_ID = ?
       ORDER BY od.Detail_ID ASC`,
      [observationId]
    );

    res.json(rows);
  } catch (err) {
    console.error('get_observation_details error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

module.exports = router;
