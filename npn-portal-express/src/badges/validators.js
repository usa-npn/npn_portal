const { npnPool } = require('../config/db');

async function queryHasRows(sql, params) {
  const [rows] = await npnPool.query(sql, params);
  return rows && rows.length > 0;
}

async function queryHasValidRow(sql, params) {
  const [rows] = await npnPool.query(sql, params);
  return rows && rows.length > 0 && rows[0].valid;
}

function weekThresholdValidator(speciesIds, threshold) {
  return async function (personId) {
    return queryHasRows(`
      SELECT Individual_ID, COUNT(DISTINCT week) c
      FROM (
        SELECT o.Individual_ID, o.Observation_Date, WEEKOFYEAR(o.Observation_Date) week
        FROM usanpn2.Person p
        LEFT JOIN usanpn2.Station st ON st.Observer_ID = p.Person_ID
        LEFT JOIN usanpn2.Station_Species_Individual ssi ON ssi.Station_ID = st.Station_ID
        LEFT JOIN usanpn2.Observation o ON o.Individual_ID = ssi.Individual_ID
        WHERE p.Person_ID = ?
          AND ssi.Species_ID IN (?)
          AND (o.Deleted IS NULL OR o.Deleted <> 1)
        GROUP BY o.Observation_Date, o.Individual_ID
        ORDER BY ssi.Individual_ID, o.Observation_Date
      ) tbl
      GROUP BY Individual_ID, YEAR(Observation_Date)
      HAVING c >= ?
    `, [personId, speciesIds, threshold]);
  };
}

function daysThresholdValidator(speciesIds, threshold) {
  return async function (personId) {
    return queryHasRows(`
      SELECT Individual_ID, COUNT(DISTINCT Observation_Date) c
      FROM (
        SELECT o.Individual_ID, o.Observation_Date
        FROM usanpn2.Person p
        LEFT JOIN usanpn2.Station st ON st.Observer_ID = p.Person_ID
        LEFT JOIN usanpn2.Station_Species_Individual ssi ON ssi.Station_ID = st.Station_ID
        LEFT JOIN usanpn2.Observation o ON o.Individual_ID = ssi.Individual_ID
        WHERE p.Person_ID = ?
          AND ssi.Species_ID IN (?)
        GROUP BY o.Observation_Date, o.Individual_ID
        ORDER BY ssi.Individual_ID, o.Observation_Date
      ) tbl
      GROUP BY Individual_ID, YEAR(Observation_Date)
      HAVING c >= ?
    `, [personId, speciesIds, threshold]);
  };
}

function numObsValidator(target) {
  return async function (personId) {
    return queryHasRows(`
      SELECT p.Person_ID, COUNT(o.Observation_ID) c
      FROM usanpn2.Person p
      LEFT JOIN usanpn2.Observation o ON o.Observer_ID = p.Person_ID
      WHERE p.Person_ID = ?
        AND (o.Deleted IS NULL OR o.Deleted <> 1)
      GROUP BY p.Person_ID
      HAVING c >= ?
    `, [personId, target]);
  };
}

function yearlyValidator(numYears) {
  const daysRequired = 365 * numYears;
  return async function (personId) {
    return queryHasValidRow(`
      SELECT DATEDIFF(newest.Submission_DateTime, oldest.Submission_DateTime) >= ? AS valid
      FROM
      (
        SELECT Submission_DateTime
        FROM usanpn2.Person p
        LEFT JOIN usanpn2.Observation o ON o.Observer_ID = p.Person_ID
        LEFT JOIN usanpn2.Submission s ON s.Submission_ID = o.Submission_ID
        WHERE p.Person_ID = ?
          AND (o.Deleted IS NULL OR o.Deleted <> 1)
        ORDER BY Submission_DateTime DESC
        LIMIT 1
      ) newest
      JOIN
      (
        SELECT Submission_DateTime
        FROM usanpn2.Person p
        LEFT JOIN usanpn2.Observation o ON o.Observer_ID = p.Person_ID
        LEFT JOIN usanpn2.Submission s ON s.Submission_ID = o.Submission_ID
        WHERE p.Person_ID = ?
          AND (o.Deleted IS NULL OR o.Deleted <> 1)
        ORDER BY Submission_DateTime ASC
        LIMIT 1
      ) oldest
    `, [daysRequired, personId, personId]);
  };
}

async function intensityValidator(personId) {
  return queryHasRows(`
    SELECT * FROM (
      SELECT
        COUNT(CASE WHEN ((Abundance_Category_Value IS NOT NULL OR Raw_Abundance_Value IS NOT NULL) AND Observation_Extent=1) THEN Observation_ID ELSE NULL END) ca,
        COUNT(CASE WHEN Observation_Extent = 1 THEN o.Observation_ID ELSE NULL END) c,
        o.Observer_ID
      FROM usanpn2.Observation o
      WHERE Observer_ID = ?
        AND (o.Deleted IS NULL OR o.Deleted <> 1)
      GROUP BY o.Observer_ID
    ) tbl
    HAVING (ca / c) >= 0.5 AND c >= 100
  `, [personId]);
}

async function groupParticipationValidator(personId) {
  return queryHasRows(`
    SELECT p.Person_ID FROM usanpn2.Person p
    LEFT JOIN usanpn2.Network_Person np ON np.Person_ID = p.Person_ID
    LEFT JOIN usanpn2.Network_Station ns ON ns.Network_ID = np.Network_ID
    LEFT JOIN usanpn2.Station_Species_Individual ssi ON ssi.Station_ID = ns.Station_ID
    LEFT JOIN usanpn2.Observation o ON o.Individual_ID = ssi.Individual_ID
    WHERE o.Observer_ID = p.Person_ID
      AND (o.Deleted IS NULL OR o.Deleted <> 1)
      AND p.Person_ID = ?
  `, [personId]);
}

async function fullPhenoCaptureValidator(personId) {
  return queryHasRows(`
    SELECT o.Observation_ID, DATEDIFF(nn.Observation_Date, o.Observation_Date) diff
    FROM usanpn2.Observation o
    LEFT JOIN usanpn2.Station_Species_Individual ssi ON ssi.Individual_ID = o.Individual_ID
    LEFT JOIN usanpn2.Species s ON s.Species_ID = ssi.Species_ID
    INNER JOIN (
      SELECT Observation_ID, Phenophase_ID, Individual_ID, Observation_Date, Observation_Extent
      FROM usanpn2.Observation o
      WHERE o.Observer_ID = ?
        AND (o.Deleted IS NULL OR o.Deleted <> 1)
        AND o.Observation_Extent = 1
    ) yes
    ON yes.Individual_Id = o.Individual_ID AND o.Phenophase_ID = yes.Phenophase_ID
      AND (YEAR(o.Observation_Date) = YEAR(yes.Observation_Date) OR YEAR(o.Observation_Date) + 1 = YEAR(yes.Observation_Date))
      AND o.Observation_Date < yes.Observation_Date
    INNER JOIN (
      SELECT Observation_ID, Phenophase_ID, Individual_ID, Observation_Date, Observation_Extent
      FROM usanpn2.Observation o
      WHERE o.Observer_ID = ?
        AND o.Observation_Extent = 0
        AND (o.Deleted IS NULL OR o.Deleted <> 1)
    ) nn
    ON nn.Individual_Id = o.Individual_ID AND o.Phenophase_ID = nn.Phenophase_ID
      AND (YEAR(o.Observation_Date) = YEAR(nn.Observation_Date) OR YEAR(o.Observation_Date) + 1 = YEAR(nn.Observation_Date))
      AND yes.Observation_Date < nn.Observation_Date
    WHERE o.Observer_ID = ?
      AND s.Kingdom = 'Plantae'
      AND o.Observation_Extent = 0
      AND (o.Deleted IS NULL OR o.Deleted <> 1)
    HAVING diff BETWEEN 1 AND 365
    LIMIT 1
  `, [personId, personId, personId]);
}

async function negativeAnimalDataValidator(personId) {
  return queryHasRows(`
    SELECT COUNT(o.Observation_Extent = 0) c,
      ppp.pps,
      o.*
    FROM usanpn2.Observation o
    LEFT JOIN usanpn2.Station_Species_Individual ssi ON ssi.Individual_ID = o.Individual_ID
    LEFT JOIN usanpn2.Species s ON s.Species_ID = ssi.Species_ID
    LEFT JOIN usanpn2.Protocol p ON p.Protocol_ID = o.Protocol_ID
    LEFT JOIN (
      SELECT ppp.Protocol_ID, COUNT(ppp.Phenophase_ID) pps
      FROM usanpn2.Protocol_Phenophase ppp
      GROUP BY ppp.Protocol_ID
    ) ppp ON ppp.Protocol_ID = p.Protocol_ID
    WHERE s.Kingdom = 'Animalia'
      AND o.Observer_ID = ?
      AND (o.Deleted IS NULL OR o.Deleted <> 1)
    GROUP BY o.Observation_Group_ID, Individual_ID
    HAVING c = pps
  `, [personId]);
}

async function twelveWeekValidator(personId) {
  return queryHasRows(`
    SELECT * FROM (
      SELECT
        IF(Individual_ID <> @i, @x:=1 AND @y:=1, Individual_ID) Prev_Indiv_ID,
        IF(week = @x+1, @y := @y+1, @y := 1) AS Consec_Weeks,
        @x := week AS Week,
        @i := Individual_ID Curr_Indiv_ID,
        year
      FROM (
        SELECT DISTINCT o.Individual_ID, WEEKOFYEAR(o.Observation_Date) week, YEAR(o.Observation_Date) year
        FROM usanpn2.Person p
        LEFT JOIN usanpn2.Station st ON st.Observer_ID = p.Person_ID
        LEFT JOIN usanpn2.Station_Species_Individual ssi ON ssi.Station_ID = st.Station_ID
        LEFT JOIN usanpn2.Observation o ON o.Individual_ID = ssi.Individual_ID
        WHERE p.Person_ID = ?
          AND (o.Deleted IS NULL OR o.Deleted <> 1)
        GROUP BY o.Observation_Date, o.Individual_ID
        ORDER BY ssi.Individual_ID, Observation_Date, week
      ) tbl
    ) tbl
    HAVING Consec_Weeks = 12
  `, [personId]);
}

async function mayflyValidator(personId) {
  const speciesIds = [1389, 1390];
  const phenophaseIds = [289, 327, 507];
  return queryHasRows(`
    SELECT transition, Observation_Date
    FROM (
      SELECT
        year, Individual_ID, has_yes, Observation_Date,
        IF(year <> @y, @prev_no :=0, year),
        IF(Individual_ID <> @i, @prev_no :=0, Individual_ID),
        IF(@prev_no > 0 AND has_yes > 0, 1, 0) \`transition\`,
        @prev_yes:= has_yes,
        @prev_no := has_no,
        @i :=Individual_ID,
        @y :=year
      FROM (
        SELECT
          COUNT(CASE WHEN o.Observation_Extent = 1 THEN Observation_ID ELSE NULL END) \`has_yes\`,
          COUNT(CASE WHEN o.Observation_Extent = 0 THEN Observation_ID ELSE NULL END) \`has_no\`,
          o.Individual_ID,
          YEAR(o.Observation_Date) year,
          o.Observation_Date
        FROM usanpn2.Observation o
        LEFT JOIN usanpn2.Station_Species_Individual ssi ON ssi.Individual_ID = o.Individual_ID
        WHERE o.Observer_ID = ?
          AND ssi.Species_ID IN (?)
          AND Phenophase_ID IN (?)
          AND o.Observation_Extent > -1
          AND (o.Deleted IS NULL OR o.Deleted <> 1)
        GROUP BY o.Individual_ID, Observation_Date
        ORDER BY Observation_Date
      ) tbl
    ) tbl
    WHERE transition > 0
  `, [personId, speciesIds, phenophaseIds]);
}

async function lplValidator(personId) {
  return queryHasRows(`
    SELECT profile_value.value FROM usanpn2.Person
    LEFT JOIN drupal5.users ON users.name = Person.UserName
    LEFT JOIN drupal5.profile_field ON profile_field.name = 'profile_LPL'
    LEFT JOIN drupal5.profile_value ON profile_value.fid = profile_field.fid AND profile_value.uid = users.uid
    WHERE Person_ID = ?
    HAVING profile_value.value IS NOT NULL AND profile_value.value = 1
  `, [personId]);
}

// Month-day window (MMDD strings) that may cross New Year (start > end).
// Returns SQL fragments + params for filtering on the window and for the season key.
// A season that crosses New Year is keyed by the year it starts in
// (Dec 2026 - Mar 2027 = season 2026).
function seasonWindow(col, startMMDD, endMMDD) {
  const md = `DATE_FORMAT(${col}, '%m%d')`;
  const crosses = startMMDD > endMMDD;
  return {
    where: crosses ? `(${md} >= ? OR ${md} <= ?)` : `${md} BETWEEN ? AND ?`,
    whereParams: [startMMDD, endMMDD],
    season: crosses ? `YEAR(${col}) - IF(${md} <= ?, 1, 0)` : `YEAR(${col})`,
    seasonParams: crosses ? [endMMDD] : [],
  };
}

// N distinct ISO weeks (YEARWEEK mode 3, so late Dec / early Jan don't collide)
// observing one individual of a campaign species (Species_Species_Type) within
// one season. Credits the observer (Observation.Observer_ID), not the station owner.
function seasonWeekValidator(speciesTypeId, startMMDD, endMMDD, threshold) {
  return async function (personId) {
    const w = seasonWindow('o.Observation_Date', startMMDD, endMMDD);
    return queryHasRows(`
      SELECT o.Individual_ID, ${w.season} season, COUNT(DISTINCT YEARWEEK(o.Observation_Date, 3)) c
      FROM usanpn2.Observation o
      JOIN usanpn2.Station_Species_Individual ssi ON ssi.Individual_ID = o.Individual_ID
      JOIN usanpn2.Species_Species_Type sst ON sst.Species_ID = ssi.Species_ID AND sst.Species_Type_ID = ?
      WHERE o.Observer_ID = ?
        AND (o.Deleted IS NULL OR o.Deleted <> 1)
        AND ${w.where}
      GROUP BY o.Individual_ID, season
      HAVING c >= ?
      LIMIT 1
    `, [...w.seasonParams, speciesTypeId, personId, ...w.whereParams, threshold]);
  };
}

// Desert Refuge: N distinct ISO weeks in one season in which the person observed one
// target milkweed (Plantae in the campaign Species_Type) AND recorded monarch presence
// or absence (not '?') at the same site that week.
const MONARCH_SPECIES_ID = 396;
function desertRefugeValidator(speciesTypeId, startMMDD, endMMDD, threshold) {
  return async function (personId) {
    const w = seasonWindow('o.Observation_Date', startMMDD, endMMDD);
    return queryHasRows(`
      SELECT m.Individual_ID, m.season, COUNT(DISTINCT m.wk) c
      FROM (
        SELECT o.Individual_ID, ssi.Station_ID, YEARWEEK(o.Observation_Date, 3) wk, ${w.season} season
        FROM usanpn2.Observation o
        JOIN usanpn2.Station_Species_Individual ssi ON ssi.Individual_ID = o.Individual_ID
        JOIN usanpn2.Species_Species_Type sst ON sst.Species_ID = ssi.Species_ID AND sst.Species_Type_ID = ?
        JOIN usanpn2.Species s ON s.Species_ID = ssi.Species_ID AND s.Kingdom = 'Plantae'
        WHERE o.Observer_ID = ?
          AND (o.Deleted IS NULL OR o.Deleted <> 1)
          AND ${w.where}
      ) m
      WHERE EXISTS (
        SELECT 1
        FROM usanpn2.Observation o2
        JOIN usanpn2.Station_Species_Individual s2 ON s2.Individual_ID = o2.Individual_ID
        WHERE o2.Observer_ID = ?
          AND s2.Station_ID = m.Station_ID
          AND s2.Species_ID = ?
          AND o2.Observation_Extent IN (0, 1)  -- present or not present; '?' (-1) doesn't count
          AND (o2.Deleted IS NULL OR o2.Deleted <> 1)
          AND YEARWEEK(o2.Observation_Date, 3) = m.wk
      )
      GROUP BY m.Individual_ID, m.season
      HAVING c >= ?
      LIMIT 1
    `, [...w.seasonParams, speciesTypeId, personId, ...w.whereParams, personId, MONARCH_SPECIES_ID, threshold]);
  };
}

const VALIDATORS = {
  bat_campaign:          weekThresholdValidator([1593,1594,1322,210], 6),
  clone:                 weekThresholdValidator([35,444], 6),
  invasive:              weekThresholdValidator([1438,1446,12,1448,839,1452,1469,915,1246,1471,770,1248,1509,1217,95,1510], 6),
  mop:                   weekThresholdValidator([2,3,27,61,102,301,316,320,777,976,977], 6),
  nectar:                weekThresholdValidator([156,170,171,186,195,197,198,199,200,201,202,203,204,207,223,224,299,714,715,747,767,772,801,845,911,912,916,921,931,1027,1028,1034,1155,1186,1325,1326,1327,1328,1329,1330,1331,1332,1333,1334,1335,1336,1337,1437,1454,1606,1614,1637,1653], 6),
  pollen_trackers:       weekThresholdValidator([777,1843,59,778,1,2,1591,60,779,780,3,781,61,1199,62,63,319,145,788,146,97,1439,98,430,1850,1339,1851,99,1805,1176,67,824,68,1177,1605,829,1924,1342,74,872,873,75,1350,2143,1353,80,43,1743,1354,289,902,291,290,44,81,1361,320,976,977,1188,2036,27,1481,705,100,1365,2043,2044,757,1870,987,1690,1484,988,316,297,2045,1485,1190,2046,765,1486,301,704,2047,101,2048,1691,2049,2164,1212,2050,2051,2052,989,1366,2053,102,1756,1213,1755,1487,1159,305,2054,1006,1007,1875,293,2066,1008,1371,77,1163,1493,1372,717,1494,322,1876,1009,1010,1192,1048,1049,1215,1216], 6),
  pop:                   weekThresholdValidator([27, 320], 6),
  southwest:             weekThresholdValidator([1435,435,1440,1455,1462,1465,117,1476,1477,84,1032,1496,1497,1170], 6),
  quercus_quest:         daysThresholdValidator([100,2043,297,2047,101,2049,1212,2050,2053,1755,305], 6),
  eastern_redbud:        daysThresholdValidator([7], 6),
  pest_patrol:           daysThresholdValidator([259,1239,1243,1700,1789,1790,1791,1792,1793,1794,1795,1796,1797], 6),
  pesky_plant:           daysThresholdValidator([184,1827,1859], 6),
  first_obs:             numObsValidator(1),
  thousand_obs:          numObsValidator(1000),
  one_year:              yearlyValidator(1),
  two_year:              yearlyValidator(2),
  three_year:            yearlyValidator(3),
  intensity:             intensityValidator,
  group_participation:   groupParticipationValidator,
  full_pheno_capture:    fullPhenoCaptureValidator,
  negative_animal_data:  negativeAnimalDataValidator,
  twelve_week:           twelveWeekValidator,
  mayfly:                mayflyValidator,
  lpl:                   lplValidator,
  pollencast:            seasonWeekValidator(44, '1201', '0331', 6),   // Ashe's juniper, Dec 1 - Mar 31
  desert_refuge:         desertRefugeValidator(45, '0901', '0531', 6), // milkweed + monarch, Sep 1 - May 31
};

module.exports = VALIDATORS;
