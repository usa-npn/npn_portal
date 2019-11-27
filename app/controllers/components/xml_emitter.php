<?php
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
require_once('generic_emitter.php');

class XmlEmitterComponent extends GenericEmitter {

    private $out;
    private $enclosing_tag;
    private $controller;

    public function __construct($out, $enclose, &$controller){
        $this->out = $out;
        $this->enclosing_tag = $enclose;
        $this->controller = $controller;
    }

    public function emitHeader(){
        $this->controller->header('Content-Type: text/xml');
        $this->out->writeRaw("<?xml version=\"1.0\" encoding=\"UTF-8\" ?><" . $this->enclosing_tag . ">");
    }

    public function emitNode($arr){
        $this->out->writeRaw("<");
        $this->out->writeRaw($arr["node_name"] . " ");
        foreach($arr as $key => $value){
            if($key == "node_name") continue;
            $value = htmlspecialchars($value);
            $this->out->writeRaw(strtolower($key) . "=\"" . $value . "\" ");
        }

        $this->out->writeRaw(" />
        ");
    }

    public function emitFooter(){
        $this->out->writeRaw("</" . $this->enclosing_tag . ">");
    }
    
    
    public function emitTopArray($node_name, $arr){
        
        $this->out->writeRaw("<" . $node_name . ">");
        
        foreach($arr as $key => $value){
            
            if(!is_array($value)){
                $this->out->writeRaw("<" . $key . ">");
                $this->out->writeRaw($value);
                $this->out->writeRaw("</" . $key . ">");            
            }else{
                $this->emitArray($key, $value);
                
            }
        }
        
        $this->out->writeRaw("</" . $node_name . ">");
        
    }
    
    public function emitArray($arr_name, $arr){

        
        foreach($arr as $key => $value){
            if(!is_numeric($arr_name)){
                $this->out->writeRaw("<" . $arr_name . ">");
            }
            
            if(!is_array($value)){
                $this->out->writeRaw("<" . $key . ">");
                $this->out->writeRaw($value);
                $this->out->writeRaw("</" . $key . ">");            
            }else{
                $this->emitArray($key, $value);
            }
            
            if(!is_numeric($arr_name)){
                $this->out->writeRaw("</" . $arr_name . ">");
            }
        }
    }    
    
    
}
