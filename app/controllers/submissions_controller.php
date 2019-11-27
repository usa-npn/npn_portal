<?php

class Sub{}
class Response{}

/**
 * Controller for getting information about NPN networks.
 */
class SubmissionsController extends AppController{


    public $uses = array('Submission', 'Person');

    public $components = array(
    	'Soap' => array(
        	'wsdl' => 'NPN', //the file name in the view folder
            	'action' => 'service', //soap service method / handler
        ),
        'RequestHandler',
        'ValidateUser'
    );

    public function soap_wsdl(){
    	//will be handled by SoapComponent
    }



    public function soap_service(){
    //no code here
    }

    public function getLastSubmissionForPerson($data=null){

        if(!$this->isProtected()){
            return $this->createResponse(null, array("This function can only be accessed using HTTPS."), 0);
        }

        $sub_date = new Sub();

        $userID = ($this->checkProperty($data, "user_id")) ? $data->user_id : null;
        $userPW = ($this->checkProperty($data, "user_pw")) ? $data->user_pw : null;
        $access_token = ($this->checkProperty($data, "access_token")) ? $data->access_token : null;
        $consumer_key = ($this->checkProperty($data, "consumer_key")) ? $data->consumer_key : null;


        $person_id = $this->ValidateUser->verifyUser($userID, $userPW, $this->Person, $access_token, $consumer_key);
        if(!$person_id){
            return $this->createResponse(null, array("User Credentials not valid"), 0);
        }



        $sub_results = $this->Submission->submission2Observation->find('first', array(
           'conditions' => array(
               'Observer_ID' => $person_id,
               '(submission2Observation.Deleted IS NULL OR submission2Observation.Deleted <>1)'
           ),
           'fields' => array(
               'observation2submission.Submission_DateTime'
           ),
           'order' => 'observation2submission.Submission_DateTime DESC'
        ));

        $sub_date->date = $sub_results['observation2submission']['Submission_DateTime'];

        $response = $this->createResponse(
                $sub_date->date,
                array("Submission Date successfully received"),
                1
        );

        //$this->set('submission_date', $sub_date);

        return $response;

    }


        private function createResponse($date, $msgs, $code){

            $response = new Response();

            $response->date = $date;
            $response->response_messages = $msgs;
            $response->response_code = $code;

            $this->set('response', $response);
            return $response;
        }




}

