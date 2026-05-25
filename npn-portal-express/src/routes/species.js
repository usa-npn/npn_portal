const express = require('express');
const router = express.Router();
const { npnPool } = require('../config/db');
const checkProperty = require('../utils/checkProperty');
const resolveBooleanText = require('../utils/resolveBooleanText');
const arrayWrap = require('../utils/arrayWrap');

// GET /get_species
router.all('/get_species', async (req, res) => {
  try {
    const p = req.query;
    const includeRestricted = resolveBooleanText(p, 'include_restricted', false);

    let sql = `
      SELECT
        s.Species_ID,
        s.Common_Name,
        s.Genus,
        s.Species,
        s.ITIS_Taxonomic_SN,
        s.Functional_Type,
        s.Kingdom,
        s.Class_ID,
        sc.Common_Name AS class_common_name,
        sc.Name AS class_name,
        s.Order_ID,
        so.Common_Name AS order_common_name,
        so.Name AS order_name,
        s.Family_ID,
        sf.Common_Name AS family_common_name,
        sf.Name AS family_name,
        sg.Taxon_ID AS genus_id,
        sg.Common_Name AS genus_common_name
      FROM usanpn2.Species s
      LEFT JOIN usanpn2.Species_Taxon sc ON sc.Taxon_ID = s.Class_ID
      LEFT JOIN usanpn2.Species_Taxon so ON so.Taxon_ID = s.Order_ID
      LEFT JOIN usanpn2.Species_Taxon sf ON sf.Taxon_ID = s.Family_ID
      LEFT JOIN usanpn2.Species_Taxon sg ON sg.Taxon_ID = s.Genus_ID
    `;

    if (!includeRestricted) {
      sql += ` WHERE s.Active = 1`;
    }

    sql += ` ORDER BY s.Common_Name ASC`;

    const [speciesRows] = await npnPool.query(sql);

    // Get species types with all columns matching the old API
    const [typeRows] = await npnPool.query(
      `SELECT sst.Species_ID, st.Species_Type_ID, st.Species_Type, st.Comment, st.User_Display, st.Kingdom, st.Image_Path
       FROM usanpn2.Species_Species_Type sst
       LEFT JOIN usanpn2.Species_Type st ON st.Species_Type_ID = sst.Species_Type_ID`
    );

    // Group types by species_id
    const typesBySpecies = {};
    typeRows.forEach(t => {
      if (!typesBySpecies[t.Species_ID]) typesBySpecies[t.Species_ID] = [];
      typesBySpecies[t.Species_ID].push({
        Species_Type_ID: t.Species_Type_ID,
        Species_Type: t.Species_Type,
        Comment: t.Comment,
        User_Display: t.User_Display,
        Kingdom: t.Kingdom,
        Image_Path: t.Image_Path,
      });
    });

    const result = speciesRows.map(r => ({
      species_id: r.Species_ID,
      common_name: r.Common_Name,
      genus: r.Genus,
      genus_id: r.genus_id,
      genus_common_name: r.genus_common_name,
      species: r.Species,
      kingdom: r.Kingdom,
      itis_taxonomic_sn: r.ITIS_Taxonomic_SN,
      functional_type: r.Functional_Type,
      class_id: r.Class_ID,
      class_common_name: r.class_common_name,
      class_name: r.class_name,
      order_id: r.Order_ID,
      order_common_name: r.order_common_name,
      order_name: r.order_name,
      family_id: r.Family_ID,
      family_name: r.family_name,
      family_common_name: r.family_common_name,
      species_type: typesBySpecies[r.Species_ID] || [],
    }));

    res.json(result);
  } catch (err) {
    console.error('get_species error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_taxon
router.all('/get_taxon', async (req, res) => {
  try {
    const p = req.query;
    const conditions = [];
    const params = [];

    let sql = `SELECT * FROM usanpn2.Species_Taxon`;

    if (checkProperty(p, 'level')) {
      conditions.push('Taxon_Level = ?');
      params.push(p.level);
    }

    if (conditions.length > 0) {
      sql += ' WHERE ' + conditions.join(' AND ');
    }

    sql += ' ORDER BY Name ASC';

    const [rows] = await npnPool.query(sql, params);

    if (resolveBooleanText(p, 'include_species', false)) {
      const [speciesRows] = await npnPool.query(
        `SELECT s.Species_ID, s.Common_Name, s.Genus, s.Species, s.Class_ID, s.Order_ID, s.Family_ID, s.Genus_ID
         FROM usanpn2.Species s WHERE s.Active = 1`
      );
      const speciesByTaxon = {};
      speciesRows.forEach(s => {
        [s.Class_ID, s.Order_ID, s.Family_ID, s.Genus_ID].forEach(tid => {
          if (tid) {
            if (!speciesByTaxon[tid]) speciesByTaxon[tid] = [];
            speciesByTaxon[tid].push({
              species_id: s.Species_ID,
              common_name: s.Common_Name,
              genus: s.Genus,
              species: s.Species,
            });
          }
        });
      });
      const result = rows.map(r => ({
        ...r,
        species: speciesByTaxon[r.Taxon_ID] || [],
      }));
      return res.json(result);
    }

    res.json(rows);
  } catch (err) {
    console.error('get_taxon error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_species_by_state
router.all('/get_species_by_state', async (req, res) => {
  try {
    const p = req.query;

    if (!checkProperty(p, 'state')) {
      return res.status(400).json({ error: 'state is required' });
    }

    const conditions = ['spsl.State_Code = ?'];
    const params = [p.state];

    if (checkProperty(p, 'kingdom')) {
      conditions.push('s.Kingdom = ?');
      params.push(p.kingdom);
    }

    const sql = `
      SELECT DISTINCT
        s.Species_ID,
        s.Common_Name,
        s.Genus,
        s.Species,
        s.Kingdom,
        s.Functional_Type,
        s.ITIS_Taxonomic_SN
      FROM usanpn2.Species s
      LEFT JOIN usanpn2.Species_State_Location spsl ON spsl.Species_ID = s.Species_ID
      WHERE ${conditions.join(' AND ')}
        AND s.Active = 1
      ORDER BY s.Common_Name ASC
    `;

    const [rows] = await npnPool.query(sql, params);

    const result = rows.map(r => ({
      species_id: r.Species_ID,
      common_name: r.Common_Name,
      genus: r.Genus,
      kingdom: r.Kingdom,
      species: r.Species,
      itis_taxonomic_sn: r.ITIS_Taxonomic_SN,
    }));

    res.json(result);
  } catch (err) {
    console.error('get_species_by_state error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_species_update_date
router.all('/get_species_update_date', async (req, res) => {
  try {
    const [rows] = await npnPool.query(
      `SELECT Update_Date FROM usanpn2.Update_Date WHERE Table_Name = 'species' LIMIT 1`
    );
    if (!rows || rows.length === 0) return res.json({ update_date: null });
    res.json({ update_date: rows[0].Update_Date });
  } catch (err) {
    console.error('get_species_update_date error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_plant_types
router.all('/get_plant_types', async (req, res) => {
  try {
    const [rows] = await npnPool.query(
      `SELECT st.Species_Type, st.Species_Type_ID, COUNT(DISTINCT s.Species_ID) AS species_count
       FROM usanpn2.Species_Type st
       LEFT JOIN usanpn2.Species_Species_Type sst ON sst.Species_Type_ID = st.Species_Type_ID
       LEFT JOIN usanpn2.Species s ON s.Species_ID = sst.Species_ID AND s.Active = 1
       WHERE st.Kingdom = 'Plantae'
       GROUP BY st.Species_Type_ID
       ORDER BY st.Species_Type ASC`
    );

    const result = rows.map(r => ({
      species_type_id: r.Species_Type_ID,
      species_type: r.Species_Type,
      species_count: r.species_count,
    }));

    res.json(result);
  } catch (err) {
    console.error('get_plant_types error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_animal_types
router.all('/get_animal_types', async (req, res) => {
  try {
    const [rows] = await npnPool.query(
      `SELECT st.Species_Type, st.Species_Type_ID, COUNT(DISTINCT s.Species_ID) AS species_count
       FROM usanpn2.Species_Type st
       LEFT JOIN usanpn2.Species_Species_Type sst ON sst.Species_Type_ID = st.Species_Type_ID
       LEFT JOIN usanpn2.Species s ON s.Species_ID = sst.Species_ID AND s.Active = 1
       WHERE st.Kingdom = 'Animalia'
       GROUP BY st.Species_Type_ID
       ORDER BY st.Species_Type ASC`
    );

    const result = rows.map(r => ({
      species_type_id: r.Species_Type_ID,
      species_type: r.Species_Type,
      species_count: r.species_count,
    }));

    res.json(result);
  } catch (err) {
    console.error('get_animal_types error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_species_functional_types
router.all('/get_species_functional_types', async (req, res) => {
  try {
    const [rows] = await npnPool.query(
      `SELECT DISTINCT Functional_Type FROM usanpn2.Species WHERE Functional_Type IS NOT NULL ORDER BY Functional_Type ASC`
    );
    res.json(rows.map(r => ({ type_name: r.Functional_Type })));
  } catch (err) {
    console.error('get_species_functional_types error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_species_filter
router.all('/get_species_filter', async (req, res) => {
  try {
    const p = req.query;
    const conditions = ['s.Active = 1'];
    const params = [];
    const extraJoins = [];

    let taxon = 'species';
    if (checkProperty(p, 'taxon')) {
      const t = String(p.taxon).toLowerCase();
      if (['family', 'order', 'class', 'genus'].includes(t)) taxon = t;
    }

    if (checkProperty(p, 'group_ids')) {
      const groupIds = arrayWrap(p.group_ids).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
      if (groupIds.length > 0) {
        extraJoins.push(
          `LEFT JOIN (
            SELECT DISTINCT sst.Species_ID
            FROM usanpn2.Species_Species_Type sst
            LEFT JOIN usanpn2.Species_Type st ON st.Species_Type_ID = sst.Species_Type_ID
            WHERE st.Species_Type_ID IN (?)
          ) st ON st.Species_ID = s.Species_ID`
        );
        params.push(groupIds);
        conditions.push('st.Species_ID IS NOT NULL');
      }
    }

    if (checkProperty(p, 'network_id')) {
      const networkIds = arrayWrap(p.network_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
      if (networkIds.length > 0) {
        const netClauses = [];
        for (const nid of networkIds) {
          netClauses.push(`csd.Network_IDs LIKE ?`, `csd.Network_IDs = ?`, `csd.Network_IDs LIKE ?`, `csd.Network_IDs LIKE ?`);
          params.push(`%,${nid},%`, String(nid), `${nid},%`, `%,${nid}`);
        }
        conditions.push(`(${netClauses.join(' OR ')})`);
      }
    }

    if (checkProperty(p, 'person_id')) {
      const personIds = arrayWrap(p.person_id);
      const personClauses = personIds.map(() => 'co.ObservedBy_Person_ID = ?');
      conditions.push(`(${personClauses.join(' OR ')})`);
      params.push(...personIds);
    }

    if (checkProperty(p, 'start_date') && checkProperty(p, 'end_date')) {
      conditions.push('co.Observation_Date BETWEEN ? AND ?');
      params.push(p.start_date, p.end_date);
    }

    if (checkProperty(p, 'station_ids')) {
      const stationIds = arrayWrap(p.station_ids).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
      if (stationIds.length > 0) {
        conditions.push('csd.Site_ID IN (?)');
        params.push(stationIds);
      }
    }

    const groupByCol = { family: 'csd.Family_ID', order: 'csd.Order_ID', class: 'csd.Class_ID', genus: 'csd.Genus_ID', species: 'csd.Species_ID' }[taxon];
    const orderByCol = { family: 'csd.Family_Common_Name', order: 'csd.Order_Common_Name', class: 'csd.Class_Common_Name', genus: 'csd.Genus_Common_Name', species: 'Common_Name' }[taxon];

    const sql = `
      SELECT
        COUNT(co.Observation_ID) AS c, csd.Kingdom, csd.Site_ID, csd.Individual_ID, csd.Phenophase_ID,
        csd.Family_ID, csd.Family_Name, csd.Family_Common_Name,
        csd.Order_ID, csd.Order_Name, csd.Order_Common_Name,
        csd.Class_ID, csd.Class_Name, csd.Class_Common_Name,
        csd.Species_ID, csd.Common_Name, csd.Genus, csd.Genus_Common_Name, csd.Species,
        s.ITIS_Taxonomic_SN, s.Functional_Type, csd.Genus_ID
      FROM usanpn2.Cached_Summarized_Data csd
      LEFT JOIN usanpn2.Cached_Observation co ON co.Series_ID = csd.Series_ID
      LEFT JOIN usanpn2.Species s ON csd.Species_ID = s.Species_ID
      ${extraJoins.join(' ')}
      WHERE ${conditions.join(' AND ')}
      GROUP BY ${groupByCol}
      ORDER BY ${orderByCol}
    `;

    const [rows] = await npnPool.query(sql, params);

    const result = [];
    for (const r of rows) {
      const obj = { kingdom: r.Kingdom, number_observations: r.c };

      if (taxon === 'family') {
        if (r.Family_ID == null) continue;
        obj.family_id = r.Family_ID;
        obj.family_name = r.Family_Name;
        obj.family_common_name = r.Family_Common_Name;
      } else if (taxon === 'class') {
        if (r.Class_ID == null) continue;
        obj.class_id = r.Class_ID;
        obj.class_name = r.Class_Name;
        obj.class_common_name = r.Class_Common_Name;
      } else if (taxon === 'order') {
        if (r.Order_ID == null) continue;
        obj.order_id = r.Order_ID;
        obj.order_name = r.Order_Name;
        obj.order_common_name = r.Order_Common_Name;
      } else if (taxon === 'genus') {
        if (r.Genus_ID == null) continue;
        obj.genus_id = r.Genus_ID;
        obj.genus_name = r.Genus;
        obj.genus_common_name = r.Genus_Common_Name;
      } else {
        obj.common_name = r.Common_Name;
        obj.genus = r.Genus;
        obj.genus_common_name = r.Genus_Common_Name;
        obj.genus_id = r.Genus_ID;
        obj.species = r.Species;
        obj.species_id = r.Species_ID;
        obj.family_id = r.Family_ID;
        obj.family_name = r.Family_Name;
        obj.family_common_name = r.Family_Common_Name;
        obj.class_id = r.Class_ID;
        obj.class_name = r.Class_Name;
        obj.class_common_name = r.Class_Common_Name;
        obj.order_id = r.Order_ID;
        obj.order_name = r.Order_Name;
        obj.order_common_name = r.Order_Common_Name;
        obj.itis_taxonomic_sn = r.ITIS_Taxonomic_SN;
        obj.functional_type = r.Functional_Type;
      }

      result.push(obj);
    }

    res.json(result);
  } catch (err) {
    console.error('get_species_filter error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_species_by_id
router.all('/get_species_by_id', async (req, res) => {
  try {
    if (!checkProperty(req.query, 'species_id')) {
      return res.status(400).json({ error: 'species_id is required' });
    }

    const speciesId = parseInt(req.query.species_id, 10);
    if (isNaN(speciesId)) return res.status(400).json({ error: 'Invalid species_id' });

    const [rows] = await npnPool.query(
      `SELECT s.Common_Name, s.Genus, s.Species, s.Kingdom, s.ITIS_Taxonomic_SN
       FROM usanpn2.Species s
       WHERE s.Species_ID = ?
       LIMIT 1`,
      [speciesId]
    );

    if (!rows || rows.length === 0) return res.json(null);
    const r = rows[0];
    res.json({
      common_name: r.Common_Name,
      genus: r.Genus,
      species: r.Species,
      kingdom: r.Kingdom,
      itis_taxonomic_sn: r.ITIS_Taxonomic_SN,
    });
  } catch (err) {
    console.error('get_species_by_id error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_species_by_itis
router.all('/get_species_by_itis', async (req, res) => {
  try {
    if (!checkProperty(req.query, 'itis_sn')) {
      return res.status(400).json({ error: 'itis_sn is required' });
    }

    const itisSn = req.query.itis_sn;

    const [rows] = await npnPool.query(
      `SELECT s.Common_Name, s.Genus, s.Species, s.Species_ID, s.Kingdom
       FROM usanpn2.Species s
       WHERE s.ITIS_Taxonomic_SN = ?
         AND s.Active = 1
       LIMIT 1`,
      [itisSn]
    );

    if (!rows || rows.length === 0) return res.json(null);
    const r = rows[0];
    res.json({
      common_name: r.Common_Name,
      genus: r.Genus,
      species: r.Species,
      species_id: r.Species_ID,
      kingdom: r.Kingdom,
    });
  } catch (err) {
    console.error('get_species_by_itis error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_species_by_scientific_name
router.all('/get_species_by_scientific_name', async (req, res) => {
  try {
    const p = req.query;

    if (!checkProperty(p, 'genus') || !checkProperty(p, 'species')) {
      return res.status(400).json({ error: 'genus and species are required' });
    }

    const [rows] = await npnPool.query(
      `SELECT s.Species_ID, s.Common_Name, s.ITIS_Taxonomic_SN, s.Kingdom
       FROM usanpn2.Species s
       WHERE s.Genus = ? AND s.Species = ? AND s.Active = 1
       LIMIT 1`,
      [p.genus, p.species]
    );

    if (!rows || rows.length === 0) {
      return res.json(null);
    }

    const r = rows[0];
    res.json({
      common_name: r.Common_Name,
      itis_taxonomic_sn: r.ITIS_Taxonomic_SN,
      kingdom: r.Kingdom,
      species_id: r.Species_ID,
    });
  } catch (err) {
    console.error('get_species_by_scientific_name error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_species_by_common_name
router.all('/get_species_by_common_name', async (req, res) => {
  try {
    if (!checkProperty(req.query, 'common_name')) {
      return res.status(400).json({ error: 'common_name is required' });
    }

    const { common_name } = req.query;

    const [rows] = await npnPool.query(
      `SELECT s.Genus, s.Species, s.ITIS_Taxonomic_SN, s.Species_ID
       FROM usanpn2.vw_Species_All_Names v
       LEFT JOIN usanpn2.Species s ON s.Species_ID = v.Species_ID
       WHERE v.All_Names LIKE ?
       LIMIT 1`,
      [`%${common_name}%`]
    );

    if (!rows || rows.length === 0) return res.json({});
    const r = rows[0];
    res.json({
      genus: r.Genus,
      itis_taxonomic_sn: r.ITIS_Taxonomic_SN,
      species: r.Species,
      species_id: r.Species_ID,
    });
  } catch (err) {
    console.error('get_species_by_common_name error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

module.exports = router;
