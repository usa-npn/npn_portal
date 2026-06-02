require('dotenv').config();
require('./logger');

const express = require('express');
const app = express();

// ── Body parsers ─────────────────────────────────────────────────────────────
app.use(express.json({ limit: '50mb' }));
app.use(express.urlencoded({ extended: true, limit: '50mb', parameterLimit: 50000 }));

// ── CakePHP compatibility: merge POST body params into req.query ─────────────
// The old CakePHP API accepted both GET and POST on all endpoints.
// Clients like pop-service POST form data, so make it available via req.query.
app.use((req, res, next) => {
  if (req.body && typeof req.body === 'object' && Object.keys(req.body).length > 0) {
    req.query = { ...req.query, ...req.body };
  }
  next();
});

// ── Route modules ─────────────────────────────────────────────────────────────
const metadataRoutes       = require('./routes/metadata');
const submissionsRoutes    = require('./routes/submissions');
const badgesRoutes         = require('./routes/badges');
const personRoutes         = require('./routes/person');
const individualsRoutes    = require('./routes/individuals');
const networksRoutes       = require('./routes/networks');
const speciesRoutes        = require('./routes/species');
const phenophasesRoutes    = require('./routes/phenophases');
const stationsRoutes       = require('./routes/stations');
const observationsRoutes   = require('./routes/observations');
const createUserRoutes     = require('./routes/create_user');
const createStationRoutes  = require('./routes/create_station');
const createIndivRoutes    = require('./routes/create_individual');
const enterObsRoutes       = require('./routes/enter_observation');

// ── URL compatibility middleware ──────────────────────────────────────────────
// Rewrites old CakePHP-style paths (camelCase + extension) to new snake_case paths.
// e.g. /species/getSpecies.json  →  /species/get_species
app.use((req, res, next) => {
  const extRe = /\.(json|xml|csv|ndjson)$/i;
  if (extRe.test(req.path)) {
    const withoutExt = req.path.replace(extRe, '');
    const parts = withoutExt.split('/');
    const action = parts[parts.length - 1];
    const snake = action
      .replace(/([A-Z]+)([A-Z][a-z])/g, '$1_$2')
      .replace(/([a-z\d])([A-Z])/g, '$1_$2')
      .toLowerCase();
    parts[parts.length - 1] = snake;
    req.url = parts.join('/') + (req.url.includes('?') ? req.url.slice(req.url.indexOf('?')) : '');
  }
  next();
});

// ── Root health check ─────────────────────────────────────────────────────────
app.get('/', (req, res) => {
  res.json({ message: 'NPN Portal Express API' });
});

// ── Mount routes ──────────────────────────────────────────────────────────────
app.use('/metadata', metadataRoutes);
app.use('/submissions', submissionsRoutes);
app.use('/badges', badgesRoutes);
app.use('/person', personRoutes);
app.use('/individuals', individualsRoutes);
app.use('/networks', networksRoutes);
app.use('/species', speciesRoutes);
app.use('/phenophases', phenophasesRoutes);
app.use('/stations', stationsRoutes);
app.use('/observations', observationsRoutes);
app.use('/create_user', createUserRoutes);
app.use('/create_station', createStationRoutes);
app.use('/create_individual', createIndivRoutes);
app.use('/enter_observation', enterObsRoutes);

// ── 404 catch-all ─────────────────────────────────────────────────────────────
app.use((req, res) => {
  res.status(404).json({ error: `Route not found: ${req.method} ${req.path}` });
});

// ── Global error handler ──────────────────────────────────────────────────────
// eslint-disable-next-line no-unused-vars
app.use((err, req, res, next) => {
  // A client aborting a request mid-upload (common when the viz tool fires many
  // large POSTs and cancels queued ones) surfaces as a raw-body BadRequestError.
  // The socket is already gone, so just note it quietly — it is not a 500.
  if (err && (err.type === 'request.aborted' || err.code === 'ECONNABORTED')) {
    console.warn('Request aborted by client:', req.method, req.path);
    return;
  }
  console.error('Unhandled error:', err);
  if (res.headersSent) return; // response already (partly) streamed; can't send JSON
  res.status(500).json({ error: err.message || 'Internal server error' });
});

// ── Start server ──────────────────────────────────────────────────────────────
// Run a small cluster so a heavy/synchronous request (e.g. the activity-curve
// magnitude POSTs) blocking one worker's event loop doesn't stall the others.
// The host has 2 cores; default to 2 workers (override with NPN_PORTAL_WORKERS).
const PORT = process.env.NPN_PORTAL_PORT || 3005;
const cluster = require('cluster');
const WORKERS = parseInt(process.env.NPN_PORTAL_WORKERS || '2', 10);

if (cluster.isPrimary && WORKERS > 1) {
  console.log(`NPN Portal primary ${process.pid} starting ${WORKERS} workers`);
  let shuttingDown = false;
  for (let i = 0; i < WORKERS; i++) cluster.fork();

  cluster.on('exit', (worker, code, signal) => {
    if (shuttingDown) {
      // During systemctl stop/restart: don't respawn; exit once all workers are gone.
      if (Object.keys(cluster.workers).length === 0) process.exit(0);
      return;
    }
    console.error(`Worker ${worker.process.pid} exited (${signal || code}); restarting`);
    cluster.fork();
  });

  // Clean shutdown: on SIGTERM/SIGINT (systemctl stop/restart) stop respawning and
  // forward the signal to workers; the primary exits when the last worker is gone.
  for (const sig of ['SIGTERM', 'SIGINT']) {
    process.on(sig, () => {
      shuttingDown = true;
      for (const id in cluster.workers) cluster.workers[id].process.kill(sig);
    });
  }
} else {
  app.listen(PORT, () => {
    console.log(`NPN Portal Express API listening on port ${PORT} (pid ${process.pid})`);
  });
}

module.exports = app;
