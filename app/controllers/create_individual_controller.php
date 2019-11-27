<?php

class Response{}

/**
 * Controller used solely for creating new individuals (plants, animals).
 */
class CreateIndividualController extends AppController{
    
    

    public $uses = array('NetworkPerson','Person', 'Station','Species', 'StationSpeciesIndividual', 'SpeciesProtocol', 'Lookup');
        public $components = array(
            'Soap' => array(
                    'wsdl' => 'NPN', //the file name in the view folder
                    'action' => 'service', //soap service method / handler
            ),
            'ValidateUser',
            'ParseErrors',
            'RequestHandler'
        );

        public function soap_wsdl(){
            //will be handled by SoapComponent
        }
	
	
	
	public function soap_service(){
        //no code here
        }
        
        
        public function createIndividual($individual_data=null){

            if(!$this->isProtected()){
                return $this->createResponse(
                            null,
                            array("This function can only be accessed using HTTPS."),
                            0
                    );
            }


            $userID = ($this->checkProperty($individual_data, "user_id")) ? $individual_data->user_id : null;
            $userPW = ($this->checkProperty($individual_data, "user_pw")) ? $individual_data->user_pw : null;
            $access_token = ($this->checkProperty($individual_data, "access_token")) ? $individual_data->access_token : null;
            $consumer_key = ($this->checkProperty($individual_data, "consumer_key")) ? $individual_data->consumer_key : null;
            
            $stationID = ($this->checkProperty($individual_data, "station_id")) ? $individual_data->station_id : null;
            if(!$stationID){
                return $this->createResponse(null, array("station_id is a required field"), 0);
            }
            
            $speciesNum = null;
            if($this->checkProperty($individual_data, "species_id")){
                $speciesNum = $individual_data->species_id;
            }else{
                $speciesNum = ($this->checkProperty($individual_data, "species_num")) ? $individual_data->species_num : null;
            }
            
            if(!$speciesNum){
                return $this->createResponse(null, array("species_num is a required field"), 0);
            }            
            
            $individualName = ($this->checkProperty($individual_data, "individual_name")) ? $individual_data->individual_name : null;
            if(!$individualName){
                return $this->createResponse(null, array("individual_name is a required field"), 0);
            }

            $is_watered = ($this->checkProperty($individual_data, "is_watered")) ? $individual_data->is_watered : null;
            $is_fertilized = ($this->checkProperty($individual_data, "is_fertilized")) ? $individual_data->is_fertilized : null;
            $is_wild = ($this->checkProperty($individual_data, "is_wild")) ? $individual_data->is_wild : null;
            $shade_status = ($this->checkProperty($individual_data, "shade_status")) ? $individual_data->shade_status : null;

            /**
             * Check user credentials first...
             */
            $person_id = $this->ValidateUser->verifyUser($userID, $userPW, $this->Person, $access_token, $consumer_key);
            if(!$person_id){
                return $this->createResponse(null, array("User Credentials not valid"), 0);
            }
            
            
            /**
             * Check that the station specified belongs to the user specified.
             */
            if(!$this->stationExistsForUser($person_id, $stationID)){
                $network_id = $this->Station->findStationNetwork($stationID);
                $member_has_privilege = false;

                if( isset ($network_id)){

                    $results = $this->NetworkPerson->find('all', array(
                       'conditions' => array(
                           'NetworkPerson.Network_ID' => $network_id,
                           'NetworkPerson.Person_ID' => $person_id
                       )
                    ));

                    $role = $results[0]['netperson2approle'][0]['Role_ID'];
                    $member_has_privilege = (!$role == null && $role < 2);

                }

                if(!$member_has_privilege){
                    return $this->createResponse(null, array("Station ID does not exist for user or user lacks privileges to add to station."), 0);

                }
           }
            

            /**
             * Business rules state that individual names must be unique for each station. Verify this.
             */
            if(!$this->individualNameUniqueForUser($individualName, $stationID)){
                return $this->createResponse(null, array("The individual name is not unique for this user"), 0);
            }


            
            /**
             * Make sure species specified actually exists.
             */
            $species_info = $this->getSpeciesInfo($speciesNum);
            if($species_info[0] == null){
                return $this->createResponse(null, array("Species ID not valid"), 0);
            }

            if(!$this->animalSpeciesUnique($species_info, $stationID)){
                return $this->createResponse(null, array("Only one animal of any given species per station."), 0);
            }

            if( (isset($shade_status) || isset($is_fertilized) || isset($is_watered) || isset($is_wild)) && $species_info[1] != "Plantae"){
                return $this->createResponse(null, array("Can't add plant details to an animal."), 0);
            }
            
            if($shade_status != null && !$this->shadeShatusIsValid($shade_status)){
                return $this->createResponse(null, array("Shade Status is not valid"), 0);
            }
            

            /**
             * Find the greatest seq number for the individuals currently at the station. Set it to 0
             * if this is the first individual, or increment it by one otherwise. This gives us the next
             * number in the sequence.
             */
            $this->StationSpeciesIndividual->order = "StationSpeciesIndividual.Seq_Num DESC";
            $seq_num = $this->StationSpeciesIndividual->find("first", array("conditions" => array("StationSpeciesIndividual.Station_ID" => $stationID )));
            if($seq_num == null){
                $seq_num = 0;
            }else{
                $seq_num = $seq_num["StationSpeciesIndividual"]["Seq_Num"] + 1;
            }

            
            /**
             * Finally, create the individual
             */
            $this->StationSpeciesIndividual->create();
            $data = array(
                            "StationSpeciesIndividual" => array(
                                        "Station_ID" => $stationID,
                                        "Species_ID" => $species_info[0],
                                        "Individual_UserStr" => $individualName,
                                        "Active" => 1,
                                        "Seq_Num" => $seq_num,
                                        "Create_Date" => date("Y-m-d"),
                                        "Watering" => $is_watered,
                                        "Shade_Status" => $shade_status,
                                        "Is_Wild" => $is_wild,
                                        "Supplemental_Feeding" => $is_fertilized
                            )
            );
            
            
            if($this->StationSpeciesIndividual->saveAll($data)){
                $response = $this->createResponse(
                    $this->StationSpeciesIndividual->id,
              	    array("Station Species Individual successfully created"),
      		    1
		);
	    }else{
                $response = $this->createResponse(
                    null,
                    $this->ParseErrors->parseErrors($this->StationSpeciesIndividual->invalidFields()),
		    0
		);	
            }                
           

            return $response;
        }
        
        public function stationExistsForUser($person_id, $stationID){
            $station_exists = $this->Station->field("Station_ID", array("Observer_ID = " => $person_id, "Station_ID = " => $stationID));
            return ($station_exists == null) ? false : true;
        }
        
        public function getSpeciesInfo($speciesNum){
            
            $the_species = $this->Species->field("Species_ID", array("Species_ID =" => $speciesNum));
            if($the_species == null)
                $the_species = $this->Species->field("Species_ID", array("ITIS_Taxonomic_SN" => $speciesNum));


            $kingdom = $this->Species->field("Kingdom", array("Species_ID =" => $the_species));

            return array($the_species, $kingdom);
        }

        private function shadeShatusIsValid($status){

            $results = $this->Lookup->find('all', array(
               'conditions' => array(
                   'Table_Name' => 'Station_Species_Individual',
                   'Column_Name' => 'Shade_Status',
                   'Allowed_Value' => $status
               ),
               'fields' => array(
                   'Allowed_Value'
               )
             ));

            return !empty($results);
     
        }
        
        public function individualNameUniqueForUser($name, $station){
            
            $individual = $this->StationSpeciesIndividual->field("Individual_ID", 
                    array("Individual_UserStr = " => $name, "Station_ID = " => $station));
            
            return ($individual == null) ? true : false;
            
        }

        public function animalSpeciesUnique($species_info, $stationID){
            if($species_info[1] != "Animalia"){
                return true;
            }

            $individual = $this->StationSpeciesIndividual->field("Individual_ID",
                    array("Species_ID = " => $species_info[0], "Station_ID = " => $stationID));

            return ($individual == null) ? true : false;
        }
        
        private function createResponse($individual_id, $msgs, $code){
            
            $response = new Response();

            $response->individual_id = $individual_id;
            $response->response_messages = $msgs;
            $response->response_code = $code; 
            
            $this->set('response', $response);
            return $response;
        }        
        
}