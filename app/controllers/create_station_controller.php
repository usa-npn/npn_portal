<?php

class Response{}


define('FIRST_CONTEMPORARY_YEAR', 2008);
define('LAST_CONTEMPORARY_YEAR', date("Y"));

class CreateStationController extends AppController{
    
   
        public $uses = array('NetworkPerson', 'Person','Station','NetworkStation');
    
        public $components = array(
            'Soap' => array(
                    'wsdl' => 'NPN', //the file name in the view folder
                    'action' => 'service', //soap service method / handler
            ), 'ValidateUser',
            'ParseErrors',
            'RequestHandler'
        );

        public function soap_wsdl(){
            //will be handled by SoapComponent
        }
	
	
	
	public function soap_service(){
        //no code here
        }


        public function createStation($station_data=null){


            if(!$this->isProtected()){
                return $this->createResponse(
                            null,
                            array("This function can only be accessed using HTTPS."),
                            0
                    );
            }

            $userID = ($this->checkProperty($station_data, "user_id")) ? $station_data->user_id : null;
            $userPW = ($this->checkProperty($station_data, "user_pw")) ? $station_data->user_pw : null;
            $access_token = ($this->checkProperty($station_data, "access_token")) ? $station_data->access_token : null;
            $consumer_key = ($this->checkProperty($station_data, "consumer_key")) ? $station_data->consumer_key : null;

            $station_name = ($this->checkProperty($station_data, "station_name")) ? $station_data->station_name : null;
            if(!$station_name){
                return $this->createResponse(null, array("station_name is a required field"), 0);
            }
            
            $latitude = ($this->checkProperty($station_data, "latitude")) ? $station_data->latitude : null;
            if(!$latitude){
                return $this->createResponse(null, array("latitude is a required field"), 0);
            }            
            
            $longitude = ($this->checkProperty($station_data, "longitude")) ? $station_data->longitude : null;
            if(!$longitude){
                return $this->createResponse(null, array("longitude is a required field"), 0);
            }            
            
            $user_elevation = ($this->checkProperty($station_data, "elevation")) ? $station_data->elevation : null;
            $datum = ($this->checkProperty($station_data, "datum")) ? $station_data->datum : "WGS84";
            $network_id = ($this->checkProperty($station_data, "network_id")) ? $station_data->network_id : null;
            $comment = ($this->checkProperty($station_data, "comment")) ? $station_data->comment : "";

            
            $person_id = $this->ValidateUser->verifyUser($userID, $userPW, $this->Person, $access_token, $consumer_key);
            if(!$person_id){
                return $this->createResponse(null, array("User Credentials not valid"), 0);
            }


            if(isset($network_id)){
                $results = $this->NetworkPerson->find('all', array(
                   'conditions' => array(
                       'NetworkPerson.Network_ID' => $network_id,
                       'NetworkPerson.Person_ID' => $person_id
                   )
                ));

                if(!isset($results[0])){
                    return $this->createResponse(null, array("User is not a member of specified network."), 0);
                }

                $role = $results[0]['netperson2approle'][0]['Role_ID'];
                $member_has_privilege = (!$role == null && $role < 2);

                if(!$member_has_privilege){
                    return $this->createResponse(null, array("User does not have administartive access to create stations for specified network."), 0);
                }

            }
            
            if(!$this->stationNameUniqueForUser($station_name, $person_id)){
                return $this->createResponse(null, array("That station name is already in use for the user."), 0);
            }

            if(empty($latitude) || !is_numeric($latitude) || $latitude > 90 || $latitude < -90 || $latitude == 0){
                return $this->createResponse(null, array("Latitude must be a nonzero value between -90 and 90."), 0);
            }

            if(empty($longitude) || !is_numeric($longitude) || $longitude > 180 || $longitude < -180 || $longitude == 0){
                return $this->createResponse(null, array("Longitude must be a nonzero value between -180 and 180."), 0);
            }

            $calc_elevation = $this->getElevationForLocation($latitude, $longitude);
            
                                                   
            
            $actual_elevation = ($calc_elevation == null) ? 
                ($user_elevation == null) ? 
                    null : 
                    $user_elevation : 
                $calc_elevation;
            
            $elevation_source = ($calc_elevation == null) ? 
                ($user_elevation == null) ? 
                    null : 
                    "User" : 
                "Google Elevation Service";
            
            $timezone = $this->getTimeZoneForLocation($latitude, $longitude);
                        
            $state = $this->getStateForLocation($latitude, $longitude);

            

            $this->Station->create();
            
            $data = 
                    array(
                            "Station" =>
                                array(
                                        "Observer_ID" => $person_id,
                                        "Station_Name" => $station_name,
                                        "Latitude" => $latitude,
                                        "Longitude" => $longitude,
                                        "State" => $state,
                                        "Lat_Lon_Datum" => $datum, 
                                        "Elevation_m" => $actual_elevation,
                                        "Elevation_Source" => $elevation_source,
                                        "Lat_Lon_Source" => "Web Service",
                                        "Active" => 1,
                                        "Elevation_User_m" => $user_elevation,
                                        "Elevation_Calc_m" => $calc_elevation,
                                        "Elevation_Calc_Source" => "Google Elevation Service",
                                        "Create_Date" => date("Y-m-d"),
                                        "Comment" => $comment,
                                        "GMT_Difference" => $timezone,
                                        "Short_Latitude" => round($latitude, 3),
                                        "Short_Longitude" => round($longitude, 3)                                        
                                )
                    );
            if(isset($network_id)){
                $data["station2NetworkStation"] = array(
                  "Network_ID" => $network_id
                );

            }
            
            if($this->Station->saveAll($data)){
                    $response = $this->createResponse(
                            $this->Station->id,
                            array("Station successfully created"),
                            1
                    );
            }else{
                    $response = $this->createResponse(
                            null,
                            $this->ParseErrors->parseErrors($this->Station->invalidFields()),
                            0
                    );
            }
            
            for($i=FIRST_CONTEMPORARY_YEAR; $i < LAST_CONTEMPORARY_YEAR; $i++){
                $this->Station->getStationWithClimateData($this->Station->id, $i, 1);
            }

            return $response;
        }       
        
        
        function stationNameUniqueForUser($station_name, $person_id){
            $nameUsed = $this->Station->field(
                        'Station_ID', 
                        array(
                            "Station_Name = " => $station_name, 
                            "Observer_ID = " => $person_id
                        ));
            
            
            return ($nameUsed == null) ? true : false;
        }
        
        
        function getElevationForLocation($latitude, $longitude){
            $google_url = 'https://maps.googleapis.com/maps/api/elevation/json?key=AIzaSyAsTM8XaktfkwpjEeDMXkNrojaiB2W5WyE&locations=';
            $google_url .= $latitude . ",";
            $google_url .= $longitude;
            $response = null;
            try{
                $response = file_get_contents($google_url);
                
            } catch (Exception $ex) {
                $this->log("Problem fetching elevation for coordinates.");
                $this->log($ex);
                return null;
            }
            
            $response = json_decode($response);
            $response = $response->results;
            
            return $response[0]->elevation;
                     
        }
        
        function getTimeZoneForLocation($latitude, $longitude){
            $endpoint_url = "https://mynpn.usanpn.org/npnapps/timeZone?lat=" . $latitude . "&lng=" . $longitude;
            $response = null;
            
            try{
                $response = file_get_contents($endpoint_url);
                
            } catch (Exception $ex) {
                $this->log("Problem fetching timzone for coordinates.");
                $this->log($ex);
                return null;
            }
            
            return ($response == 0) ? null : $response;
            
        }
        
        function getStateForLocation($latitude, $longitude){
            $google_url = 'https://maps.googleapis.com/maps/api/geocode/json?key=AIzaSyAoi0NPbtcn9bi4RXoLMEupMT4Kz-aEoQo&latlng=';
            $google_url .= $latitude . ",";
            $google_url .= $longitude;
            $response = null;
            try{
                $response = file_get_contents($google_url);
                
            } catch (Exception $ex) {
                $this->log("Problem fetching state for coordinates.");
                $this->log($ex);
                return null;
            }
            
            $response = json_decode($response);
            if(property_exists($response, "results") && count($response->results) > 0){
                
            
                $response = $response->results;
                $response = $response[0]->address_components;

                foreach($response as $address){
                    foreach($address->types as $info){
                        if($info == "administrative_area_level_1"){
                            return $address->short_name;
                        }
                    }
                }
            }
            return null;
                     
        }        

        private function createResponse($station_id, $msgs, $code){
            
            $response = new Response();

            $response->station_id = $station_id;
            $response->response_messages = $msgs;
            $response->response_code = $code;
            
            $this->set('response', $response);
            return $response;
        }         
        
}

