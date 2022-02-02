<?php


abstract class GenericEmitter extends Object{
    
    protected $explicit_string_fields = array("Plant_Nickname");

    abstract public function emitHeader();

    abstract public function emitNode($arr);

    abstract public function emitFooter();
    
    abstract public function emitTopArray($node_name, $arr);
    
    abstract public function emitArray($arr_name, $arr);
    
    
    public function isExplicitString($field_name){
        return in_array($field_name, $this->explicit_string_fields);
    }
}