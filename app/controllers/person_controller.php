<?php

class Pers{}
class Update{}

/**
 * Controller for returning data about people
 */
class PersonController extends AppController{


    public $uses = array('Person', 'Network');

    public $components = array(
    	'Soap' => array(
        	'wsdl' => 'NPN', //the file name in the view folder
            	'action' => 'service', //soap service method / handler
        ),
        'RequestHandler',
        'ValidateUser',
        'Gnupg'
    );

    public function soap_wsdl(){
    	//will be handled by SoapComponent
    }



    public function soap_service(){
    //no code here
    }


    /**
     * Only real function. This takes a drupal id found in drupal database
     * and returns the associated person id found in the NN database.
     *
     * Useful for drupal apps needing to interface with NN database/web service.
     * Particularly for viz tool which gets user-centric data, as passed in via
     * drupal and needs the corresponding person id to make queries to the service.
     */
    public function getPersonIDFromDrupalID($params=null){

        $person = new Pers();

        if(!$this->checkProperty($params, "drupal_id")){
            $this->set('person', new stdClass() );
            return null;
        }

        /**
         * Remove wasteful bindings.
         */
        $this->Person->
            unbindModel(
                array('hasMany' =>
                    array('person2Observation', 'person2Station')
        ));
        
        $results = $this->Person->find('first', array(
            'fields' => array(
                            "Person_ID"
            ),
            'conditions' => array(
                            "Load_Key LIKE " => "Drupal_" . $params->drupal_id
            )
        ));


        if($results){
            $person->person_id = $results['Person']['Person_ID'];
        }else{
            $person->person_id = null;
        }

        $this->set('person', $person);
        return $person;

    }


    public function getObserverDetails($params,$out, $format){
        App::import('Model','VwObserverDetails');
        $this->VwObserverDetails =  new VwObserverDetails();

        $emitter = $this->getEmitter($format, $out, "observers", "getObserverDetailsResponse");
        $conditions = array();

        if($this->checkProperty($params, "person_id")){
            $params->person_id = $this->arrayWrap($params->person_id);
            $conditions["VwObserverDetails.Person_ID "] = $params->person_id;
        }
        
        if($this->checkProperty($params, "ids")){
            $ids = urldecode($params->ids);
            $ids = explode(",", $ids);
            
            $conditions["VwObserverDetails.Person_ID "] = $ids;
        }          

        $results = null;

        $objs = array();
        $emitter->emitHeader();

        $dbo = $this->VwObserverDetails->getDataSource();
        $query = $dbo->buildStatement(array(
            "fields" => array('*'),
            "table" => $dbo->fullTableName($this->VwObserverDetails),
            "alias" => 'VwObserverDetails',
            "conditions" => $conditions,
            "group" => null,
            "limit" => null,
            "order" => null
            ),
        $this->VwObserverDetails
        );

        $results = mysql_unbuffered_query($query);

        while($result = mysql_fetch_array($results, MYSQL_ASSOC)){
         
            $result['node_name'] = "observer";            
            $result['Ecological_Experience'] = @unserialize($result['Ecological_Experience']);
            
            if(!empty($result['Ecological_Experience'])){
                $result['Ecological_Experience'] = implode(",", $result['Ecological_Experience']);
            }
          

            $emitter->emitNode($result);
            $out->flush();
        }


        $emitter->emitFooter();

    }
    
    public function getUserUpdate($params=null){
        
        $res = new Update();        
        
        if(!$this->isProtected()){
            $res->timestamp = -1;
            $this->set('update', $res);
            return -1;            
        }        
        
        $userID = ($this->checkProperty($params, "user_id")) ? $params->user_id : null;
        $userPW = ($this->checkProperty($params, "user_pw")) ? $params->user_pw : null;
        $access_token = ($this->checkProperty($params, "access_token")) ? $params->access_token : null;
        $consumer_key = ($this->checkProperty($params, "consumer_key")) ? $params->consumer_key : null;
        
        $person_id = $this->ValidateUser->verifyUser($userID, $userPW, $this->Person, $access_token, $consumer_key);
        
        
        
        if(!$person_id){
            $res->timestamp = -1;
            $this->set('update', $res);
            return -1;
        }
        
        $person = $this->Person->find('first', array(
           'conditions' => array(
               'Person_ID' => $person_id
           ) 
        ));
                
        $res->timestamp = $person['Person']['Last_Update'];
        $this->set('update', $res);
        
        return $res;
        
    }
    
    public function getGroupMembers($params=null){

        $this->Gnupg->setFingerPrint("SciStarter");

                
        $this->Person->
            unbindModel(
                array('hasMany' =>
                    array('person2Observation', 'person2Station')
        ));
        
       $this->Network->
            unbindModel(
                array('hasMany' =>
                    array('network2networkperson', 'network2networkstation')
        ));
        

        $net = $this->Network->find('all', array(
           'conditions' => array(
               'Network.Name' => 'SciStarter'
           ),
           'fields' => array(
               'Network_ID'
           )
        ));
        
        
        $conditions = array('network_person.Network_ID' => $net[0]['Network']['Network_ID']);
        
        $joins = array(
            array(
                "table" => "Network_Person",
                "alias" => "network_person",
                "type" => "LEFT",
                "conditions" => "network_person.Person_ID = Person.Person_ID"
            )
        );


        $people = array();
        if(!empty($net)){
            $pps = $this->Person->find('all', array(
                'conditions' => $conditions,
                'joins' => $joins
                )
            );

            foreach($pps
                    as $pp){         
                $obj = new Pers();
                $obj->person_id = $pp["Person"]["Person_ID"];
                $obj->email = $pp["Person"]["email"];


                $people[] = $obj;
            }


            if($this->RequestHandler->responseType() == "xml"){
                $people = $this->xml->serialize($people);
            }else{
                $people = json_encode($people);
            }

            $enc_data = $this->Gnupg->encryptData($people);
        }
        $this->set('people', $enc_data);
        return $enc_data;
        
    }



}
