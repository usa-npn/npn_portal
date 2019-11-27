<?php

require_once('abstract_badge.php');



class FullPhenoCaptureBadge extends AbstractBadge{
        
    private static $DAYS_IN_YEAR = 365;
    
    
    public function __construct($person_id){
        $this->person_id = $person_id;
    }
    
    public function validate(){
        
        $badgeModel = ClassRegistry::init('Badge');
                
        $res = $badgeModel->query("SELECT o.Observation_ID, o.Phenophase_ID, o.Individual_ID, o.Observation_Date, o.Observation_Extent,
                                    yes.Observation_ID, yes.Observation_Extent, yes.Observation_Date,
                                    nn.Observation_ID, nn.Observation_Extent, nn.Observation_Date, DATEDIFF(nn.Observation_Date, o.Observation_Date) diff
                                    FROM usanpn2.Observation o
                                    LEFT JOIN usanpn2.Station_Species_Individual ssi
                                    ON ssi.Individual_ID = o.Individual_ID
                                    LEFT JOIN usanpn2.Species s
                                    ON s.Species_ID = ssi.Species_ID
                                    INNER JOIN
                                    (
                                    SELECT Observation_ID, Phenophase_ID, Individual_ID, Observation_Date, Observation_Extent
                                    FROM usanpn2.Observation o
                                    WHERE o.Observer_ID = " . $this->person_id . " 
                                    AND (o.Deleted IS NULL OR o.Deleted <> 1)
                                    AND o.Observation_Extent = 1
                                    ) yes
                                    ON yes.Individual_Id = o.Individual_ID AND o.Phenophase_ID = yes.Phenophase_ID
                                    AND (YEAR(o.Observation_Date) = YEAR(yes.Observation_Date) OR YEAR(o.Observation_Date) + 1 = YEAR(yes.Observation_Date))
                                    AND o.Observation_Date < yes.Observation_Date
                                    INNER JOIN
                                    (
                                    SELECT Observation_ID, Phenophase_ID, Individual_ID, Observation_Date, Observation_Extent
                                    FROM usanpn2.Observation o
                                    WHERE o.Observer_ID = " . $this->person_id . "
                                    AND o.Observation_Extent = 0
                                    AND (o.Deleted IS NULL OR o.Deleted <> 1)
                                    ) nn
                                    ON nn.Individual_Id = o.Individual_ID AND o.Phenophase_ID = nn.Phenophase_ID
                                    AND (YEAR(o.Observation_Date) = YEAR(nn.Observation_Date) OR YEAR(o.Observation_Date) + 1 = YEAR(nn.Observation_Date))
                                    AND yes.Observation_Date < nn.Observation_Date
                                    WHERE o.Observer_ID = " . $this->person_id . "
                                    AND s.Kingdom = 'Plantae'
                                    AND o.Observation_Extent = 0
                                    AND (o.Deleted IS NULL OR o.Deleted <> 1)
                                    HAVING diff BETWEEN 1 AND " . FullPhenoCaptureBadge::$DAYS_IN_YEAR . " 
                                    LIMIT 1"
        );
        

        return count($res) > 0;

    }
    
}
?>
