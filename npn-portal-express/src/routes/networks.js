const express = require('express');
const router = express.Router();
const { npnPool, drupalPool } = require('../config/db');
const { verifyUser } = require('../utils/validateUser');
const checkProperty = require('../utils/checkProperty');
const resolveBooleanText = require('../utils/resolveBooleanText');
const arrayWrap = require('../utils/arrayWrap');

// GET /get_partner_networks
router.get('/get_partner_networks', async (req, res) => {
  try {
    const p = req.query;
    const activeOnly = resolveBooleanText(p, 'active_only', false);

    let sql = `
      SELECT DISTINCT
        n.Network_ID,
        n.Name
      FROM usanpn2.Network n
    `;

    const joins = [];
    const conditions = ['n.User_Display = 1'];
    const params = [];

    if (activeOnly) {
      joins.push(
        `LEFT JOIN usanpn2.Network_Station ns_act ON ns_act.Network_ID = n.Network_ID`,
        `LEFT JOIN usanpn2.Cached_Summarized_Data csd ON csd.Station_ID = ns_act.Station_ID`
      );
      conditions.push('csd.Station_ID IS NOT NULL');
    }

    if (checkProperty(p, 'member_id')) {
      joins.push(`LEFT JOIN usanpn2.Network_Person np_m ON np_m.Network_ID = n.Network_ID`);
      conditions.push('np_m.Person_ID = ?');
      params.push(p.member_id);
    }

    if (joins.length > 0) {
      sql += ' ' + joins.join(' ');
    }

    sql += ' WHERE ' + conditions.join(' AND ');

    if (checkProperty(p, 'network_id')) {
      const networkIds = arrayWrap(p.network_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
      sql += ` AND n.Network_ID IN (?)`;
      params.push(networkIds);
    }

    if (checkProperty(p, 'search')) {
      sql += ` AND n.Name LIKE ?`;
      params.push(`%${p.search}%`);
    }

    sql += ' ORDER BY n.Name ASC';

    const [rows] = await npnPool.query(sql, params);

    const result = rows.map(r => ({
      network_id: r.Network_ID,
      network_name: r.Name,
    }));

    res.json(result);
  } catch (err) {
    console.error('get_partner_networks error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_networks_for_user
router.get('/get_networks_for_user', async (req, res) => {
  try {
    const { user_id, user_pw, access_token, consumer_key } = req.query;
    const personId = await verifyUser(user_id, user_pw, access_token, consumer_key);

    if (!personId) {
      return res.status(401).json({ error: 'Invalid credentials' });
    }

    const [rows] = await npnPool.query(
      `SELECT n.Network_ID, n.Name
       FROM usanpn2.Network n
       LEFT JOIN usanpn2.Network_Person np ON np.Network_ID = n.Network_ID
       WHERE np.Person_ID = ?
       ORDER BY n.Name ASC`,
      [personId]
    );

    res.json(rows.map(r => ({ network_id: r.Network_ID, name: r.Name })));
  } catch (err) {
    console.error('get_networks_for_user error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_user_network_status
router.get('/get_user_network_status', async (req, res) => {
  try {
    const p = req.query;

    if (!checkProperty(p, 'person_id') || !checkProperty(p, 'network_id')) {
      return res.status(400).json({ error: 'person_id and network_id are required' });
    }

    const [rows] = await npnPool.query(
      `SELECT np.Person_ID, np.Network_ID, np.Role_ID, r.Role_Name
       FROM usanpn2.Network_Person np
       LEFT JOIN usanpn2.Role r ON r.Role_ID = np.Role_ID
       WHERE np.Person_ID = ? AND np.Network_ID = ?
       LIMIT 1`,
      [p.person_id, p.network_id]
    );

    if (!rows || rows.length === 0) {
      return res.json({ member: false, role_id: null, role_name: null });
    }

    res.json({
      member: true,
      person_id: rows[0].Person_ID,
      network_id: rows[0].Network_ID,
      role_id: rows[0].Role_ID,
      role_name: rows[0].Role_Name,
    });
  } catch (err) {
    console.error('get_user_network_status error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_species_for_network
router.get('/get_species_for_network', async (req, res) => {
  try {
    if (!checkProperty(req.query, 'network_id')) {
      return res.status(400).json({ error: 'network_id is required' });
    }

    const { network_id } = req.query;

    const [rows] = await npnPool.query(
      `SELECT DISTINCT
         s.Species_ID,
         s.Common_Name,
         s.Genus,
         s.Species,
         s.Kingdom,
         s.Functional_Type
       FROM usanpn2.Species s
       LEFT JOIN usanpn2.Cached_Summarized_Data csd ON csd.Species_ID = s.Species_ID
       LEFT JOIN usanpn2.Network_Station ns ON ns.Station_ID = csd.Station_ID
       WHERE ns.Network_ID = ?
       ORDER BY s.Common_Name ASC`,
      [network_id]
    );

    const result = rows.map(r => ({
      species_id: r.Species_ID,
      common_name: r.Common_Name,
      genus: r.Genus,
      species: r.Species,
      kingdom: r.Kingdom,
      functional_type: r.Functional_Type,
    }));

    res.json(result);
  } catch (err) {
    console.error('get_species_for_network error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_admins_for_network
router.get('/get_admins_for_network', async (req, res) => {
  try {
    if (!checkProperty(req.query, 'network_id')) {
      return res.status(400).json({ error: 'network_id is required' });
    }

    const { network_id } = req.query;

    const [rows] = await npnPool.query(
      `SELECT p.Person_ID, p.First_Name, p.Last_Name, p.email
       FROM usanpn2.Person p
       LEFT JOIN usanpn2.Network_Person np ON np.Person_ID = p.Person_ID
       WHERE np.Network_ID = ? AND np.Role_ID < 2
       ORDER BY p.Last_Name ASC, p.First_Name ASC`,
      [network_id]
    );

    const result = rows.map(r => ({
      person_id: r.Person_ID,
      first_name: r.First_Name,
      last_name: r.Last_Name,
      email: r.email,
    }));

    res.json(result);
  } catch (err) {
    console.error('get_admins_for_network error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_network_tree
router.get('/get_network_tree', async (req, res) => {
  try {
    // Fetch full Drupal taxonomy hierarchy for vid=6 (network groups) cross-joined with NPN Network table
    // Use drupalPool since the NPN user can't access Drupal tables
    const [allRows] = await drupalPool.query(`
      SELECT
        n_parent.Network_ID AS parent_network_id,
        n_parent.Name AS parent_name,
        n_child.Network_ID AS child_network_id,
        n_child.Name AS child_name
      FROM taxonomy_term_data ttd_parent
      INNER JOIN taxonomy_term_hierarchy tth ON tth.parent = ttd_parent.tid
      INNER JOIN taxonomy_term_data ttd_child ON ttd_child.tid = tth.tid
      INNER JOIN usanpn2.Network n_parent ON n_parent.Name = ttd_parent.name
      INNER JOIN usanpn2.Network n_child ON n_child.Name = ttd_child.name
      WHERE ttd_parent.vid = 6
      ORDER BY n_parent.Name ASC, n_child.Name ASC
    `);

    // Fetch top-level (root) networks: parent tid = 0
    const [rootRows] = await drupalPool.query(`
      SELECT n.Network_ID, n.Name
      FROM taxonomy_term_data ttd
      INNER JOIN taxonomy_term_hierarchy tth ON tth.tid = ttd.tid
      INNER JOIN usanpn2.Network n ON n.Name = ttd.name
      WHERE ttd.vid = 6 AND tth.parent = 0 AND n.Network_ID IS NOT NULL
      ORDER BY n.Name ASC
    `);

    // Build parent→children map
    const childrenOf = {};
    for (const row of allRows) {
      if (!row.parent_network_id) continue;
      if (!childrenOf[row.parent_network_id]) childrenOf[row.parent_network_id] = [];
      childrenOf[row.parent_network_id].push({ network_id: row.child_network_id, network_name: row.child_name });
    }

    // Recursively build tree with explicit nesting keys
    const buildNode = (networkId, networkName, depth) => {
      const node = { network_id: networkId, network_name: networkName };
      const children = childrenOf[networkId];
      if (!children || children.length === 0) return node;

      const nestKey = depth === 0 ? 'secondary_network'
        : depth === 1 ? 'tertiary_network'
        : 'quaternary_network';

      node[nestKey] = children.map(c => buildNode(c.network_id, c.network_name, depth + 1));
      return node;
    };

    const tree = rootRows
      .filter(r => r.Network_ID)
      .map(r => buildNode(r.Network_ID, r.Name, 0));

    res.json(tree);
  } catch (err) {
    console.error('get_network_tree error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

module.exports = router;
