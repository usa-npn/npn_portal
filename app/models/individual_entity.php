<?php

class IndividualEntity{
    
    var $useTable = false;
    
    
    var $individual_id;
    
    var $num_series;
    
    var $first_yes;
    var $first_julian;
    var $days_last_no;
    
    var $last_yes;
    var $last_julian;
    var $days_next_no;
    
    var $gdd;
    var $gddf;
    var $accum_prcp;
    var $daylength;
    
    var $mult_observers;
    var $conflict_flag;
    var $multiple_first_yes;
    
    
    var $num_ys;
    
    public function __construct($individual_id){
        
        $this->individual_id = $individual_id;
        $this->num_series = 1;
        
        
        $this->first_yes = null;
        $this->first_julian = null;
        $this->days_last_no = null;
        
        $this->last_yes = null;
        $this->last_julian = null;
        $this->days_next_no = null;
        
        $this->num_ys = 0;
        
        $this->mult_observers = false;
        
        $this->multiple_first_yes = false;
        $this->conflict_flag = null;
    }
    
    public function setConflict($conflict_value){
        if($this->conflict_flag == null || $this->conflict_flag != "MultiObserver-StatusConflict"){
            $this->conflict_flag = $conflict_value;
        }
    }
}