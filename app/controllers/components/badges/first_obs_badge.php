<?php

require_once('num_obs_badge.php');



class FirstObsBadge extends NumObsBadge{
    
    private static $num_obs = 1;

    public function __construct($person_id){
        parent::__construct($person_id, FirstObsBadge::$num_obs);
    }

}
?>
