# Streaming Large Responses

## Problem

The old PHP backend streamed MySQL results directly to the HTTP client as rows arrived. The original Express migration used `await pool.query()` (mysql2's promise API), which **buffers the entire result set in Node.js heap** before sending anything. For large date ranges this caused:

- **Timeout risk** — the consumer gets no bytes until MySQL finishes the full query, so a proxy or client timeout can fire before data starts flowing
- **OOM risk** — millions of rows held in memory simultaneously
- **No cancellation** — client disconnects didn't stop the DB query

Confirmed with a scoped test (one year, one species):

```
first_byte: 0.312s   total: 8.98s   size: 82MB
```

A 0.3s first byte vs 9s total confirms streaming is working — the consumer's `JSONStream` pipeline starts processing rows immediately rather than waiting for all 82MB.

---

## Solution

Four observation endpoints were converted to use `mysql2`'s callback-based streaming API via `conn.connection` (the underlying non-promise `PoolConnection`). Each row is serialized and written to the response as it arrives from MySQL, forming a valid JSON array incrementally.

### Core pattern (used by getObservations, getSummarizedData, getMagnitudeData)

```js
const conn = await npnPool.getConnection();
const rawConn = conn.connection;         // underlying callback PoolConnection
let released = false;
const release = () => { if (!released) { released = true; conn.release(); } };

res.setHeader('Content-Type', 'application/json');
res.write('[');
let first = true;

const q = rawConn.query(sql, params);

q.on('result', (row) => {
  const chunk = (first ? '' : ',') + JSON.stringify(transform(row));
  first = false;
  if (!res.write(chunk)) rawConn.pause();   // backpressure: pause MySQL when TCP buffer full
});

res.on('drain', () => rawConn.resume());            // resume when buffer clears
req.on('close', () => { released = true; rawConn.destroy(); }); // client disconnect → kill query
q.on('end', () => { res.write(']'); res.end(); release(); });
q.on('error', (err) => {
  if (!res.headersSent) res.status(500).json({ error: err.message });
  else res.end(']');
  release();
});
```

### getSiteLevelData — hybrid approach

`getSiteLevelData` must aggregate all rows (computing means, standard errors per site/species/phenophase group) before it can emit any output. It **cannot** stream early. The pattern used:

1. Stream MySQL rows into an in-memory `siteMap` (smaller than holding full raw rows)
2. On `end`, compute the result array and write it item-by-item

First-byte-time will equal total-time for this endpoint — that is expected, not a bug. The benefit is lower peak memory (aggregation structures are smaller than raw rows) and proper disconnect handling.

---

## Endpoints changed

| Endpoint | File | Streams early? | Notes |
|---|---|---|---|
| `getObservations` | `src/routes/observations.js` | Yes | Removed hardcoded 5000-row default limit |
| `getSummarizedData` | `src/routes/observations.js` | Yes | Removed hardcoded 5000-row default limit |
| `getMagnitudeData` | `src/routes/observations.js` | Yes | `SET SESSION group_concat_max_len` now runs on the same connection as the query (was a latent bug) |
| `getSiteLevelData` | `src/routes/observations.js` | No (by design) | Streams MySQL input; batches output after aggregation |

All endpoints respect a `?limit=N` query param if provided. Without it, all rows are returned (matching PHP behavior).

---

## Curl tests

Start the server first:
```bash
npm run dev
# or: npm start
# stop with Ctrl+C, or: lsof -ti :3000 | xargs kill
```

### getObservations

```bash
# Sanity check — valid array with correct field shape
curl -s "http://localhost:3000/observations/getObservations.json?start_date=2023-01-01&end_date=2023-12-31&species_id=3" \
  | jq 'length, .[0] | keys'

# Streaming test — first_byte should be a fraction of total
curl -s -o /dev/null \
  --write-out "first_byte: %{time_starttransfer}s  total: %{time_total}s  size: %{size_download} bytes\n" \
  "http://localhost:3000/observations/getObservations.json?start_date=2023-01-01&end_date=2023-12-31&species_id=3"

# Watch chunks arrive in real time
curl -N --no-buffer \
  "http://localhost:3000/observations/getObservations.json?start_date=2023-01-01&end_date=2023-12-31&species_id=3" \
  2>&1 | head -c 2000

# Validate complete response is well-formed JSON
curl -s "http://localhost:3000/observations/getObservations.json?start_date=2023-01-01&end_date=2023-06-30" \
  | jq 'if type == "array" then "OK: \(length) rows" else "FAIL: not an array" end'
```

### getSummarizedData

```bash
# Sanity check
curl -s "http://localhost:3000/observations/getSummarizedData.json?start_date=2023-01-01&end_date=2023-12-31&species_id=3" \
  | jq 'length, .[0] | keys'

# Streaming test
curl -s -o /dev/null \
  --write-out "first_byte: %{time_starttransfer}s  total: %{time_total}s  size: %{size_download} bytes\n" \
  "http://localhost:3000/observations/getSummarizedData.json?start_date=2023-01-01&end_date=2023-12-31&species_id=3"

# Validate well-formed JSON
curl -s "http://localhost:3000/observations/getSummarizedData.json?start_date=2022-01-01&end_date=2022-12-31" \
  | jq 'if type == "array" then "OK: \(length) rows" else "FAIL: not an array" end'
```

### getMagnitudeData

```bash
# Sanity check — default 30-day frequency
curl -s "http://localhost:3000/observations/getMagnitudeData.json?start_date=2023-01-01&end_date=2023-12-31&species_id=3" \
  | jq 'length, .[0] | keys'

# Streaming test
curl -s -o /dev/null \
  --write-out "first_byte: %{time_starttransfer}s  total: %{time_total}s  size: %{size_download} bytes\n" \
  "http://localhost:3000/observations/getMagnitudeData.json?start_date=2023-01-01&end_date=2023-12-31&species_id=3"

# Monthly frequency variant
curl -s "http://localhost:3000/observations/getMagnitudeData.json?start_date=2023-01-01&end_date=2023-12-31&species_id=3&frequency=months" \
  | jq 'if type == "array" then "OK: \(length) rows" else "FAIL: not an array" end'
```

### getSiteLevelData

```bash
# Sanity check
curl -s "http://localhost:3000/observations/getSiteLevelData.json?start_date=2023-01-01&end_date=2023-12-31&species_id=3" \
  | jq 'length, .[0] | keys'

# Timing test — NOTE: first_byte ≈ total is EXPECTED for this endpoint.
# It must aggregate all rows before writing a single byte.
# What you're verifying is that it completes without error.
curl -s -o /dev/null \
  --write-out "first_byte: %{time_starttransfer}s  total: %{time_total}s  size: %{size_download} bytes\n" \
  "http://localhost:3000/observations/getSiteLevelData.json?start_date=2023-01-01&end_date=2023-12-31&species_id=3"

# Validate JSON and spot-check an aggregated field
curl -s "http://localhost:3000/observations/getSiteLevelData.json?start_date=2023-01-01&end_date=2023-12-31&species_id=3" \
  | jq 'if type == "array" then "OK: \(length) rows, sample mean_first_yes_doy: \(.[0].mean_first_yes_doy)" else "FAIL" end'
```

---

## What to expect

| Endpoint | Expected first_byte | Why |
|---|---|---|
| getObservations | Fast (< 1s) | Row-by-row streaming, no sort materialization for filtered queries |
| getSummarizedData | Moderate | CTEs must materialize server-side before rows flow |
| getMagnitudeData | Moderate | GROUP BY must complete server-side before rows flow |
| getSiteLevelData | Slow (≈ total) | Node aggregation requires all rows; response held until `end` |

For all endpoints, Ctrl+C during a curl cancels the request cleanly: Node detects the disconnect via `req.on('close')`, destroys the MySQL connection immediately, and releases it back to the pool.

---

## All streaming endpoints — complete

Identified by auditing the PHP source for `mysql_unbuffered_query()` + `$out->flush()`. All have been converted.

| PHP function | Express route | File | Notes |
|---|---|---|---|
| `getObservations` | `GET /observations/get_observations` | `src/routes/observations.js` | ✅ |
| `getSummarizedData` | `GET /observations/get_summarized_data` | `src/routes/observations.js` | ✅ |
| `getSiteLevelData` | `GET /observations/get_site_level_data` | `src/routes/observations.js` | ✅ streams MySQL input; batches output (aggregation required) |
| `getMagnitudeData` | `GET /observations/get_magnitude_data` | `src/routes/observations.js` | ✅ |
| `getObservationGroupDetails` | `GET /observations/get_observation_group_details` | `src/routes/observations.js` | ✅ PHP had `max_execution_time = -1` — expected to be large |
| `getStationDetails` | `GET /stations/get_station_details` | `src/routes/stations.js` | ✅ |
| `getObserverDetails` | `GET /person/get_observer_details` | `src/routes/person.js` | ✅ |
| `getPlantDetails` | `GET /individuals/get_plant_details` | `src/routes/individuals.js` | ✅ |
| `getPhenophaseDetails` | `GET /phenophases/get_phenophase_details` | `src/routes/phenophases.js` | ✅ no-filter call returns all phenophase details |
| `getSecondaryPhenophaseDetails` | `GET /phenophases/get_secondary_phenophase_details` | `src/routes/phenophases.js` | ✅ no filters — always a full table dump |
