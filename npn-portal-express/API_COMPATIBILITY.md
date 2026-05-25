# API Compatibility Report

Comparison of old PHP API (`https://services.usanpn.org/npn_portal/`) against new Express API (`http://localhost:3000/`).

Updated: 2026-05-11

**Results: 30 passing, 0 failing**

---

## ✅ All Passing (30)

| Endpoint | Notes |
|---|---|
| `species/getSpecies` | |
| `species/getSpeciesById` | Returns single object (not array) |
| `species/getSpeciesByItis` | Param: `itis_sn` |
| `species/getSpeciesByScientificName` | |
| `species/getSpeciesByCommonName` | Searches `vw_Species_All_Names.All_Names` |
| `species/getSpeciesByState` | Param: `state` (not `state_code`) |
| `species/getSpeciesUpdateDate` | |
| `species/getSpeciesFunctionalTypes` | Returns `{type_name}` |
| `species/getPlantTypes` | Filtered by `Kingdom=Plantae` |
| `species/getAnimalTypes` | Filtered by `Kingdom=Animalia` |
| `phenophases/getPhenophases` | |
| `phenophases/getPhenophaseDetails` | |
| `phenophases/getPhenophaseDefinitionDetails` | |
| `phenophases/getPhenophasesForSpecies` | Grouped by species: `{species_id, species_name, phenophases:[...]}` |
| `phenophases/getProtocolDetails` | |
| `phenophases/getSpeciesProtocolDetails` | Returns `{dataset_id, species_id, protocol_id, start_date, end_date}` |
| `phenophases/getAbundanceCategory` | |
| `phenophases/getAbundanceCategories` | |
| `stations/getAllStations` | Accepts `network_id` or `network_ids`; returns `{station_id, station_name, latitude, longitude, network_id, file_url}` |
| `stations/getStates` | Queries `State_List` table; returns `{state_code, state_name, state_id}` |
| `stations/getStationCountByState` | Returns `{state, number_stations}` |
| `stations/getStationsById` | Returns array of `{station_id, station_name, latitude, longitude}` |
| `networks/getPartnerNetworks` | Returns `{network_id, network_name}` |
| `networks/getNetworkTree` | Uses Drupal taxonomy hierarchy (drupalPool) |
| `observations/getObservationComment` | Returns `{observation_comment}` |
| `observations/getObservationsCount` | Slow (~15s) with no filter — full count of 45M+ rows |
| `observations/getDatasetDetails` | |
| `observations/getObservationGroupDetails` | Accepts comma-separated `ids` |
| `observations/getObservationDates` | Nested structure grouped by species/phenophase/year |
| `person/getObserverDetails` | |

---

## Implementation Notes

- `getObservationsCount` accepts params via both GET query and POST JSON body (old PHP used POST).
- `getNetworkTree` must use `drupalPool` — the npn user lacks access to Drupal taxonomy tables.
- `getPhenophasesForSpecies` date filter goes in JOIN ON clause (not WHERE) to preserve LEFT JOIN semantics; JOIN params must precede WHERE params in the params array.
- `Species_State_Location` alias must not be `ssl` (MySQL reserved keyword for SSL).
