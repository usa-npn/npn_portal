<?php

require_once('abstract_badge.php');



class YearlyBadge extends AbstractBadge{
        
    private static $DAYS_IN_YEAR = 365;
    
    protected $num_years;
    
    public function __construct($person_id, $num_years){
        $this->person_id = $person_id;
        $this->num_years = $num_years;
    }
    
    public function validate(){
        
        $badgeModel = ClassRegistry::init('Badge');
                
        $res = $badgeModel->query("SELECT DATEDIFF(newest.Submission_DateTime, oldest.Submission_DateTime) >= " . 
                (YearlyBadge::$DAYS_IN_YEAR * $this->num_years) . " valid
                                    FROM
                                    (
                                    SELECT Submission_DateTime
                                    FROM usanpn2.Person p
                                    LEFT JOIN usanpn2.Observation o
                                    ON o.Observer_ID = p.Person_ID
                                    LEFT JOIN usanpn2.Submission s
                                    ON s.Submission_ID = o.Submission_ID
                                    WHERE p.Person_ID = " . $this->person_id . " " .
                                    "AND (o.Deleted IS NULL OR o.Deleted <> 1) 
                                    ORDER BY Submission_DateTime DESC
                                    LIMIT 1) newest
                                    JOIN 
                                    (
                                    SELECT Submission_DateTime
                                    FROM usanpn2.Person p
                                    LEFT JOIN usanpn2.Observation o
                                    ON o.Observer_ID = p.Person_ID
                                    LEFT JOIN usanpn2.Submission s
                                    ON s.Submission_ID = o.Submission_ID
                                    WHERE p.Person_ID = " . $this->person_id . " " .
                                    "AND (o.Deleted IS NULL OR o.Deleted <> 1) 
                                    ORDER BY Submission_DateTime ASC
                                    LIMIT 1) oldest"
                );
        

        return $res[0][0]['valid'];

    }
    
}
?>
