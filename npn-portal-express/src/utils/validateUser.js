const { npnPool, drupalPool } = require('../config/db');

/**
 * Verifies a user via OAuth token or direct user_id/password lookup.
 *
 * @param {string|number} userId   - NPN Person_ID
 * @param {string}        userPw   - Hashed password (Passwd_Hash)
 * @param {string}        accessToken  - OAuth access token key
 * @param {string}        consumerKey  - OAuth consumer key
 * @returns {number|null} Person_ID on success, null on failure
 */
async function verifyUser(userId, userPw, accessToken, consumerKey) {
  // OAuth token path
  if (accessToken && consumerKey) {
    try {
      const sql = `
        SELECT u.name, oct.expires
        FROM drupal5.oauth_common_token oct
        LEFT JOIN drupal5.users u ON u.uid = oct.uid
        LEFT JOIN drupal5.oauth_common_consumer occ ON occ.csid = oct.csid
        WHERE oct.token_key = ?
          AND occ.consumer_key = ?
        LIMIT 1
      `;
      const [rows] = await drupalPool.query(sql, [accessToken, consumerKey]);
      if (!rows || rows.length === 0) return null;

      const token = rows[0];
      const now = Math.floor(Date.now() / 1000);
      if (token.expires && token.expires < now) return null;

      const drupalName = token.name;
      if (!drupalName) return null;

      const [personRows] = await npnPool.query(
        `SELECT Person_ID FROM usanpn2.Person WHERE UserName LIKE ? LIMIT 1`,
        [drupalName]
      );
      if (!personRows || personRows.length === 0) return null;
      return personRows[0].Person_ID;
    } catch (err) {
      console.error('verifyUser OAuth error:', err.message);
      return null;
    }
  }

  // Direct user_id / password hash path
  if (userId && userPw) {
    try {
      const [rows] = await npnPool.query(
        `SELECT Person_ID FROM usanpn2.Person WHERE Person_ID = ? AND Passwd_Hash = ? LIMIT 1`,
        [userId, userPw]
      );
      if (!rows || rows.length === 0) return null;
      return rows[0].Person_ID;
    } catch (err) {
      console.error('verifyUser password error:', err.message);
      return null;
    }
  }

  return null;
}

module.exports = { verifyUser };
