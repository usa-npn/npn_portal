<?php

require_once('abstract_badge.php');


class IntensityBadge extends AbstractBadge{
    
    private static $OBS_COUNT_THRESHOLD = 100;
    private static $PERCENT_ABUNDANCE_THRESHOLD = .5;
    
    public function __construct($person_id){
        $this->person_id = $person_id;
    }
    
    public function validate(){
        
        $badgeModel = ClassRegistry::init('Badge');

        $res = $badgeModel->query("
            SELECT * FROM
                (
                SELECT 
                COUNT( CASE WHEN ((Abundance_Category_Value IS NOT NULL OR Raw_Abundance_Value IS NOT NULL) AND Observation_Extent=1 ) THEN Observation_ID ELSE null END) ca,
                COUNT( CASE WHEN Observation_Extent = 1 THEN o.Observation_ID ELSE NULL END) c,
                o.Observer_ID
                FROM usanpn2.Observation o
                WHERE Observer_ID = " . $this->person_id . "
                AND (o.Deleted IS NULL OR o.Deleted <> 1)
                GROUP BY o.Observer_ID
                ) tbl
                HAVING (ca / c) >= " . IntensityBadge::$PERCENT_ABUNDANCE_THRESHOLD . "
                AND c >= " . IntensityBadge::$OBS_COUNT_THRESHOLD
                );

        return count($res) > 0;

    }
    
}
?>
