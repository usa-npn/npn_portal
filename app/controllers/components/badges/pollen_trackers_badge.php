<?php

require_once('abstract_badge.php');

define('POLLEN_WEEK_THRESHOLD', 6);


class PollenTrackersBadge extends AbstractBadge{
        
    private static $qualifying_species_ids;
    
    public function __construct($person_id){
        $this->person_id = $person_id;
        PollenTrackersBadge::$qualifying_species_ids = array(777,
        1843,
        59,
        778,
        1,
        2,
        1591,
        60,
        779,
        780,
        3,
        781,
        61,
        1199,
        62,
        63,
        319,
        145,
        788,
        146,
        97,
        1439,
        98,
        430,
        1850,
        1339,
        1851,
        99,
        1805,
        1176,
        67,
        824,
        68,
        1177,
        1605,
        829,
        1924,
        1342,
        74,
        872,
        873,
        75,
        1350,
        2143,
        1353,
        80,
        43,
        1743,
        1354,
        289,
        902,
        291,
        290,
        44,
        81,
        1361,
        320,
        976,
        977,
        1188,
        2036,
        27,
        1481,
        705,
        100,
        1365,
        2043,
        2044,
        757,
        1870,
        987,
        1690,
        1484,
        988,
        316,
        297,
        2045,
        1485,
        1190,
        2046,
        765,
        1486,
        301,
        704,
        2047,
        101,
        2048,
        1691,
        2049,
        2164,
        1212,
        2050,
        2051,
        2052,
        989,
        1366,
        2053,
        102,
        1756,
        1213,
        1755,
        1487,
        1159,
        305,
        2054,
        1006,
        1007,
        1875,
        293,
        2066,
        1008,
        1371,
        77,
        1163,
        1493,
        1372,
        717,
        1494,
        322,
        1876,
        1009,
        1010,
        1192,
        1048,
        1049,
        1215,
        1216);
    }
    
    public function validate(){
        
        $badgeModel = ClassRegistry::init('Badge');
                
        $res = $badgeModel->query("SELECT Individual_ID, COUNT(DISTINCT week) c
                            FROM(
                                SELECT o.Individual_ID, o.Observation_Date, WEEKOFYEAR(o.Observation_Date) week
                                FROM usanpn2.Person p
                                LEFT JOIN usanpn2.Station st
                                ON st.Observer_ID = p.Person_ID
                                LEFT JOIN usanpn2.Station_Species_Individual ssi
                                ON ssi.Station_ID = st.Station_ID
                                LEFT JOIN usanpn2.Observation o
                                ON o.Individual_ID = ssi.Individual_ID
                                WHERE p.Person_ID = " . $this->person_id . " " .
                                "AND ssi.Species_ID IN (" . implode(",", PollenTrackersBadge::$qualifying_species_ids) . ")
                                AND (o.Deleted IS NULL OR o.Deleted <> 1)
                                GROUP BY o.Observation_Date, o.Individual_ID
                                ORDER BY ssi.Individual_ID,o.Observation_Date
                                ) tbl
                                GROUP BY Individual_ID, YEAR(Observation_Date)
                                HAVING c >= " . POLLEN_WEEK_THRESHOLD
                );

        return count($res) > 0;

    }
    
}
?>
