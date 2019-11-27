<?php

require_once('abstract_badge.php');


class TwelveWeekBadge extends AbstractBadge{
    
    private static $NUM_WEEKS = 12;
    
    public function __construct($person_id){
        $this->person_id = $person_id;
    }
    
    public function validate(){
        
        $badgeModel = ClassRegistry::init('Badge');
                
        $res = $badgeModel->query("
                                SELECT * FROM (
                                SELECT 

                                IF(Individual_ID <> @i, @x:=1 AND @y:=1, Individual_ID) Prev_Indiv_ID, 
                                IF(week = @x+1, @y := @y+1, @y := 1) AS Consec_Weeks, 
                                @x := week AS Week , 
                                @i := Individual_ID Curr_Indiv_ID,
                                year
                                FROM
                                (
                                    SELECT DISTINCT o.Individual_ID, WEEKOFYEAR(o.Observation_Date) week, YEAR(o.Observation_Date) year
                                    FROM usanpn2.Person p
                                    LEFT JOIN usanpn2.Station st
                                    ON st.Observer_ID = p.Person_ID
                                    LEFT JOIN usanpn2.Station_Species_Individual ssi
                                    ON ssi.Station_ID = st.Station_ID
                                    LEFT JOIN usanpn2.Observation o
                                    ON o.Individual_ID = ssi.Individual_ID
                                    WHERE p.Person_ID = " . $this->person_id . "
                                    AND (o.Deleted IS NULL OR o.Deleted <> 1)
                                    GROUP BY o.Observation_Date, o.Individual_ID
                                    ORDER BY ssi.Individual_ID,Observation_Date, week
                                ) tbl
                                ) tbl
                                HAVING Consec_Weeks = " . TwelveWeekBadge::$NUM_WEEKS                                    
                );

        return count($res) > 0;

    }
    
}
?>
