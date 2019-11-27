<?php

/**
 * This component is shared by the controllers used for inputting data.
 *
 * The purpose of this function is to take the response array from a CakePHP save
 * operation and parse it out into a simple 1-D array from whatever format is returned
 * from Cake. Eventually the array generated here is returned via the service.
 */
class ParseErrorsComponent extends Object{
        
        
    public function parseErrors($messages){
        $msgs = array();

        try{
            if(!is_array($messages)) throw new Exception;
            foreach($messages as $models){
                if(!is_array($models)) throw new Exception;
                foreach($models as $index){
                    if(!is_array($index)) throw new Exception;
                    foreach($index as $message){
                        $msgs[] = $message;
                    }
                }
            }
        }catch(Exception $ex){
            return array_values($messages);
        }

        return $msgs;

    }
        
        
}


