<?php

class Site{}
class StationState{}
class State{}
class Response{}
class Message{}
App::import('Sanitize');


/**
 * Controller to retrieve station information
 */
class StationsController extends AppController{
    
    
    public $uses = array('NetworkPerson', 'Station', 'VwStationsByState', 'Observation', 'Person', 'StateList', 'Boundary');
    
    public $components = array(
    	'Soap' => array(
        	'wsdl' => 'NPN', //the file name in the view folder
            	'action' => 'service', //soap service method / handler
        ),
        'CleanText',
        'RequestHandler',
        'ValidateUser',
        'CheckProperty'
    );
    
    /**
     * Included Cake cache helper, but not sure if it's enabled, or even helping.
     * Solved problem by returning smaller payload.
     */
    public $helpers = array(
        'Cache'
    );
    
    public $cacheAction = array(
        'getAllStations' => 1440
    );


    public function soap_wsdl(){
    	//will be handled by SoapComponent
    }
	
	
	
    public function soap_service(){
    //no code here
    }
    
    /**
     * Function to return all station data. Can actually apply some filter to
     * this as well, including person id and state code.
     */
    public function getAllStations($params=null){
        $stations = array();        

        /**
         * Take out unneeded bindings. Keeps from hogging memory.
         */
        $this->Station->unbindModel(
                array('hasMany' => 
                    array(
                        'station2ssi')
                    ),
                array('belongsTo' =>
                        'station2Person'
                    )
                );

        $conditions = array();
        $fields = array("Station_ID", "Station_Name", "Latitude", "Longitude", "station2NetworkStation.Network_ID", "Observer_ID");
        
        if($this->checkProperty($params, 'state_code')){
            $conditions['Station.State ='] = $params->state_code;
        }

        if($this->checkProperty($params, 'person_id')){
            $fields[] = "Observer_ID";
            if($this->checkProperty($params, 'network_ids')){
                $conditions["OR"] = array(
                    "Station.Observer_ID" => $params->person_id,
                    "station2NetworkStation.Network_ID" => $params->network_ids
                );
            }else{
                $conditions['Station.Observer_ID ='] = $params->person_id;
            }
        }

        if($this->checkProperty($params, 'network_ids') && !$this->checkProperty($params, 'person_id')){
            $conditions['station2NetworkStation.Network_ID'] = $params->network_ids;
        }
        
        
        $joins = array(
            array(
                "table" => "Image_Station",
                "alias" => "image_station",
                "type" => "LEFT",
                "conditions" => "image_station.Station_ID = Station.Station_ID"
            ),
            array(
                "table" => "Image_Image_Source",
                "alias" => "image_image_source",
                "type" => "LEFT",
                "conditions" => array(
                    "image_image_source.Image_Source_ID = 1",
                    "image_image_source.Image_ID = image_station.Image_ID"
                )
            )
        );
        $fields[] = "image_image_source.File_URL";

        
        $results = $this->Station->find('all', array(
            'fields' => $fields,
            'conditions' => $conditions,
            'joins' => $joins
        ));
        foreach($results
                as $st){
            
            $name = $this->CleanText->cleanText($st['Station']['Station_Name']);
            $station = new Site();
            $station->latitude = $st["Station"]["Latitude"];
            $station->longitude = $st["Station"]["Longitude"];
            $station->station_name = $name;
            $station->station_id = $st["Station"]["Station_ID"];
            $station->network_id = ($st["station2NetworkStation"]["Network_ID"]) ? $st["station2NetworkStation"]["Network_ID"] : "";
            $station->file_url = $st['image_image_source']['File_URL'];
            
            if(isset($st["Station"]["Observer_ID"]) && $this->checkProperty($params, 'person_id')){
                $station->is_owner = ($st["Station"]["Observer_ID"] == $params->person_id);
            }

            $stations[$station->station_id] = $station;
            
        }


        if($this->checkProperty($params, 'person_id')){
            $params->callback = true;
        }
        
        $stations = array_values($stations);
        $this->set('stations', $stations);
        return $stations;
    }


    /**
     * Function to get all station belonging to a user. Actually just calls
     * getAllStations but adds input paramater for person id.
     * Can take either person_id or access_token/consumer key. Will not validate
     * raw person id, just returns null results if bad person id.
     */
    public function getStationsForUser($params=null){

        if(!$this->isProtected()){
            $this->set('stations', null);
            return null;
        }

        if($this->checkProperty($params, "access_token") && $this->checkProperty($params, "consumer_key")){
            $person_id = $this->ValidateUser->verifyUser(null, null, $this->Person, $params->access_token, $params->consumer_key);
            if(!$person_id){
                $this->set('stations', array());
                return null;
            }else{
                $params->person_id = $person_id;
            }
        }
                

        if($this->checkProperty($params, "person_id")){

            $network_data = $this->NetworkPerson->findNetworksForPerson($params->person_id);
            $users_networks = array();
            foreach($network_data as $net){
                $users_networks[] = $net['networkperson2Network']["Network_ID"];
            }
            if(!empty($users_networks)){
                $params->network_ids = $users_networks;
            }

            return $this->getAllStations($params);
            
        }else{
            $this->set('stations', null);
            return array();
        }
    }

    public function getStationsForBoundary($params=null){
        
        $conditions = array();
        $joins = array();
        $stations = array();
        $boundary = null;
        
        $this->Station->unbindModel(
            array('hasMany' =>
                array('station2ssi'),
                  'belongsTo' =>
                array('station2Person'),
                  'hasOne' =>
                array('station2NetworkStation')
            ) 
        );
        
        
        if(($this->checkProperty($params, "person_id"))){
            $params->person_id = $this->arrayWrap($params->person_id);    
            $conditions['Station.Observer_ID'] = $params->person_id;
        }
        
        if(($this->checkProperty($params, "group_id"))){
            $params->network_id = $params->group_id;    
        }

        if(($this->checkProperty($params, "network_id"))){        
            $params->network_id = $this->arrayWrap($params->network_id);    
            $conditions['Network_Station.Network_ID'] = $params->network_id;

            $joins = array(
                array(
                    "table" => "Network_Station",
                    "alias" => "Network_Station",
                    "type" => "LEFT",
                    "conditions" => "Network_Station.Station_ID = Station.Station_ID"
                )
            );
        }        
        
        
        if($this->checkProperty($params, "boundary_id")){
            
            $boundary_id = (int)$params->boundary_id;
            
            if(is_int($boundary_id) && $boundary_id > 0){
                $boundary = $this->Boundary->findByBoundaryId($boundary_id);
            }else{
                $boundary = null;                
                return $this->createResponse(0, array("Invalid boundary_id"));                    
            }

        }else{
            return $this->createResponse(0, array("boundary_id required parameter"));
        }
        
        if($boundary){
            $conditions["ST_Contains(ST_GeomFromText(\"" . $boundary['Boundary']['Simple_WKT'] . "\"),ST_GeomFromText(CONCAT(\"POINT(\",Longitude,\" \",Latitude,\")\")))"] = 1;
            
            $this->log($conditions);
            
            $res = $this->Station->find('all', array(
                'conditions' => $conditions,
                'fields' => array('Station_ID'),
                'joins' => $joins
            ));
            
            foreach($res as $station){
                $stations[] = $station['Station']['Station_ID'];
            }
            
            $this->log($res);
        }else{
            return $this->createResponse(0, array("boundary_id does not exist"));
        }
        
        $this->set('stations',$stations);
        return $stations;
        
    }
    
    
    private function createResponse($status, $msgs){
        
        $response = new Response();        
        $response->status_code = $status;        
        $response->messages = $msgs;
        
        
        $this->set('stations', $response);
        return $response;
    }


    public function getStationsByLocation($props,$out, $format){
        
        App::import('Model','Station');
        $this->Station =  new Station();
        
        $emitter = $this->getEmitter($format, $out, "stations", "getStationResponse");
        
        $wkt = ($this->checkProperty($props, "wkt")) ? $props->wkt : null;
        $stations = array();
        $response = new Response();
        $conditions = array();
        
        $joins = array();
        
        $emitter->emitHeader();
        
        if(!$wkt){
            $response->stations = $stations;
            $response->response_messages = "Empty WKT value";
            $emitter->emitNode($response);
            $emitter->emitFooter();
            return;
        }else{
            $wkt = Sanitize::escape($wkt);
        }
        
        if(($this->checkProperty($props, "person_id"))){
            $props->person_id = $this->arrayWrap($props->person_id);    
            $conditions['Station.Observer_ID'] = $props->person_id;
        }     
        
        if(($this->checkProperty($props, "group_id"))){
            $props->network_id = $props->group_id;    
        }

        if(($this->checkProperty($props, "network_id"))){        
            $props->network_id = $this->arrayWrap($props->network_id);    
            $conditions['Network_Station.Network_ID'] = $props->network_id;

            $joins = array(
                array(
                    "table" => "Network_Station",
                    "alias" => "Network_Station",
                    "type" => "LEFT",
                    "conditions" => "Network_Station.Station_ID = Station.Station_ID"
                )
            );
        }
        
        $this->Station->recursive = -1;
        
        $this->Station->query("SET @wkt=ST_GeomFromText(\"" . $wkt ."\")");
        
        $wkt_test = $this->Station->query('SELECT @wkt');

        if($wkt_test[0][0]['@wkt'] == null){
            $response->stations = $stations;
            $response->response_messages = "Invalid WKT text";

            $emitter->emitNode($response);
            $emitter->emitFooter();
            return;
        }
        
        $res = $this->Station->find('all', array(
            'conditions' => $conditions,
            'fields' => array(
                "ST_Contains(@wkt,ST_GeomFromText(CONCAT(\"POINT(\",Station.Longitude,\" \",Station.Latitude,\")\"))) `in_poly`",
                "Station.Latitude",
                "Station.Longitude",
                "Station.Station_ID",
                "Station.Station_Name"
            ),
            'group' => array(
                "Station.Station_ID HAVING `in_poly` = 1"
            ),
            'joins' => $joins
        ));

        foreach($res as $r){

            $station = new Site();
            $station->latitude = $r['Station']['Latitude'];
            $station->longitude = $r['Station']['Longitude'];
            $station->station_name = $r['Station']['Station_Name'];
            $station->station_id = $r['Station']['Station_ID'];
 
            $emitter->emitNode($station);

        }        
        

        $response->stations = $stations;
        $response->response_messages = "Success";


        $emitter->emitFooter();
        
        return;

    }

    
    

    /**
     *
     * Simple function to get the rest of a stations' information from its ID.
     */
    public function getStationsById($params=null){

        $conditions = array();
        $stations = array();
        
        if($this->checkProperty($params, 'station_id')){
            $conditions['Station.Station_ID'] = $this->arrayWrap($params->station_id);;
        }else{
            $this->set('stations', new stdClass());
            return null;
        }

        $results = $this->Station->find('all', array(
            'fields' => array("Station_Name", "Latitude", "Longitude", "Station_ID"),
            'conditions' => $conditions
        ));

        $stations = array();
        if(!empty($results)){
            foreach($results as $result){
                $station = new Site();
                $station->latitude = $result["Station"]["Latitude"];
                $station->longitude = $result["Station"]["Longitude"];
                $station->station_name = $this->CleanText->cleanText($result['Station']['Station_Name']);
                $station->station_id = $result["Station"]["Station_ID"];
                $stations[] = $station;
            }
        }

        $this->set('stations', $stations);
        return $stations;

    }
    
    /**
     * Function to get all stations which have registered an individual belonging
     * to a set of a species.
     */
    public function getStationsWithSpecies($params=null){        
        $stations = array();
 
        $species_ids = array();
        /**
         * Make sure species ids is an array, if it is instead a singular object.
         */
        if($this->checkProperty($params, "species_id")){
            $species_ids = $this->arrayWrap($params->species_id);
        }else{
            $this->set('stations', array());
            return null;
        }


        /**
         * Remove unwanted bindings.
         */
        $this->Observation->unbindModel(
                array('belongsTo' =>
                    array(
                        'observation2submission',
                        'observation2person',
                        'observation2ObservationGroup'
                    )
                )
        );

        /**
         * Create explicit mapping between station and observation
         */
        $this->Observation->bindModel(array(
            'hasOne' => array(
                        'Station' => array(
                                    'foreignKey' => false,
                                    'conditions' => array('observation2StationSpeciesIndividual.Station_ID = Station.Station_ID')
                        )
                )
        ));

        /**
         * Using observation model to run this query, because doing so allows for a
         * way to more easily filter out species which haven't had an observation
         * made about them (as per business rules of vis tool). Keeps viz tool
         * from displaying many stations with species but no observations.
         */
        $ss = $this->Observation->find('all', array(
            'fields' => array(
                        "DISTINCT Station.Latitude",
                        "Station.Longitude",
                        "Station.Station_Name",
                        "observation2StationSpeciesIndividual.Station_ID"
            ),
            'conditions' => array(
                "observation2StationSpeciesIndividual.Species_ID" => $species_ids,
                '(Observation.Deleted IS NULL OR Observation.Deleted <> 1)'
            )
        ));
        
        foreach($ss as $s){
            $station = new Site();
            $station->latitude = $s['Station']['Latitude'];
            $station->longitude = $s['Station']['Longitude'];
            $station->station_name = $s['Station']['Station_Name'];
            $station->station_id = $s['observation2StationSpeciesIndividual']['Station_ID'];

            $stations[] = $station;
        }
        
        $this->set("stations", $stations);
        return $stations;
        
        
    }

    /**
     * Returns set of all states, and the number of stations in each state.
     * State field not required when entering new station. Many stations with
     * null state. Need to automate script to update stations based on coordinates
     * regularly.
     */
    public function getStationCountByState(){
        $results = array();
        
        $ss = $this->VwStationsByState->find('all');
        
        foreach($ss as $s){
            
            $obj = new StationState();
            
            $obj->state = $s['VwStationsByState']['State'];
            $obj->number_stations = $s['VwStationsByState']['Number_Stations'];
            
            $results[] = $obj;
            
        }
        
        $this->set('results', $results);
        return $results;
    }



    public function getStationsForNetwork($params=null){

        $stations = array();
        if($this->checkProperty($params, 'network_id')){
            $data = new stdClass();
            $data->network_ids = array($params->network_id);
            $stations = $this->getAllStations($data);
        }else{
            $this->set('stations', array());
            return null;
        }

        $this->set('stations', $stations);
        return $stations;


    }



    /**
     * Simple function to simply return a list of all states used in the database.
     * Standardizes lists showing states in apps which use web service.
     */
    public function getStates(){
        $results = array();

        $states = $this->StateList->find('all');

        foreach($states as $state){
            $state_obj = new State();
            $state_obj->state_code = $state['StateList']['State_Code'];
            $state_obj->state_name = $state['StateList']['State_Name'];
            $state_obj->state_id = $state['StateList']['State_ID'];

            $results [] = $state_obj;
        }

        $this->set('states', $results);
        return $results;
    }


    /**
     * 
     * @param array $params - An array of variables provided to the function through either the HTTP GET or POST variables.
     * @param XMLWriter $out - An XMLWriter object configured to write to standard out. This is provided by the master controller.
     * @param string $format - The specified output format. This is provided by the request URL, either JSON or XML, and is set
     * in the master controller
     */
    public function getStationDetails($params,$out, $format){


        App::import('Model','VwStationDetails');
        $this->VwStationDetails =  new VwStationDetails();

        /**
         * Create an output emitter object, configured based on the output format
         * and the type of data to output.
         */
        $emitter = $this->getEmitter($format, $out, "stations", "getStationDetailsResponse");
        $conditions = array();

        if($this->checkProperty($params, "site_id")){
            $conditions["VwStationDetails.Site_ID "] = $this->arrayWrap($params->site_id);
        }
        
        if($this->checkProperty($params, "ids")){
            $ids = urldecode($params->ids);
            $ids = explode(",", $ids);
            
            $conditions["VwStationDetails.Site_ID "] = $ids;
        }
        
        

        $joins = null;      
        $fields = array('VwStationDetails.*');
        
        if($this->checkProperty($params, "no_live") && $params->no_live == "true"){
            $joins = array();
        }else{
            $fields[] = 'COUNT(DISTINCT ssi.Individual_ID) AS Num_Individuals';
            $fields[] = 'COUNT(DISTINCT o.Observation_ID) AS Num_Records';
            $fields[] = 'n.Name AS Group_Name';
            
            $joins = array(
                    array(
                        "table" => "Station_Species_Individual",
                        "alias" => "ssi",
                        "type" => "left",
                        "conditions" => "ssi.Station_ID = VwStationDetails.Site_ID"
                    ),
                    array(
                        "table" => "Observation",
                        "alias" => "o",
                        "type" => "left",
                        "conditions" => "o.Individual_ID = ssi.Individual_ID"
                    ),
                    array(
                        "table" => "Network_Station",
                        "alias" => "ns",
                        "type" => "left",
                        "conditions" => "ns.Station_ID = VwStationDetails.Site_ID"
                    ),
                    array(
                        "table" => "Network",
                        "alias" => "n",
                        "type" => "left",
                        "conditions" => "n.Network_ID = ns.Network_ID"
                    )
            );            
            
            
            $conditions[] = "(o.Deleted IS NULL OR o.Deleted <> 1)";
        }        

        $results = null;

        

        /**
         * Output the appropriate headers / start output for the dataset.
         */
        $emitter->emitHeader();

        $dbo = $this->VwStationDetails->getDataSource();
        $query = $dbo->buildStatement(array(
            "fields" => $fields,
            "table" => $dbo->fullTableName($this->VwStationDetails),
            "alias" => 'VwStationDetails',
            "conditions" => $conditions,
            "group" => "VwStationDetails.Site_ID",
            "limit" => null,
            "order" => null,
            "joins" => $joins
            ),
        $this->VwStationDetails
        );

        $results = mysql_unbuffered_query($query);
        while($result = mysql_fetch_array($results, MYSQL_ASSOC)){
            $result['node_name'] = "station_details";
            $result['Site_Comments'] =  str_replace(">", "less than", str_replace("<", "greater than", str_replace("&", "and", strip_tags(preg_replace('/[^(\x20-\x7F)]*/','',  $result['Site_Comments'])))));
            
            $emitter->emitNode($result);
            $out->flush();
        }

        /**
         * Outputs whatever closing tags is needed to create a valid response.
         */
        $emitter->emitFooter();
    }
    
    
    public function getDaymetData($props=null){
        $station_id = ($this->checkProperty($props, "station_id")) ? $props->station_id : null;
        $year = ($this->checkProperty($props, "year")) ? $props->year : null;
        $doy = ($this->checkProperty($props, "doy")) ? $props->doy : 1;
        
        if(!$this->checkProperty($props, 'station_id') || !$this->checkProperty($props, 'year')){
            $this->set('daymet', null);
            return null;
        }

        $daymet_data = $this->Station->getStationWithClimateData($station_id, $year, $doy);

        $this->set('daymet', $daymet_data);
        return  $daymet_data; 
    }
    
    public function getModisForStation($props=null){
             
        
        $station_id = ($this->checkProperty($props, "station_id")) ? $props->station_id : null;
        $year = ($this->checkProperty($props, "year")) ? $props->year : null;

        if(!$this->checkProperty($props, 'station_id') || !$this->checkProperty($props, 'year')){
            $this->set('modis', null);
            return null;
        }
        
        $modis_data = $this->Station->getStationModisValues($station_id, $year);
        
        $this->set('modis', $modis_data);
        return  $modis_data;  
    }
    
       
    
/**
 * TODO: This needs better input sanitation
 */    
    public function getModisForCoordinates($props=null){
        
        $modis_null_value = 32767;
        $npn_null_value = -9999;
        
        $latitude = ($this->checkProperty($props, "latitude")) ? $props->latitude : null;
        $longitude = ($this->checkProperty($props, "longitude")) ? $props->longitude : null;
        $year = ($this->checkProperty($props, "year")) ? $props->year : null;
        
        if(!$latitude || !$longitude){
            $this->set('modis', null);
            return null;
        }
        
        if(!$year){
            $this->set('modis', null);
            return null;            
        }
        
        $data_dir = "files" . DIRECTORY_SEPARATOR . "modis" . DIRECTORY_SEPARATOR;
        
        
        $v6_layer_names = array(
           'Greenup_0',
           'Greenup_1',
           'MidGreenup_0',
           'MidGreenup_1',
           'Peak_0',
           'Peak_1',
           'NumCycles',
           'Maturity_0',
           'Maturity_1',
           'MidGreendown_0',
           'MidGreendown_1',
           'Senescence_0',
           'Senescence_1',
           'Dormancy_0',
           'Dormancy_1',
           'EVI_Minimum_0',
           'EVI_Minimum_1',
           'EVI_Amplitude_0',
           'EVI_Amplitude_1',
           'EVI_Area_0',
           'EVI_Area_1',
           'QA_Detailed_0',
           'QA_Detailed_1',
           'QA_Overall_0',
           'QA_Overall_1'
        );
        
        $values = array();
        
        
        foreach($v6_layer_names as $layer_name){
            $output = null;
            $command = "gdallocationinfo -valonly -wgs84 " . $data_dir . "MCD12Q2.006_2007335_to_2016366-MCD12Q2.006_" . $layer_name . "_doy" . $year . "001_aid0001.tif " . $longitude . " " . $latitude; // -89.497414 40.627389";
            exec($command, $output);

            if(empty($output) || $output[0] == $modis_null_value){
                $output = $npn_null_value;
            }else{
                $output = $output[0];
            }
            
            $values[$layer_name] = $output;
        }        
        
        
        $this->set('modis', $values);
        return $values;
        
        
    }

    public function getDaymetDataTemp($props=null){
        
        $station_id = ($this->checkProperty($props, "station_id")) ? $props->station_id : null;
        $year = ($this->checkProperty($props, "year")) ? $props->year : null;
        $doy = ($this->checkProperty($props, "doy")) ? $props->doy : 1;
        
        if(!$this->checkProperty($props, 'station_id') || !$this->checkProperty($props, 'year')){
            $this->set('daymet', null);
            return  null;
        }
         
        
        $daymet_data = $this->Station->getStationWithClimateDataTemp($station_id, $year, $doy);

        $this->set('daymet', $daymet_data);
        return  $daymet_data;
        
        
        
    }
    
    public function getTimeSeries($props=null){
        
        $latitude = ($this->checkProperty($props, "latitude")) ? $props->latitude : null;
        $longitude = ($this->checkProperty($props, "longitude")) ? $props->longitude : null;
        $start_date = ($this->checkProperty($props, "start_date")) ? $props->start_date : null;
        $end_date = ($this->checkProperty($props, "end_date")) ? $props->end_date : null;    
        $layer = ($this->checkProperty($props, "layer")) ? $props->layer : null;       
        
        if($latitude == null || $longitude == null || $layer == null){
            $this->set('time_series', array());
            return array();
        }
        
        if( ($start_date == null || $end_date == null) && ($layer == "gdd:agdd" || $layer == "gdd:agdd_50f") ){
            $this->set('time_series', array());
            return array();            
        }
        
        $start_date_obj = new DateTime($start_date);
        $end_date_obj = new DateTime($end_date);
        
        $end_year = $end_date_obj->format('Y');
        $start_year = $start_date_obj->format('Y');
        
        if(($layer == "gdd:agdd" || $layer == "gdd:agdd_50f")){
            if($end_date_obj <= $start_date_obj){
                $this->set('time_series', array());
                return array();            
            }

            if($start_year != $end_year){
                $this->set('time_series', array());
                return array();             
            }
        }
        
        
        $table = "";
        $base = "";
        $where_clause = "";
        $time_field = "";
        
        if($layer == "gdd:agdd"){
            $table = "agdd_" . $start_year; 
            $base = "32";
            $where_clause = "rast_date BETWEEN '". $start_date . "' AND '". $end_date . "' " .
                    "AND base = " . $base . " ";
            $time_field = "rast_date";
        }else if($layer == "gdd:agdd_50f"){
            $table = "agdd_" . $start_year;
            $base = "50";
            $where_clause = "rast_date BETWEEN '". $start_date . "' AND '". $end_date . "' " .
                    "AND base = " . $base . " ";            
            $time_field = "rast_date";
        }else if($layer == "gdd:30yr_avg_agdd"){
            $base = 32;
            $table = "prism_30yr_avg_agdd";
            $where_clause = "base = " . $base . " ";
            $time_field = "doy";
        }else if($layer == "gdd:30yr_avg_agdd_50f"){
            $base = 50;
            $table = "prism_30yr_avg_agdd";
            $where_clause = "base = " . $base . " ";
            $time_field = "doy";
        }
    
        App::import('Model','Agdd');
        $this->agdd =  new Agdd();
     

        $res = $this->agdd->query("SELECT " . $time_field . ", st_value(rast,ST_SetSRID(ST_Point(" . $longitude . ", " . $latitude . "),4269)) " .
                                  "FROM  " . $table . " " .
                                  "WHERE " . $where_clause . " AND " .
                                  "ST_Intersects(rast, ST_SetSRID(ST_MakePoint(" . $longitude . ", " . $latitude . "),4269)) " .
                                  "ORDER BY " . $time_field);

        
        $time_series = array();
        
        if($res and count($res) > 0){
            $i=1;
            foreach($res as $data_point){
                
                $dp = $data_point[0];
                $ret_data = array("point_value" => $dp['st_value']);
                
                if(($layer == "gdd:agdd" || $layer == "gdd:agdd_50f")){
                    
                    $date = new DateTime($dp['rast_date']);
                    $ret_data['doy'] = $date->format('z') + 1;
                    $ret_data['date'] = $date->format('Y-m-d');

                    
                }else{
                    $ret_data['doy'] = $i++;
                }
                
                $time_series[] = $ret_data;
//                $time_series[] = $dp['st_value'];
            }
            
        }
        
        $this->set('time_series', $time_series);
        return  $time_series;
        
        
    }
    

} 