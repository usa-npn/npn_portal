const express = require('express');
const router = express.Router();
const { npnPool } = require('../config/db');
const checkProperty = require('../utils/checkProperty');
const VALIDATORS = require('../badges/validators');

// POST /check_user_badge
router.post('/check_user_badge', async (req, res) => {
  try {
    const p = { ...req.query, ...(req.body || {}) };

    if (!checkProperty(p, 'person_id') || !checkProperty(p, 'hook_name')) {
      return res.json({ status_code: 0, badge_messages: [{ message: 'INVALID_INPUT' }] });
    }

    const [hookRows] = await npnPool.query(
      `SELECT h.Hook_ID FROM usanpn2.Badge_Hook h WHERE h.Name_Functional = ? LIMIT 1`,
      [p.hook_name]
    );
    if (!hookRows || hookRows.length === 0) {
      return res.json({ status_code: 0, badge_messages: [{ message: 'INVALID_HOOK' }] });
    }
    const hookId = hookRows[0].Hook_ID;

    const [personRows] = await npnPool.query(
      `SELECT Person_ID FROM usanpn2.Person WHERE Person_ID = ? LIMIT 1`,
      [p.person_id]
    );
    if (!personRows || personRows.length === 0) {
      return res.json({ status_code: 0, badge_messages: [{ message: 'INVALID_PERSON' }] });
    }

    const [existingRows] = await npnPool.query(
      `SELECT Badge_ID FROM usanpn2.Badge_Person WHERE Person_ID = ?`,
      [p.person_id]
    );
    const existingBadgeIds = new Set(existingRows.map(r => r.Badge_ID));

    const [badgeRows] = await npnPool.query(
      `SELECT b.Badge_ID, b.Name_Functional, b.Name_Internal, b.Name_External
       FROM usanpn2.Badge_Badge_Hook bbh
       JOIN usanpn2.Badge b ON b.Badge_ID = bbh.Badge_ID
       WHERE bbh.Hook_ID = ?`,
      [hookId]
    );

    const badgeMessages = [];
    let success = 0;

    for (const badge of badgeRows) {
      if (existingBadgeIds.has(badge.Badge_ID)) continue;

      const validatorKey = badge.Name_Functional ? badge.Name_Functional.toLowerCase() : null;
      const validator = validatorKey ? VALIDATORS[validatorKey] : null;
      if (!validator) continue;

      let qualified = false;
      try {
        qualified = await validator(parseInt(p.person_id, 10));
      } catch (valErr) {
        console.error(`Badge validation error for ${badge.Name_Functional}:`, valErr.message);
        continue;
      }

      if (!qualified) continue;

      badgeMessages.push({ message: `${badge.Name_Functional} QUALIFIED` });
      try {
        await npnPool.query(
          `INSERT INTO usanpn2.Badge_Person (Badge_ID, Person_ID, Date_Earned) VALUES (?, ?, ?)`,
          [badge.Badge_ID, p.person_id, new Date()]
        );
        success++;
      } catch (insertErr) {
        console.error('Error creating badge-person entity:', insertErr.message);
        badgeMessages.push({ message: `${badge.Name_Functional} CREATE_ERR` });
      }
    }

    res.json({ status_code: success > 0 ? 1 : 0, badge_messages: badgeMessages });
  } catch (err) {
    console.error('check_user_badge error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_user_badges
router.all('/get_user_badges', async (req, res) => {
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
router.all('/get_badges', async (req, res) => {
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
