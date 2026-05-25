const express = require('express');
const router = express.Router();
const { npnPool, drupalPool } = require('../config/db');
const { isNotSecure } = require('../utils/httpsCheck');
const checkProperty = require('../utils/checkProperty');
const cleanText = require('../utils/cleanText');
const crypto = require('crypto');

/**
 * Generate a random alphanumeric password string.
 */
function generatePassword(length = 12) {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
  let pw = '';
  for (let i = 0; i < length; i++) {
    pw += chars[Math.floor(Math.random() * chars.length)];
  }
  return pw;
}

/**
 * Hash a password the same way the PHP app does (MD5).
 * Adjust to match the actual hashing scheme used in the PHP app if different.
 */
function hashPassword(pw) {
  return crypto.createHash('md5').update(pw).digest('hex');
}

// POST /create_user
router.post('/create_user', async (req, res) => {
  try {
    if (isNotSecure(req)) {
      return res.status(403).json({
        response_code: 'failure',
        response_messages: ['HTTPS required'],
        user_id: null,
        user_pw: null,
      });
    }

    const body = req.body;

    if (
      !checkProperty(body, 'f_name') ||
      !checkProperty(body, 'l_name') ||
      !checkProperty(body, 'email') ||
      !checkProperty(body, 'consumer_key')
    ) {
      return res.status(400).json({
        response_code: 'failure',
        response_messages: ['f_name, l_name, email, and consumer_key are required'],
        user_id: null,
        user_pw: null,
      });
    }

    const fName = cleanText(body.f_name.trim());
    const lName = cleanText(body.l_name.trim());
    const email = cleanText(body.email.trim());
    const consumerKey = body.consumer_key.trim();

    // Verify consumer key
    const [consumerRows] = await npnPool.query(
      `SELECT consumer_key FROM usanpn2.Oauth_Common_Consumer WHERE consumer_key = ? LIMIT 1`,
      [consumerKey]
    );

    if (!consumerRows || consumerRows.length === 0) {
      return res.status(403).json({
        response_code: 'failure',
        response_messages: ['Invalid consumer key'],
        user_id: null,
        user_pw: null,
      });
    }

    // Check if email already exists
    const [existingRows] = await npnPool.query(
      `SELECT Person_ID FROM usanpn2.Person WHERE email = ? LIMIT 1`,
      [email]
    );

    if (existingRows && existingRows.length > 0) {
      return res.status(409).json({
        response_code: 'failure',
        response_messages: ['A user with that email already exists'],
        user_id: null,
        user_pw: null,
      });
    }

    // Generate credentials
    const rawPassword = generatePassword();
    const hashedPassword = hashPassword(rawPassword);
    const userName = email; // Use email as username
    const loadKey = `API_${Date.now()}`;
    const createDate = new Date();

    const [insertResult] = await npnPool.query(
      `INSERT INTO usanpn2.Person
         (First_Name, Last_Name, Create_Date, Load_Key, email, UserName, Passwd_Hash)
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [fName, lName, createDate, loadKey, email, userName, hashedPassword]
    );

    const newPersonId = insertResult.insertId;

    res.json({
      user_id: newPersonId,
      user_pw: hashedPassword,
      response_messages: ['User created successfully'],
      response_code: 'success',
    });
  } catch (err) {
    console.error('create_user error:', err.message);
    res.status(500).json({
      response_code: 'failure',
      response_messages: [err.message],
      user_id: null,
      user_pw: null,
    });
  }
});

module.exports = router;
