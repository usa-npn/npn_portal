<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */


class AppController extends Controller {

    public $components = array(

        'ArrayWrap',
        'CheckProperty'
    );    


    protected function isProtected(){

        $ssl = false;

        if(Configure::read('protected') == 0){
            $ssl = true;
        }
        else if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on'){
            $ssl = true;
        }

        return $ssl;
    }


    protected function checkProperty($params, $prop_name, $allows_literal_zero=false){
        if(!property_exists($this, "CheckProperty")){
            $this->CheckProperty = new CheckPropertyComponent();
        }
        return $this->CheckProperty->checkProperty($params, $prop_name, $allows_literal_zero);
    }
    
    protected function arrayWrap($var){
        
        if(!property_exists($this, "ArrayWrap")){
            $this->ArrayWrap = new ArrayWrapComponent();
        }
                
        return $this->ArrayWrap->arrayWrap($var);
    }
    
    protected function resolveBooleanText($params, $var_name, $default_value=false){
        
        $b = $default_value;
        
        if($this->checkProperty($params, $var_name)){
            
            $b_value = $params->$var_name;
            if(is_bool($b_value) && $b_value === true){
                $b = true;
            }else if(is_bool($b_value) && $b_value === false){
                $b = false;
            }else if(is_int($b_value) && $b_value === 1){
                $b = true;
            }else if(is_int($b_value) && $b_value === 0){
                $b = false;
            }else if(is_string($b_value) && (($b_value === "true") || $b_value === "1")){
                $b = true;
            }else if(is_string($b_value) && (($b_value !== "true") || $b_value !== "1")){
                $b = false;
            }
        }else{
            $b = $default_value;
        }
        
        return $b;

    }

    protected function getEmitter($format, $out, $xml_node_name, $soap_wrapper, $noHtmlEncoding = false){

        $emitter = null;
        switch($format){
            case 'xml':
            case 'XML':
                App::import('Component','XmlEmitter');
                $emitter = new XmlEmitterComponent($out, $xml_node_name, $this);
                break;
            case 'soap':
            case 'SOAP':
                App::import('Component', 'SoapEmitter');
                $emitter = new SoapEmitterComponent($out, $soap_wrapper, $this);
                break;
            case 'json':
            case 'JSON':
                App::import('Component', 'JsonEmitter');
                $emitter = new JsonEmitterComponent($out, $this, $noHtmlEncoding);
                break;
            case 'csv':
            case 'CSV':
                App::import('Component', 'CsvEmitter');
                $emitter = new CsvEmitterComponent($out, $this);
                break;
            default:
                App::import('Component', 'JsonEmitter');
                $emitter = new JsonEmitterComponent($out,  $this, $noHtmlEncoding);
        }

        return $emitter;


    }
    
    
    public function beforeRender(){

        parent::beforeRender();
     
        if(isset($this->viewVars)){
            $this->viewVars = $this->iterateViewData($this->viewVars);
        }
        
    }
    
    private function iterateViewData(&$array){

        foreach($array as $k => $var){
            
            if(is_array($var)){
                if(is_object($array)){
                    $array->$k = $this->iterateViewData($var);
                }else if(is_array($array)){
                    $array[$k] = $this->iterateViewData($var);
                }                
            }
            
            else if(is_numeric($var)){
                $new_val = (is_int($var)) ? intval($var) : floatval($var);
                if(is_object($array)){
                    $array->$k = $new_val;
                }else if(is_array($array)){
                    $array[$k] = $new_val;
                }
            }
            
            else if(is_object($var)){
                $var = $this->iterateViewData($var);
            }
        }
        

        return $array;
        
    }    

    


}