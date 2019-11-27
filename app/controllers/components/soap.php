<?php 
App::import('core', 'AppHelper');

    /**
    * Soap component for handling soap requests in Cake
    *
    * @author      Marcel Raaijmakers (Marcelius)
    * @copyright   Copyright 2009, Marcel Raaijmakers
    * @license     http://www.opensource.org/licenses/mit-license.php The MIT License
     *
     *
     * This component in the core of the SOAP service, as it handles all soap
     * requests made. This will intercept all requests made to controllers
     * and then route them to the correct functions, but making the response
     * soap compatible.
     *
     * This was taken from the original author, and modified to
     * be a bit more flexible.
    */
class SoapComponent extends Component{

        var $name = 'Soap';

        var $components = array('RequestHandler');

        var $controller;

        var $__settings = array(
            'wsdl' => false,
            'wsdlAction' => 'wsdl',
            'prefix' => 'soap',
            'action' => array('service'),
        );


        public function initialize($controller, $settings = array()){
            if (Configure::read('debug') != 0){
                ini_set('soap.wsdl_cache_enabled', false);
            }

            //Reference back to the parent controller.
            $this->controller = $controller;

            if (isset($settings['wsdl']) && !empty($settings['wsdl'])){
                $this->__settings['wsdl'] = $settings['wsdl'];
            }

            if (isset($settings['prefix'])){
                $this->__settings['prefix'] = $settings['prefix'];
            }

            if (isset($settings['action'])){
                $this->__settings['action'] = is_array($settings['action']) ? $settings['action'] : array($settings['action']);
            }

            parent::initialize($controller);
        }


        public function startup(){
            if (isset($this->controller->params['soap'])){
                if ($this->__settings['wsdl'] != false){
                    //render the wsdl file
                    if ($this->action() == $this->__settings['wsdlAction']){
                        Configure::write('debug', 0);
                        $this->RequestHandler->respondAs('xml');

                        $this->controller->ext = '.wsdl';
                        $this->controller->render(null, false, DS . 'elements' . DS . $this->__settings['wsdl']); //only works with short open tags set to false!
                    } elseif(in_array($this->action(), $this->__settings['action'])) {
                        //If the action is a valid request then the soap server is initialized,
                        //and the parent controller is set as the object to invoke. The handle()
                        //function then takes take of everything else automagically.
                        $soapServer = new SoapServer($this->wsdlUrl());
                        $soapServer->setObject($this->controller);

                        $soap_action = $this->getSoapAction();

                        /**
                         * The only exception to this is if the function is a large request. In that
                         * case we manually create the soap envelope and invoke the function on the
                         * controller. That function will have to be ready to take the $out object
                         * and be prepared to manually write/generate the payload of the SOAP message.
                         *
                         * The difference here is that the XMLWriter object will not store the entire SOAP
                         * message in memory before sending it to the client, as it does in the soapServer object.
                         * If the response is very large, it can overload the server in the normal case, but
                         * XMLWriter avoids this problem.
                         */
                        if($this->hasLargeRequest($soap_action)){
                            $out =new XMLWriter();
                            $this->autoRender = false;
                            $out->openURI('php://output');
                            $obj = $this->constructSoapObject($soap_action);
                            call_user_func_array(array($this->controller, $soap_action), array($obj,$out, "soap"));
                        }else{
                            $soapServer->handle();
                        }

                        //stop script execution
                        $this->_stop();
                        return false;

                    }
                }
            }
        }

        /**
         * Return the current action
         *
         * @return string
         */
        public function action(){
            return (!empty($this->__settings['prefix'])) ? str_replace( $this->__settings['prefix'] . '_', '',  $this->controller->action) : $this->controller->action;
        }

        /**
         * Return the url to the wsdl file
         *
         * @return string
         */
        public function wsdlUrl(){
            return AppHelper::url(array('controller'=>Inflector::underscore($this->controller->name), 'action'=>$this->__settings['wsdlAction'], $this->__settings['prefix'] => true), true);
        }


        /**
         *
         * Uses exclusively for the large SOAP functions, this will look directly
         * at the request to see what SOAP action is being requested. Have to look
         * at different headers depending on if it's SOAP 1.1 or SOAP 1.2
         */
        private function getSoapAction(){
            if(array_key_exists("HTTP_SOAPACTION", $_SERVER)){
                $key = "HTTP_SOAPACTION";
            }else if(array_key_exists("CONTENT_TYPE", $_SERVER)){
                $key = "CONTENT_TYPE";
            }else{
                return "";
            }

            $name = "";
            
            try{
                preg_match('/:[A-Za-z]+\"/', $_SERVER[$key], $name);
                $name = str_replace(array(":", '"'), "", $name[0]);
            }catch(Exception $ex){
                return "";
            }
            
            return $name;


        }


        private function hasLargeRequest($function_name){
            return (in_array($function_name, Configure::read('LARGE_FUNCTIONS')));
        }


        /**
         *
         * The purpose of this function is to read the SOAP request and extract
         * any parameters that were passed in, and parse them into a stdClass object.
         * This is used exclusively for the 'large soap functions', which require
         * manually construction of the SOAP envelope.
         *
         * This is necessary because the controller classes are still expecting a single
         * stdClass object as a parameter, if it has any, and couldn't otherwise handle
         * being passed a string of XML containing the input parameters.
         */
        private function constructSoapObject($function_name){

            $in = new XMLReader();
            $soap_header = file_get_contents('php://input');
            $soap_header = str_replace("ns1", "ns", $soap_header);
            
            $soap_substring = substr($soap_header, 0, 50);         
            if(strpos($soap_substring, "xml version") === false){
                $soap_header = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>" . $soap_header;
            }
            
            $in->XML($soap_header);

            $obj = new stdClass();
            $current_var_name = "";
            while ($in->read()) {

                if(strstr($in->name, "ns:") && !strstr($in->name, "SOAP-ENV") && !strstr($in->name, $function_name)){
                    $current_var_name = str_replace("ns:", "", $in->name);

                    $in->read();
                    if(!property_exists($obj, $current_var_name)){
                        $obj->$current_var_name = $in->value;
                    }else if(property_exists($obj, $current_var_name) && is_array($obj->$current_var_name)){
                        $arr = $obj->$current_var_name;
                        $arr[] = $in->value;
                        $obj->$current_var_name = $arr;
                    }else{
                        $arr = array($obj->$current_var_name, $in->value);
                        $obj->$current_var_name = $arr;
                    }
                    
                    $in->read();
                }else if(strstr($in->name, $function_name) && $in->nodeType != XMLReader::END_ELEMENT){

                    $c = $in->attributeCount;
                    for($i=0; $i < $c; $i++){
                        $in->moveToNextAttribute();
                        $var_name = str_replace("ns:", "", $in->name);
                        $obj->$var_name = $in->value;
                    }
                }
            }
            
            $in->close();
            return $obj;
        }

}