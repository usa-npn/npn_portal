<?php

require_once('abstract_badge.php');


class MayflyBadge extends AbstractBadge{
    
    private static $qualifying_phenophase_ids = array(289,327,507);
    private static $qualifying_species_ids = array(1389, 1390);
    
    public function __construct($person_id){
        $this->person_id = $person_id;
    }
    
    public function validate(){
        
        $badgeModel = ClassRegistry::init('Badge');
        
        
        $res = $badgeModel->query("
            SELECT transition, Observation_Date
            FROM(
                SELECT 
                year,
                Individual_ID,
                has_yes,
                Observation_Date,
                IF(year <> @y, @prev_no :=0, year),
                IF(Individual_ID <> @i, @prev_no :=0, Individual_ID),
                IF(@prev_no > 0 AND has_yes > 0, 1, 0) `transition`, 
                @prev_yes:= has_yes,
                @prev_no := has_no,
                @i :=Individual_ID,
                @y :=year
                FROM (
                    SELECT 
                    COUNT(CASE WHEN o.Observation_Extent = 1 THEN Observation_ID ELSE null END) `has_yes`, 
                    COUNT(CASE WHEN o.Observation_Extent = 0 THEN Observation_ID ELSE null END) `has_no`, 
                    o.Individual_ID,
                    YEAR(o.Observation_Date) year,
                    o.Observation_Date
                    FROM usanpn2.Observation o
                    LEFT JOIN usanpn2.Station_Species_Individual ssi
                    ON ssi.Individual_ID = o.Individual_ID
                    WHERE o.Observer_ID = " . $this->person_id . "
                    AND ssi.Species_ID IN (" . implode(",", MayflyBadge::$qualifying_species_ids) . ")
                    AND Phenophase_ID IN (" . implode(",", MayflyBadge::$qualifying_phenophase_ids) . ")
                    AND o.Observation_Extent > -1
                    AND (o.Deleted IS NULL OR o.Deleted <> 1)
                    GROUP BY o.Individual_ID, Observation_Date
                    ORDER BY Observation_Date
                ) tbl
            ) tbl
            WHERE transition > 0
        "
        );
        
        return count($res) > 0;

    }
    
}
?>
