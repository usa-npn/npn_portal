const express = require('express');
const router = express.Router();
const { npnPool } = require('../config/db');
const { verifyUser } = require('../utils/validateUser');
const { isNotSecure } = require('../utils/httpsCheck');

// GET /get_last_submission_for_person
router.all('/get_last_submission_for_person', async (req, res) => {
  try {
    if (isNotSecure(req)) {
      return res.status(403).json({
        response_code: 'failure',
        response_messages: ['HTTPS required'],
        date: null,
      });
    }

    const { user_id, user_pw, access_token, consumer_key } = req.query;
    const personId = await verifyUser(user_id, user_pw, access_token, consumer_key);

    if (!personId) {
      return res.status(401).json({
        response_code: 'failure',
        response_messages: ['Invalid credentials'],
        date: null,
      });
    }

    const sql = `
      SELECT s.Submission_DateTime
      FROM usanpn2.Submission s
      LEFT JOIN usanpn2.Observation o ON o.Observation_ID = s.Observation_ID
      WHERE o.Observer_ID = ?
        AND (s.Deleted IS NULL OR s.Deleted <> 1)
      ORDER BY s.Submission_DateTime DESC
      LIMIT 1
    `;

    const [rows] = await npnPool.query(sql, [personId]);

    if (!rows || rows.length === 0) {
      return res.json({
        date: null,
        response_messages: ['No submissions found'],
        response_code: 'success',
      });
    }

    res.json({
      date: rows[0].Submission_DateTime,
      response_messages: [],
      response_code: 'success',
    });
  } catch (err) {
    console.error('get_last_submission_for_person error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

module.exports = router;
