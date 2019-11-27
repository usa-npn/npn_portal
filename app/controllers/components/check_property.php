<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of check_property_component
 *
 * @author Lee
 */
class CheckPropertyComponent extends Object{
    
    
    public function checkProperty($params, $prop_name, $allow_literal_zero=false){
      
        $v = false;
        if ($params != null && property_exists($params, $prop_name) ){

            if(!$allow_literal_zero && !empty($params->$prop_name)){
                $v = true;
            }               

            if($allow_literal_zero && ( strval($params->$prop_name) === '0' || !empty($params->$prop_name) ) ){
                $v = true;
            }
     
        }
        
        return $v;
    }
    
}
