<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of magnitude_period
 *
 * @author Lee
 */
class MagnitudePeriod {

    var $start_date;
    var $end_date;        
    
    public function __construct($start_date, $end_date){
        $this->start_date = $start_date;
        $this->end_date = $end_date;
    }
}
