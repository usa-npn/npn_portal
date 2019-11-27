<?php

require_once('num_obs_badge.php');



class ThousandObsBadge extends NumObsBadge{
    
    private static $num_obs = 1000;

    public function __construct($person_id){
        parent::__construct($person_id, ThousandObsBadge::$num_obs);
    }

}
?>
