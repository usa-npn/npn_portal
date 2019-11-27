<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

require_once('generic_emitter.php');

class SoapEmitterComponent extends GenericEmitter {

    private $out;
    private $response_tag;
    private $controller;

    public function __construct($out, $response_tag, &$controller){
        $this->out = $out;
        $this->response_tag = $response_tag;
        $this->controller = $controller;

    }


    public function emitHeader(){
        $this->controller->header('Content-Type: text/xml');
        $this->out->writeRaw('<?xml version="1.0" encoding="UTF-8"?>');
        $this->out->writeRaw('<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://usanpn.service.org/types"><SOAP-ENV:Body>');
        
        $this->out->writeRaw("<ns1:" . $this->response_tag . ">");
    }

    public function emitNode($arr){
        $this->out->writeRaw("<");
        $this->out->writeRaw("ns1:" . $arr["node_name"] . " ");
        foreach($arr as $key => $value){
            if($key == "node_name") continue;
            $value = htmlspecialchars($value);
            $this->out->writeRaw("ns1:" . strtolower($key) . "=\"" . $value . "\" ");
        }

        $this->out->writeRaw(" />");
    }

    public function emitFooter(){
        $this->out->writeRaw("</ns1:" . $this->response_tag . ">");
        $this->out->writeRaw('</SOAP-ENV:Body></SOAP-ENV:Envelope>');

    }
    
    public function emitTopArray($node_name, $arr){
        
        $this->out->writeRaw("<ns1:" . $node_name . ">");
        
        foreach($arr as $key => $value){
            
            if(!is_array($value)){
                $this->out->writeRaw("<ns1:" . $key . ">");
                $this->out->writeRaw($value);
                $this->out->writeRaw("</ns1:" . $key . ">");            
            }else{
                $this->emitArray($key, $value);
                
            }
        }
        
        $this->out->writeRaw("</ns1:" . $node_name . ">");
        
    }
    
    public function emitArray($arr_name, $arr){

        
        foreach($arr as $key => $value){
            if(!is_numeric($arr_name)){
                $this->out->writeRaw("<ns1:" . $arr_name . ">");
            }
            
            if(!is_array($value)){
                $this->out->writeRaw("<ns1:" . $key . ">");
                $this->out->writeRaw($value);
                $this->out->writeRaw("</ns1:" . $key . ">");            
            }else{
                $this->emitArray($key, $value);
            }
            
            if(!is_numeric($arr_name)){
                $this->out->writeRaw("</ns1:" . $arr_name . ">");
            }
        }
        

        
    }
    

}

