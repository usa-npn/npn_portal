<?php
class Response{}

/**
 * Controller for handling funtions relating to creating or transmitting user
 * data.
 */
class CreateUserController extends AppController{
    
    

   
        public $uses = array('Network', 'Person', 'NetworkPerson', 'OauthCommonConsumer');
    
        public $components = array(
            'Soap' => array(
                    'wsdl' => 'NPN', //the file name in the view folder
                    'action' => 'service', //soap service method / handler
            ),
            "ParseErrors",
            "RequestHandler"
        );

        public function soap_wsdl(){
            //will be handled by SoapComponent
        }
	
	
	
	public function soap_service(){
        //no code here
        }


	public function createUser($user_data=null){

            if(!$this->isProtected()){
                return $this->createResponse(
                            null,
                            null,
                            array("This function can only be accessed using HTTPS."),
                            0
                    );
            }
            
            $firstName = ($this->checkProperty($user_data, "f_name")) ? $user_data->f_name : null;
            if(!$firstName){
                return $this->createResponse(null, null, array("f_name is a required field"), 0);
            }
            
            $lastName = ($this->checkProperty($user_data, "l_name")) ? $user_data->l_name : null;
            if(!$lastName){
                return $this->createResponse(null, null, array("l_name is a required field"), 0);
            }            
            
            $email = ($this->checkProperty($user_data, "email")) ? $user_data->email : null;
            if(!$email){
                return $this->createResponse(null, null, array("email is a required field"), 0);
            }
            
            $consumerKey = ($this->checkProperty($user_data, "consumer_key")) ? $user_data->consumer_key : null;
            if(!$consumerKey){
                return $this->createResponse(null, null, array("consumer_key is a required field"), 0);
            }            

            if(!$this->verifyConsumer($consumerKey)){
                return $this->createResponse(
                            null,
                            null,
                            array("Consumer Key is not valid."),
                            0
                    );
            }

            $this->Person->create();
            $data =
                    array(
                            "Person" =>
                            array(
                                    "First_Name" => $firstName,
                                    "Last_Name" => $lastName,
                                    "Create_Date" => date("Y-m-d"),
                                    "Load_Key" => uniqid (($consumerKey . "_")),
                                    "email" => $email,
                                    "UserName" => $email,
                                    "Passwd_Hash" => uniqid(null, true)
                            )
                    );

            if($this->Person->saveAll($data)){
                    /**
                     * Removing this for the time being, because no good justification can come from having to have a corresponding account on the
                     * Drupal site, and creating a drupal account this way, may violate our terms of service.
                     */
                    //$this->Person->query("call Create_Drupal_User_From_User('" . $email . "', '" . $firstName . "', '" . $lastName . "')");
                    $pw = $this->Person->field("Passwd_Hash", array("Person_ID =" => $this->Person->id));
                    $response = $this->createResponse(
                            $this->Person->id,
                            $pw,
                            array("User successfully created"),
                            1
                    );
            }else{
                    $response = $this->createResponse(
                            null,
                            null,
                            $this->ParseErrors->parseErrors($this->Person->invalidFields()),
                            0
                    );	
            }




            return $response;
	}



        private function verifyConsumer($consumerKey){
            $results = $this->OauthCommonConsumer->find('first', array(
                'conditions' => array(
                    'consumer_key' => $consumerKey
                )
            ));

            return isset($results["OauthCommonConsumer"]);


        }
        
        
        private function createResponse($user_id, $user_pw, $msgs, $code){
            $response = new Response();

            $response->user_id = $user_id;
            $response->user_pw = $user_pw;
            $response->response_messages = $msgs;
            $response->response_code = $code;
            
            $this->set('response', $response);
            return $response;
        }        
        
}
