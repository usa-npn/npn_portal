<?php
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
require_once('generic_emitter.php');

class NdJsonEmitterComponent extends GenericEmitter {

    private $out;
    private $controller;
    private $first;
    private $noHtmlEncoding;

    public function __construct($out, &$controller, $noHtmlEncoding = false){
        $this->out = $out;
        $this->controller = $controller;
        $this->first = true;
        $this->noHtmlEncoding = $noHtmlEncoding;
    }

    public function emitHeader(){
        $this->controller->header('Content-Type: application/json');
        //$this->out->writeRaw("[");
    }

    public function emitNode($arr){
        if(!$this->first){
            $this->out->writeRaw("\n");
        }else{
            $this->first = false;
        }
        $this->out->writeRaw("{");
        $i=0;
        foreach($arr as $key => $value){
            if($key == "node_name") continue;
            if(!$this->noHtmlEncoding)
                $value = htmlspecialchars($value);
            else
                $value = str_replace('"', "'", $value);
            if($i!=0) $this->out->writeRaw(",");
            $this->out->writeRaw("\"" . strtolower($key) . "\":" . $this->encodeVariable($value));
            ++$i;
        }

        $this->out->writeRaw("}");
    }

    public function emitFooter(){
        //$this->out->writeRaw("]");
    }
    
    
    public function emitTopArray($node_name, $arr){
        
        if(!$this->first){
            $this->out->writeRaw(",");
        }else{
            $this->first = false;
        }
        
        $num_elements = count($arr);
        $i=1;
        
        $this->out->writeRaw("{");
        foreach($arr as $key => $value){
            
            if(!is_array($value)){
                $this->out->writeRaw("\"" . $key . "\":" . $this->encodeVariable($value));
            }else{
                
                if(!is_numeric($key)){                
                    $this->out->writeRaw("\"" . trim($key) . "\":" . "[");
                    $this->emitArray($key, $value);   
                    $this->out->writeRaw("]");
                }else{
                    $this->out->writeRaw("{");
                    $this->emitArray($key, $value);
                    $this->out->writeRaw("}");
                }
                
                
            }
            
            
            if($i++ != $num_elements){
                $this->out->writeRaw(",");
            }       
        }
        $this->out->writeRaw("}");
    }
    
    public function emitArray($arr_name, $arr){
        
        $num_elements = count($arr);
        $i=1;
        

        foreach($arr as $key => $value){

            if(!is_array($value)){
                if(!is_numeric($key)){
                    $this->out->writeRaw("\"" . $key . "\":" );
                }
                $this->out->writeRaw($this->encodeVariable($value));       
            }else{
                
                if(!is_numeric($key)){
                    $this->out->writeRaw("\"" . trim($key) . "\":" . "[");
                    $this->emitArray($key, $value);
                    $this->out->writeRaw("]");
                }else{
                    $this->out->writeRaw("{");
                    $this->emitArray($key, $value);
                    $this->out->writeRaw("}");
                }
            }
            
            
            if($i++ != $num_elements){
                $this->out->writeRaw(",");
            }

        }

    }

    private function encodeVariable($variable){

        if(!is_numeric($variable)) {
            if (is_string($variable))
            {
                if(strpos($variable, "\\") !== false) $variable = str_replace("\\", "\\\\", $variable);
                if(strpos($variable, "\r") !== false) $variable = str_replace("\r", "\\r", $variable);
                if(strpos($variable, "\f") !== false) $variable = str_replace("\f", "\\f", $variable);
                if(strpos($variable, "\n") !== false) $variable = str_replace("\n", "\\n", $variable);
                if(strpos($variable, "\t") !== false) $variable = str_replace("\t", "\\t", $variable);
                if(strpos($variable, "\b") !== false) $variable = str_replace("\b", "\\b", $variable);
                if(strpos($variable, "\/") !== false) $variable = str_replace("\/", "\\/", $variable);
            }
            return "\"" . $variable . "\"";
        } else {
            return $variable;
        }
    }
   
    
    
}
