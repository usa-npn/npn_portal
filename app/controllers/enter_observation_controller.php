<?php

class Response{}
class ObsDetails{}


define('MINUTES_UNITS_ID', 278);
define('HOURS_UNITS_ID', 279);
define('NO_UNITS_ID', 12);

define('MINUTES_IN_HOUR', 60);


/**
 * Controller for entering observation data.
 */
class EnterObservationController extends AppController{
    
    

        public $uses = array('Station', 'Person', 'StationSpeciesIndividual', 'Species', 'SpeciesProtocol', 'Protocol',
            'Phenophase', 'ProtocolPhenophase', 'Session', 'Submission', 'ObservationGroup', 'Observation', 
            'SpeciesSpecificPhenophaseInformation', 'AbundanceCategory', 'NetworkPerson', 'Dataset','RetiredPhenophase',
            'NetworkStation');
        public $components = array(
            'Soap' => array(
                    'wsdl' => 'NPN', //the file name in the view folder
                    'action' => 'service', //soap service method / handler
            ),
            "ValidateUser",
            "RequestHandler",
            "ParseErrors"
        );

        public function soap_wsdl(){
            //will be handled by SoapComponent
        }
	
	
	
	public function soap_service(){
        //no code here
        }

        private function handleValidation($data){
            $userID = ($this->checkProperty($data, "user_id")) ? $data->user_id : null;
            $userPW = ($this->checkProperty($data, "user_pw")) ? $data->user_pw : null;
            $access_token = ($this->checkProperty($data, "access_token")) ? $data->access_token : null;
            $consumer_key = ($this->checkProperty($data, "consumer_key")) ? $data->consumer_key : null;

            /**
             * Verify user credentials first.
             */
            $person_id = $this->ValidateUser->verifyUser($userID, $userPW, $this->Person, $access_token, $consumer_key);
            if(!$person_id){
                return null;
            }

            return $person_id;
        }


        /**
         *
         * This function is for entering a set of observations together. Observations can be
         * grouped by the date and individual to which they belong.
         */
        public function enterObservationSet($observation_data=null){
            if(!$this->isProtected()){
                
                $response = $this->createResponse(
                            null,
                            array("This function can only be accessed using HTTPS."),
                            0
                    );
                $this->set('response', $response);
                return $response;
            }
            
            if(!$this->checkProperty($observation_data, "phenophase_id")){
                $response = $this->createResponse(
                            null,
                            array("Must provide at least one phenophase_id value"),
                            0
                    );
                $this->set('response', $response);
                return $response;
            }
            
            if(!$this->checkProperty($observation_data, "observation_extent")){
                $response = $this->createResponse(
                            null,
                            array("Must provide at least one observation_extent value"),
                            0
                    );
                $this->set('response', $response);
                return $response;
            }            


            /**
             * Make sure the phenophase and observation values are arrays. If there
             * is only a single value, in the SOAP transmission they may be caste
             * to singular values instead of collections.
             *
             * Updated with abundance to also include the arrays for incoming abundance values
             */
            $observation_data->phenophase_id = $this->arrayWrap($observation_data->phenophase_id);
            $observation_data->observation_extent = $this->arrayWrap($observation_data->observation_extent);
            
            if($this->checkProperty($observation_data, "abundance_value_id")){
                $observation_data->abundance_value_id = $this->arrayWrap($observation_data->abundance_value_id);
            }
            
            if($this->checkProperty($observation_data, "raw_abundance_value")){
                $observation_data->raw_abundance_value = $this->arrayWrap($observation_data->raw_abundance_value);
            }

            $pheno_count = count($observation_data->phenophase_id);
            $obs_count = count($observation_data->observation_extent);
            $responses = array();

            /**
             * Check that the indices specified in the phenphase and observation
             * extent arrays are the same. 
             */
            $pheno_keys = array_keys($observation_data->phenophase_id);
            $obs_keys = array_keys($observation_data->observation_extent);
            if(!empty(array_diff($pheno_keys, $obs_keys)) || !empty(array_diff($obs_keys, $pheno_keys))){
                $response = $this->createResponse(
                            null,
                            array("phenophase_id and observation_extent indices don't match."),
                            0
                    );
                $this->set('response', $response);
                return $response;                
            }
            
            

            $person_id = $this->handleValidation($observation_data);

            if(!$person_id){
                return $this->createResponse(null, array("User Credentials not valid"), 0);
            }

/*
            if(!$this->createSubmissionAndSession($person_id)){
                return $this->createResponse(null, array("Internal database error. Please try again later"), 0);
            }
*/
            /**
             * Now, simply create a new stdClass object and pass it to the singular enter observation function, each time parsing
             * the results into an array.
             */
     
            $observation = clone $observation_data;
            for($i = 0; $i < $pheno_count; $i++){
                $observation->submission = $this->Submission->field("Submission_ID", array("Session_ID = " => $this->Session->id));
                $observation->phenophase_id = $observation_data->phenophase_id[$i];
                $observation->observation_extent = $observation_data->observation_extent[$i];
                $observation->abundance_value_id = 
                        (($this->checkProperty($observation_data, "abundance_value_id") && array_key_exists($i, $observation_data->abundance_value_id)) ? 
                        $observation_data->abundance_value_id[$i] : 
                    null);
                
                $observation->raw_abundance_value = 
                        (($this->checkProperty($observation_data, "raw_abundance_value") && array_key_exists($i, $observation_data->raw_abundance_value)) ? 
                        $observation_data->raw_abundance_value[$i] : 
                    null);

                if($this->checkProperty($observation_data, "observation_date") && $this->checkProperty($observation_data, "individual_id")){
                    $observation->observation_group_id = $this->findObservationGroup($observation->observation_date, $observation->individual_id, $person_id);
                }

                $responses[] = $this->enterObservation($observation);
            }

            $add_success = false;

            foreach($responses as $response){
                if(strpos($response->response_messages[0], "successfully created") != false ||
                   strpos($response->response_messages[0], "successfully updated") != false){
                    $add_success = true;
                    break;
                }
            }
/*
            if(!$add_success){
                $this->deleteSubmissionAndSession();
            }
*/
            
            $this->set('response', $responses);
            return $responses;


        }
        
        /**
         * Function for entering one observation at a time.
         */
        public function enterObservation($observationData=null){

            if(!$this->isProtected()){
                return $this->createResponse(
                            null,
                            array("This function can only be accessed using HTTPS."),
                            0
                    );
            }

            $abundance_value_id = ($this->checkProperty($observationData, "abundance_value_id")) ? $observationData->abundance_value_id : null;
            $raw_abundance_value = ($this->checkProperty($observationData, "raw_abundance_value")) ? $observationData->raw_abundance_value : null;
            $comment = ($this->checkProperty($observationData, "observation_comment")) ? $observationData->observation_comment : null;
            $abundance_category_id = null;
            $sub_id = ($this->checkProperty($observationData, "submission")) ? $observationData->submission : null;

            
            $phenophaseID = ($this->checkProperty($observationData, "phenophase_id")) ? $observationData->phenophase_id : null;
            if(!$phenophaseID){
                return $this->createResponse(null, null, array("phenophase_id is a required field"), 0);
            }
            
            $individualID = ($this->checkProperty($observationData, "individual_id")) ? $observationData->individual_id : null;
            if(!$individualID){
                return $this->createResponse(null, null, array("individual_id is a required field"), 0);
            } 
            
            $date = ($this->checkProperty($observationData, "observation_date")) ? $observationData->observation_date : null;
            if(!$date){
                return $this->createResponse(null, null, array("observation_date is a required field"), 0);
            }
 

            $extent = null;
      
            if($this->checkProperty($observationData, "observation_extent", true)){
               $extent = $observationData->observation_extent;
            }else{
                return $this->createResponse(null, null, array("observation_extent is a required field"), 0);
            }
        

            
            $this->StationSpeciesIndividual->
                    unbindModel(
                            array('hasMany' =>
                                array('stationspeciesindividual2Observation')
                    ),false);


            /**
             * Allows for passing in the observation group id. Makes it easier for the observation set function. Optionally parameter
             * Would never be called with this parameter from anywhere but the enterObservationSet function.
             */
            $observation_group_id = ($this->checkProperty($observationData, "observation_group_id")) ?
                    $observationData->observation_group_id :
                    null;


           
            /**
             * Verify user credentials first.
             */
            $person_id = $this->handleValidation($observationData);
            if(!$person_id){
                return $this->createResponse(null, array("User Credentials not valid"), 0);
            }

            /**
             * Check that the individual specified actually belongs to the user.
             * This will have to be revised for the shared sites enhancement.
             */
            if(!$this->individualExistsForUser($person_id, $individualID)){
                return $this->createResponse(null, array("Individual ID does not exist for user."), 0);
            }            
            
            /**
             * The following block of code handles historical protocols. Will first find the species being
             * handled, then the appropriate protocol given the observation date provided.
             * If no protocol is found within those parameters, just use the currently active
             * protocol. Finally, checks that the phenophase provided is a member of the
             * protocol.
             */
            $species_id = $this->StationSpeciesIndividual->field("Species_ID", array("Individual_ID = " => $individualID));

            $protocol = $this->SpeciesProtocol->find('first', 
                array("conditions" => array("SpeciesProtocol.Species_ID = " => $species_id, 
                                            "SpeciesProtocol.Start_Date <= " => $date,
                                            "SpeciesProtocol.End_Date >= " => $date)));            
            if($protocol == null){
                $protocol = $this->SpeciesProtocol->find('first', 
                        array("conditions" => array("SpeciesProtocol.Species_ID = " => $species_id, 
                                                    "SpeciesProtocol.Active = " => 1)));                  
            }
            
            $protocol = $protocol["SpeciesProtocol"]["Protocol_ID"];
            
            
            
            if(!$this->phenophaseValidForSpecies($phenophaseID, $species_id, $protocol)){
                
                if($this->phenophaseIsRetired($phenophaseID)){
                    
                    return $this->createResponse(null, array("Phenophase is no longer valid."), 1);
                    
                }else{
                    return $this->createResponse(null, array("Phenophase is not valid for that species."), 0);                    
                }
                
                
            }

            if( ($raw_abundance_value || $abundance_value_id) && $extent == 0){
                return $this->createResponse(null, array("Can't enter abundance for a extent value of 0."), 0);
            }

            if($raw_abundance_value && $abundance_value_id){
                return $this->createResponse(null, array("Cannot enter categorical and raw abundance values simultaneously."), 0);
            }

            if($raw_abundance_value && !$this->abundanceValid($phenophaseID, $species_id, $date,null, $raw_abundance_value)){
                return $this->createResponse(null, array("Raw abundance is not valid for that species/date."), 0);
            }

            if($abundance_value_id){           
                $abundance_category_id = $this->abundanceValid($phenophaseID, $species_id, $date, $abundance_value_id, null);
                if(!$abundance_category_id){
                    return $this->createResponse(null, array("Abundance Category Value not valid."), 0);
                }
            }


            /**
             * Check to see if a record already exists in the database, sharing
             * the data, individual id, and phenophase provided. If so, it will
             * handle the request different as an update instead of an insert.
             * This will need to be revised for shared sites.
             */
            $update_observation = $this->isUpdate($date, $person_id, $phenophaseID, $individualID);
                       
            if($update_observation == -1 && $sub_id == null){
                if(!$this->createSubmissionAndSession($person_id)){
                    return $this->createResponse(null, array("Internal database error. Please try again later"), 0);
                }
            }

            
            /**
             * If not updating, then insert a new record.
             */
            if($update_observation == -1){

                $dataset_id = $this->findDataset($person_id,
                        (
                        (property_exists($observationData, "consumer_key")) ? $observationData->consumer_key : null)
                        );


                if($this->createObservation($person_id, $phenophaseID, $date, $extent, $comment, $individualID, $protocol, $dataset_id, $observation_group_id, $abundance_category_id, $abundance_value_id, $raw_abundance_value, ($sub_id == null)?null:$sub_id)){

                     $response = $this->createResponse($this->Observation->field("Observation_ID", array("Observation_Group_ID =" => $this->ObservationGroup->id), 'Observation_ID DESC'),
                             array("Observation successfully created"),
                             1);                

                }else{

                    /**
                     * If the observation group save failed, then we should remove the submission
                     * and session objects and back out.
                     */
                    $this->deleteSubmissionAndSession();

                    $msgs = $this->ParseErrors->parseErrors($this->ObservationGroup->invalidFields());
                    
                    $response = $this->createResponse(null,
                             $msgs,
                             0);                         

                }
                
                
            }else{
                if($this->updateObservation($update_observation, $extent, $abundance_category_id, $abundance_value_id, $raw_abundance_value, $comment, $person_id, $sub_id)){
  
 
                     $response = $this->createResponse($update_observation,
                             array("Observation " . $update_observation . " successfully updated."),
                             1);                     
                    
                }else{
                    
                     $msgs = $this->ParseErrors->parseErrors($this->Observation->invalidFields());
                     $response = $this->createResponse(null,
                             $msgs,
                             0);

                     
                }
                
            }
            
            $this->badgeCheck($person_id);

            return $response;

        }
        
        public function enterObservationDetails($obsevation_details=null){
            if(!$this->isProtected()){
                return $this->createObsDetailResponse(
                            null,
                            array("This function can only be accessed using HTTPS."),
                            0
                    );
            }
            
            /**
             * Verify user credentials first.
             */
            $person_id = $this->handleValidation($obsevation_details);
            $station_id = null;
            $date = null;
            
            if(!$person_id){
                return $this->createObsDetailResponse(null, array("User Credentials not valid"), 0);
            }
            
            if($this->checkProperty($obsevation_details, "station_id")){
                $station_id = $obsevation_details->station_id;
            }
            
            if($this->checkProperty($obsevation_details, "date")){
                $date = $obsevation_details->date;
            }                       
            
            if(!$station_id){
                return $this->createObsDetailResponse(null, array("Station ID not provided."), 0);
            }
            
            if(!$date){
                return $this->createObsDetailResponse(null, array("Date not provided."), 0);
            }
            
            $num_obs = $this->Observation->find('count', array(
                'conditions' => array(
                    'Observation.Observation_Date' => $date,
                    'observation2StationSpeciesIndividual.Station_ID' => $station_id,
                    'Observation.Observer_ID' => $person_id
                )
            ));
            
            if($num_obs < 1){
                return $this->createObsDetailResponse(null, array("Cannot create Observation Details. No associated observation records."), 0);
            }
            
            
            if(!$this->stationExistsForUser($person_id, $station_id)){
                return $this->createObsDetailResponse(null, array("Station not valid for that user."), 0);
            }            

            $data = array();            
            $this->addObservationDetailArray($data, $obsevation_details, "time_travel", "Travel_Time", false, "Travel_Time_Units_ID");
            $this->addObservationDetailArray($data, $obsevation_details, "time_observing", "Time_Spent", false, "Time_Spent_Units_ID");
            $this->addObservationDetailArray($data, $obsevation_details, "time_searching", "Duration_Of_Search", false, "Duration_Of_Search_Units_ID");
            $this->addObservationDetailArray($data, $obsevation_details, "snow_ground_percent_covered", "Snow_Ground_Coverage", false);
            $this->addObservationDetailArray($data, $obsevation_details, "snow_ground", "Snow_Ground", true);
            $this->addObservationDetailArray($data, $obsevation_details, "snow_canopy", "Snow_Overstory_Canopy", true);
            $this->addObservationDetailArray($data, $obsevation_details, "animal_search_method", "Method", false);
            
            $data['ObservationGroup']['Observer_ID'] = $person_id;
            $data['ObservationGroup']['Station_ID'] = $station_id;
            $data['ObservationGroup']['Observation_Group_Date'] = $date;
            
            
            if(array_key_exists("Snow_Ground", $data) && $data["Snow_Ground"] == 0){
                $data["Snow_Ground_Coverage"] = null;
            }

            $methods = array("Stationary", "Walking", "Area Search", "Incidental");
            if(array_key_exists("Method", $data) && $data['Method'] != null && !in_array($data['Method'], $methods)){
                return $this->createObsDetailResponse(null, array("Animal search method is not a valid allowed value."), 0);
            }

            
            $data["Observation_Group_ID"] = $this->findObservationGroupByStation($date, $station_id, $person_id);
                        
            $obs_data = 
                    array(
                            "ObservationGroup" =>
                            $data

            );
            
            if($this->ObservationGroup->saveAll($obs_data)){
                
                $this->Observation->updateAll(
                        array("Observation.Observation_Group_ID" => $this->ObservationGroup->id),
                        array(
                            "Observation.Observation_Date" => $date,
                            "observation2StationSpeciesIndividual.Station_ID" => $station_id,
                            "Observation.Observer_ID" => $person_id
                        )
                );
                
                return $this->createObsDetailResponse($this->ObservationGroup->id, array("Observation Details Successfully updated."), 1);
            }else{
                return $this->createObsDetailResponse(null, array("Internal Database Error."), 0);
            }
        }
        
        private function addObservationDetailArray(&$data, $details, $variable_name, $db_field, $zero_explicit, $units_field = null){

            if(property_exists($details, $variable_name)){
                $val = $details->$variable_name;
                
                if($val == ""){
                    $val = null;
                }
                if($zero_explicit && $val == 0){
                    $data[$db_field] = 0;
                    
                }else if($val !== 0){
                    $data[$db_field] = $val;
                    if($val != null && $units_field != null){
                        $data[$units_field] = MINUTES_UNITS_ID;
                    }
                }
                
                if($units_field != null && $val == null){
                    $data[$units_field] = NO_UNITS_ID;
                }                                
            }
        }
        
        public function getObservationDetails($params=null){
            
            if(!$this->isProtected()){
                return $this->createResponse(
                            null,
                            array("This function can only be accessed using HTTPS."),
                            0
                    );
            }

            $person_id = $this->handleValidation($params);
            
            if(!$person_id){
                return $this->createBlankObservationGroupResponse();
            }                   
            
            $station_id = null;
            $date = null;
            
            if($this->checkProperty($params, "station_id")){
                $station_id = $params->station_id;
            }else{
                return $this->createBlankObservationGroupResponse();
            }
            
            if($this->checkProperty($params, "date")){
                $date = $params->date;
            }else{
                return $this->createBlankObservationGroupResponse();
            }
            
            $result = $this->Observation->find('first', array(
                'conditions' => array(
                    'Observation.Observer_ID' => $person_id,
                    'Observation.Observation_Date' => $date,
                    'observation2StationSpeciesIndividual.Station_ID' => $station_id
                ), 'fields' => array(
                    'observation2ObservationGroup.Observation_Group_ID',
                    'observation2ObservationGroup.Travel_Time',
                    'observation2ObservationGroup.Time_Spent',
                    'observation2ObservationGroup.Duration_Of_Search',
                    'observation2ObservationGroup.Snow_Ground',
                    'observation2ObservationGroup.Snow_Ground_Coverage',
                    'observation2ObservationGroup.Snow_Overstory_Canopy',
                    'observation2ObservationGroup.Method',
                    'observation2ObservationGroup.Travel_Time_Units_ID',
                    'observation2ObservationGroup.Time_Spent_Units_ID',
                    'observation2ObservationGroup.Duration_Of_Search_Units_ID',
                    'observation2ObservationGroup.Observation_Group_Date',
                    'observation2ObservationGroup.Observer_ID',
                    'observation2ObservationGroup.Station_ID'
                )
            ));
            
            if($result['observation2ObservationGroup']['Time_Spent']){
                if($result['observation2ObservationGroup']['Travel_Spent_Units_ID'] == HOURS_UNITS_ID){
                    $result['observation2ObservationGroup']['Time_Spent'] = 
                    $result['observation2ObservationGroup']['Time_Spent'] * MINUTES_IN_HOUR;
                }
            }
            
            if($result['observation2ObservationGroup']['Duration_Of_Search']){
                if($result['observation2ObservationGroup']['Duration_Of_Search_Units_ID'] == HOURS_UNITS_ID){
                    $result['observation2ObservationGroup']['Duration_Of_Search'] = 
                    $result['observation2ObservationGroup']['Duration_Of_Search'] * MINUTES_IN_HOUR;
                }
            }            
            
            if($result['observation2ObservationGroup']['Travel_Time']){
                if($result['observation2ObservationGroup']['Travel_Time_Units_ID'] == HOURS_UNITS_ID){
                    $result['observation2ObservationGroup']['Travel_Time'] = 
                    $result['observation2ObservationGroup']['Travel_Time'] * MINUTES_IN_HOUR;
                }
            }            
            
            if($result){
                
                $obs_details = new ObsDetails();
                
                $obs_details->observation_group_id = $result['observation2ObservationGroup']['Observation_Group_ID'];
                
                $obs_details->time_travel = $result['observation2ObservationGroup']['Travel_Time'];
                $obs_details->time_observing = $result['observation2ObservationGroup']['Time_Spent'];
                $obs_details->time_searching = $result['observation2ObservationGroup']['Duration_Of_Search'];
                
                $obs_details->snow_ground = $result['observation2ObservationGroup']['Duration_Of_Search'];
                $obs_details->snow_ground_percent_covered = $result['observation2ObservationGroup']['Snow_Ground_Coverage'];                
                $obs_details->snow_canopy = $result['observation2ObservationGroup']['Snow_Overstory_Canopy'];
                
                $obs_details->animal_search_method = $result['observation2ObservationGroup']['Method'];
                
                $obs_details->observation_group_date = $result['observation2ObservationGroup']['Observation_Group_Date'];
                $obs_details->observer_id = $result['observation2ObservationGroup']['Observer_ID'];
                $obs_details->station_id = $result['observation2ObservationGroup']['Station_ID'];
                
                $this->set('obs_details', $obs_details);
                return $obs_details;
                
            }else{
                return $this->createBlankObservationGroupResponse();
            }
            
        }
        
        private function createBlankObservationGroupResponse(){
            $response = null;
            $this->set('obs_details', $response);
            return $response;
        }
        
        private function stationExistsForUser($person_id, $stationID){
            $station_exists = $this->Station->field("Station_ID", array("Observer_ID" => $person_id, "Station_ID" => $stationID));			
            
            if($station_exists == null){
                $network_id = $this->NetworkStation->field("Network_ID", array("Station_ID" => $stationID));
                $station_exists = $this->NetworkPerson->field("Network_ID", array("Network_ID" => $network_id, "Person_ID" => $person_id));
            }
            
            return ($station_exists == null) ? false : true;
        }        
        
        private function badgeCheck($person_id){
            ini_set('soap.wsdl_cache_ttl', 1);
            ini_set('soap.wsdl_cache_enabled', 0);            
            $selfClient = new SoapClient(getenv('SERV_PROTOCOL').getenv('SERVER_ROOT').'/npn_portal/soap/wsdl/wsdl');
            $obj = new stdClass();
            $obj->person_id = $person_id;
            $obj->hook_name = "ADD_OBS";
            
            $selfClient->checkUserBadge($obj);             
        }
        
        private function individualExistsForUser($person_id, $individualID){

            $this->Station->station2ssi->
                    unbindModel(
                            array('hasMany' =>
                                array('stationspeciesindividual2Observation')
                    ),false);

            $user_individuals = $this->Station->station2ssi->find("all", array("conditions" => array("Observer_ID = " => $person_id)));

            foreach($user_individuals as $individual){

                if($individual["station2ssi"]["Individual_ID"] == $individualID){
                    return true;
                }
            }
            $individual = $this->StationSpeciesIndividual->findByIndividualId($individualID);
            if(isset ($individual)){
                $network_id = $this->Station->findStationNetwork($individual["StationSpeciesIndividual"]["Station_ID"]);

                if(isset($network_id)){

                    $results = $this->NetworkPerson->find('all', array(
                       'conditions' => array(
                           'NetworkPerson.Network_ID' => $network_id,
                           'NetworkPerson.Person_ID' => $person_id
                       )
                    ));

                    if(isset($results[0])){
                        return true;
                    }
                }


                if(isset($results[0])){
                    return true;
                }

            }
            
            return false;
        }      
        
        
        private function phenophaseValidForSpecies($phenophase_id, $species_id, $protocol){

            $rels = $this->SpeciesProtocol->find("all", array("conditions" => array("SpeciesProtocol.Species_ID = " => $species_id, 
                                                                                    "SpeciesProtocol.Protocol_ID = " => $protocol)));

            foreach($rels as $spp){
                $protocol = $this->ProtocolPhenophase->find('all', array("conditions" => array("ProtocolPhenophase.Protocol_ID = " => $spp["SpeciesProtocol"]["Protocol_ID"])));
                
                foreach($protocol as $phenophase){
                    if($phenophase["ProtocolPhenophase"]["Phenophase_ID"] == $phenophase_id) return true;
                }
            }
            return false;
        }
        
        private function phenophaseIsRetired($phenophase_id){
            $pid = $this->RetiredPhenophase->find("all", array(
                "conditions" => array(
                    "RetiredPhenophase.Phenophase_ID" => $phenophase_id
                )
            ));
            
            return !empty($pid[0]);
        }
        
        /**
         * Utility function to check to see if a given abundance valid makes
         * sense for the species, phenophase and date.
         * @param type $phenophase
         * @param type $species
         * @param type $date
         * @param type $abundance_category_value - This should be the cateogry
         * value id the user passed in
         * @param type $raw_abundance - This is the raw abundance value the user
         * provided.
         * @return boolean
         */
        private function abundanceValid($phenophase, $species, $date, $abundance_category_value, $raw_abundance){

            $conditions = array(
                "SpeciesSpecificPhenophaseInformation.Phenophase_ID" => $phenophase,
                "SpeciesSpecificPhenophaseInformation.Species_ID" => $species,
                "SpeciesSpecificPhenophaseInformation.Effective_Datetime <= " => $date,
                "SpeciesSpecificPhenophaseInformation.Deactivation_Datetime >=" => $date
            );


            /**
             * First check to see if this is some historic sspi record, i.e.
             * there is a deactivation date time that is valid.
             */
            $results = $this->SpeciesSpecificPhenophaseInformation->find('first', array(
               'conditions' => $conditions,
               'order' => array('Effective_Datetime DESC')
            ));
            
            
            /**
             * If no historic sspi record could be found for the species and
             * phenophase, then remove those conditions from the query and try
             * again, this time, simply finding the "active" sspi.
             */
            if(!$results){
                array_pop($conditions);
                array_pop($conditions);

                $conditions['SpeciesSpecificPhenophaseInformation.Active'] = 1;
                
                $results = $this->SpeciesSpecificPhenophaseInformation->find('first', array(
                   'conditions' => $conditions,
                   'order' => array('Effective_Datetime DESC')
                ));
                
            }

            /**
             * Now that the SSPI record that is correct for the given date is
             * determined, first check to see if the user provided an abundance
             * category value id and if so, if the SSPI record takes abundance
             * category values
             */
            if($abundance_category_value && $results['SpeciesSpecificPhenophaseInformation']['Abundance_Category'] != null){
                if($results['sspi2AbundanceCategory']){
                    $category = $this->AbundanceCategory->find('first', array(
                        'conditions' => array(
                            'Abundance_Category_ID' => $results['sspi2AbundanceCategory']['Abundance_Category_ID']
                        )
                    ));

                    
                    /**
                     * This will check to see if the actual value id is valid
                     * for the given category and returns the category id
                     * if it is.
                     */
                    if($category){
                        foreach($category["abundance_category_2_abundance_values"] as $value){
                            if($value["Abundance_Value_ID"] == $abundance_category_value){
                                return $category["AbundanceCategory"]["Abundance_Category_ID"];
                            }
                        }
                    }
                }
            }else{
                /**
                 * This else statement is triggered if there is no abundance, so
                 * it is checking to see if raw abundance makes sense.
                 * If there were actually results and the SSPI takes raw abundance
                 * AND the user provided a raw abundance value, then it checks out.
                 */
                if(!empty($results) && ($results['SpeciesSpecificPhenophaseInformation']['Extent_Min'] != null && $raw_abundance)){
                    return true;
                }
            }

            return false;
        }
        
        private function isUpdate($date, $person_id, $phenophase_id, $individual_id){
            $update_observation = -1;
            $record = $this->Observation->find('first', 
                    array("conditions" => 
                        array(
                            "Observation_Date" => $date, 
                            "Observation.Observer_ID" => $person_id,
                            "Observation.Phenophase_ID" => $phenophase_id, 
                            "Observation.Individual_ID" => $individual_id)));
            if($record != null){
                
                $update_observation = $this->Observation->find('first', 
                    array("conditions" => 
                        array(
                            "Observation_Date = " => $date, 
                            "Observation.Observer_ID =" => $person_id,
                            "Observation.Phenophase_ID =" => $phenophase_id, 
                            "Observation.Individual_ID =" => $individual_id)));
                
                $update_observation = $update_observation["Observation"]["Observation_ID"];
                
            }
            
            return $update_observation;
        }
        
        
        private function createSubmissionAndSession($person_id){
            
            $this->Session->create();

            $data =
                    array(
                            "Session" =>
                            array(
                                    "Person_ID" => $person_id,
                                    "IP_Address" => $this->RequestHandler->getClientIP(),
                                    "Create_DateTime" => date("Y-m-d G:i:s"),
                                    "Active" => 0,
                                    "End_DateTime" => date("Y-m-d G:i:s")
                            ),
                            "session2Submission" =>
                            array(
                                array(
                                    "Submission_DateTime" => date("Y-m-d G:i:s"),
                                    "Create_Person_ID" => $person_id
                                 )

                            )
            );

            return ($this->Session->saveAll($data)) ? true : false;
        
        }
        
   
        private function createObservation($person_id, $phenophase_id, $date, $extent, $comment, $individual_id, $protocol, $dataset_id, $observation_group_id=null, $abundance_category_id, $abundance_value_id, $raw_abundance_value, $submission_id){
            if($submission_id == null){
                $submission_id = $this->Submission->field("Submission_ID", array("Session_ID = " => $this->Session->id));
            }

            $user_time = false;
            if(count(explode(":", $date)) > 1){
                $user_time = true;
            }


            
            $data = 
                    array(

                            "ObservationGroup" =>
                            array(
                                "Travel_Time" => null,
                                "User_Time" => $user_time
                            ),
                            "observationgroup2Observation" =>
                            array(
                                array(
                                    "Observer_ID" => $person_id,
                                    "Submission_ID" => $submission_id,
                                    "Phenophase_ID" => $phenophase_id,
                                    "Observation_Date" => $date,
                                    "Observation_Extent" => $extent,
                                    "Comment" => $comment,
                                    "Individual_ID" => $individual_id,                             
                                    "Protocol_ID" => $protocol,
                                    "Raw_Abundance_Value" => $raw_abundance_value,
                                    "Abundance_Category" => $abundance_category_id,
                                    "Abundance_Category_Value" => $abundance_value_id,
                                    "Deleted" => 0
                                )
                            )

            );

            /**
             * If the observation group id was not previously provided, then attempt to find it now.
             * If one was found, then use it. Otherwise, one will be created automatically.
             */
            if(!$observation_group_id){
                $observation_group_id = $this->findObservationGroup($date, $individual_id, $person_id);
            }

            if($observation_group_id){
                $data['ObservationGroup']['Observation_Group_ID'] = $observation_group_id;
            }
            $individual = $this->StationSpeciesIndividual->findByIndividualId($individual_id);
            
            $data['ObservationGroup']['Observer_ID'] = $person_id;
            $data['ObservationGroup']['Station_ID'] = $individual['StationSpeciesIndividual']['Station_ID'];
            $data['ObservationGroup']['Observation_Group_Date'] = $date;
            
            $this->Dataset->unbindModel(array('hasAndBelongsToMany' => array('dataset2observation')));

            if($this->ObservationGroup->saveAll($data)){

                $data =
                    array(
                        "Dataset" => array(
                          "Dataset_ID" => $dataset_id
                        ),
                        "dataset2observation" => array(
                            "dataset2observation" =>
                                array(
                                    "Observation_ID" => $this->ObservationGroup->observationgroup2Observation->id
                                )
                        )

                    );
                if($this->Dataset->saveAll($data)){
                    return true;
                }else{
                    return false;
                }
            }else{
                return false;
            }







        }
        
        
        private function deleteSubmissionAndSession(){
            $this->Submission->delete($this->Submission->field("Submission_ID", array("Session_ID = " => $this->Session->id)), false);                    
            $this->Submission->commit();
            $this->Session->delete($this->Session->id, false);                    
            $this->Session->commit();            
        }
        
        /**
         * An update only requires that the id is specified, and the new
         * extent value is given.
         */
        private function updateObservation($obs_id, $extent, $abundance_category_id, $abundance_value_id, $raw_abundance_value, $comment, $person_id, $submission_id){

            if($submission_id == null){
                $submission_id = $this->Observation->find('first', array(
                    'fields' => array(
                        'Submission_ID'
                    ),
                    'conditions' => array(
                        "Observation_ID" => $obs_id                        
                    )
                ));
                $submission_id = $submission_id['Observation']['Submission_ID'];
            }
            
            $data = array(
                        "Observation" =>
                            array(
                                    "Observation_ID" => $obs_id,
                                    "Observation_Extent" => $extent,
                                    "Abundance_Category" => $abundance_category_id,
                                    "Abundance_Category_Value" => $abundance_value_id,
                                    "Raw_Abundance_Value" => $raw_abundance_value,
                                    "Comment" => $comment,
                                    "Deleted" => 0
                            ),
                        "observation2submission" =>
                            array(
                                    "Submission_ID" => $submission_id,
                                    "Update_DateTime" => date("Y-m-d H:i:s"),
                                    "Update_Person_ID" => $person_id
                            )
            );     

            return ($this->Observation->saveAll($data)) ? true : false;
        
        }
        
        
        private function createResponse($obs_id, $msgs, $code){
            
            $response = new Response();

            $response->observation_id = $obs_id;
            $response->response_messages = $msgs;
            $response->response_code = $code;
            
            $this->set('response', $response);
            return $response;
        }
        
        private function createObsDetailResponse($obs_id, $msgs, $code){
            
            $response = new Response();

            $response->observation_group_id = $obs_id;
            $response->response_messages = $msgs;
            $response->response_code = $code;
            
            $this->set('response', $response);
            return $response;
        }        


        /**
         * This will find the observation group id for an observation if one
         * already exists.
         *
         */
        private function findObservationGroup($date, $individual_id, $observer_id){
            /**
             * First find the individual (to get the station id), then use
             * the station id / date combo to see if an obs group id exists.
             */
            $individual = $this->StationSpeciesIndividual->findByIndividualId($individual_id);
            return $this->findObservationGroupByStation($date, $individual['StationSpeciesIndividual']['Station_ID'], $observer_id);
            
        }
        
        private function findObservationGroupByStation($date, $station_id, $observer_id){
            /**
             * use
             * the station id / date combo to see if an obs group id exists.
             */
            $obsgrp = $this->ObservationGroup->find('first', array(
               'conditions' => array(
                                'ObservationGroup.Observation_Group_Date' => $date,
                                'ObservationGroup.Observer_ID' => $observer_id,
                                'ObservationGroup.Station_ID' => $station_id
               )
            ));

            return ($obsgrp) ? $obsgrp['ObservationGroup']['Observation_Group_ID'] : null;
        }        


        private function findDataset($person_id, $consumer_key){
            $id = null;
            if($consumer_key != null){
                $this->Dataset->unbindModel(array('hasAndBelongsToMany' => array('dataset2observation')));
                $dataset_id = $this->Dataset->find('first', array(
                   'conditions' => array(
                       "consumer_key" => $consumer_key
                   ),
                   "fields" => array(
                       "Dataset_ID"
                   )
                ));

                if(isset($dataset_id)){
                    $id = $dataset_id['Dataset']["Dataset_ID"];
                }
            }else{
                $person_data = $this->Person->find('first', array(
                    'conditions' => array(
                        'Person_ID' => $person_id
                    ),
                    'fields' => array(
                        'Load_Key'
                    )
                ));

                if(isset($person_data)){
                    $load_key = explode("_",$person_data['Person']['Load_Key']);
                    $this->Dataset->unbindModel(array('hasAndBelongsToMany' => array('dataset2observation')));
                    $dataset_id = $this->Dataset->find('first', array(
                       'conditions' => array(
                           'consumer_key' => $load_key[0]
                       ),
                       'fields' => array(
                           'Dataset_ID'
                       )
                    ));

                    if(isset($dataset_id)){
                        $id = $dataset_id['Dataset']['Dataset_ID'];
                    }

                }

            }

            return $id;

        }


        
        
}
