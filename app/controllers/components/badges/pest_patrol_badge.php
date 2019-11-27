<?php

require_once('abstract_badge.php');

define('PEST_PATROL_CAMPAIGN_DAYS_THRESHOLD', 6);


class PestPatrolBadge extends AbstractBadge{
        
    private static $qualifying_species_ids;
    
    public function __construct($person_id){
        $this->person_id = $person_id;
        PestPatrolBadge::$qualifying_species_ids = array(259,1239,1243,1700,1789,1790,1791,1792,1793,1794,1795,1796,1797);
    }
    
    public function validate(){
        
        $badgeModel = ClassRegistry::init('Badge');
        /*
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
                                "AND ssi.Species_ID IN (" . implode(",", BatCampaignBadge::$qualifying_species_ids) . ")
                                GROUP BY o.Observation_Date, o.Individual_ID
                                ORDER BY ssi.Individual_ID,o.Observation_Date
                                ) tbl
                                GROUP BY Individual_ID, YEAR(Observation_Date)
                                HAVING c >= " . BAT_CAMPAIGN_WEEK_THRESHOLD
                );
        */
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
                                "AND ssi.Species_ID IN (" . implode(",", PestPatrolBadge::$qualifying_species_ids) . ")
                                GROUP BY o.Observation_Date, o.Individual_ID
                                ORDER BY ssi.Individual_ID,o.Observation_Date
                                ) tbl
                                GROUP BY Individual_ID, YEAR(Observation_Date)
                                HAVING c >= " . PEST_PATROL_CAMPAIGN_DAYS_THRESHOLD
                );

        return count($res) > 0;

    }
    
}
?>
