<?php

require_once('abstract_badge.php');

define('NECTAR_WEEK_THRESHOLD', 6);


class NectarBadge extends AbstractBadge{
        
    private static $qualifying_species_ids;
    
    public function __construct($person_id){
        $this->person_id = $person_id;
        NectarBadge::$qualifying_species_ids = array(156,170,171,186,195,197,198,199,200,201,202,203,204,207,223,224,299,714,715,747,767,772,801,845,911,912,916,921,931,1027,1028,1034,1155,1186,1325,1326,1327,1328,1329,1330,1331,1332,1333,1334,1335,1336,1337,1437,1454,1606,1614,1637,1653);
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
                                "AND ssi.Species_ID IN (" . implode(",", NectarBadge::$qualifying_species_ids) . ")
                                AND (o.Deleted IS NULL OR o.Deleted <> 1)
                                GROUP BY o.Observation_Date, o.Individual_ID
                                ORDER BY ssi.Individual_ID,o.Observation_Date
                                ) tbl
                                GROUP BY Individual_ID, YEAR(Observation_Date)
                                HAVING c >= " . NECTAR_WEEK_THRESHOLD
                );

        return count($res) > 0;

    }
    
}
?>
