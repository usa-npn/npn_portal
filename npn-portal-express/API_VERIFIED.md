# Verified API Compatibility

Endpoints confirmed to return identical JSON structure (keys and types) to `https://services.usanpn.org/npn_portal/`.

**Verified:** 2026-05-12 | **Passing: 42 / 42**

---

## Species — 10 passing

| Endpoint | Required Params | Response Shape |
|---|---|---|
| `species/getSpecies` | `kingdom` (optional) | Array of `{species_id, common_name, genus, species, kingdom, itis_taxonomic_sn, functional_type, class_id, class_name, class_common_name, order_id, order_name, order_common_name, family_id, family_name, family_common_name, genus_id, genus_common_name, species_type}` |
| `species/getSpeciesById` | `species_id` | Single object `{common_name, genus, species, kingdom, itis_taxonomic_sn}` |
| `species/getSpeciesByItis` | `itis_sn` | Single object `{common_name, genus, species, species_id, kingdom}` |
| `species/getSpeciesByScientificName` | `genus`, `species` | Single object `{common_name, itis_taxonomic_sn, kingdom, species_id}` |
| `species/getSpeciesByCommonName` | `common_name` | Single object `{genus, itis_taxonomic_sn, species, species_id}` |
| `species/getSpeciesByState` | `state` (2-letter code, not `state_code`) | Array of `{species_id, common_name, genus, kingdom, species, itis_taxonomic_sn}` |
| `species/getSpeciesUpdateDate` | — | `{update_date}` (plain MySQL datetime string) |
| `species/getSpeciesFunctionalTypes` | — | Array of `{type_name}` |
| `species/getPlantTypes` | — | Array of `{species_type_id, species_type, species_count}` (Kingdom=Plantae only) |
| `species/getAnimalTypes` | — | Array of `{species_type_id, species_type, species_count}` (Kingdom=Animalia only) |

---

## Phenophases — 10 passing

| Endpoint | Required Params | Response Shape |
|---|---|---|
| `phenophases/getPhenophases` | — | Array of `{phenophase_id, phenophase_name, phenophase_category, color, pheno_class_id}` |
| `phenophases/getPhenophaseDetails` | `phenophase_id` | Array of `{phenophase_id, phenophase_description, definition_ids, phenophase_names, phenophase_revision_comments}` |
| `phenophases/getPhenophaseDefinitionDetails` | — | Array of `{definition_id, dataset_id, phenophase_id, phenophase_name, definition, start_date, end_date, comments}` |
| `phenophases/getPhenophasesForSpecies` | `species_id`, optional `date` | Array of `{species_id, species_name, phenophases:[{phenophase_id, phenophase_name, phenophase_category, phenophase_definition, phenophase_additional_definition, seq_num, color, pheno_class_id, pheno_class_name, pheno_class_sequence, abundance_category, raw_abundance}]}` |
| `phenophases/getPhenoClasses` | — | Array of `{id, name, description, sequence}` |
| `phenophases/getPhenoClass` | `pheno_class_id` | Object with `{id, name, description, sequence, phenophases:[...]}` |
| `phenophases/getProtocolDetails` | `protocol_id` | Array of `{protocol_id, protocol_name, primary_name, secondary_name, phenophase_list, protocol_comments}` |
| `phenophases/getSpeciesProtocolDetails` | `species_id` | Array of `{dataset_id, species_id, protocol_id, start_date, end_date}` |
| `phenophases/getAbundanceCategory` | `abundance_category_id` | Object with `{category_id, category_name, category_description, category_values:[{value_id, value_name, value_description}]}` |
| `phenophases/getAbundanceCategories` | — | Array of `{category_id, category_name, category_description, category_values:[...]}` |

---

## Stations — 4 passing

| Endpoint | Required Params | Response Shape |
|---|---|---|
| `stations/getAllStations` | optional `network_id`/`network_ids`, `person_id`, `state_code`, bbox | Array of `{station_id, station_name, latitude, longitude, network_id, file_url}` |
| `stations/getStates` | — | Array of `{state_code, state_name, state_id}` (from `State_List` table) |
| `stations/getStationCountByState` | — | Array of `{state, number_stations}` |
| `stations/getStationsById` | `station_id` | Array of `{station_id, station_name, latitude, longitude}` |

---

## Networks — 2 passing

| Endpoint | Required Params | Response Shape |
|---|---|---|
| `networks/getPartnerNetworks` | optional `active_only`, `member_id`, `network_id`, `search` | Array of `{network_id, network_name}` |
| `networks/getNetworkTree` | — | Nested tree: `[{network_id, network_name, secondary_network:[{..., tertiary_network:[...]}]}]` (uses Drupal taxonomy via `drupalPool`) |

---

## Observations — 10 passing

| Endpoint | Required Params | Response Shape |
|---|---|---|
| `observations/getObservations` | `start_date`, `end_date`, optional `state[]`, `species_id[]`, `station_id[]`, `individual_id[]`, `phenophase_id[]`, `dataset_ids[]`, `kingdom`, `group_id[]`, `limit`, `additional_field[]` | Array of `{observation_id, update_datetime, site_id, latitude, longitude, elevation_in_meters, state, species_id, genus, species, common_name, kingdom, individual_id, phenophase_id, phenophase_description, observation_date, day_of_year, phenophase_status, intensity_category_id, intensity_value, abundance_value}` plus any requested `additional_field` columns |
| `observations/getSummarizedData` | `start_date`, `end_date`, optional filters | Same 25-field shape as getObservations but aggregated per series |
| `observations/getMagnitudeData` | `start_date`, `end_date`, `frequency` (days or `months`, default 30), optional `species_id`, `phenophase_id`, `station_id` | Array of `{species_id, genus, species, common_name, kingdom, phenophase_id, phenophase_description, year, start_date, end_date, status_records_sample_size, individuals_sample_size, sites_sample_size, num_yes_records, numindividuals_with_yes_record, numsites_with_yes_record, proportion_yes_records, proportion_individuals_with_yes_record, proportion_sites_with_yes_record, in-phase_sites_sample_size, in-phase_site_visits_sample_size, total_numanimals_in-phase, mean_numanimals_in-phase, se_numanimals_in-phase, in-phase_per_hr_sites_sample_size, in-phase_per_hr_site_visits_sample_size, mean_numanimals_in-phase_per_hr, se_numanimals_in-phase_per_hr, in-phase_per_hr_per_acre_sites_sample_size, in-phase_per_hr_per_acre_site_visits_sample_size, mean_numanimals_in-phase_per_hr_per_acre, se_numanimals_in-phase_per_hr_per_acre}` |
| `observations/getSiteLevelData` | `start_date`, `end_date`, optional `species_id`, `station_id`, `phenophase_id` | Array of `{site_id, latitude, longitude, elevation_in_meters, state, species_id, genus, species, common_name, kingdom, phenophase_id, phenophase_description, first_yes_sample_size, mean_first_yes_year, mean_first_yes_doy, mean_first_yes_julian_date, se_first_yes_in_days, mean_numdays_since_prior_no, se_numdays_since_prior_no, last_yes_sample_size, mean_last_yes_year, mean_last_yes_doy, mean_last_yes_julian_date, se_last_yes_in_days, mean_numdays_until_next_no, se_numdays_until_next_no}` |
| `observations/getAllObservationsForSpecies` | `start_date`, `end_date`, optional `species_id`, `network_id` | `{station_list:[{station_id, station_name, latitude, longitude, networks?, species:{species_id:{phenophase_id:{y?, n?, q?}}}}], phenophase_list:[{phenophase_id, phenophase_name}]}` |
| `observations/getObservationComment` | `observation_id` | `{observation_comment}` |
| `observations/getObservationsCount` | optional filters via GET or POST JSON body | `{obsCount}` (~15s with no filter — full 45M+ row count) |
| `observations/getDatasetDetails` | `dataset_id` | Array of `{dataset_id, dataset_name, dataset_description, contact_name, contact_institution, contact_email, contact_phone, contact_address, dataset_comments, dataset_documentation_url}` |
| `observations/getObservationGroupDetails` | `ids` (comma-separated) or `observation_group_id` | Array of `{observation_group_id, travel_time, total_observation_time, animal_search_time, num_observers_searching, animal_search_method, snow_on_ground, percent_snow_cover, snow_in_tree_canopy, site_visit_comments}` |
| `observations/getObservationDates` | `year`, `species_id` | Array of `{species_id, common_name, phenophases:[{phenophase_id, phenophase_name, seq_num, years:{year:{positive:[doy,...], negative:[doy,...]}}}]}` |

---

## Individuals — 4 passing

| Endpoint | Required Params | Response Shape |
|---|---|---|
| `individuals/getIndividualsOfSpeciesAtStations` | `species_id`, `station_id` (also accepts `station_ids`) | Array of `{individual_id, individual_name, number_observations}` |
| `individuals/getIndividualsAtStations` | `station_id` (also accepts `station_ids`) | Array of `{individual_id, individual_name, species_id, kingdom, active, seq_num, file_url}` |
| `individuals/getIndividualById` | `individual_id` | Object `{individual_name, kingdom, species_id}` or `null` if not found |
| `individuals/getShadeStatuses` | — | Array of `{status}` |

---

## Person — 1 passing

| Endpoint | Required Params | Response Shape |
|---|---|---|
| `person/getObserverDetails` | `person_id` | Array of `{person_id, read_online_training_materials, trained_in_person, place_of_training, ecological_experience, eco_experience_comments, self_described_naturalist, naturalist_skill_level, participate_as_part_of_job, type_of_job, job_comments, lpl_certified_date}` |

---

## Implementation Notes

- `getObservations` `additional_field` param accepts an array of lowercase field names (e.g. `additional_field[0]=gdd`). Valid names are a whitelist of ~80 columns from CO (climate: `gdd`, `gddf`, `tmax`/`tmin`/`prcp` + seasonal variants, `daylength`, `acc_prcp`, remote-sensing: `greenup_0/1` through `qa_overall_0/1`; observation metadata: `dataset_id`, `observedby_person_id`, `submission_id`, `protocol_id`, `phenophase_name`, `phenophase_definition_id`, `observation_time`, `observation_group_id`, `observation_comments`, `observed_status_conflict_flag`, `status_conflict_related_records`) and CSD (site/species metadata: `site_name`, `partner_group`, `species_functional_type`, `species_category`, `lifecycle_duration`, `growth_habit`, `usda_plants_symbol`, `itis_number`, `plant_nickname`, `patch`, `phenophase_category`, `pheno_class_id`, `pheno_class_name`, taxonomy: `genus_id`, `genus_common_name`, `class_id/name/common_name`, `order_id/name/common_name`, `family_id/name/common_name`). Unknown or duplicate base fields are silently ignored. NULL values return as `-9999`. DECIMAL columns (`gdd`, `tmax`, etc.) are converted to JS floats via `parseFloat`.
- `getObservationsCount` accepts params via GET query string **or** POST JSON body (old PHP used POST).
- `getNetworkTree` must use `drupalPool` — the `npn_web_services` DB user cannot access Drupal taxonomy tables.
- `getPhenophasesForSpecies` date filter belongs in the JOIN ON clause (not WHERE) to preserve LEFT JOIN semantics; JOIN `?` params must precede WHERE `?` params in the params array.
- `Species_State_Location` table alias must not be `ssl` — MySQL reserves `ssl` as a keyword.
- `getSpeciesByState` uses param `state=AZ` (not `state_code`); `getAllStations` uses `network_ids` (plural) in the old PHP but the new Express also accepts `network_id` (singular).
- `getAllObservationsForSpecies` groups observations: `SUM(Observation_Extent=1)` → `y`, `SUM(Observation_Extent=0)` → `n`, `SUM(Observation_Extent=-1)` → `q`; only non-zero keys are included.
- `getIndividualsOfSpeciesAtStations` and `getIndividualsAtStations` accept both `station_id` (singular) and `station_ids` (plural).
- `getIndividualById` returns `null` (not an object with null fields) when individual not found.
- `getMagnitudeData` uses MySQL boolean GROUP BY trick: one boolean expression per time period in GROUP BY clause (e.g. `(co.Observation_Date BETWEEN 'P1_START' AND 'P1_END')`) so non-overlapping periods naturally partition. For animals: abundance/per-hr/per-acre computed in JS with population SE (STDDEV_POP/√N). For plants: animal-only fields are -9999. `frequency=14` means 14-day windows (PHP subtracts 1: adds 13 days to period start); last period trimmed to end_date. Requires `SET SESSION group_concat_max_len = 10000000`.
- `getSummarizedData` and `getSiteLevelData` use MySQL 8 CTEs to find first/last yes per series within the date range; Julian dates computed as `ROUND(UNIX_TIMESTAMP(date)/86400+2440587.5)`, returned as integers. Quality filter for site-level: `0 < numdays_since_prior_no <= 30`; aggregation (mean, SE = STDDEV_SAMP/SQRT(N)) computed in JavaScript.
- `getSiteLevelData` SE for first_yes uses DOY values; SE for last_yes uses Julian date values (matching PHP `stats_standard_deviation` behavior).
