const express = require('express');
const router = express.Router();
const { npnPool } = require('../config/db');
const checkProperty = require('../utils/checkProperty');
const arrayWrap = require('../utils/arrayWrap');
const resolveBooleanText = require('../utils/resolveBooleanText');

/**
 * Build common observation filter conditions and params from query params.
 * Returns { conditions: string[], params: any[] }
 */
function buildObservationFilters(p) {
  const conditions = [];
  const params = [];

  if (checkProperty(p, 'start_date')) {
    conditions.push('Observation_Date >= ?');
    params.push(p.start_date);
  }

  if (checkProperty(p, 'end_date')) {
    conditions.push('Observation_Date <= ?');
    params.push(p.end_date);
  }

  if (checkProperty(p, 'species_id')) {
    const ids = arrayWrap(p.species_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    if (ids.length > 0) {
      conditions.push('Species_ID IN (?)');
      params.push(ids);
    }
  }

  if (checkProperty(p, 'station_id')) {
    const ids = arrayWrap(p.station_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    if (ids.length > 0) {
      conditions.push('Station_ID IN (?)');
      params.push(ids);
    }
  }

  if (checkProperty(p, 'network_id')) {
    const ids = arrayWrap(p.network_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    if (ids.length > 0) {
      conditions.push('Network_ID IN (?)');
      params.push(ids);
    }
  }

  if (checkProperty(p, 'individual_id')) {
    const ids = arrayWrap(p.individual_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    if (ids.length > 0) {
      conditions.push('Individual_ID IN (?)');
      params.push(ids);
    }
  }

  if (checkProperty(p, 'phenophase_id')) {
    const ids = arrayWrap(p.phenophase_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    if (ids.length > 0) {
      conditions.push('Phenophase_ID IN (?)');
      params.push(ids);
    }
  }

  if (checkProperty(p, 'person_id')) {
    conditions.push('Observer_ID = ?');
    params.push(p.person_id);
  }

  if (checkProperty(p, 'state') && String(p.state).trim()) {
    conditions.push('State = ?');
    params.push(p.state);
  }

  return { conditions, params };
}

// GET /get_observation_comment
router.all('/get_observation_comment', async (req, res) => {
  try {
    if (!checkProperty(req.query, 'observation_id')) {
      return res.json({ observation_comment: null });
    }

    const obsId = parseInt(req.query.observation_id, 10);
    const [rows] = await npnPool.query(
      `SELECT Comment FROM usanpn2.Observation WHERE Observation_ID = ? LIMIT 1`,
      [obsId]
    );

    res.json({ observation_comment: rows.length > 0 ? (rows[0].Comment || null) : null });
  } catch (err) {
    console.error('get_observation_comment error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_all_observations_for_species
router.all('/get_all_observations_for_species', async (req, res) => {
  try {
    const p = req.query;

    if (!checkProperty(p, 'start_date') || !checkProperty(p, 'end_date')) {
      return res.status(400).json({ error: 'start_date and end_date are required' });
    }

    const conditions = ['Observation_Date BETWEEN ? AND ?'];
    const params = [p.start_date, p.end_date];

    if (checkProperty(p, 'species_id')) {
      const ids = arrayWrap(p.species_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
      if (ids.length > 0) {
        conditions.push('Species_ID IN (?)');
        params.push(ids);
      }
    }

    if (checkProperty(p, 'network_id')) {
      const ids = arrayWrap(p.network_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
      if (ids.length > 0) {
        const networkClauses = [];
        for (const nid of ids) {
          networkClauses.push(`Network_IDs LIKE ?`, `Network_IDs LIKE ?`, `Network_IDs LIKE ?`, `Network_IDs = ?`);
          params.push(`%,${nid},%`, `${nid},%`, `%,${nid}`, String(nid));
        }
        conditions.push(`(${networkClauses.join(' OR ')})`);
      }
    }

    const sql = `
      SELECT
        Station_ID, Station_Name, Latitude, Longitude, Network_IDs,
        Species_ID, Phenophase_ID, Phenophase_Name,
        SUM(Observation_Extent = 1)  AS y,
        SUM(Observation_Extent = 0)  AS n,
        SUM(Observation_Extent = -1) AS q
      FROM usanpn2.vw_Observations_By_Species_And_Station
      WHERE ${conditions.join(' AND ')}
      GROUP BY Station_ID, Species_ID, Phenophase_ID
      HAVING y > 0 OR n > 0 OR q > 0
      ORDER BY Station_ID, Species_ID
    `;

    const [rows] = await npnPool.query(sql, params);

    // Build nested response: {station_list, phenophase_list}
    const stationMap = new Map();
    const phenophaseMap = new Map();

    for (const r of rows) {
      const sid = r.Station_ID;
      if (!stationMap.has(sid)) {
        const entry = {
          station_id: sid,
          station_name: r.Station_Name,
          latitude: parseFloat(r.Latitude),
          longitude: parseFloat(r.Longitude),
          species: {},
        };
        if (r.Network_IDs) {
          entry.networks = r.Network_IDs.split(',').map(Number).filter(Boolean);
        }
        stationMap.set(sid, entry);
      }

      const station = stationMap.get(sid);
      const spKey = String(r.Species_ID);
      const ppKey = String(r.Phenophase_ID);

      if (!station.species[spKey]) station.species[spKey] = {};
      const counts = {};
      if (r.y > 0) counts.y = Number(r.y);
      if (r.n > 0) counts.n = Number(r.n);
      if (r.q > 0) counts.q = Number(r.q);
      station.species[spKey][ppKey] = counts;

      if (!phenophaseMap.has(r.Phenophase_ID)) {
        phenophaseMap.set(r.Phenophase_ID, {
          phenophase_id: r.Phenophase_ID,
          phenophase_name: r.Phenophase_Name,
        });
      }
    }

    res.json({
      station_list: Array.from(stationMap.values()),
      phenophase_list: Array.from(phenophaseMap.values()),
    });
  } catch (err) {
    console.error('get_all_observations_for_species error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_observations_count
router.all('/get_observations_count', async (req, res) => {
  try {
    // Support both GET query params and POST body (old API used POST with JSON body)
    const p = { ...req.query, ...(req.body || {}) };

    const conditions = [];
    const params = [];

    if (checkProperty(p, 'start_date') && checkProperty(p, 'end_date')) {
      conditions.push('co.Observation_Date BETWEEN ? AND ?');
      params.push(p.start_date, p.end_date);
    }

    if (checkProperty(p, 'stations')) {
      const ids = arrayWrap(p.stations).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
      if (ids.length > 0) {
        conditions.push('csd.Site_ID IN (?)');
        params.push(ids);
      }
    }

    if (checkProperty(p, 'state') && String(p.state).trim()) {
      conditions.push('csd.State = ?');
      params.push(p.state);
    }

    if (checkProperty(p, 'dataset_ids')) {
      const ids = arrayWrap(p.dataset_ids).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
      if (ids.length > 0) {
        conditions.push('co.Dataset_ID IN (?)');
        params.push(ids);
      }
    }

    if (checkProperty(p, 'network') && String(p.network).trim()) {
      conditions.push('csd.Partner_Group = ?');
      params.push(p.network);
    }

    if (checkProperty(p, 'species_id')) {
      const ids = arrayWrap(p.species_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
      if (ids.length > 0) {
        conditions.push('csd.Species_ID IN (?)');
        params.push(ids);
      }
    }

    if (checkProperty(p, 'phenophase_category')) {
      conditions.push('csd.Phenophase_Category = ?');
      params.push(p.phenophase_category);
    }

    const whereClause = conditions.length > 0 ? 'WHERE ' + conditions.join(' AND ') : '';

    const countField = (checkProperty(p, 'is_magnitude') && p.is_magnitude == 1)
      ? 'COUNT(DISTINCT csd.Species_ID, csd.Phenophase_ID)'
      : 'COUNT(co.Observation_ID)';

    const sql = `
      SELECT ${countField} AS cnt
      FROM usanpn2.Cached_Summarized_Data csd
      LEFT JOIN usanpn2.Cached_Observation co ON co.Series_ID = csd.Series_ID
      ${whereClause}
    `;

    const [rows] = await npnPool.query(sql, params);
    res.json({ obsCount: rows[0].cnt });
  } catch (err) {
    console.error('get_observations_count error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_observation_dates
router.all('/get_observation_dates', async (req, res) => {
  try {
    const p = req.query;

    if (!checkProperty(p, 'year') && !checkProperty(p, 'species_id')) {
      return res.json({ error_message: '`year` required input parameter' });
    }
    if (!checkProperty(p, 'year')) {
      return res.json({ error_message: '`year` required input parameter' });
    }
    if (!checkProperty(p, 'species_id')) {
      return res.json({ error_message: '`species_id` required input parameter' });
    }

    const years = arrayWrap(p.year).map(y => parseInt(y, 10)).filter(y => !isNaN(y));
    const speciesIds = arrayWrap(p.species_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    const params = [speciesIds];

    let whereExtra = '';

    if (checkProperty(p, 'station_id')) {
      const stIds = arrayWrap(p.station_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
      whereExtra += ` AND csd.Site_ID IN (?)`;
      params.push(stIds);
    }

    if (checkProperty(p, 'phenophase_id')) {
      const ppIds = arrayWrap(p.phenophase_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
      whereExtra += ` AND csd.Phenophase_ID IN (?)`;
      params.push(ppIds);
    }

    if (checkProperty(p, 'person_id')) {
      const pIds = arrayWrap(p.person_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
      whereExtra += ` AND co.ObservedBy_Person_ID IN (?)`;
      params.push(pIds);
    }

    if (checkProperty(p, 'pheno_class_id')) {
      const pcIds = arrayWrap(p.pheno_class_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
      if (pcIds.length > 0) {
        whereExtra += ` AND csd.Pheno_Class_ID IN (?)`;
        params.push(pcIds);
      }
    }

    const odPhenoClassAgg = checkProperty(p, 'pheno_class_aggregate') && String(p.pheno_class_aggregate) === '1';
    const phenoGroupCol = odPhenoClassAgg ? 'csd.Pheno_Class_ID' : 'csd.Phenophase_ID';

    // HAVING clause for year filter
    const havingParts = years.map(() => '`year` = ?');
    params.push(...years);

    const sql = `
      SELECT
        YEAR(co.Observation_Date) AS \`year\`,
        COUNT(co.Observation_ID) AS \`count\`,
        pp.Phenophase_ID,
        pp.Phenophase_Name,
        ppp.Seq_Num,
        pp.Color,
        csd.Pheno_Class_ID,
        csd.Pheno_Class_Name,
        csd.Species_ID,
        csd.Common_Name,
        GROUP_CONCAT(DISTINCT IF(co.Phenophase_Status=1, DATE_FORMAT(co.Observation_Date,'%j'), NULL) ORDER BY co.Observation_Date) AS Dates_Positive,
        GROUP_CONCAT(DISTINCT IF(co.Phenophase_Status=0, DATE_FORMAT(co.Observation_Date,'%j'), NULL) ORDER BY co.Observation_Date) AS Dates_Negative
      FROM usanpn2.Cached_Observation co
      LEFT JOIN usanpn2.Cached_Summarized_Data csd ON csd.Series_ID = co.Series_ID
      LEFT JOIN usanpn2.Cached_Phenophase pp ON pp.Phenophase_ID = csd.Phenophase_ID
      LEFT JOIN usanpn2.Protocol_Phenophase ppp ON ppp.Phenophase_ID = csd.Phenophase_ID AND ppp.Protocol_ID = co.Protocol_ID
      WHERE csd.Species_ID IN (?) ${whereExtra}
        AND (co.Phenophase_Status = 1 OR co.Phenophase_Status = 0)
      GROUP BY csd.Species_ID, ${phenoGroupCol}, YEAR(co.Observation_Date)
      HAVING ${havingParts.join(' OR ')}
      ORDER BY csd.Species_ID ASC, ${phenoGroupCol} ASC, \`year\` ASC
    `;

    const conn = await npnPool.getConnection();
    await conn.query('SET SESSION group_concat_max_len = 10000000');
    const [rows] = await conn.query(sql, params);
    conn.release();

    if (!rows || rows.length === 0) {
      return res.json({ error_message: 'No results found' });
    }

    // Build nested structure
    const speciesMap = {};
    for (const row of rows) {
      const sId = row.Species_ID;
      if (!speciesMap[sId]) {
        const entry = {
          species_id: sId,
          common_name: row.Common_Name,
          phenophases: [],
        };
        if (odPhenoClassAgg) entry.pheno_classes = [];
        speciesMap[sId] = entry;
      }
      const species = speciesMap[sId];
      const listKey = odPhenoClassAgg ? 'pheno_classes' : 'phenophases';

      let pheno;
      if (odPhenoClassAgg) {
        pheno = species[listKey].find(p => p.pheno_class_id === row.Pheno_Class_ID);
        if (!pheno) {
          pheno = {
            pheno_class_id: row.Pheno_Class_ID,
            pheno_class_name: row.Pheno_Class_Name,
            years: {},
          };
          species[listKey].push(pheno);
        }
      } else {
        pheno = species[listKey].find(p => p.phenophase_id === row.Phenophase_ID);
        if (!pheno) {
          pheno = {
            phenophase_id: row.Phenophase_ID,
            phenophase_name: row.Phenophase_Name,
            seq_num: row.Seq_Num,
            years: {},
          };
          species[listKey].push(pheno);
        }
      }

      const yr = String(row.year);
      let positive = row.Dates_Positive
        ? row.Dates_Positive.split(',').map(d => parseInt(d, 10)).filter(d => d > 0)
        : [];
      let negative = row.Dates_Negative
        ? row.Dates_Negative.split(',').map(d => parseInt(d, 10)).filter(d => d > 0)
        : [];

      if (positive.length > 0) {
        const positiveSet = new Set(positive);
        negative = negative.filter(d => !positiveSet.has(d));
      }

      pheno.years[yr] = { positive, negative };
    }

    res.json(Object.values(speciesMap));
  } catch (err) {
    console.error('get_observation_dates error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_observation_by_id
router.all('/get_observation_by_id', async (req, res) => {
  try {
    if (!checkProperty(req.query, 'observation_id')) {
      return res.status(400).json({ error: 'observation_id is required' });
    }

    const obsIds = arrayWrap(req.query.observation_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));

    const [rows] = await npnPool.query(
      `SELECT * FROM usanpn2.Observation WHERE Observation_ID IN (?)`,
      [obsIds]
    );

    res.json(obsIds.length === 1 ? (rows[0] || null) : rows);
  } catch (err) {
    console.error('get_observation_by_id error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// GET /get_dataset_details
router.all('/get_dataset_details', async (req, res) => {
  try {
    const [rows] = await npnPool.query(
      `SELECT Dataset_ID, Dataset_Name, Dataset_Description, Contact_Name,
              Contact_Institution, Contact_Email, Contact_Phone, Contact_Address,
              Dataset_Comments, Dataset_Documentation_URL
       FROM usanpn2.vw_Dataset_Details
       ORDER BY Dataset_ID ASC`
    );
    res.json(rows.map(r => ({
      dataset_id: r.Dataset_ID,
      dataset_name: r.Dataset_Name,
      dataset_description: r.Dataset_Description,
      contact_name: r.Contact_Name,
      contact_institution: r.Contact_Institution,
      contact_email: r.Contact_Email,
      contact_phone: r.Contact_Phone,
      contact_address: r.Contact_Address,
      dataset_comments: r.Dataset_Comments,
      dataset_documentation_url: r.Dataset_Documentation_URL,
    })));
  } catch (err) {
    console.error('get_dataset_details error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// Whitelist map for additional_field param: lowercase key → {table, col}
const ADDITIONAL_FIELD_MAP = {
  // CSD fields
  partner_group:                           { table: 'csd', col: 'Partner_Group' },
  site_name:                               { table: 'csd', col: 'Site_Name' },
  species_functional_type:                 { table: 'csd', col: 'Species_Functional_Type' },
  species_category:                        { table: 'csd', col: 'Species_Category' },
  lifecycle_duration:                      { table: 'csd', col: 'Lifecycle_Duration' },
  growth_habit:                            { table: 'csd', col: 'Growth_Habit' },
  usda_plants_symbol:                      { table: 'csd', col: 'USDA_PLANTS_Symbol' },
  itis_number:                             { table: 'csd', col: 'ITIS_Number' },
  plant_nickname:                          { table: 'csd', col: 'Plant_Nickname' },
  patch:                                   { table: 'csd', col: 'Patch' },
  phenophase_category:                     { table: 'csd', col: 'Phenophase_Category' },
  pheno_class_id:                          { table: 'csd', col: 'Pheno_Class_ID' },
  pheno_class_name:                        { table: 'csd', col: 'Pheno_Class_Name' },
  genus_id:                                { table: 'csd', col: 'Genus_ID' },
  genus_common_name:                       { table: 'csd', col: 'Genus_Common_Name' },
  class_id:                                { table: 'csd', col: 'Class_ID' },
  class_name:                              { table: 'csd', col: 'Class_Name' },
  class_common_name:                       { table: 'csd', col: 'Class_Common_Name' },
  order_id:                                { table: 'csd', col: 'Order_ID' },
  order_name:                              { table: 'csd', col: 'Order_Name' },
  order_common_name:                       { table: 'csd', col: 'Order_Common_Name' },
  family_id:                               { table: 'csd', col: 'Family_ID' },
  family_name:                             { table: 'csd', col: 'Family_Name' },
  family_common_name:                      { table: 'csd', col: 'Family_Common_Name' },
  // CO fields
  dataset_id:                              { table: 'co', col: 'Dataset_ID' },
  observedby_person_id:                    { table: 'co', col: 'ObservedBy_Person_ID' },
  submission_id:                           { table: 'co', col: 'Submission_ID' },
  submittedby_person_id:                   { table: 'co', col: 'SubmittedBy_Person_ID' },
  submission_datetime:                     { table: 'co', col: 'Submission_Datetime' },
  updatedby_person_id:                     { table: 'co', col: 'UpdatedBy_Person_ID' },
  update_datetime:                         { table: 'co', col: 'Update_Datetime' },
  protocol_id:                             { table: 'co', col: 'Protocol_ID' },
  phenophase_name:                         { table: 'co', col: 'Phenophase_Name' },
  phenophase_definition_id:                { table: 'co', col: 'Phenophase_Definition_ID' },
  secondary_species_specific_definition_id:{ table: 'co', col: 'Secondary_Species_Specific_Definition_ID' },
  observation_time:                        { table: 'co', col: 'Observation_Time' },
  observation_group_id:                    { table: 'co', col: 'Observation_Group_ID' },
  observation_comments:                    { table: 'co', col: 'Observation_Comments' },
  observed_status_conflict_flag:           { table: 'co', col: 'Observed_Status_Conflict_Flag' },
  status_conflict_related_records:         { table: 'co', col: 'Status_Conflict_Related_Records' },
  gdd:                                     { table: 'co', col: 'gdd',          decimal: true },
  gddf:                                    { table: 'co', col: 'gddf',         decimal: true },
  tmax_winter:                             { table: 'co', col: 'tmax_winter',  decimal: true },
  tmax_spring:                             { table: 'co', col: 'tmax_spring',  decimal: true },
  tmax_summer:                             { table: 'co', col: 'tmax_summer',  decimal: true },
  tmax_fall:                               { table: 'co', col: 'tmax_fall',    decimal: true },
  tmax:                                    { table: 'co', col: 'tmax',         decimal: true },
  tmaxf:                                   { table: 'co', col: 'tmaxf',        decimal: true },
  tmin_winter:                             { table: 'co', col: 'tmin_winter',  decimal: true },
  tmin_spring:                             { table: 'co', col: 'tmin_spring',  decimal: true },
  tmin_summer:                             { table: 'co', col: 'tmin_summer',  decimal: true },
  tmin_fall:                               { table: 'co', col: 'tmin_fall',    decimal: true },
  tmin:                                    { table: 'co', col: 'tmin',         decimal: true },
  tminf:                                   { table: 'co', col: 'tminf',        decimal: true },
  prcp_winter:                             { table: 'co', col: 'prcp_winter',  decimal: true },
  prcp_spring:                             { table: 'co', col: 'prcp_spring',  decimal: true },
  prcp_summer:                             { table: 'co', col: 'prcp_summer',  decimal: true },
  prcp_fall:                               { table: 'co', col: 'prcp_fall',    decimal: true },
  prcp:                                    { table: 'co', col: 'prcp',         decimal: true },
  acc_prcp:                                { table: 'co', col: 'acc_prcp',     decimal: true },
  daylength:                               { table: 'co', col: 'daylength' },
  greenup_0:                               { table: 'co', col: 'Greenup_0' },
  greenup_1:                               { table: 'co', col: 'Greenup_1' },
  midgreenup_0:                            { table: 'co', col: 'MidGreenup_0' },
  midgreenup_1:                            { table: 'co', col: 'MidGreenup_1' },
  peak_0:                                  { table: 'co', col: 'Peak_0' },
  peak_1:                                  { table: 'co', col: 'Peak_1' },
  numcycles:                               { table: 'co', col: 'NumCycles' },
  maturity_0:                              { table: 'co', col: 'Maturity_0' },
  maturity_1:                              { table: 'co', col: 'Maturity_1' },
  midgreendown_0:                          { table: 'co', col: 'MidGreendown_0' },
  midgreendown_1:                          { table: 'co', col: 'MidGreendown_1' },
  senescence_0:                            { table: 'co', col: 'Senescence_0' },
  senescence_1:                            { table: 'co', col: 'Senescence_1' },
  dormancy_0:                              { table: 'co', col: 'Dormancy_0' },
  dormancy_1:                              { table: 'co', col: 'Dormancy_1' },
  evi_minimum_0:                           { table: 'co', col: 'EVI_Minimum_0' },
  evi_minimum_1:                           { table: 'co', col: 'EVI_Minimum_1' },
  evi_amplitude_0:                         { table: 'co', col: 'EVI_Amplitude_0' },
  evi_amplitude_1:                         { table: 'co', col: 'EVI_Amplitude_1' },
  evi_area_0:                              { table: 'co', col: 'EVI_Area_0' },
  evi_area_1:                              { table: 'co', col: 'EVI_Area_1' },
  qa_detailed_0:                           { table: 'co', col: 'QA_Detailed_0' },
  qa_detailed_1:                           { table: 'co', col: 'QA_Detailed_1' },
  qa_overall_0:                            { table: 'co', col: 'QA_Overall_0' },
  qa_overall_1:                            { table: 'co', col: 'QA_Overall_1' },
};

// Fields already in the base SELECT — skip if requested as additional_field
const BASE_OBSERVATION_KEYS = new Set(['update_datetime']);

// GET /get_observations
router.all('/get_observations', async (req, res) => {
  const p = req.query;
  const conditions = [];
  const params = [];

  if (checkProperty(p, 'start_date')) {
    conditions.push('co.Observation_Date >= ?');
    params.push(p.start_date);
  }
  if (checkProperty(p, 'end_date')) {
    conditions.push('co.Observation_Date <= ?');
    params.push(p.end_date);
  }
  if (checkProperty(p, 'state')) {
    const states = arrayWrap(p.state).filter(s => s !== '' && s !== null && s !== undefined);
    if (states.length > 0) {
      conditions.push('csd.State IN (?)');
      params.push(states);
    }
  }
  if (checkProperty(p, 'species_id')) {
    const ids = arrayWrap(p.species_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    if (ids.length > 0) {
      conditions.push('csd.Species_ID IN (?)');
      params.push(ids);
    }
  }
  if (checkProperty(p, 'station_id')) {
    const ids = arrayWrap(p.station_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    if (ids.length > 0) {
      conditions.push('csd.Site_ID IN (?)');
      params.push(ids);
    }
  }
  if (checkProperty(p, 'individual_id')) {
    const ids = arrayWrap(p.individual_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    if (ids.length > 0) {
      conditions.push('csd.Individual_ID IN (?)');
      params.push(ids);
    }
  }
  if (checkProperty(p, 'phenophase_id')) {
    const ids = arrayWrap(p.phenophase_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    if (ids.length > 0) {
      conditions.push('csd.Phenophase_ID IN (?)');
      params.push(ids);
    }
  }
  if (checkProperty(p, 'dataset_ids')) {
    const ids = arrayWrap(p.dataset_ids).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    if (ids.length > 0) {
      conditions.push('co.Dataset_ID IN (?)');
      params.push(ids);
    }
  }
  if (checkProperty(p, 'pheno_class_id')) {
    const ids = arrayWrap(p.pheno_class_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    if (ids.length > 0) {
      conditions.push('csd.Pheno_Class_ID IN (?)');
      params.push(ids);
    }
  }
  if (checkProperty(p, 'kingdom') && String(p.kingdom).trim()) {
    conditions.push('csd.Kingdom = ?');
    params.push(p.kingdom);
  }
  if (checkProperty(p, 'group_id')) {
    const ids = arrayWrap(p.group_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    if (ids.length > 0) {
      conditions.push('co.Observation_Group_ID IN (?)');
      params.push(ids);
    }
  }

  const whereClause = conditions.length > 0 ? 'WHERE ' + conditions.join(' AND ') : '';
  const limitClause = checkProperty(p, 'limit') ? `LIMIT ${parseInt(p.limit, 10)}` : '';

  const extraKeys = [];
  const extraCols = [];
  if (checkProperty(p, 'additional_field')) {
    const requested = arrayWrap(p.additional_field).map(f => String(f).toLowerCase());
    for (const key of requested) {
      if (BASE_OBSERVATION_KEYS.has(key)) continue;
      const def = ADDITIONAL_FIELD_MAP[key];
      if (!def) continue;
      extraCols.push(`${def.table}.${def.col} AS ${key}`);
      extraKeys.push(key);
    }
  }

  const extraSelect = extraCols.length > 0 ? ',\n        ' + extraCols.join(',\n        ') : '';

  const sql = `
    SELECT
      co.Observation_ID                                         AS observation_id,
      co.Update_Datetime                                        AS update_datetime,
      csd.Site_ID                                               AS site_id,
      csd.Latitude                                              AS latitude,
      csd.Longitude                                             AS longitude,
      csd.Elevation_in_Meters                                   AS elevation_in_meters,
      csd.State                                                 AS state,
      csd.Species_ID                                            AS species_id,
      csd.Genus                                                 AS genus,
      csd.Species                                               AS species,
      csd.Common_Name                                           AS common_name,
      csd.Kingdom                                               AS kingdom,
      csd.Individual_ID                                         AS individual_id,
      csd.Phenophase_ID                                         AS phenophase_id,
      csd.Phenophase_Description                                AS phenophase_description,
      co.Observation_Date                                       AS observation_date,
      co.Day_of_Year                                            AS day_of_year,
      co.Phenophase_Status                                      AS phenophase_status,
      IFNULL(co.Intensity_Category_ID, -9999)                  AS intensity_category_id,
      co.Intensity_Value                                        AS intensity_value,
      IFNULL(co.Abundance_Value, -9999)                        AS abundance_value${extraSelect}
    FROM usanpn2.Cached_Summarized_Data csd
    INNER JOIN usanpn2.Cached_Observation co ON co.Series_ID = csd.Series_ID
    ${whereClause}
    ORDER BY co.Observation_Date ASC
    ${limitClause}
  `;

  let conn;
  try {
    conn = await npnPool.getConnection();
  } catch (err) {
    console.error('get_observations error:', err.message);
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
    const base = {
      ...row,
      update_datetime: row.update_datetime !== null ? row.update_datetime : -9999,
      intensity_value: (row.intensity_value === null || row.intensity_value === '-9999') ? -9999 : row.intensity_value,
    };
    for (const key of extraKeys) {
      const raw = row[key];
      if (raw === null || raw === undefined) {
        base[key] = -9999;
      } else if (ADDITIONAL_FIELD_MAP[key].decimal) {
        base[key] = parseFloat(raw);
      } else {
        base[key] = raw;
      }
    }
    const chunk = (first ? '' : ',') + JSON.stringify(base);
    first = false;
    if (!res.write(chunk)) rawConn.pause();
  });

  res.on('drain', () => rawConn.resume());

  req.on('close', () => { ended = true; released = true; rawConn.destroy(); });

  q.on('end', () => { if (ended) return; ended = true; res.write(']'); res.end(); release(); });

  q.on('error', (err) => {
    console.error('get_observations stream error:', err.message);
    if (ended) { release(); return; }
    ended = true;
    if (!res.headersSent) res.status(500).json({ error: err.message });
    else { try { res.end(']'); } catch (_) {} }
    release();
  });
});

// GET /get_summarized_data
router.all('/get_summarized_data', async (req, res) => {
  const p = req.query;

  if (!checkProperty(p, 'start_date') || !checkProperty(p, 'end_date')) {
    return res.status(400).json({ error: 'start_date and end_date are required' });
  }

  const startDate = p.start_date;
  const endDate = p.end_date;

  const seriesConditions = [];
  const seriesParams = [];

  if (checkProperty(p, 'state')) {
    const states = arrayWrap(p.state).filter(s => s !== '' && s !== null && s !== undefined);
    if (states.length > 0) {
      seriesConditions.push('csd.State IN (?)');
      seriesParams.push(states);
    }
  }
  if (checkProperty(p, 'species_id')) {
    const ids = arrayWrap(p.species_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    if (ids.length > 0) {
      seriesConditions.push('csd.Species_ID IN (?)');
      seriesParams.push(ids);
    }
  }
  if (checkProperty(p, 'station_id')) {
    const ids = arrayWrap(p.station_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    if (ids.length > 0) {
      seriesConditions.push('csd.Site_ID IN (?)');
      seriesParams.push(ids);
    }
  }
  if (checkProperty(p, 'individual_id')) {
    const ids = arrayWrap(p.individual_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    if (ids.length > 0) {
      seriesConditions.push('csd.Individual_ID IN (?)');
      seriesParams.push(ids);
    }
  }
  if (checkProperty(p, 'phenophase_id')) {
    const ids = arrayWrap(p.phenophase_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    if (ids.length > 0) {
      seriesConditions.push('csd.Phenophase_ID IN (?)');
      seriesParams.push(ids);
    }
  }
  if (checkProperty(p, 'pheno_class_id')) {
    const ids = arrayWrap(p.pheno_class_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    if (ids.length > 0) {
      seriesConditions.push('csd.Pheno_Class_ID IN (?)');
      seriesParams.push(ids);
    }
  }
  if (checkProperty(p, 'kingdom') && String(p.kingdom).trim()) {
    seriesConditions.push('csd.Kingdom = ?');
    seriesParams.push(p.kingdom);
  }

  const seriesWhere = seriesConditions.length > 0 ? 'AND ' + seriesConditions.join(' AND ') : '';
  const limitClause = checkProperty(p, 'limit') ? `LIMIT ${parseInt(p.limit, 10)}` : '';

  const params = [
    startDate, endDate,
    startDate, endDate,
    startDate, endDate,
    ...seriesParams,
  ];

  const sql = `
    WITH series_yes AS (
      SELECT
        co.Series_ID,
        MIN(CASE WHEN co.Phenophase_Status = 1 THEN co.Observation_Date END) AS first_yes_date,
        MAX(CASE WHEN co.Phenophase_Status = 1 THEN co.Observation_Date END) AS last_yes_date
      FROM usanpn2.Cached_Observation co
      WHERE co.Observation_Date BETWEEN ? AND ?
      GROUP BY co.Series_ID
      HAVING first_yes_date IS NOT NULL
    ),
    prior_no AS (
      SELECT co.Series_ID, MAX(co.Observation_Date) AS prior_no_date
      FROM usanpn2.Cached_Observation co
      INNER JOIN series_yes sy ON sy.Series_ID = co.Series_ID
      WHERE co.Phenophase_Status = 0
        AND co.Observation_Date BETWEEN ? AND ?
        AND co.Observation_Date < sy.first_yes_date
      GROUP BY co.Series_ID
    ),
    next_no AS (
      SELECT co.Series_ID, MIN(co.Observation_Date) AS next_no_date
      FROM usanpn2.Cached_Observation co
      INNER JOIN series_yes sy ON sy.Series_ID = co.Series_ID
      WHERE co.Phenophase_Status = 0
        AND co.Observation_Date BETWEEN ? AND ?
        AND co.Observation_Date > sy.last_yes_date
      GROUP BY co.Series_ID
    )
    SELECT
      csd.Site_ID                                                           AS site_id,
      csd.Latitude                                                          AS latitude,
      csd.Longitude                                                         AS longitude,
      csd.Elevation_in_Meters                                               AS elevation_in_meters,
      csd.State                                                             AS state,
      csd.Species_ID                                                        AS species_id,
      csd.Genus                                                             AS genus,
      csd.Species                                                           AS species,
      csd.Common_Name                                                       AS common_name,
      csd.Kingdom                                                           AS kingdom,
      csd.Individual_ID                                                     AS individual_id,
      csd.Phenophase_ID                                                     AS phenophase_id,
      csd.Phenophase_Description                                            AS phenophase_description,
      YEAR(sy.first_yes_date)                                               AS first_yes_year,
      MONTH(sy.first_yes_date)                                              AS first_yes_month,
      DAY(sy.first_yes_date)                                                AS first_yes_day,
      DAYOFYEAR(sy.first_yes_date)                                          AS first_yes_doy,
      ROUND(UNIX_TIMESTAMP(sy.first_yes_date) / 86400.0 + 2440587.5)       AS first_yes_julian_date,
      IFNULL(DATEDIFF(sy.first_yes_date, pn.prior_no_date), -9999)         AS numdays_since_prior_no,
      YEAR(sy.last_yes_date)                                                AS last_yes_year,
      MONTH(sy.last_yes_date)                                               AS last_yes_month,
      DAY(sy.last_yes_date)                                                 AS last_yes_day,
      DAYOFYEAR(sy.last_yes_date)                                           AS last_yes_doy,
      ROUND(UNIX_TIMESTAMP(sy.last_yes_date) / 86400.0 + 2440587.5)        AS last_yes_julian_date,
      IFNULL(DATEDIFF(nn.next_no_date, sy.last_yes_date), -9999)           AS numdays_until_next_no
    FROM usanpn2.Cached_Summarized_Data csd
    INNER JOIN series_yes sy ON sy.Series_ID = csd.Series_ID
    LEFT JOIN prior_no pn ON pn.Series_ID = csd.Series_ID
    LEFT JOIN next_no nn ON nn.Series_ID = csd.Series_ID
    WHERE 1=1 ${seriesWhere}
    ORDER BY csd.Site_ID ASC, csd.Species_ID ASC
    ${limitClause}
  `;

  let conn;
  try {
    conn = await npnPool.getConnection();
  } catch (err) {
    console.error('get_summarized_data error:', err.message);
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
    const chunk = (first ? '' : ',') + JSON.stringify({
      ...row,
      first_yes_julian_date: parseInt(row.first_yes_julian_date, 10),
      last_yes_julian_date: parseInt(row.last_yes_julian_date, 10),
    });
    first = false;
    if (!res.write(chunk)) rawConn.pause();
  });

  res.on('drain', () => rawConn.resume());
  req.on('close', () => { ended = true; released = true; rawConn.destroy(); });
  q.on('end', () => { if (ended) return; ended = true; res.write(']'); res.end(); release(); });
  q.on('error', (err) => {
    console.error('get_summarized_data stream error:', err.message);
    if (ended) { release(); return; }
    ended = true;
    if (!res.headersSent) res.status(500).json({ error: err.message });
    else { try { res.end(']'); } catch (_) {} }
    release();
  });
});

// GET /get_site_level_data
router.all('/get_site_level_data', async (req, res) => {
  const p = req.query;

  if (!checkProperty(p, 'start_date') || !checkProperty(p, 'end_date')) {
    return res.status(400).json({ error: 'start_date and end_date are required' });
  }

  const startDate = p.start_date;
  const endDate = p.end_date;

  const seriesConditions = [];
  const seriesParams = [];

  if (checkProperty(p, 'species_id')) {
    const ids = arrayWrap(p.species_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    if (ids.length > 0) {
      seriesConditions.push('csd.Species_ID IN (?)');
      seriesParams.push(ids);
    }
  }

  if (checkProperty(p, 'station_id')) {
    const ids = arrayWrap(p.station_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    if (ids.length > 0) {
      seriesConditions.push('csd.Site_ID IN (?)');
      seriesParams.push(ids);
    }
  }

  if (checkProperty(p, 'phenophase_id')) {
    const ids = arrayWrap(p.phenophase_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    if (ids.length > 0) {
      seriesConditions.push('csd.Phenophase_ID IN (?)');
      seriesParams.push(ids);
    }
  }

  if (checkProperty(p, 'network_id')) {
    const ids = arrayWrap(p.network_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    if (ids.length > 0) {
      seriesConditions.push('csd.Network_ID IN (?)');
      seriesParams.push(ids);
    }
  }

  if (checkProperty(p, 'pheno_class_id')) {
    const ids = arrayWrap(p.pheno_class_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    if (ids.length > 0) {
      seriesConditions.push('csd.Pheno_Class_ID IN (?)');
      seriesParams.push(ids);
    }
  }

  const slPhenoClassAggregate = checkProperty(p, 'pheno_class_aggregate') && String(p.pheno_class_aggregate) === '1';
  const slTaxonomyAggregate = checkProperty(p, 'taxonomy_aggregate') && String(p.taxonomy_aggregate) === '1';

  const seriesWhere = seriesConditions.length > 0 ? 'AND ' + seriesConditions.join(' AND ') : '';
  const params = [startDate, endDate, startDate, endDate, startDate, endDate, ...seriesParams];

  const sql = `
    WITH series_yes AS (
      SELECT co.Series_ID,
        MIN(CASE WHEN co.Phenophase_Status = 1 THEN co.Observation_Date END) AS first_yes_date,
        MAX(CASE WHEN co.Phenophase_Status = 1 THEN co.Observation_Date END) AS last_yes_date
      FROM usanpn2.Cached_Observation co
      WHERE co.Observation_Date BETWEEN ? AND ?
      GROUP BY co.Series_ID
      HAVING first_yes_date IS NOT NULL
    ),
    prior_no AS (
      SELECT co.Series_ID, MAX(co.Observation_Date) AS prior_no_date
      FROM usanpn2.Cached_Observation co
      INNER JOIN series_yes sy ON sy.Series_ID = co.Series_ID
      WHERE co.Phenophase_Status = 0
        AND co.Observation_Date BETWEEN ? AND ?
        AND co.Observation_Date < sy.first_yes_date
      GROUP BY co.Series_ID
    ),
    next_no AS (
      SELECT co.Series_ID, MIN(co.Observation_Date) AS next_no_date
      FROM usanpn2.Cached_Observation co
      INNER JOIN series_yes sy ON sy.Series_ID = co.Series_ID
      WHERE co.Phenophase_Status = 0
        AND co.Observation_Date BETWEEN ? AND ?
        AND co.Observation_Date > sy.last_yes_date
      GROUP BY co.Series_ID
    )
    SELECT
      csd.Site_ID                                                           AS site_id,
      csd.Latitude                                                          AS latitude,
      csd.Longitude                                                         AS longitude,
      csd.Elevation_in_Meters                                               AS elevation_in_meters,
      csd.State                                                             AS state,
      csd.Species_ID                                                        AS species_id,
      csd.Genus                                                             AS genus,
      csd.Species                                                           AS species,
      csd.Common_Name                                                       AS common_name,
      csd.Kingdom                                                           AS kingdom,
      csd.Phenophase_ID                                                     AS phenophase_id,
      csd.Phenophase_Description                                            AS phenophase_description,
      csd.Pheno_Class_ID                                                    AS pheno_class_id,
      csd.Pheno_Class_Name                                                  AS pheno_class_name,
      DAYOFYEAR(sy.first_yes_date)                                          AS first_yes_doy,
      ROUND(UNIX_TIMESTAMP(sy.first_yes_date) / 86400.0 + 2440587.5)       AS first_yes_julian_date,
      IFNULL(DATEDIFF(sy.first_yes_date, pn.prior_no_date), -9999)         AS numdays_since_prior_no,
      DAYOFYEAR(sy.last_yes_date)                                           AS last_yes_doy,
      ROUND(UNIX_TIMESTAMP(sy.last_yes_date) / 86400.0 + 2440587.5)        AS last_yes_julian_date,
      IFNULL(DATEDIFF(nn.next_no_date, sy.last_yes_date), -9999)           AS numdays_until_next_no
    FROM usanpn2.Cached_Summarized_Data csd
    INNER JOIN series_yes sy ON sy.Series_ID = csd.Series_ID
    LEFT JOIN prior_no pn ON pn.Series_ID = csd.Series_ID
    LEFT JOIN next_no nn ON nn.Series_ID = csd.Series_ID
    WHERE 1=1 ${seriesWhere}
  `;

  function stdErrSample(arr) {
    if (arr.length <= 1) return -9999;
    const mean = arr.reduce((a, b) => a + b, 0) / arr.length;
    const variance = arr.reduce((a, b) => a + (b - mean) ** 2, 0) / (arr.length - 1);
    return Math.sqrt(variance) / Math.sqrt(arr.length);
  }

  function julianToYearDoy(jd) {
    const unixSec = (jd - 2440587.5) * 86400;
    const d = new Date(unixSec * 1000);
    const year = d.getUTCFullYear();
    const start = Date.UTC(year, 0, 1);
    const doy = Math.floor((d.getTime() - start) / 86400000) + 1;
    return { year, doy };
  }

  let conn;
  try {
    conn = await npnPool.getConnection();
  } catch (err) {
    console.error('get_site_level_data error:', err.message);
    return res.status(500).json({ error: err.message });
  }

  const rawConn = conn.connection;
  let released = false;
  const release = () => { if (!released) { released = true; conn.release(); } };

  const siteMap = new Map();

  const q = rawConn.query(sql, params);

  q.on('result', (r) => {
    const julianFirst = parseInt(r.first_yes_julian_date, 10);
    const julianLast = parseInt(r.last_yes_julian_date, 10);
    const daysSince = r.numdays_since_prior_no;
    const daysUntil = r.numdays_until_next_no;
    const phenoKey = slPhenoClassAggregate ? r.pheno_class_id : r.phenophase_id;
    const speciesKey = (slPhenoClassAggregate && !slTaxonomyAggregate) ? '*' : r.species_id;
    const key = `${r.site_id}|${speciesKey}|${phenoKey}`;

    if (!siteMap.has(key)) {
      const entry = {
        site_id: r.site_id,
        latitude: r.latitude,
        longitude: r.longitude,
        elevation_in_meters: r.elevation_in_meters,
        state: r.state,
        species_id: r.species_id,
        genus: r.genus,
        species: r.species,
        common_name: r.common_name,
        kingdom: r.kingdom,
        phenophase_id: r.phenophase_id,
        phenophase_description: r.phenophase_description,
        firstJulians: [],
        firstDoys: [],
        firstDaysSince: [],
        lastJulians: [],
        lastDoys: [],
        lastDaysUntil: [],
      };
      if (slPhenoClassAggregate) {
        entry.pheno_class_id = r.pheno_class_id;
        entry.pheno_class_name = r.pheno_class_name;
      }
      siteMap.set(key, entry);
    }

    const site = siteMap.get(key);

    if (daysSince > 0 && daysSince <= 30) {
      site.firstJulians.push(julianFirst);
      site.firstDoys.push(r.first_yes_doy);
      site.firstDaysSince.push(daysSince);
    }

    if (daysUntil > 0 && daysUntil <= 30) {
      site.lastJulians.push(julianLast);
      site.lastDoys.push(r.last_yes_doy);
      site.lastDaysUntil.push(daysUntil);
    }
  });

  let ended = false;
  req.on('close', () => { ended = true; released = true; rawConn.destroy(); });

  q.on('end', () => {
    if (ended) { release(); return; }
    ended = true;
    const result = [];
    for (const site of siteMap.values()) {
      const nFirst = site.firstJulians.length;
      const nLast = site.lastJulians.length;
      if (nFirst === 0 && nLast === 0) continue;

      let meanFirstJulian = -9999, meanFirstYear = -9999, meanFirstDoy = -9999;
      let seFirst = -9999, meanDaysSince = -9999, seDaysSince = -9999;

      if (nFirst > 0) {
        meanFirstJulian = Math.round(site.firstJulians.reduce((a, b) => a + b, 0) / nFirst);
        const fd = julianToYearDoy(meanFirstJulian);
        meanFirstYear = fd.year;
        meanFirstDoy = fd.doy;
        seFirst = stdErrSample(site.firstDoys);
        meanDaysSince = Math.round(site.firstDaysSince.reduce((a, b) => a + b, 0) / nFirst);
        seDaysSince = stdErrSample(site.firstDaysSince);
      }

      let meanLastJulian = -9999, meanLastYear = -9999, meanLastDoy = -9999;
      let seLast = -9999, meanDaysUntil = -9999, seDaysUntil = -9999;

      if (nLast > 0) {
        meanLastJulian = Math.round(site.lastJulians.reduce((a, b) => a + b, 0) / nLast);
        const ld = julianToYearDoy(meanLastJulian);
        meanLastYear = ld.year;
        meanLastDoy = ld.doy;
        seLast = stdErrSample(site.lastJulians);
        meanDaysUntil = Math.round(site.lastDaysUntil.reduce((a, b) => a + b, 0) / nLast);
        seDaysUntil = stdErrSample(site.lastDaysUntil);
      }

      const item = {
        site_id: site.site_id,
        latitude: site.latitude,
        longitude: site.longitude,
        elevation_in_meters: site.elevation_in_meters,
        state: site.state,
        species_id: site.species_id,
        genus: site.genus,
        species: site.species,
        common_name: site.common_name,
        kingdom: site.kingdom,
        phenophase_id: site.phenophase_id,
        phenophase_description: site.phenophase_description,
      };
      if (site.pheno_class_id !== undefined) {
        item.pheno_class_id = site.pheno_class_id;
        item.pheno_class_name = site.pheno_class_name;
      }
      Object.assign(item, {
        first_yes_sample_size: nFirst,
        mean_first_yes_year: meanFirstYear,
        mean_first_yes_doy: meanFirstDoy,
        mean_first_yes_julian_date: meanFirstJulian,
        se_first_yes_in_days: seFirst,
        mean_numdays_since_prior_no: meanDaysSince,
        se_numdays_since_prior_no: seDaysSince,
        last_yes_sample_size: nLast,
        mean_last_yes_year: meanLastYear,
        mean_last_yes_doy: meanLastDoy,
        mean_last_yes_julian_date: meanLastJulian,
        se_last_yes_in_days: seLast,
        mean_numdays_until_next_no: meanDaysUntil,
        se_numdays_until_next_no: seDaysUntil,
      });
      result.push(item);
    }

    result.sort((a, b) => a.site_id - b.site_id || a.species_id - b.species_id || (a.phenophase_id || 0) - (b.phenophase_id || 0));

    res.setHeader('Content-Type', 'application/json');
    res.write('[');
    let first = true;
    for (const item of result) {
      res.write((first ? '' : ',') + JSON.stringify(item));
      first = false;
    }
    res.write(']');
    res.end();
    release();
  });

  q.on('error', (err) => {
    console.error('get_site_level_data stream error:', err.message);
    if (ended) { release(); return; }
    ended = true;
    if (!res.headersSent) res.status(500).json({ error: err.message });
    else { try { res.end(']'); } catch (_) {} }
    release();
  });
});

// GET /get_magnitude_data
router.all('/get_magnitude_data', async (req, res) => {
  const p = req.query;

  if (!checkProperty(p, 'start_date') || !checkProperty(p, 'end_date')) {
    return res.status(400).json({ error: 'start_date and end_date are required' });
  }

  const startDate = p.start_date;
  const endDate = p.end_date;
  const frequency = checkProperty(p, 'frequency') ? p.frequency : '30';

  function buildPeriods(start, end, freq) {
    const toUTC = s => new Date(s + 'T00:00:00Z');
    const endDt = toUTC(end);
    const periods = [];

    if (freq === 'months') {
      let cur = toUTC(start);
      while (cur <= endDt) {
        const yr = cur.getUTCFullYear(), mo = cur.getUTCMonth();
        const lastDay = new Date(Date.UTC(yr, mo + 1, 0)).getUTCDate();
        periods.push({ start: new Date(Date.UTC(yr, mo, 1)), end: new Date(Date.UTC(yr, mo, lastDay)) });
        cur = new Date(Date.UTC(yr, mo + 1, 1));
      }
    } else {
      const freqDays = parseInt(freq, 10) - 1;
      let cur = toUTC(start);
      while (cur <= endDt) {
        const pEnd = new Date(cur);
        pEnd.setUTCDate(pEnd.getUTCDate() + freqDays);
        periods.push({ start: new Date(cur), end: new Date(pEnd) });
        cur = new Date(pEnd);
        cur.setUTCDate(cur.getUTCDate() + 1);
      }
      // If last period overshoots end, remove it and extend the previous to end
      if (periods.length > 0 && periods[periods.length - 1].end > endDt) {
        periods.pop();
        if (periods.length > 0) periods[periods.length - 1].end = endDt;
      }
    }
    return periods;
  }

  const periods = buildPeriods(startDate, endDate, frequency);
  if (periods.length === 0) return res.json([]);

  const fmt = d => d.toISOString().slice(0, 10);

  // Build period GROUP BY conditions (boolean expressions, one per period)
  const periodGroupBy = periods.map(per =>
    `(co.Observation_Date BETWEEN '${fmt(per.start)}' AND '${fmt(per.end)}')`
  ).join(', ');

  // Optional filters on CSD
  const filterConds = [];
  const filterParams = [];

  if (checkProperty(p, 'species_id')) {
    const ids = arrayWrap(p.species_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    if (ids.length > 0) { filterConds.push('csd.Species_ID IN (?)'); filterParams.push(ids); }
  }
  if (checkProperty(p, 'phenophase_id')) {
    const ids = arrayWrap(p.phenophase_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    if (ids.length > 0) { filterConds.push('csd.Phenophase_ID IN (?)'); filterParams.push(ids); }
  }
  if (checkProperty(p, 'station_id') || checkProperty(p, 'site_id')) {
    const raw = p.station_id || p.site_id;
    const ids = arrayWrap(raw).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    if (ids.length > 0) { filterConds.push('csd.Site_ID IN (?)'); filterParams.push(ids); }
  }
  if (checkProperty(p, 'network_id')) {
    const ids = arrayWrap(p.network_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    if (ids.length > 0) { filterConds.push('csd.Network_ID IN (?)'); filterParams.push(ids); }
  }
  if (checkProperty(p, 'pheno_class_id')) {
    const ids = arrayWrap(p.pheno_class_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    if (ids.length > 0) { filterConds.push('csd.Pheno_Class_ID IN (?)'); filterParams.push(ids); }
  }

  const phenoClassAggregate = checkProperty(p, 'pheno_class_aggregate') && String(p.pheno_class_aggregate) === '1';
  const taxonomyAggregate = checkProperty(p, 'taxonomy_aggregate') && String(p.taxonomy_aggregate) === '1';

  const groupByCols = [];
  if (phenoClassAggregate) {
    groupByCols.push('csd.Pheno_Class_ID');
    if (taxonomyAggregate) groupByCols.push('csd.Species_ID');
  } else {
    groupByCols.push('csd.Phenophase_ID');
    groupByCols.push('csd.Species_ID');
  }

  const filterWhere = filterConds.length > 0 ? 'AND ' + filterConds.join(' AND ') : '';

  const extraSelectCols = phenoClassAggregate
    ? `,\n      csd.Pheno_Class_ID AS pheno_class_id,\n      csd.Pheno_Class_Name AS pheno_class_name`
    : '';

  const sql = `
    SELECT
      csd.Species_ID                                                  AS species_id,
      csd.Genus                                                       AS genus,
      csd.Species                                                     AS species,
      csd.Common_Name                                                 AS common_name,
      csd.Kingdom                                                     AS kingdom,
      csd.Phenophase_ID                                               AS phenophase_id,
      csd.Phenophase_Description                                      AS phenophase_description${extraSelectCols},
      COUNT(co.Phenophase_Status)                                     AS status_records_sample_size,
      COUNT(DISTINCT csd.Individual_ID)                               AS individuals_sample_size,
      COUNT(DISTINCT csd.Site_ID)                                     AS sites_sample_size,
      SUM(CASE WHEN co.Phenophase_Status = 1 THEN 1 ELSE 0 END)      AS num_yes_records,
      IF(MIN(co.Phenophase_Status) = 0,
         COUNT(DISTINCT csd.Individual_ID * co.Phenophase_Status) - 1,
         COUNT(DISTINCT csd.Individual_ID * co.Phenophase_Status))    AS num_individuals_with_yes_record,
      IF(MIN(co.Phenophase_Status) = 0,
         COUNT(DISTINCT csd.Site_ID * co.Phenophase_Status) - 1,
         COUNT(DISTINCT csd.Site_ID * co.Phenophase_Status))          AS num_sites_with_yes_record,
      GROUP_CONCAT(
        IF(co.Phenophase_Status = 1,
          IF(co.Abundance_Value IS NULL OR co.Abundance_Value = -9999, -1, co.Abundance_Value),
          0)
        ORDER BY co.Observation_ID
      )                                                               AS abundances,
      GROUP_CONCAT(
        IF(co.Search_Time IS NULL, 0, co.Search_Time)
        ORDER BY co.Observation_ID
      )                                                               AS search_times,
      GROUP_CONCAT(
        IFNULL(co.Observation_Group_ID, 0)
        ORDER BY co.Observation_ID
      )                                                               AS observation_group_ids,
      GROUP_CONCAT(
        IFNULL(co.Search_Method, '')
        ORDER BY co.Observation_ID
      )                                                               AS search_methods,
      GROUP_CONCAT(
        csd.Site_ID
        ORDER BY co.Observation_ID
      )                                                               AS site_ids,
      GROUP_CONCAT(
        IF(csd.Site_Area IS NULL, 0, csd.Site_Area)
        ORDER BY co.Observation_ID
      )                                                               AS site_areas,
      MIN(co.Observation_Date)                                        AS sample_date
    FROM usanpn2.Cached_Summarized_Data csd
    INNER JOIN usanpn2.Cached_Observation co ON co.Series_ID = csd.Series_ID
    WHERE co.Phenophase_Status >= 0
      AND co.Observation_Date BETWEEN ? AND ?
      ${filterWhere}
    GROUP BY ${periodGroupBy}, ${groupByCols.join(', ')}
    ORDER BY ${groupByCols.join(', ')}, MIN(co.Observation_Date)
  `;

  // Population SE (as PHP stats_standard_deviation($arr, false))
  function stdErrPop(arr) {
    if (arr.length <= 1) return -9999;
    const n = arr.length;
    const mean = arr.reduce((a, b) => a + b, 0) / n;
    const variance = arr.reduce((a, b) => a + (b - mean) ** 2, 0) / n;
    return Math.round(Math.sqrt(variance) / Math.sqrt(n) * 100) / 100;
  }

  function round2(v) { return Math.round(v * 100) / 100; }

  let conn;
  try {
    conn = await npnPool.getConnection();
    await conn.query('SET SESSION group_concat_max_len = 10000000');
  } catch (err) {
    console.error('get_magnitude_data error:', err.message);
    return res.status(500).json({ error: err.message });
  }

  const rawConn = conn.connection;
  let released = false;
  const release = () => { if (!released) { released = true; conn.release(); } };

  res.setHeader('Content-Type', 'application/json');
  res.write('[');
  let first = true;

  const q = rawConn.query(sql, [startDate, endDate, ...filterParams]);

  q.on('result', (r) => {
    const sampleDt = new Date(r.sample_date + 'T00:00:00Z');
    const period = periods.find(per => sampleDt >= per.start && sampleDt <= per.end) || periods[0];

    const year = parseInt(fmt(period.start).slice(0, 4), 10);
    const startDStr = fmt(period.start);
    const endDStr = fmt(period.end);

    const abundances = r.abundances.split(',').map(Number);
    const searchTimes = r.search_times.split(',').map(Number);
    const groupIds = r.observation_group_ids.split(',');
    const searchMethods = r.search_methods.split(',');
    const siteIds = r.site_ids.split(',');
    const siteAreas = r.site_areas.split(',').map(Number);

    const isAnimal = r.kingdom === 'Animalia';
    const statusSize = r.status_records_sample_size;
    const indivSize = r.individuals_sample_size;
    const siteSize = r.sites_sample_size;

    const proportionYes = statusSize > 0 ? round2(r.num_yes_records / statusSize) : -9999;
    const proportionIndiv = indivSize > 0 ? round2(r.num_individuals_with_yes_record / indivSize) : -9999;

    let numIndivWithYes = r.num_individuals_with_yes_record;
    let numSitesWithYes = r.num_sites_with_yes_record;
    let proportionIndivFinal = proportionIndiv;
    let proportionSites = -9999;

    if (isAnimal) {
      numIndivWithYes = -9999;
      proportionIndivFinal = -9999;
      proportionSites = siteSize > 0 ? round2(numSitesWithYes / siteSize) : -9999;
    } else {
      numSitesWithYes = -9999;
      proportionSites = -9999;
    }

    let inPhaseSites = -9999, inPhaseSiteVisits = -9999;
    let totalAnimals = -9999, meanAnimals = -9999, seAnimals = -9999;
    let inPhasePerHrSites = -9999, inPhasePerHrSiteVisits = -9999;
    let meanPerHr = -9999, sePerHr = -9999;
    let inPhasePerAcreSites = -9999, inPhasePerAcreSiteVisits = -9999;
    let meanPerAcre = -9999, sePerAcre = -9999;

    if (isAnimal) {
      const totalMap = {};
      const totalSiteSet = {};

      for (let i = 0; i < abundances.length; i++) {
        if (abundances[i] > -1) {
          const gid = groupIds[i];
          totalMap[gid] = (totalMap[gid] || 0) + abundances[i];
          totalSiteSet[siteIds[i]] = 1;
        }
      }

      const totalVals = Object.values(totalMap);
      inPhaseSiteVisits = totalVals.length;
      inPhaseSites = Object.keys(totalSiteSet).length;
      totalAnimals = totalVals.reduce((a, b) => a + b, 0);

      if (inPhaseSiteVisits > 0) {
        meanAnimals = round2(totalAnimals / inPhaseSiteVisits);
        seAnimals = stdErrPop(totalVals);
      } else {
        meanAnimals = -9999;
        seAnimals = -9999;
      }

      const perHrAbundMap = {}, perHrTimeMap = {}, perHrAreaMap = {};
      const perHrSiteSet = {};
      const perAcreAbundMap = {}, perAcreSiteSet = {}, perAcreSiteVisitSet = {};

      for (let i = 0; i < abundances.length; i++) {
        const sm = searchMethods[i];
        if (abundances[i] > -1 && searchTimes[i] > 0 && searchTimes[i] <= 180
            && sm !== '-9999' && sm !== 'Incidental' && sm !== '') {
          const gid = groupIds[i];
          perHrTimeMap[gid] = searchTimes[i];
          perHrAreaMap[gid] = siteAreas[i];
          perHrAbundMap[gid] = (perHrAbundMap[gid] || 0) + abundances[i];
          perHrSiteSet[siteIds[i]] = 1;

          if ((sm === 'Area search' || sm === 'Area Search') && siteAreas[i] > 0) {
            perAcreAbundMap[gid] = (perAcreAbundMap[gid] || 0) + abundances[i];
            perAcreSiteSet[siteIds[i]] = 1;
            perAcreSiteVisitSet[gid] = 1;
          }
        }
      }

      const perHrRates = [], perAcreRates = [];
      for (const gid of Object.keys(perHrAbundMap)) {
        const rate = perHrAbundMap[gid] / (perHrTimeMap[gid] / 60);
        perHrRates.push(rate);
        if (gid in perAcreAbundMap) {
          perAcreRates.push((perAcreAbundMap[gid] / (perHrTimeMap[gid] / 60)) / perHrAreaMap[gid]);
        }
      }

      inPhasePerHrSiteVisits = perHrRates.length;
      inPhasePerHrSites = Object.keys(perHrSiteSet).length;
      if (perHrRates.length > 0) {
        meanPerHr = round2(perHrRates.reduce((a, b) => a + b, 0) / perHrRates.length);
        sePerHr = stdErrPop(perHrRates);
      }

      inPhasePerAcreSiteVisits = perAcreRates.length;
      inPhasePerAcreSites = Object.keys(perAcreSiteSet).length;
      if (perAcreRates.length > 0) {
        meanPerAcre = round2(perAcreRates.reduce((a, b) => a + b, 0) / perAcreRates.length);
        sePerAcre = stdErrPop(perAcreRates);
      }
    }

    const transformed = {
      species_id: r.species_id,
      genus: r.genus,
      species: r.species,
      common_name: r.common_name,
      kingdom: r.kingdom,
      phenophase_id: r.phenophase_id,
      phenophase_description: r.phenophase_description,
      year,
      start_date: startDStr,
      end_date: endDStr,
      status_records_sample_size: statusSize,
      individuals_sample_size: indivSize,
      sites_sample_size: siteSize,
      num_yes_records: parseInt(r.num_yes_records, 10),
      numindividuals_with_yes_record: numIndivWithYes,
      numsites_with_yes_record: numSitesWithYes,
      proportion_yes_records: proportionYes,
      proportion_individuals_with_yes_record: proportionIndivFinal,
      proportion_sites_with_yes_record: proportionSites,
      'in-phase_sites_sample_size': inPhaseSites,
      'in-phase_site_visits_sample_size': inPhaseSiteVisits,
      'total_numanimals_in-phase': totalAnimals,
      'mean_numanimals_in-phase': meanAnimals,
      'se_numanimals_in-phase': seAnimals,
      'in-phase_per_hr_sites_sample_size': inPhasePerHrSites,
      'in-phase_per_hr_site_visits_sample_size': inPhasePerHrSiteVisits,
      'mean_numanimals_in-phase_per_hr': meanPerHr,
      'se_numanimals_in-phase_per_hr': sePerHr,
      'in-phase_per_hr_per_acre_sites_sample_size': inPhasePerAcreSites,
      'in-phase_per_hr_per_acre_site_visits_sample_size': inPhasePerAcreSiteVisits,
      'mean_numanimals_in-phase_per_hr_per_acre': meanPerAcre,
      'se_numanimals_in-phase_per_hr_per_acre': sePerAcre,
    };

    const chunk = (first ? '' : ',') + JSON.stringify(transformed);
    first = false;
    if (!res.write(chunk)) rawConn.pause();
  });

  let ended = false;
  res.on('drain', () => rawConn.resume());
  req.on('close', () => { ended = true; released = true; rawConn.destroy(); });
  q.on('end', () => { if (ended) return; ended = true; res.write(']'); res.end(); release(); });
  q.on('error', (err) => {
    console.error('get_magnitude_data stream error:', err.message);
    if (ended) { release(); return; }
    ended = true;
    if (!res.headersSent) res.status(500).json({ error: err.message });
    else { try { res.end(']'); } catch (_) {} }
    release();
  });
});

// GET /get_observation_group_details
router.all('/get_observation_group_details', async (req, res) => {
  const p = req.query;
  const conditions = [];
  const params = [];

  if (checkProperty(p, 'observation_group_id')) {
    const ids = arrayWrap(p.observation_group_id).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    if (ids.length > 0) {
      conditions.push('Observation_Group_ID IN (?)');
      params.push(ids);
    }
  } else if (checkProperty(p, 'ids')) {
    const ids = String(p.ids).split(',').map(id => parseInt(id.trim(), 10)).filter(id => !isNaN(id));
    if (ids.length > 0) {
      conditions.push('Observation_Group_ID IN (?)');
      params.push(ids);
    }
  }

  if (checkProperty(p, 'person_id')) {
    conditions.push('Observer_ID = ?');
    params.push(p.person_id);
  }

  const whereClause = conditions.length > 0 ? 'WHERE ' + conditions.join(' AND ') : '';
  const sql = `SELECT * FROM usanpn2.vw_Observation_Group_Details ${whereClause} ORDER BY Observation_Group_ID ASC`;

  let conn;
  try {
    conn = await npnPool.getConnection();
  } catch (err) {
    console.error('get_observation_group_details error:', err.message);
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
    console.error('get_observation_group_details stream error:', err.message);
    if (ended) { release(); return; }
    ended = true;
    if (!res.headersSent) res.status(500).json({ error: err.message });
    else { try { res.end(']'); } catch (_) {} }
    release();
  });
});

module.exports = router;
