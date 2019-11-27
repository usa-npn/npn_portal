<?php


/**
 * Some requests are passing so much data on to the user that it's not feasable
 * to load it all into memory and set a view variable. This controller acts as
 * a proxy for other controllers for specific requests and provides an XML Writer
 * which can be used to immediately send data on to the client. 
 * The get_rest_params_route class is repsonsible for sending specific requests
 * to this class.
 * 
 */
class LargeController extends AppController{
    
    
    public $uses = array();
    
    public $components = array(
    );


    public function handleLargeRequest($params, $controller_class, $function, $format){
        $controller = Inflector::camelize($controller_class . "_controller");
        App::import('Controller', $controller_class);
        $out =new XMLWriter();
        $this->autoRender = false;
        $out->openURI('php://output');
        $r = new ReflectionClass($controller);
        $objInstance = $r->newInstanceArgs(array());

        call_user_func_array(array($objInstance, $function), array($params, $out, $format));

    }


    


} 

