<?php
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
require_once('generic_emitter.php');

class CsvEmitterComponent extends GenericEmitter {

    private $out;
    private $controller;
    private $first;

    public function __construct($out, &$controller){
        $this->out = $out;
        $this->controller = $controller;
        $this->first = true;
    }

    public function emitHeader(){
        $this->controller->header('Content-Type: text/csv');
    }

    public function emitNode($arr){
        if(!$this->first){
            $this->out->writeRaw("\n");
        }else{
            $this->first = false;
        }

        $i=0;
        foreach($arr as $key => $value){
            if($key == "node_name") continue;
            $value = htmlspecialchars($value);
            if($i!=0) $this->out->writeRaw(",");
            $this->out->writeRaw($value);
            ++$i;
        }
    }

    public function emitFooter(){}

    public function emitArray($arr_name, $arr) {
        return $this->emitNode($arr);
    }

    public function emitTopArray($node_name, $arr) {
        return $this->emitNode($arr);
    }
}
