<?php

require_once('yearly_badge.php');



class OneYearBadge extends YearlyBadge{
    
    private static $years = 1;

    public function __construct($person_id){
        parent::__construct($person_id, OneYearBadge::$years);
    }

}
?>
