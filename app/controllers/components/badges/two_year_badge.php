<?php

require_once('yearly_badge.php');



class TwoYearBadge extends YearlyBadge{
    
    private static $years = 2;

    public function __construct($person_id){
        parent::__construct($person_id, TwoYearBadge::$years);
    }

}
?>
