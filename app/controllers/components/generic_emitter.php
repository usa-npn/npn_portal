<?php



abstract class GenericEmitter extends Object{


    abstract public function emitHeader();

    abstract public function emitNode($arr);

    abstract public function emitFooter();
    
    abstract public function emitTopArray($node_name, $arr);
    
    abstract public function emitArray($arr_name, $arr);
}