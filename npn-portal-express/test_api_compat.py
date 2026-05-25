#!/usr/bin/env python3
"""
API compatibility test: compares new Express API against old PHP API.
Checks that response JSON keys match for each endpoint.

Usage:
    python3 test_api_compat.py
    python3 test_api_compat.py --verbose   # show keys on pass too
"""

import urllib.request
import json
import sys
import argparse

BASE_NEW = "http://localhost:3000"
BASE_OLD = "https://services.usanpn.org/npn_portal"

# Each entry: (path, query_string, timeout_seconds)
# Add new tests here as more endpoints are verified.
TESTS = [
    # --- Species ---
    ("species/getSpecies.json",                    "?kingdom=Animalia",                                          30),
    ("species/getSpeciesById.json",                "?species_id=35",                                             30),
    ("species/getSpeciesByItis.json",              "?itis_sn=27806",                                             30),
    ("species/getSpeciesByScientificName.json",    "?genus=Syringa&species=vulgaris",                            30),
    ("species/getSpeciesByCommonName.json",        "?common_name=lilac",                                         30),
    ("species/getSpeciesByState.json",             "?state=AZ",                                                  30),
    ("species/getSpeciesUpdateDate.json",          "",                                                           30),
    ("species/getSpeciesFunctionalTypes.json",     "",                                                           30),
    ("species/getPlantTypes.json",                 "",                                                           30),
    ("species/getAnimalTypes.json",                "",                                                           30),

    # --- Phenophases ---
    ("phenophases/getPhenophases.json",            "",                                                           30),
    ("phenophases/getPhenophaseDetails.json",      "?phenophase_id=373",                                         30),
    ("phenophases/getPhenophaseDefinitionDetails.json", "",                                                      30),
    ("phenophases/getPhenophasesForSpecies.json",  "?species_id=35&date=2024-01-01",                             30),
    ("phenophases/getProtocolDetails.json",        "?protocol_id=28",                                            30),
    ("phenophases/getSpeciesProtocolDetails.json", "?species_id=35",                                             30),
    ("phenophases/getAbundanceCategory.json",      "?abundance_category_id=1",                                   30),
    ("phenophases/getAbundanceCategories.json",    "",                                                           30),
    ("phenophases/getPhenoClasses.json",           "",                                                           30),
    ("phenophases/getPhenoClass.json",             "?pheno_class_id=1",                                         30),

    # --- Stations ---
    ("stations/getAllStations.json",               "",                                                           30),
    ("stations/getStates.json",                    "",                                                           30),
    ("stations/getStationCountByState.json",       "",                                                           30),
    ("stations/getStationsById.json",              "?station_id=1234",                                          30),

    # --- Networks ---
    ("networks/getPartnerNetworks.json",           "",                                                           30),
    ("networks/getNetworkTree.json",               "",                                                           30),

    # --- Observations ---
    ("observations/getObservations.json",
     "?start_date=2012-01-01&end_date=2012-01-03&state%5B0%5D=AZ&state%5B1%5D=IL&request_src=rest_test", 30),
    ("observations/getSummarizedData.json",
     "?start_date=2012-01-01&end_date=2012-01-10&request_src=rest_test",                                  60),
    ("observations/getAllObservationsForSpecies.json",
     "?species_id%5B0%5D=52&species_id%5B1%5D=53&start_date=2008-01-01&end_date=2011-12-31",                    60),
    ("observations/getObservationComment.json",    "?observation_id=1",                                          30),
    ("observations/getObservationsCount.json",     "",                                                           60),
    ("observations/getDatasetDetails.json",        "?dataset_id=7",                                              30),
    ("observations/getObservationGroupDetails.json", "?ids=1",                                                   30),
    ("observations/getObservationDates.json",      "?year=2020&species_id=35",                                   60),

    # --- Individuals ---
    ("individuals/getIndividualsOfSpeciesAtStations.json", "?species_id=35&station_id=100",                     30),
    ("individuals/getIndividualsAtStations.json",  "?station_ids=1234",                                         30),
    ("individuals/getIndividualById.json",         "?individual_id=100",                                        30),
    ("individuals/getShadeStatuses.json",          "",                                                           30),

    # --- Observations with additional fields ---
    ("observations/getObservations.json",
     "?start_date=2012-01-01&end_date=2012-01-03&state%5B0%5D=AZ&state%5B1%5D=IL"
     "&additional_field%5B0%5D=dataset_id&additional_field%5B1%5D=site_name"
     "&additional_field%5B2%5D=observation_group_id&additional_field%5B3%5D=gdd"
     "&additional_field%5B4%5D=tmax&request_src=rest_test",                           30),

    # --- Observations (site/summarized level) ---
    ("observations/getSiteLevelData.json",
     "?start_date=2012-01-01&end_date=2012-03-30&request_src=rest_test",                                   90),
    ("observations/getMagnitudeData.json",
     "?start_date=2013-01-01&end_date=2013-12-31&species_id%5B0%5D=246&request_src=test&frequency=14",     60),

    # --- Person ---
    ("person/getObserverDetails.json",             "?person_id=1",                                              30),
]


def fetch(url, timeout=30):
    try:
        with urllib.request.urlopen(url, timeout=timeout) as r:
            return json.loads(r.read()), None
    except Exception as e:
        return None, str(e)


def first(d):
    if isinstance(d, list):
        return d[0] if d else {}
    return d if isinstance(d, dict) else {}


def run_tests(verbose=False):
    passed = failed = 0

    for path, params, timeout in TESTS:
        new_d, new_err = fetch(f"{BASE_NEW}/{path}{params}", timeout)
        old_d, old_err = fetch(f"{BASE_OLD}/{path}{params}", timeout)

        if new_err:
            print(f"❌  {path}{params[:50]}")
            print(f"    NEW error: {new_err}")
            failed += 1
            continue
        if old_err:
            print(f"❌  {path}{params[:50]}")
            print(f"    OLD error: {old_err}")
            failed += 1
            continue

        # Exact match (handles null, empty array, etc.)
        if new_d == old_d:
            passed += 1
            if verbose:
                print(f"✅  {path}")
            else:
                print(f"✅  {path}")
            continue

        n = first(new_d)
        o = first(old_d)
        nk = sorted(n.keys()) if isinstance(n, dict) else []
        ok = sorted(o.keys()) if isinstance(o, dict) else []

        if nk == ok:
            passed += 1
            if verbose:
                print(f"✅  {path}  keys={nk}")
            else:
                print(f"✅  {path}")
        else:
            print(f"⚠️   {path}{params[:50]}")
            if nk and ok:
                extra = set(nk) - set(ok)
                missing = set(ok) - set(nk)
                if extra:   print(f"    NEW has extra : {sorted(extra)}")
                if missing: print(f"    NEW is missing: {sorted(missing)}")
            else:
                print(f"    NEW first={type(n).__name__}  OLD first={type(o).__name__}")
                print(f"    NEW sample: {str(new_d)[:120]}")
                print(f"    OLD sample: {str(old_d)[:120]}")
            failed += 1

    total = passed + failed
    print(f"\n{passed}/{total} passing")
    return failed == 0


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--verbose", "-v", action="store_true")
    args = parser.parse_args()
    ok = run_tests(verbose=args.verbose)
    sys.exit(0 if ok else 1)
