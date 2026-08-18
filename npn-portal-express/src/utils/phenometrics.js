// Pure port of legacy CakePHP SummarizedDataSearch::createSeries()
// (app/controllers/components/summarized_data_search.php:339-698).
//
// A single Series_ID's ordered (date, status) history can contain more than one
// "yes" run (status goes yes -> no -> yes). Legacy walks the ordered arrays and
// recursively splits each run into its own output row ("cycle"); the Express CTE
// rewrite that preceded this file collapsed every run into one flat MIN/MAX and
// lost that splitting. This module restores it.
//
// No DB/Express dependencies by design, so it can be unit-tested against
// hand-traced fixtures without a live database.

function toStatusNum(v) {
  return Number(v);
}

function yearOf(dateStr) { return Number(dateStr.slice(0, 4)); }
function monthOf(dateStr) { return Number(dateStr.slice(5, 7)); }
function dayOf(dateStr) { return Number(dateStr.slice(8, 10)); }

function toUTCms(dateStr) {
  const y = Number(dateStr.slice(0, 4));
  const m = Number(dateStr.slice(5, 7));
  const d = Number(dateStr.slice(8, 10));
  return Date.UTC(y, m - 1, d);
}

function dayOfYear(dateStr) {
  const y = yearOf(dateStr);
  return Math.round((toUTCms(dateStr) - Date.UTC(y, 0, 1)) / 86400000) + 1;
}

// PHP date_diff(...)->days is always the absolute day count regardless of
// argument order — replicate with abs() rather than a signed difference.
function daysBetween(a, b) {
  return Math.round(Math.abs(toUTCms(a) - toUTCms(b)) / 86400000);
}

// julianDate(): number_format($timestamp/86400 + 2440587.5, 0) — always an
// exact N.5 for a UTC-midnight timestamp, so plain Math.round (round-half-up
// for positive numbers) matches PHP's round-half-away-from-zero here.
function julianDate(dateStr) {
  return Math.round(toUTCms(dateStr) / 86400000 + 2440587.5);
}

// PHP empty($v): true for null/unset, "", "0", 0, 0.0, false. A non-empty
// numeric string that isn't exactly "0" (e.g. "0.00") is NOT considered empty
// by PHP — this quirk is legacy's, not ours; ported as-is per the plan (a
// real climate value of 0 becomes -9999, same as production always has).
function isPhpEmpty(v) {
  if (v === null || v === undefined) return true;
  if (typeof v === 'number') return v === 0;
  const s = String(v);
  return s === '' || s === '0';
}

// Unique values (first-occurrence order, matching PHP array_unique) over the
// inclusive [firstYesIndex, lastYesIndex] slice of a positional array.
function sliceUnique(arr, firstYesIndex, lastYesIndex) {
  if (!arr) return [];
  const seen = new Set();
  const out = [];
  for (let idx = firstYesIndex; idx <= lastYesIndex; idx++) {
    const v = arr[idx];
    if (v === undefined || v === null || v === '') continue;
    if (!seen.has(v)) { seen.add(v); out.push(v); }
  }
  return out;
}

function conflictFlagOver(conflicts, firstYesIndex, lastYesIndex) {
  if (!conflicts) return '-9999';
  let hasMulti = false;
  let hasOne = false;
  for (let idx = firstYesIndex; idx <= lastYesIndex; idx++) {
    const v = conflicts[idx];
    if (v === 'MultiObserver-StatusConflict') hasMulti = true;
    else if (v === 'OneObserver-StatusConflict') hasOne = true;
  }
  if (hasMulti) return 'MultiObserver-StatusConflict';
  if (hasOne) return 'OneObserver-StatusConflict';
  return '-9999';
}

// populateClimateDataField(): value at this cycle's local first-yes index,
// but only if this level's climate list is exactly as long as this level's
// (sliced) dates/statuses list — otherwise the whole series' value is -9999.
// Legacy applies this per recursion level using that level's OWN slice
// lengths (both climate and date arrays are array_slice()'d together at each
// recursion step), so this must be evaluated with `c` = the CURRENT level's
// length, not the original series' length.
function climateValuesAt(climate, firstYesIndex, c) {
  const out = {};
  if (!climate) return out;
  for (const key of Object.keys(climate)) {
    const arr = climate[key];
    if (arr && arr.length === c) {
      const v = arr[firstYesIndex];
      out[key] = isPhpEmpty(v) ? -9999 : v;
    } else {
      out[key] = -9999;
    }
  }
  return out;
}

function buildDescriptor({ dates, statuses, lists, firstYesIndex, lastYesIndex, priorNoIndex, nextNoIndex, numYes, multipleFirstY, c }) {
  const firstYesDate = dates[firstYesIndex];
  const lastYesDate = dates[lastYesIndex];

  const firstYesJulian = julianDate(firstYesDate);
  const lastYesJulian = julianDate(lastYesDate);

  const numDaysSincePriorNo = priorNoIndex === -1 ? -9999 : daysBetween(dates[priorNoIndex], firstYesDate);
  const numDaysUntilNextNo = nextNoIndex === -1 ? -9999 : daysBetween(dates[nextNoIndex], lastYesDate);

  const numDaysInSeriesRaw = lastYesJulian - firstYesJulian;
  const numDaysInSeries = numDaysInSeriesRaw === 0 ? 1 : numDaysInSeriesRaw + 1;

  const observerIds = sliceUnique(lists.observerIds, firstYesIndex, lastYesIndex);
  const datasetIds = sliceUnique(lists.datasetIds, firstYesIndex, lastYesIndex);
  const conflictFlag = conflictFlagOver(lists.conflicts, firstYesIndex, lastYesIndex);
  const climateValues = climateValuesAt(lists.climate, firstYesIndex, c);

  return {
    firstYesDate, lastYesDate,
    firstYesYear: yearOf(firstYesDate), firstYesMonth: monthOf(firstYesDate), firstYesDay: dayOf(firstYesDate),
    firstYesDoy: dayOfYear(firstYesDate), firstYesJulian,
    lastYesYear: yearOf(lastYesDate), lastYesMonth: monthOf(lastYesDate), lastYesDay: dayOf(lastYesDate),
    lastYesDoy: dayOfYear(lastYesDate), lastYesJulian,
    numDaysSincePriorNo, numDaysUntilNextNo,
    numYs: numYes, numDaysInSeries,
    multipleFirstY,
    multipleObservers: observerIds.length > 1 ? 1 : 0,
    observerIds, datasetIds, conflictFlag,
    climateValues,
  };
}

// Slice every list in `lists` (each keyed array, plus lists.climate's nested
// keyed arrays) positionally from `from` — mirrors PHP's
// `foreach($group_data as $key => $value){ array_slice($value, $j); }`
// applied uniformly to every parallel array, climate included.
function sliceLists(lists, from) {
  const out = {};
  if (lists.observerIds) out.observerIds = lists.observerIds.slice(from);
  if (lists.datasetIds) out.datasetIds = lists.datasetIds.slice(from);
  if (lists.conflicts) out.conflicts = lists.conflicts.slice(from);
  if (lists.climate) {
    out.climate = {};
    for (const key of Object.keys(lists.climate)) out.climate[key] = lists.climate[key].slice(from);
  }
  return out;
}

// Recursive walk over one (possibly already-sliced) series. Pushes one
// descriptor per yes-run found, deepest (latest) cycle first — matching
// legacy's push-before-return-from-recursion order; the caller reverses.
//
// Two independent mechanisms combine into a cycle's multipleFirstY, both
// required for parity (verified by hand-tracing 3-cycle fixtures against the
// PHP source):
//   1. Forward inheritance: `inheritedMultipleFirstY` carries a parent's
//      already-true flag down through the value-copy semantics PHP gets for
//      free (passing $other_fields by value) — once true, it stays true for
//      every descendant, not just the immediate child.
//   2. Backward correction: after a recursive call returns 1 (meaning the
//      child's OWN first-yes-year matched the year passed to it), the CALLER
//      also sets its own flag to 1 — but only for the caller's own pushed
//      row, since recursion here never continues past finding the next "no".
//
// Returns 1 if THIS cycle's own first-yes year matched `previousYear`
// (mechanism 2's signal to the caller), else 0.
function walk(dates, statuses, lists, previousYear, inheritedMultipleFirstY, output) {
  const c = dates.length;

  let i = 0;
  while (i < c && toStatusNum(statuses[i]) !== 1) i++;
  if (i >= c) return 0; // no "yes" in this slice at all

  let daysIdentical = 0;
  while ((daysIdentical + i + 1) < c && dates[i] === dates[daysIdentical + i + 1]) daysIdentical++;

  let numYes = 1;
  const firstYesIndex = i;
  let lastYesIndex = i;
  const firstYesYear = yearOf(dates[firstYesIndex]);

  const ownMatch = previousYear != null && previousYear === firstYesYear;
  let multipleFirstY = (inheritedMultipleFirstY || ownMatch) ? 1 : 0;
  const parentSameYear = ownMatch ? 1 : 0;

  // Backward scan for the prior "no": compares each candidate date to the
  // FIRST-YES date (dates[i]), skipping same-date duplicates. PHP's
  // `$k--; continue;` inside this for-loop double-decrements on a duplicate
  // match (the manual $k-- plus the for-loop's own post-expression) — an
  // off-by-one in the original, replicated here for parity.
  let priorNoIndex = -1;
  if (i > 0) {
    for (let k = i - 1; k >= 0; k--) {
      if (dates[i] === dates[k]) { k--; continue; }
      if (toStatusNum(statuses[k]) === 0) { priorNoIndex = k; break; }
    }
  }

  i += daysIdentical;

  // Forward scan for the next "no" (or the run's last "yes"). Compares
  // ADJACENT dates (dates[j-1] vs dates[j]), unlike the backward scan above —
  // this is deliberate, matching the PHP source exactly.
  let nextNoIndex = -1;
  outer:
  for (let j = i + 1; j < c; j++) {
    if (dates[j - 1] === dates[j]) {
      if (j === c - 1) break outer;
      else continue;
    }
    const st = toStatusNum(statuses[j]);
    if (st === 0) {
      nextNoIndex = j;
      const childSameYear = walk(
        dates.slice(j), statuses.slice(j), sliceLists(lists, j),
        firstYesYear, multipleFirstY, output
      );
      if (childSameYear === 1) multipleFirstY = 1;
      break outer;
    } else if (st === 1) {
      lastYesIndex = j;
      numYes++;
    }
    if (j === c - 1) break outer;
  }

  output.push(buildDescriptor({
    dates, statuses, lists,
    firstYesIndex, lastYesIndex, priorNoIndex, nextNoIndex,
    numYes, multipleFirstY, c,
  }));

  return parentSameYear;
}

// Split one Series_ID's full observation history into per-cycle descriptors.
//
//   dates    string[]  'YYYY-MM-DD', ordered ASC, status DESC on ties (must
//                       match the GROUP_CONCAT ORDER BY that produced them)
//   statuses (string|number)[]  Phenophase_Status aligned 1:1 with `dates`
//   lists    optional parallel arrays for per-cycle side fields:
//              { observerIds?: string[], datasetIds?: string[],
//                conflicts?: string[], climate?: { [col]: array } }
//            Each array must be positionally aligned with `dates` (same
//            length as the raw GROUP_CONCAT list for that column — NOT
//            pre-truncated to match `dates`.length, since a shorter list from
//            GROUP_CONCAT dropping NULLs is itself meaningful: see
//            climateValuesAt()).
//
// Returns cycles in chronological order (earliest first).
function splitSeriesCycles(dates, statuses, lists = {}) {
  if (!dates || dates.length === 0) return [];
  const output = [];
  walk(dates, statuses, lists, null, 0, output);
  output.reverse();
  return output;
}

module.exports = { splitSeriesCycles };
