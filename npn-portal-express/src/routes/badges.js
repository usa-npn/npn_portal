const express = require('express');
const router = express.Router();
const { npnPool } = require('../config/db');
const checkProperty = require('../utils/checkProperty');

// POST /check_user_badge
router.post('/check_user_badge', async (req, res) => {
  try {
    const { person_id, hook_name } = req.body;

    if (!checkProperty(req.body, 'person_id') || !checkProperty(req.body, 'hook_name')) {
      return res.status(400).json({
        status_code: 'failure',
        badge_messages: [{ message: 'person_id and hook_name are required' }],
      });
    }

    // Find the hook
    const [hookRows] = await npnPool.query(
      `SELECT h.Hook_ID FROM usanpn2.Badge_Hook h WHERE h.Name_Functional = ? LIMIT 1`,
      [hook_name]
    );
    if (!hookRows || hookRows.length === 0) {
      return res.status(404).json({
        status_code: 'failure',
        badge_messages: [{ message: 'Hook not found' }],
      });
    }
    const hookId = hookRows[0].Hook_ID;

    // Verify person exists
    const [personRows] = await npnPool.query(
      `SELECT Person_ID FROM usanpn2.Person WHERE Person_ID = ? LIMIT 1`,
      [person_id]
    );
    if (!personRows || personRows.length === 0) {
      return res.status(404).json({
        status_code: 'failure',
        badge_messages: [{ message: 'Person not found' }],
      });
    }

    // Get user's existing badge IDs
    const [existingRows] = await npnPool.query(
      `SELECT Badge_ID FROM usanpn2.Badge_Person WHERE Person_ID = ?`,
      [person_id]
    );
    const existingBadgeIds = new Set(existingRows.map(r => r.Badge_ID));

    // Get badges linked to this hook
    const [badgeHookRows] = await npnPool.query(
      `SELECT bbh.Badge_ID FROM usanpn2.Badge_Badge_Hook bbh WHERE bbh.Hook_ID = ?`,
      [hookId]
    );

    const messages = [];
    const now = new Date();

    for (const bhRow of badgeHookRows) {
      const badgeId = bhRow.Badge_ID;
      if (existingBadgeIds.has(badgeId)) {
        continue; // Already earned
      }
      // Award badge
      await npnPool.query(
        `INSERT INTO usanpn2.Badge_Person (Badge_ID, Person_ID, Award_Date) VALUES (?, ?, ?)`,
        [badgeId, person_id, now]
      );
      messages.push({ message: `Badge ${badgeId} awarded` });
    }

    res.json({
      status_code: 'success',
      badge_messages: messages.length > 0 ? messages : [{ message: 'No new badges awarded' }],
    });
  } catch (err) {
    console.error('check_user_badge error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_user_badges
router.get('/get_user_badges', async (req, res) => {
  try {
    if (!checkProperty(req.query, 'person_id')) {
      return res.status(400).json({ error: 'person_id is required' });
    }

    const { person_id } = req.query;

    const [rows] = await npnPool.query(
      `SELECT bp.Badge_ID, b.Name_External, b.Description, b.Image_URL
       FROM usanpn2.Badge_Person bp
       LEFT JOIN usanpn2.Badge b ON b.Badge_ID = bp.Badge_ID
       WHERE bp.Person_ID = ?`,
      [person_id]
    );

    const badges = rows.map(r => ({
      badge_id: r.Badge_ID,
      name: r.Name_External,
      description: r.Description,
      image_url: r.Image_URL,
    }));

    res.json({ badges });
  } catch (err) {
    console.error('get_user_badges error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_badges
router.get('/get_badges', async (req, res) => {
  try {
    const [rows] = await npnPool.query(
      `SELECT Badge_ID, Name_External, Description, Image_URL FROM usanpn2.Badge`
    );

    const badges = rows.map(r => ({
      badge_id: r.Badge_ID,
      name: r.Name_External,
      description: r.Description,
      image_url: r.Image_URL,
    }));

    res.json({ badges });
  } catch (err) {
    console.error('get_badges error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

module.exports = router;
