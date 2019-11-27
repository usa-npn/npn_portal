<?php

require_once('abstract_badge.php');

define('MOP_WEEK_THRESHOLD', 6);


class MopBadge extends AbstractBadge{
        
    private static $qualifying_species_ids;
    
    public function __construct($person_id){
        $this->person_id = $person_id;
        MopBadge::$qualifying_species_ids = array(2,3,27,61,102,301,316,320,777,976,977);
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
                                "AND ssi.Species_ID IN (" . implode(",", MopBadge::$qualifying_species_ids) . ")
                                AND (o.Deleted IS NULL OR o.Deleted <> 1)
                                GROUP BY o.Observation_Date, o.Individual_ID
                                ORDER BY ssi.Individual_ID,o.Observation_Date
                                ) tbl
                                GROUP BY Individual_ID, YEAR(Observation_Date)
                                HAVING c >= " . MOP_WEEK_THRESHOLD
                );

        return count($res) > 0;

    }
    
}
?>
