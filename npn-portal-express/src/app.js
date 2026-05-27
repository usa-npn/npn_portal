require('dotenv').config();

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
  console.error('Unhandled error:', err);
  res.status(500).json({ error: err.message || 'Internal server error' });
});

// ── Start server ──────────────────────────────────────────────────────────────
const PORT = process.env.NPN_PORTAL_PORT || 3005;
app.listen(PORT, () => {
  console.log(`NPN Portal Express API listening on port ${PORT}`);
});

module.exports = app;
