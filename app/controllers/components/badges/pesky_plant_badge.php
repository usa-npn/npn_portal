<?php

require_once('abstract_badge.php');

define('PESKY_PLANT_CAMPAIGN_DAYS_THRESHOLD', 6);


class PeskyPlantBadge extends AbstractBadge{
        
    private static $qualifying_species_ids;
    
    public function __construct($person_id){
        
        $this->person_id = $person_id;
        PeskyPlantBadge::$qualifying_species_ids = array(184,1827, 1859);
    }
    
    public function validate(){
        
        $badgeModel = ClassRegistry::init('Badge');

        $res = $badgeModel->query("SELECT Individual_ID, COUNT(DISTINCT Observation_Date) c
                            FROM(
                                SELECT o.Individual_ID, o.Observation_Date
                                FROM usanpn2.Person p
                                LEFT JOIN usanpn2.Station st
                                ON st.Observer_ID = p.Person_ID
                                LEFT JOIN usanpn2.Station_Species_Individual ssi
                                ON ssi.Station_ID = st.Station_ID
                                LEFT JOIN usanpn2.Observation o
                                ON o.Individual_ID = ssi.Individual_ID
                                WHERE p.Person_ID = " . $this->person_id . " " .
                                "AND ssi.Species_ID IN (" . implode(",", PeskyPlantBadge::$qualifying_species_ids) . ")
                                GROUP BY o.Observation_Date, o.Individual_ID
                                ORDER BY ssi.Individual_ID,o.Observation_Date
                                ) tbl
                                GROUP BY Individual_ID, YEAR(Observation_Date)
                                HAVING c >= " . PESKY_PLANT_CAMPAIGN_DAYS_THRESHOLD
                );

        return count($res) > 0;

    }
    
}
?>
