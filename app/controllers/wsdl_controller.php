<?php
/**
 * The sole purpose of this controller is to server up render the WSDL file.
 * Provides the WSDL URL and nothing more. In theory other controllers should
 * be able to render wsdl as well, but no success when testing this. Other controllers
 * also not helpful to consumers - confusing to see wsdl URL with controller specific
 * URL.
 */
class WSDLController extends AppController{
    
    
    public $uses = array();
    
    public $components = array(
    	'Soap' => array(
        	'wsdl' => 'NPN', //the file name in the view folder
            	'action' => 'service', //soap service method / handler
        )
    );

    public function soap_wsdl(){
    	//will be handled by SoapComponent
    }
	
	
	
    public function soap_service(){
        //no code here
    }
    
} 
