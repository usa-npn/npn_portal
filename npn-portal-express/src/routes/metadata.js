const express = require('express');
const router = express.Router();
const { npnPool } = require('../config/db');
const checkProperty = require('../utils/checkProperty');
const resolveBooleanText = require('../utils/resolveBooleanText');

const ALLOWED_TYPES = new Set([
  'individual_summarized',
  'raw',
  'site_summarized',
  'magnitude',
  'dataset',
  'person',
  'station',
  'plant',
  'protocol',
  'species_protocol',
  'phenophase',
  'phenophase_definition',
  'sspi',
  'intensity',
  'observation_group',
]);

// GET /get_metadata_fields
router.get('/get_metadata_fields', async (req, res) => {
  try {
    const p = req.query;
    const conditions = [];
    const params = [];

    if (checkProperty(p, 'quality_check')) {
      const val = resolveBooleanText(p, 'quality_check');
      conditions.push('mf.Quality_Check = ?');
      params.push(val ? 1 : 0);
    }

    if (checkProperty(p, 'climate')) {
      const val = resolveBooleanText(p, 'climate');
      conditions.push('mf.Climate = ?');
      params.push(val ? 1 : 0);
    }

    if (checkProperty(p, 'required')) {
      const val = resolveBooleanText(p, 'required');
      conditions.push('mf.Required = ?');
      params.push(val ? 1 : 0);
    }

    if (checkProperty(p, 'remote_sensing')) {
      const val = resolveBooleanText(p, 'remote_sensing');
      conditions.push('mf.Remote_Sensing = ?');
      params.push(val ? 1 : 0);
    }

    if (checkProperty(p, 'type')) {
      const typeVal = p.type;
      if (!ALLOWED_TYPES.has(typeVal)) {
        return res.status(400).json({ error: 'Invalid type value' });
      }
      conditions.push('mf.Type = ?');
      params.push(typeVal);
    }

    const whereClause = conditions.length > 0 ? 'WHERE ' + conditions.join(' AND ') : '';

    const sql = `
      SELECT
        mf.Metadata_Field_ID,
        mf.Field_Name,
        mf.Field_Description,
        mf.Seq_Num,
        mf.Type,
        mf.Quality_Check,
        mf.Climate,
        mf.Required,
        mf.Machine_Name,
        mf.Remote_Sensing,
        GROUP_CONCAT(mcv.Value SEPARATOR '|') AS controlled_values
      FROM usanpn2.Metadata_Field mf
      LEFT JOIN usanpn2.Metadata_Controlled_Value mcv
        ON mcv.Metadata_Field_ID = mf.Metadata_Field_ID
      ${whereClause}
      GROUP BY mf.Metadata_Field_ID
      ORDER BY mf.Type ASC, mf.Seq_Num ASC
    `;

    const [rows] = await npnPool.query(sql, params);

    const result = rows.map(r => ({
      metadata_field_id: r.Metadata_Field_ID,
      field_name: r.Field_Name,
      field_description: r.Field_Description,
      seq_num: r.Seq_Num,
      type: r.Type,
      quality_check: r.Quality_Check,
      climate: r.Climate,
      required: r.Required,
      machine_name: r.Machine_Name,
      remote_sensing: r.Remote_Sensing,
      controlled_values: r.controlled_values || "",
    }));

    res.json(result);
  } catch (err) {
    console.error('get_metadata_fields error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

module.exports = router;
