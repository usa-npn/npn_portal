# NPN Portal API

REST API serving observation data, station management, species/phenophase metadata, badge tracking, and user/network operations for the USA National Phenology Network.

This project was originally a CakePHP application. It has been fully replaced by a Node.js/Express server located in the `npn-portal-express/` directory.

## Prerequisites

- **Node.js v16.17.0** (prod and dev servers currently run this version via nvm)

```bash
nvm install 16.17.0
nvm use 16.17.0
```

## Setup

```bash
cd npn-portal-express
cp .env.example .env   # then fill in database credentials and API keys
npm install
```

### Environment Variables

| Variable | Description |
|---|---|
| `OPS_USANPN_HOST` | MySQL host for the NPN database |
| `OPS_USANPN_USER` | MySQL user for the NPN database |
| `OPS_USANPN_PASSWORD` | MySQL password for the NPN database |
| `OPS_USANPN_DATABASE` | NPN database name |
| `OPS_DRUPAL_HOST` | MySQL host for the Drupal database |
| `OPS_DRUPAL_USER` | MySQL user for the Drupal database |
| `OPS_DRUPAL_PASSWORD` | MySQL password for the Drupal database |
| `OPS_DRUPAL_DATABASE` | Drupal database name |
| `PGHOST` | PostgreSQL host (GIS database) |
| `PGPORT` | PostgreSQL port |
| `PGUSER` | PostgreSQL user |
| `PGPASSWORD` | PostgreSQL password |
| `PGDATABASE` | PostgreSQL database name |
| `GOOGLE_ELEVATION_KEY` | Google Elevation API key |
| `GOOGLE_GEOCODE_KEY` | Google Geocode API key |
| `NPN_TIMEZONE_URL` | Timezone lookup endpoint |
| `PORT` | Server port (default: 3005) |
| `REQUIRE_HTTPS` | Set to `1` to require HTTPS |

## Running

**Development** (auto-restarts on file changes):

```bash
cd npn-portal-express
npm run dev
```

**Production**:

```bash
cd npn-portal-express
npm start
```

The server listens on the port specified by `PORT` (default `3005`).

## Deployment

1. SSH into the target server.
2. Ensure the correct Node version is active:
   ```bash
   nvm use 16.17.0
   ```
3. Pull the latest code and install dependencies:
   ```bash
   cd /path/to/npn_portal/npn-portal-express
   git pull
   npm install
   ```
4. Restart the application process (e.g. via `pm2`, `systemctl`, or however the service is managed on that host).

## API Routes

All routes are mounted under the root path:

| Prefix | Description |
|---|---|
| `/metadata` | Dataset and protocol metadata |
| `/submissions` | Observation submission queries |
| `/badges` | Badge definitions and validation |
| `/person` | Person/user lookups |
| `/individuals` | Individual plant/animal records |
| `/networks` | Network management |
| `/species` | Species lookups |
| `/phenophases` | Phenophase definitions |
| `/stations` | Station lookups |
| `/observations` | Observation data queries |
| `/create_user` | User creation |
| `/create_station` | Station creation |
| `/create_individual` | Individual creation |
| `/enter_observation` | Observation entry |

### Legacy URL Compatibility

The API transparently handles old CakePHP-style URLs:
- File extensions (`.json`, `.xml`, `.csv`, `.ndjson`) are stripped automatically.
- CamelCase action names are converted to snake_case (e.g. `/species/getSpecies.json` becomes `/species/get_species`).
- Both GET and POST are accepted on all endpoints for backward compatibility with existing clients.
