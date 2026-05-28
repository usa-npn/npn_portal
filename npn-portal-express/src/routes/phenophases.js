const express = require('express');
const router = express.Router();
const { npnPool } = require('../config/db');
const checkProperty = require('../utils/checkProperty');
const arrayWrap = require('../utils/arrayWrap');
const resolveBooleanText = require('../utils/resolveBooleanText');

// GET /get_phenophases
router.all('/get_phenophases', async (req, res) => {
  try {
    const [rows] = await npnPool.query(
      `SELECT Phenophase_ID, Phenophase_Name, Short_Name, Pheno_Class_ID, Color
       FROM usanpn2.vw_Phenophases
       ORDER BY Phenophase_ID ASC`
    );
    res.json(rows.map(r => ({
      phenophase_id: r.Phenophase_ID,
      phenophase_name: r.Phenophase_Name,
      phenophase_category: r.Short_Name,
      color: r.Color,
      pheno_class_id: r.Pheno_Class_ID,
    })));
  } catch (err) {
    console.error('get_phenophases error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_phenophase_details
router.all('/get_phenophase_details', async (req, res) => {
  const p = req.query;
  let ids = [];

  if (checkProperty(p, 'phenophase_id')) {
    ids = arrayWrap(p.phenophase_id);
  } else if (checkProperty(p, 'ids')) {
    ids = arrayWrap(p.ids);
  }

  let sql = `SELECT * FROM usanpn2.vw_Phenophase_Details`;
  let params = [];

  if (ids.length > 0) {
    const numericIds = ids.map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    if (numericIds.length > 0) {
      sql += ` WHERE Phenophase_ID IN (?)`;
      params = [numericIds];
    }
  }

  sql += ` ORDER BY Phenophase_ID ASC`;

  let conn;
  try {
    conn = await npnPool.getConnection();
  } catch (err) {
    console.error('get_phenophase_details error:', err.message);
    return res.status(500).json({ error: err.message });
  }

  const rawConn = conn.connection;
  let released = false;
  const release = () => { if (!released) { released = true; conn.release(); } };

  res.setHeader('Content-Type', 'application/json');
  res.write('[');
  let first = true;
  let ended = false;

  const q = rawConn.query(sql, params);

  q.on('result', (row) => {
    if (ended) return;
    const chunk = (first ? '' : ',') + JSON.stringify(
      Object.fromEntries(Object.entries(row).map(([k, v]) => [k.toLowerCase(), v]))
    );
    first = false;
    if (!res.write(chunk)) rawConn.pause();
  });

  res.on('drain', () => rawConn.resume());
  req.on('close', () => { ended = true; released = true; rawConn.destroy(); });
  q.on('end', () => { if (ended) return; ended = true; res.write(']'); res.end(); release(); });
  q.on('error', (err) => {
    console.error('get_phenophase_details stream error:', err.message);
    if (ended) { release(); return; }
    ended = true;
    if (!res.headersSent) res.status(500).json({ error: err.message });
    else { try { res.end(']'); } catch (_) {} }
    release();
  });
});

// GET /get_phenophase_definition_details
router.all('/get_phenophase_definition_details', async (req, res) => {
  try {
    const [rows] = await npnPool.query(
      `SELECT *
       FROM usanpn2.Phenophase_Definition
       WHERE Start_Date IS NOT NULL
       ORDER BY Phenophase_ID ASC, Start_Date ASC`
    );
    res.json(rows.map(r => Object.fromEntries(Object.entries(r).map(([k, v]) => [k.toLowerCase(), v]))));
  } catch (err) {
    console.error('get_phenophase_definition_details error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_phenophases_for_species
router.all('/get_phenophases_for_species', async (req, res) => {
  try {
    const p = req.query;

    if (!checkProperty(p, 'species_id')) {
      return res.status(400).json({ error: 'species_id is required' });
    }

    const speciesIds = arrayWrap(p.species_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    if (speciesIds.length === 0) {
      return res.status(400).json({ error: 'No valid species_id provided' });
    }

    const returnAll = resolveBooleanText(p, 'return_all', false);
    const dateFilter = checkProperty(p, 'date') ? p.date : null;

    let sql = `
      SELECT DISTINCT
        pp.Phenophase_ID AS phenophase_id,
        pd.Phenophase_Name AS phenophase_name,
        pp.Short_Name AS phenophase_category,
        pd.Definition AS phenophase_definition,
        sspi.Additional_Definition AS phenophase_additional_definition,
        ppp.Seq_Num AS seq_num,
        s.Common_Name AS common_name,
        pp.Color AS color,
        pp.Pheno_Class_ID AS pheno_class_id,
        pc.Name AS pheno_class_name,
        pc.Sequence AS pheno_class_sequence,
        sspi.Abundance_Category AS abundance_category,
        sspi.Extent_Min AS extent_min,
        sp.Species_ID AS species_id
      FROM usanpn2.Species_Protocol sp
      LEFT JOIN usanpn2.Protocol pr ON pr.Protocol_ID = sp.Protocol_ID
      LEFT JOIN usanpn2.Protocol_Phenophase ppp ON ppp.Protocol_ID = pr.Protocol_ID
      LEFT JOIN usanpn2.Phenophase pp ON pp.Phenophase_ID = ppp.Phenophase_ID
      LEFT JOIN usanpn2.Pheno_Class pc ON pc.Pheno_Class_ID = pp.Pheno_Class_ID
      LEFT JOIN usanpn2.Species s ON s.Species_ID = sp.Species_ID
    `;

    // params must be in SQL appearance order — JOIN params come before WHERE params
    const joinParams = [];
    const whereParams = [speciesIds];

    const conditions = ['sp.Species_ID IN (?)', 'pd.Dataset_ID IS NULL'];

    if (!returnAll) {
      conditions.push('(sp.End_Date IS NULL OR sp.End_Date >= CURDATE())');
      conditions.push('(sp.Start_Date IS NULL OR sp.Start_Date <= CURDATE())');
    }

    if (dateFilter) {
      // Put date conditions in the JOIN ON for pd so LEFT JOIN semantics are preserved
      sql += `
        LEFT JOIN usanpn2.Phenophase_Definition pd
          ON pd.Phenophase_ID = pp.Phenophase_ID
          AND ? >= pd.Start_Date
          AND (pd.End_Date IS NULL OR pd.End_Date >= ?)
      `;
      joinParams.push(dateFilter, dateFilter);
    } else {
      sql += `
        LEFT JOIN usanpn2.Phenophase_Definition pd
          ON pd.Phenophase_ID = pp.Phenophase_ID
          AND pd.End_Date IS NULL
      `;
    }

    sql += `
      LEFT JOIN usanpn2.Species_Specific_Phenophase_Information sspi
        ON sspi.Species_ID = sp.Species_ID AND sspi.Phenophase_ID = pp.Phenophase_ID
    `;

    sql += ' WHERE ' + conditions.join(' AND ');
    sql += ' ORDER BY sp.Species_ID ASC, pc.Sequence ASC, ppp.Seq_Num ASC';

    // JOIN params appear before WHERE params in the SQL
    const params = [...joinParams, ...whereParams];
    const [rows] = await npnPool.query(sql, params);

    // Group by species, matching old PHP API structure
    const stripHtml = s => s ? s.replace(/<[^>]*>/g, '') : '';
    const speciesMap = new Map();
    for (const r of rows) {
      if (!speciesMap.has(r.species_id)) {
        speciesMap.set(r.species_id, { species_id: r.species_id, species_name: r.common_name, phenophases: [] });
      }
      speciesMap.get(r.species_id).phenophases.push({
        phenophase_id: r.phenophase_id,
        phenophase_name: r.phenophase_name,
        phenophase_category: r.phenophase_category,
        phenophase_definition: stripHtml(r.phenophase_definition),
        phenophase_additional_definition: r.phenophase_additional_definition || '',
        seq_num: r.seq_num,
        color: r.color,
        pheno_class_id: r.pheno_class_id,
        pheno_class_name: r.pheno_class_name,
        pheno_class_sequence: r.pheno_class_sequence,
        abundance_category: r.abundance_category !== null ? r.abundance_category : -1,
        raw_abundance: false,
      });
    }

    res.json(Array.from(speciesMap.values()));
  } catch (err) {
    console.error('get_phenophases_for_species error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_phenophases_for_taxon
// rnpn sends one (or more) of: family_id, order_id, class_id, genus_id (as arrays)
// plus either `date=YYYY-MM-DD` or `return_all=1`. Returns one entry per taxon ID,
// each containing the phenophases applicable to that taxon on that date.
router.all('/get_phenophases_for_taxon', async (req, res) => {
  try {
    const p = req.query;
    const returnAll = resolveBooleanText(p, 'return_all', false);
    const hasDate = checkProperty(p, 'date');

    if (!hasDate && !returnAll) {
      return res.json([]);
    }

    const date = hasDate ? p.date : null;

    // Match PHP precedence: family_id > order_id > class_id > genus_id
    let joinField = null;
    let taxonIds = null;
    const taxonResponseKey = {
      Family_ID: ['family_id', 'family_name'],
      Order_ID:  ['order_id',  'order_name'],
      Class_ID:  ['class_id',  'class_name'],
      Genus_ID:  ['genus_id',  'genus_name'],
    };

    for (const [param, col] of [
      ['family_id', 'Family_ID'],
      ['order_id',  'Order_ID'],
      ['class_id',  'Class_ID'],
      ['genus_id',  'Genus_ID'],
    ]) {
      if (checkProperty(p, param)) {
        joinField = col;
        taxonIds = arrayWrap(p[param]).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
        break;
      }
    }

    if (joinField === null || !taxonIds || taxonIds.length === 0) {
      return res.json([]);
    }

    const stripHtml = s => s ? String(s).replace(/<[^>]*>/g, '') : '';
    const cleanTextLike = s => stripHtml(s);

    const finalResults = [];

    for (const taxonId of taxonIds) {
      // Try date-scoped protocol/phenophase definition first (matches PHP).
      // If that returns nothing, fall back to the current active protocol.
      let rows = [];

      if (!returnAll) {
        const dateScopedSql = `
          SELECT DISTINCT
            pp.Phenophase_ID,
            pd.Phenophase_Name,
            pp.Short_Name,
            st.Name AS Taxon_Name,
            st.Taxon_ID,
            pd.Definition,
            ppp.Seq_Num,
            pp.Color,
            pp.Pheno_Class_ID,
            pc.Name AS Pheno_Class_Name,
            pc.Sequence AS Pheno_Class_Sequence,
            pd.Definition_ID
          FROM usanpn2.Species s
          LEFT JOIN usanpn2.Species_Taxon st ON st.Taxon_ID = s.${joinField}
          LEFT JOIN usanpn2.Species_Protocol sp ON sp.Species_ID = s.Species_ID
          LEFT JOIN usanpn2.Protocol_Phenophase ppp ON ppp.Protocol_ID = sp.Protocol_ID
          LEFT JOIN usanpn2.Phenophase pp ON pp.Phenophase_ID = ppp.Phenophase_ID
          LEFT JOIN usanpn2.Phenophase_Definition pd
            ON pd.Phenophase_ID = pp.Phenophase_ID
            AND ? >= pd.Start_Date
            AND (pd.End_Date IS NULL OR pd.End_Date >= ?)
          LEFT JOIN usanpn2.Pheno_Class pc ON pc.Pheno_Class_ID = pp.Pheno_Class_ID
          WHERE s.${joinField} = ?
            AND sp.Start_Date <= ?
            AND (
              (sp.End_Date IS NULL AND sp.Active = 1)
              OR (sp.End_Date IS NOT NULL AND sp.End_Date > ?)
            )
            AND pd.Dataset_ID IS NULL
          ORDER BY ppp.Seq_Num ASC
        `;
        [rows] = await npnPool.query(dateScopedSql, [date, date, taxonId, date, date]);

        if (rows.length === 0) {
          const fallbackSql = `
            SELECT DISTINCT
              pp.Phenophase_ID,
              pd.Phenophase_Name,
              pp.Short_Name,
              st.Name AS Taxon_Name,
              st.Taxon_ID,
              pd.Definition,
              ppp.Seq_Num,
              pp.Color,
              pp.Pheno_Class_ID,
              pc.Name AS Pheno_Class_Name,
              pc.Sequence AS Pheno_Class_Sequence,
              pd.Definition_ID
            FROM usanpn2.Species s
            LEFT JOIN usanpn2.Species_Taxon st ON st.Taxon_ID = s.${joinField}
            LEFT JOIN usanpn2.Species_Protocol sp ON sp.Species_ID = s.Species_ID
            LEFT JOIN usanpn2.Protocol_Phenophase ppp ON ppp.Protocol_ID = sp.Protocol_ID
            LEFT JOIN usanpn2.Phenophase pp ON pp.Phenophase_ID = ppp.Phenophase_ID
            LEFT JOIN usanpn2.Phenophase_Definition pd
              ON pd.Phenophase_ID = pp.Phenophase_ID
              AND pd.End_Date IS NULL
            LEFT JOIN usanpn2.Pheno_Class pc ON pc.Pheno_Class_ID = pp.Pheno_Class_ID
            WHERE s.${joinField} = ?
              AND sp.Active = 1
              AND pd.Dataset_ID IS NULL
            ORDER BY ppp.Seq_Num ASC
          `;
          [rows] = await npnPool.query(fallbackSql, [taxonId]);
        }
      } else {
        const allSql = `
          SELECT DISTINCT
            pp.Phenophase_ID,
            pd.Phenophase_Name,
            pp.Short_Name,
            st.Name AS Taxon_Name,
            st.Taxon_ID,
            pd.Definition,
            ppp.Seq_Num,
            pp.Color,
            pp.Pheno_Class_ID,
            pc.Name AS Pheno_Class_Name,
            pc.Sequence AS Pheno_Class_Sequence,
            pd.Definition_ID
          FROM usanpn2.Species s
          LEFT JOIN usanpn2.Species_Taxon st ON st.Taxon_ID = s.${joinField}
          LEFT JOIN usanpn2.Species_Protocol sp ON sp.Species_ID = s.Species_ID
          LEFT JOIN usanpn2.Protocol_Phenophase ppp ON ppp.Protocol_ID = sp.Protocol_ID
          LEFT JOIN usanpn2.Phenophase pp ON pp.Phenophase_ID = ppp.Phenophase_ID
          LEFT JOIN usanpn2.Phenophase_Definition pd ON pd.Phenophase_ID = pp.Phenophase_ID
          LEFT JOIN usanpn2.Pheno_Class pc ON pc.Pheno_Class_ID = pp.Pheno_Class_ID
          WHERE s.${joinField} = ?
          ORDER BY ppp.Seq_Num ASC
        `;
        [rows] = await npnPool.query(allSql, [taxonId]);
      }

      const phenophases = rows
        .filter(r => r.Phenophase_ID != null)
        .map(r => ({
          phenophase_id: r.Phenophase_ID,
          phenophase_name: cleanTextLike(r.Phenophase_Name),
          phenophase_category: cleanTextLike(r.Short_Name),
          phenophase_definition: cleanTextLike(r.Definition),
          seq_num: r.Seq_Num,
          color: r.Color,
          pheno_class_id: r.Pheno_Class_ID,
          pheno_class_name: r.Pheno_Class_Name,
          pheno_class_sequence: r.Pheno_Class_Sequence,
          phenophase_definition_id: r.Definition_ID,
        }));

      // Pull taxon name/ID from any row (they're all the same taxon)
      const taxonRow = rows.find(r => r.Taxon_ID != null) || {};
      const [idKey, nameKey] = taxonResponseKey[joinField];
      finalResults.push({
        [idKey]: taxonRow.Taxon_ID != null ? taxonRow.Taxon_ID : taxonId,
        [nameKey]: taxonRow.Taxon_Name || null,
        phenophases,
      });
    }

    res.json(finalResults);
  } catch (err) {
    console.error('get_phenophases_for_taxon error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

async function fetchAbundanceCategory(categoryId) {
  const [catRows] = await npnPool.query(
    `SELECT Abundance_Category_ID, Name, Description FROM usanpn2.Abundance_Category WHERE Abundance_Category_ID = ?`,
    [categoryId]
  );
  if (!catRows || catRows.length === 0) return null;
  const cat = catRows[0];

  const [valRows] = await npnPool.query(
    `SELECT av.Abundance_Value_ID, av.Abundance_Value, av.Short_Name
     FROM usanpn2.Abundance_Category_Abundance_Values acav
     LEFT JOIN usanpn2.Abundance_Values av ON av.Abundance_Value_ID = acav.Abundance_Value_ID
     WHERE acav.Abundance_Category_ID = ?
     ORDER BY acav.Seq_Num ASC`,
    [categoryId]
  );

  return {
    category_id: cat.Abundance_Category_ID,
    category_name: cat.Name,
    category_description: cat.Description,
    category_values: valRows.map(v => ({
      value_id: v.Abundance_Value_ID,
      value_description: v.Abundance_Value,
      value_name: v.Short_Name,
    })),
  };
}

// GET /get_abundance_category
router.all('/get_abundance_category', async (req, res) => {
  try {
    const p = req.query;

    if (!checkProperty(p, 'category_id')) {
      return res.json({});
    }

    const result = await fetchAbundanceCategory(parseInt(p.category_id, 10));
    res.json(result || {});
  } catch (err) {
    console.error('get_abundance_category error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_abundance_categories
router.all('/get_abundance_categories', async (req, res) => {
  try {
    const [catRows] = await npnPool.query(
      `SELECT Abundance_Category_ID FROM usanpn2.Abundance_Category ORDER BY Abundance_Category_ID ASC`
    );

    const categories = await Promise.all(
      catRows.map(r => fetchAbundanceCategory(r.Abundance_Category_ID))
    );

    res.json(categories.filter(Boolean));
  } catch (err) {
    console.error('get_abundance_categories error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_pheno_classes
router.all('/get_pheno_classes', async (req, res) => {
  try {
    const [rows] = await npnPool.query(
      `SELECT Pheno_Class_ID, Name, Description, Sequence FROM usanpn2.Pheno_Class ORDER BY Pheno_Class_ID ASC`
    );
    res.json(rows.map(r => ({
      id: r.Pheno_Class_ID,
      name: r.Name,
      description: r.Description,
      sequence: r.Sequence,
    })));
  } catch (err) {
    console.error('get_pheno_classes error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_pheno_class
router.all('/get_pheno_class', async (req, res) => {
  try {
    if (!checkProperty(req.query, 'pheno_class_id')) {
      return res.json({});
    }

    const [rows] = await npnPool.query(
      `SELECT pc.Pheno_Class_ID, pc.Name, pc.Description, pc.Sequence,
              pp.Phenophase_ID, pp.Short_Name,
              pp.Description AS Phenophase_Description,
              pp.Preferred_Action
       FROM usanpn2.Pheno_Class pc
       LEFT JOIN usanpn2.Phenophase pp ON pp.Pheno_Class_ID = pc.Pheno_Class_ID
       WHERE pc.Pheno_Class_ID = ?`,
      [req.query.pheno_class_id]
    );

    if (!rows || rows.length === 0) {
      return res.json({});
    }

    const first = rows[0];
    const phenophases = rows
      .filter(r => r.Phenophase_ID != null)
      .map(r => ({
        phenophase_id: r.Phenophase_ID,
        short_name: r.Short_Name,
        description: r.Phenophase_Description,
        action: r.Preferred_Action,
      }));

    res.json({
      id: first.Pheno_Class_ID,
      name: first.Name,
      description: first.Description,
      sequence: first.Sequence,
      phenophases,
    });
  } catch (err) {
    console.error('get_pheno_class error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_phenophases_update_date
router.all('/get_phenophases_update_date', async (req, res) => {
  try {
    const [rows] = await npnPool.query(
      `SELECT Update_Date FROM usanpn2.Update_Date WHERE Table_Name = 'phenophase' LIMIT 1`
    );
    if (!rows || rows.length === 0) return res.json({ update_date: null });
    res.json({ update_date: rows[0].Update_Date });
  } catch (err) {
    console.error('get_phenophases_update_date error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_any_update_date
router.all('/get_any_update_date', async (req, res) => {
  try {
    const [rows] = await npnPool.query(
      `SELECT MAX(Update_Date) AS latest_update FROM usanpn2.Update_Date`
    );
    res.json({ update_date: rows[0].latest_update || null });
  } catch (err) {
    console.error('get_any_update_date error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_species_protocol_details
router.all('/get_species_protocol_details', async (req, res) => {
  try {
    const p = req.query;
    const conditions = [];
    const params = [];

    if (checkProperty(p, 'species_id')) {
      const speciesIds = arrayWrap(p.species_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
      conditions.push('sp.Species_ID IN (?)');
      params.push(speciesIds);
    }

    if (checkProperty(p, 'protocol_id')) {
      conditions.push('sp.Protocol_ID = ?');
      params.push(p.protocol_id);
    }

    let sql = `
      SELECT
        sp.Dataset_ID,
        sp.Species_ID,
        sp.Protocol_ID,
        sp.Start_Date,
        sp.End_Date
      FROM usanpn2.Species_Protocol sp
    `;

    if (conditions.length > 0) {
      sql += ' WHERE ' + conditions.join(' AND ');
    }

    sql += ' ORDER BY sp.Species_ID ASC';

    const [rows] = await npnPool.query(sql, params);
    res.json(rows.map(r => ({
      dataset_id: r.Dataset_ID,
      species_id: r.Species_ID,
      protocol_id: r.Protocol_ID,
      start_date: r.Start_Date,
      end_date: r.End_Date,
    })));
  } catch (err) {
    console.error('get_species_protocol_details error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_protocol_details
router.all('/get_protocol_details', async (req, res) => {
  try {
    const [rows] = await npnPool.query(
      `SELECT Protocol_ID, Protocol_Name, Primary_Name, Secondary_Name, Phenophases, Comment
       FROM usanpn2.vw_Protocol_Details
       ORDER BY Protocol_ID ASC`
    );
    res.json(rows.map(r => ({
      protocol_id: r.Protocol_ID,
      protocol_name: r.Protocol_Name,
      primary_name: r.Primary_Name,
      secondary_name: r.Secondary_Name,
      phenophase_list: r.Phenophases ? r.Phenophases.replace(/,/g, ', ') : '',
      protocol_comments: r.Comment,
    })));
  } catch (err) {
    console.error('get_protocol_details error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_secondary_phenophase_details
router.all('/get_secondary_phenophase_details', async (req, res) => {
  const p = req.query;
  const conditions = [];
  const params = [];

  if (checkProperty(p, 'species_id')) {
    const speciesIds = arrayWrap(p.species_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    conditions.push('sspi.Species_ID IN (?)');
    params.push(speciesIds);
  }

  if (checkProperty(p, 'phenophase_id')) {
    conditions.push('sspi.Phenophase_ID = ?');
    params.push(p.phenophase_id);
  }

  let sql = `SELECT * FROM usanpn2.Species_Specific_Phenophase_Information sspi`;

  if (conditions.length > 0) {
    sql += ' WHERE ' + conditions.join(' AND ');
  }

  let conn;
  try {
    conn = await npnPool.getConnection();
  } catch (err) {
    console.error('get_secondary_phenophase_details error:', err.message);
    return res.status(500).json({ error: err.message });
  }

  const rawConn = conn.connection;
  let released = false;
  const release = () => { if (!released) { released = true; conn.release(); } };

  res.setHeader('Content-Type', 'application/json');
  res.write('[');
  let first = true;
  let ended = false;

  const q = rawConn.query(sql, params);

  q.on('result', (row) => {
    if (ended) return;
    const chunk = (first ? '' : ',') + JSON.stringify(row);
    first = false;
    if (!res.write(chunk)) rawConn.pause();
  });

  res.on('drain', () => rawConn.resume());
  req.on('close', () => { ended = true; released = true; rawConn.destroy(); });
  q.on('end', () => { if (ended) return; ended = true; res.write(']'); res.end(); release(); });
  q.on('error', (err) => {
    console.error('get_secondary_phenophase_details stream error:', err.message);
    if (ended) { release(); return; }
    ended = true;
    if (!res.headersSent) res.status(500).json({ error: err.message });
    else { try { res.end(']'); } catch (_) {} }
    release();
  });
});

// GET /get_abundance_details
router.all('/get_abundance_details', async (req, res) => {
  try {
    const [rows] = await npnPool.query(
      `SELECT * FROM usanpn2.vw_Abundance_Details`
    );
    res.json(rows);
  } catch (err) {
    console.error('get_abundance_details error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

module.exports = router;
