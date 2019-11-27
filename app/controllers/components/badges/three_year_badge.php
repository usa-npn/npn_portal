<?php

require_once('yearly_badge.php');



class ThreeYearBadge extends YearlyBadge{
    
    private static $years = 3;

    public function __construct($person_id){
        parent::__construct($person_id, ThreeYearBadge::$years);
    }

}
?>
