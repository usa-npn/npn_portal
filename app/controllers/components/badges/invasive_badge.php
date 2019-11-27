<?php

require_once('abstract_badge.php');

define('INVASIVE_WEEK_THRESHOLD', 6);


class InvasiveBadge extends AbstractBadge{
        
    private static $qualifying_species_ids;
    
    public function __construct($person_id){
        $this->person_id = $person_id;
//change this        
        InvasiveBadge::$qualifying_species_ids = array(1438,1446,12,1448,839,1452,1469,915,1246,1471,770,1248,1509,1217,95,1510);
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
                                "AND ssi.Species_ID IN (" . implode(",", InvasiveBadge::$qualifying_species_ids) . ")
                                AND (o.Deleted IS NULL OR o.Deleted <> 1)
                                GROUP BY o.Observation_Date, o.Individual_ID
                                ORDER BY ssi.Individual_ID,o.Observation_Date
                                ) tbl
                                GROUP BY Individual_ID, YEAR(Observation_Date)
                                HAVING c >= " . INVASIVE_WEEK_THRESHOLD
                );

        return count($res) > 0;

    }
    
}
?>
