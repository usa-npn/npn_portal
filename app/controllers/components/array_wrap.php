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
class ArrayWrapComponent extends Object{
    
    
    public function arrayWrap($var){
        
        $ret_val = array();
        
        if(is_array($var)){
            $ret_val = $var;            
        }else{
            $ret_val[] = $var;
        }

        return $ret_val;
        
    }
    
}
