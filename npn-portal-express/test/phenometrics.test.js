// Fixture tests for src/utils/phenometrics.js's splitSeriesCycles(), hand-traced
// against the legacy PHP algorithm it ports (app/controllers/components/
// summarized_data_search.php::createSeries()). Run with: node --test test/
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { splitSeriesCycles } = require('../src/utils/phenometrics');

// Mirrors the num_days_quality_filter_individual gate that get_summarized_data
// applies AFTER splitSeriesCycles() returns (kept out of the pure module per
// the fix plan). PHP: `!filter || (previous_no_date && previous_no_date != "-9999"
// && previous_no_date <= filter)` — note previous_no_date === 0 is falsy in PHP
// and drops the cycle even with a generous filter.
function qualifiesForQualityFilter(cycle, filter) {
  if (!filter) return true;
  const d = cycle.numDaysSincePriorNo;
  return !!d && d !== -9999 && d <= filter;
}

test('case 1: real repro (individual 8333/phenophase 292, 2020) — yes/no/yes same year', () => {
  const cycles = splitSeriesCycles(
    ['2020-04-13', '2020-04-26', '2020-05-03'],
    [1, 0, 1],
  );
  assert.equal(cycles.length, 2);

  assert.equal(cycles[0].firstYesDate, '2020-04-13');
  assert.equal(cycles[0].lastYesDate, '2020-04-13');
  assert.equal(cycles[0].numDaysSincePriorNo, -9999);
  assert.equal(cycles[0].numDaysUntilNextNo, 13);
  assert.equal(cycles[0].multipleFirstY, 1);
  assert.equal(cycles[0].numYs, 1);

  assert.equal(cycles[1].firstYesDate, '2020-05-03');
  assert.equal(cycles[1].lastYesDate, '2020-05-03');
  assert.equal(cycles[1].numDaysSincePriorNo, 7);
  assert.equal(cycles[1].numDaysUntilNextNo, -9999);
  assert.equal(cycles[1].multipleFirstY, 1);
  assert.equal(cycles[1].numYs, 1);
});

test('case 2: multi-cycle spanning a year boundary — Multiple_FirstY is 0 on both', () => {
  const cycles = splitSeriesCycles(
    ['2020-12-20', '2021-01-05', '2021-02-10'],
    [1, 0, 1],
  );
  assert.equal(cycles.length, 2);
  assert.equal(cycles[0].firstYesDate, '2020-12-20');
  assert.equal(cycles[0].multipleFirstY, 0);
  assert.equal(cycles[1].firstYesDate, '2021-02-10');
  assert.equal(cycles[1].multipleFirstY, 0);
});

test('case 3: three cycles, years 2020/2021/2021 — Multiple_FirstY is an adjacent-pair relation (0/1/1)', () => {
  const cycles = splitSeriesCycles(
    ['2020-06-01', '2020-06-10', '2021-04-01', '2021-04-10', '2021-07-01'],
    [1, 0, 1, 0, 1],
  );
  assert.equal(cycles.length, 3);
  assert.deepEqual(cycles.map(c => c.firstYesYear), [2020, 2021, 2021]);
  assert.deepEqual(cycles.map(c => c.multipleFirstY), [0, 1, 1]);
});

test('case 3b: three cycles, years 2020/2020/2021 — forward inheritance carries the flag past a year change (1/1/1)', () => {
  // Locks in the forward-inheritance mechanism (not just the backward/return-value
  // correction): B's own match (2020==2020) sets its flag before recursing into C,
  // so C inherits it as true even though C's own year (2021) doesn't match B's.
  const cycles = splitSeriesCycles(
    ['2020-03-01', '2020-03-05', '2020-08-01', '2020-08-05', '2021-01-01'],
    [1, 0, 1, 0, 1],
  );
  assert.equal(cycles.length, 3);
  assert.deepEqual(cycles.map(c => c.firstYesYear), [2020, 2020, 2021]);
  assert.deepEqual(cycles.map(c => c.multipleFirstY), [1, 1, 1]);
});

test('case 4: single cycle (no "no" anywhere) — regression guard, matches pre-fix single-row behavior', () => {
  const cycles = splitSeriesCycles(
    ['2019-05-01', '2019-05-10'],
    [1, 1],
  );
  assert.equal(cycles.length, 1);
  assert.equal(cycles[0].firstYesDate, '2019-05-01');
  assert.equal(cycles[0].lastYesDate, '2019-05-10');
  assert.equal(cycles[0].numYs, 2);
  assert.equal(cycles[0].numDaysSincePriorNo, -9999);
  assert.equal(cycles[0].numDaysUntilNextNo, -9999);
});

test('case 5: all-"no" series — zero rows', () => {
  const cycles = splitSeriesCycles(
    ['2020-01-01', '2020-01-05', '2020-01-10'],
    [0, 0, 0],
  );
  assert.equal(cycles.length, 0);
});

test('case 6: -1 status inside a yes-run does not split the run or count toward NumYs', () => {
  const cycles = splitSeriesCycles(
    ['2020-01-01', '2020-01-05', '2020-01-10'],
    [1, -1, 1],
  );
  assert.equal(cycles.length, 1);
  assert.equal(cycles[0].firstYesDate, '2020-01-01');
  assert.equal(cycles[0].lastYesDate, '2020-01-10');
  assert.equal(cycles[0].numYs, 2); // the -1 observation is not counted
});

test('case 7a: same-day yes+no at run start does not terminate the run (status DESC ordering swallows it)', () => {
  const cycles = splitSeriesCycles(
    ['2020-01-01', '2020-01-01', '2020-01-10'],
    [1, 0, 1],
  );
  assert.equal(cycles.length, 1);
  assert.equal(cycles[0].firstYesDate, '2020-01-01');
  assert.equal(cycles[0].lastYesDate, '2020-01-10');
  assert.equal(cycles[0].numDaysUntilNextNo, -9999);
});

test('case 7b: duplicate-date backward scan double-decrement (PHP off-by-one, ported for parity)', () => {
  // dates[2] intentionally duplicates dates[3] (the first-yes date) to trigger the
  // skip; a genuine prior "no" at index 1 is then skipped OVER by the double
  // decrement and the scan lands on index 0 instead. A "fixed" (single-decrement)
  // implementation would find index 1 (21 days) instead of index 0 (40 days) —
  // asserting 40 here is intentionally the faithful-to-legacy (buggy) value.
  const cycles = splitSeriesCycles(
    ['2019-12-01', '2019-12-20', '2020-01-10', '2020-01-10'],
    [0, 0, 0, 1],
  );
  assert.equal(cycles.length, 1);
  assert.equal(cycles[0].numDaysSincePriorNo, 40);
});

test('case 7c: duplicate-date pair at the final index breaks out without finding a next-no', () => {
  const cycles = splitSeriesCycles(
    ['2020-01-01', '2020-02-01', '2020-02-01'],
    [1, 1, 0],
  );
  assert.equal(cycles.length, 1);
  assert.equal(cycles[0].lastYesDate, '2020-02-01');
  assert.equal(cycles[0].numDaysUntilNextNo, -9999); // trailing same-date "no" is swallowed
});

test('case 8: leading/trailing single observation — both gaps -9999', () => {
  const cycles = splitSeriesCycles(['2020-05-01'], [1]);
  assert.equal(cycles.length, 1);
  assert.equal(cycles[0].numDaysSincePriorNo, -9999);
  assert.equal(cycles[0].numDaysUntilNextNo, -9999);
});

test('case 9: num_days_quality_filter_individual — threshold and the priorNoDays===0-is-falsy quirk', () => {
  const cycles = splitSeriesCycles(
    ['2020-04-13', '2020-04-26', '2020-05-03'],
    [1, 0, 1],
  );
  // cycle[0].numDaysSincePriorNo === -9999 -> never qualifies once a filter is set
  assert.equal(qualifiesForQualityFilter(cycles[0], 30), false);
  // cycle[1].numDaysSincePriorNo === 7
  assert.equal(qualifiesForQualityFilter(cycles[1], 7), true);
  assert.equal(qualifiesForQualityFilter(cycles[1], 6), false);
  assert.equal(qualifiesForQualityFilter(cycles[1], 0), true); // filter itself falsy -> no gate
  // The PHP truthiness quirk: previous_no_date === 0 is falsy and is dropped even
  // under a generous filter.
  assert.equal(qualifiesForQualityFilter({ numDaysSincePriorNo: 0 }, 30), false);
});

test('case 10: climate list misalignment (NULL-shortened) forces -9999 for the whole series', () => {
  const cycles = splitSeriesCycles(
    ['2020-01-01', '2020-01-10'],
    [1, 0],
    { climate: { tmax: ['55'] } }, // shorter than dates (length 2) -> misaligned
  );
  assert.equal(cycles.length, 1);
  assert.equal(cycles[0].climateValues.tmax, -9999);
});

test('case 10b: literal "0" climate value at the first-yes index becomes -9999 (legacy empty() quirk)', () => {
  const cycles = splitSeriesCycles(
    ['2020-01-01', '2020-01-10'],
    [1, 0],
    { climate: { tmax: ['0', '30'] } }, // aligned (length 2), but value is "0"
  );
  assert.equal(cycles.length, 1);
  assert.equal(cycles[0].climateValues.tmax, -9999);
});

test('observer/conflict/dataset slicing is scoped to [firstYesIndex, lastYesIndex] of the cycle', () => {
  const cycles = splitSeriesCycles(
    ['2020-01-01', '2020-01-05', '2020-01-10'],
    [1, 1, 0],
    {
      observerIds: ['A', 'B', 'C'],
      datasetIds: ['10', '10', '99'],
      conflicts: ['-9999', 'OneObserver-StatusConflict', '-9999'],
    },
  );
  assert.equal(cycles.length, 1);
  assert.equal(cycles[0].firstYesDate, '2020-01-01');
  assert.equal(cycles[0].lastYesDate, '2020-01-05');
  assert.deepEqual(cycles[0].observerIds, ['A', 'B']); // index 2 (dataset '99') excluded — outside the cycle
  assert.equal(cycles[0].multipleObservers, 1);
  assert.equal(cycles[0].conflictFlag, 'OneObserver-StatusConflict');
  assert.deepEqual(cycles[0].datasetIds, ['10']);
});
