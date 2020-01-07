<?php

class Net{}
class Status{}

/**
 * Controller for getting information about NPN networks.
 */
class NetworksController extends AppController{
    
    
    public $uses = array('NetworkPerson','Network', 'Person', 'TaxonomyTermData', 'vwGroupAdmins');
    
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
    
    /**
     * Only real function in this controller. Simply finds all networks with
     * user display = 1.
     */
    
    public function getPartnerNetworks($params=null){
        
        
        $joins = array();
        $where = array();
        $group = array();
        
        $this->Network->
                unbindModel(
                        array('hasMany' =>
                            array('network2networkperson', 'network2networkstation'),
                              'hasAndBelongsToMany' =>
                            array('network2species')
                ));        
        
        $active_only = $this->resolveBooleanText($params, "active_only");

        
        
        if($active_only){
            $joins[] = array(
                'table' => 'Network_Station',
                'type' => 'left',
                'conditions' => array(
                    'Network_Station.Network_ID = Network.Network_ID'                       
                )                
            );
			
            $joins[] = array(
                'table' => 'Cached_Summarized_Data',
                'type' => 'left',
                'conditions' => array(
                    'Cached_Summarized_Data.Site_ID = Network_Station.Station_ID'                       
                )                
            );			
            
            $where[] = 'Cached_Summarized_Data.Series_ID IS NOT NULL';
            
            $group[] = 'Network.Network_ID';
            
        }
        
        $networks = array();
        
        $ns =  $this->Network->find('all', array(
            'fields' => array(
                            "Name",
                            "Network_ID"
                        ),
            'order' => array(
                'Name'
            ),
            'joins' => $joins,
            'conditions' => $where,
            'group' => $group
            ));

        
        /**
         * "no_group_site" type networks can't be joined by
         * station_id because data is associated on the member user level.
         * Adding this as an additional qualifier in the above query's where
         * clause makes the query extremely slow, so as a solution we assume
         * that every no_group_site type group probably has at least one
         * observation (there is so few of them), and instead just select groups
         * of that type and then sort them by name into the array generated
         * with the first query.
         * Of course, this is only necessary if the 'active only' filter is
         * being applied.
         */
        if($active_only){
            $no_group_sites =  $this->Network->find('all', array(
                'fields' => array(
                                "Name",
                                "Network_ID"
                            ),
                'order' => array(
                    'Name'
                ),
                'conditions' => array('Network.no_group_site' => 1),
                ));

            $ns = array_merge($ns, $no_group_sites);
            
            usort($ns, array('NetworksController','sortByName'));
            
        }
        
       

        foreach($ns as $theNetwork){
            
            $aNetwork = new Net();
            $aNetwork->network_id = $theNetwork['Network']['Network_ID'];
            $aNetwork->network_name = $theNetwork['Network']['Name'];
            $networks[] = $aNetwork;
        }



        $this->set('networks', $networks);
        return $networks;
    }
    
    
    public static function sortByName($a, $b) {
        return strcmp($a['Network']['Name'], $b['Network']['Name']);
    }          
    
       
    
    public function getTopLevelNetworks(){
        
        $networks = array();
        
        $nr = $this->TaxonomyTermData->find('all', array(
           'fields' => array(
               'n.Network_ID',
               'n.Name'
           ),
           'conditions' => array(
             'vid' => 6,
             'taxonomy_term_hierarchy.parent' => 0,
              'n.Network_ID IS NOT NULL'
           ),
           'order' => 'n.Name', 
           'joins' => array(
               array(
                   'table' => 'taxonomy_term_hierarchy',
                   'type' => 'left',
                   'conditions' => array(
                       'taxonomy_term_hierarchy.tid = TaxonomyTermData.tid'
                       
                   )
               ),
               array(
                   'table' => 'usanpn2.Network',
                   'alias' => 'n',
                   'type' => 'left',
                   'conditions' => array(
                       'n.name = TaxonomyTermData.name'
                       
                   )
               )               
           )
        ));
        
                
        foreach($nr as $theNetwork){
            
            $aNetwork = new Net();
            $aNetwork->network_id = $theNetwork['n']['Network_ID'];
            $aNetwork->network_name = $theNetwork['n']['Name'];
            $networks[] = $aNetwork;
        }        
        
        
        $this->set('networks', $networks);
        return $networks;
    }
    
    private function getChildNetworks($params){
        $networks = array();
        
        
        if(property_exists($params, "parent_network_id")){
            
            
            $net_id = $params->parent_network_id;
            

            
            
            
            $nr = $this->TaxonomyTermData->find('all', array(
            'fields' => array(
                'n2.Network_ID',
                'n2.Name'
            ),
            'conditions' => array(
                'TaxonomyTermData.vid' => 6,
                'n.Network_ID' => $net_id

            ),
            'order' => 'n2.Name',
            'joins' => array(
                array(
                    'table' => 'taxonomy_term_hierarchy',
                    'type' => 'left',
                    'conditions' => array(
                        'taxonomy_term_hierarchy.parent = TaxonomyTermData.tid'

                    )
                ),
                array(
                    'table' => 'usanpn2.Network',
                    'alias' => 'n',
                    'type' => 'inner',
                    'conditions' => array(
                        'n.name = TaxonomyTermData.name'

                    )
                ),
                array(
                    'table' => 'taxonomy_term_data',
                    'alias' => 't2',
                    'type' => 'left',
                    'conditions' => array(
                        'taxonomy_term_hierarchy.tid = t2.tid'
                    )
                ),
                array(
                    'table' => 'usanpn2.Network',
                    'alias' => 'n2',
                    'type' => 'left',
                    'conditions' => array(
                        'n2.Name = t2.name'
                    )
                )
                )
            ));

            foreach($nr as $theNetwork){

                $aNetwork = new Net();
                $aNetwork->network_id = $theNetwork['n2']['Network_ID'];
                $aNetwork->network_name = $theNetwork['n2']['Name'];
                $networks[] = $aNetwork;
            }
                
            
        }
        $this->set('networks', $networks);
        return $networks;                    
        
    }

    
    public function getNetworksForUser($params=null){


        if(!$this->isProtected()){
            $this->set('networks', array());
            return array();
        }
        $networks = array();
        if($this->checkProperty($params, "access_token") && $this->checkProperty($params, "consumer_key")){        
           
            $person_id = $this->ValidateUser->verifyUser(null, null, $this->Person, $params->access_token, $params->consumer_key);         
            if(!$person_id){              
                $this->set('networks', array());
                return array();
            }else{
                $params->person_id = $person_id;
            }
        }


        if($this->checkProperty($params, "person_id")){

            $data = $this->NetworkPerson->findNetworksForPerson($params->person_id);
            foreach($data as $net){
                $network = new Net();
                $network->network_id = $net["networkperson2Network"]["Network_ID"];
                $network->network_name = $net["networkperson2Network"]["Name"];
                
                $params->network_id = $network->network_id;
                $role = $this->getUsernetworkStatus($params);
                $network->role_id = $role->status;
                $networks[] = $network;

            }
        }else{
            $this->set('networks', array());
            return array();
        }

        $this->set('networks', $networks);
        return $networks;


    }


    public function getUserNetworkStatus($params=null){

        if(!$this->isProtected()){
            $this->set('status', null);
            return null;
        }

        $conditions = array();

        if($this->checkProperty($params, "access_token") && $this->checkProperty($params, "consumer_key")){
            $person_id = $this->ValidateUser->verifyUser(null, null, $this->Person, $params->access_token, $params->consumer_key);
            if(!$person_id){
                $this->set('status', new stdClass());
                return null;
            }else{
                $params->person_id = $person_id;
            }
        }

        if($this->checkProperty($params, 'network_id') && !empty($params->network_id)){
            $conditions['NetworkPerson.Network_ID ='] = $params->network_id;
        }else{

            $this->set('status', new stdClass());
            return null;
        }

        if($this->checkProperty($params, "person_id")){
            $conditions["NetworkPerson.Person_ID ="] = $params->person_id;
            $results = $this->NetworkPerson->find('all', array(
               'conditions' => $conditions
            ));
            $status = new Status();
            $status->status = $results[0]['netperson2approle'][0]['Role_ID'];
            $this->set('status',$status);
            return $status;

        }else{
            $this->set('status', new stdClass());
            return null;
        }

    }
    
    public function getObserversByMonth($params=null){
        $network_id = null;
        $station_ids = array();
        $year = null;
        $results = array();
        $joins = array();
        
        /**
         * Limit results from querying the network model.s
         */
        $this->Network->
                unbindModel(
                        array('hasMany' =>
                            array('network2networkperson', 'network2networkstation'),
                              'hasAndBelongsToMany' =>
                            array('network2species')
                ), false);        
        
        
        /**
         * If user doesn't specify both a network and a year,
         * throw out an empty response
         */
        if($this->checkProperty($params, "network_id")){
            $network_id = $params->network_id;
        } elseif ($this->checkProperty($params, "station_id")){
            // $station_id = $params->station_id;
            $station_ids = $this->arrayWrap($params->station_id);
        }

        
        if($this->checkProperty($params, "year")){
            $year = $params->year;
        }
        
        
        
        if(($network_id == null && $station_ids == array()) || $year == null){
            $this->set('results', $results);
            return $results;
        }
        
        $results = new stdClass();
        $results->year = $year;
        $results->network_id = $network_id;
        $results->station_ids = $station_ids;
        $results->months = array();
        
        if(!empty($network_id)){
            /**
             * This will run two separate queries for each of the metric's being
             * counted. This first query gets the number of active observers in the
             * year.
             */
            $conditions = array(
                'Network.Network_ID' => $network_id,
                'YEAR(Observation_Date)' => $year
            );
            
            $conditions [] = '(Observation.Deleted IS NULL OR Observation.Deleted <> 1  )';
            
            
            
            $joins[] = array(
                'table' => 'Network_Station',
                'type' => 'left',
                'conditions' => array(
                    'Network_Station.Network_ID = Network.Network_ID'                       
                )                
            );
            
            $joins[] = array(
                'table' => 'Station_Species_Individual',
                'type' => 'left',
                'conditions' => array(
                    'Station_Species_Individual.Station_ID = Network_Station.Station_ID'                       
                )                
            );        
            
            $joins[] = array(
                'table' => 'Observation',
                'type' => 'left',
                'conditions' => array(
                    'Observation.Individual_ID = Station_Species_Individual.Individual_ID'                       
                )                
            );        
            
            $active_observers_results = $this->Network->find('all', array(
            'conditions' => $conditions,
                'fields' => array(
                    'GROUP_CONCAT(DISTINCT Observer_ID) `Observers`',
                    'MONTH(Observation_Date) `Month`'
                ),
                'joins' => $joins,
                'group' => 'MONTH(Observation_Date)'
            ));
            
            
            /**
             * This now has the active observer results stored but will go ahead
             * and run the other query before doing anything else. The second
             * query will get the number of new observers added in the month/year.
             */
            
            $conditions = array(
                'Network.Network_ID' => $network_id,
                'YEAR(Create_Date)' => $year
            );
            
            $joins = array();
            $joins[] = array(
                'table' => 'Network_Person',
                'type' => 'left',
                'conditions' => array(
                    'Network_Person.Network_ID = Network.Network_ID'                       
                )                
            );
            $joins[] = array(
                'table' => 'Person',
                'type' => 'left',
                'conditions' => array(
                    'Person.Person_ID = Network_Person.Person_ID'                       
                )                
            );        
            
            $new_observers_results = $this->Network->find('all', array(
            'conditions' => $conditions,
                'fields' => array(
                    'GROUP_CONCAT(Person.Person_ID) `Observers`',
                    'MONTH(Person.Create_Date) `Month`',
                    'Network.Name'
                ),
                'joins' => $joins,
                'group' => 'MONTH(Create_Date)'
            ));
            

            /**
             * Iterate over the results for active observers per month and populate
             * the results array accordingly.
             */
            foreach($active_observers_results as $r){
                $r = $r[0];

                $obj = new stdClass();

                
                $obj->active_observers = explode(",",$r['Observers']);
                $results->months[$r['Month']] = $obj;
                
            }
            
            
            /**
             * Do the same iteration over the new observers per month array and
             * populate the results array. This loop has to check first to see if
             * a key has already been set and either creates a new object or
             * sets the property on the existing object accordingly.
             */
            foreach($new_observers_results as $r){
                
                $r = $r[0];
                
                if(array_key_exists($r['Month'], $results->months)){
                    $results->months[$r['Month']]->new_observers = explode(",",$r['Observers']);
                }else{
                    $obj = new stdClass();
                    $obj->new_observers = explode(",",$r['Observers']);
                    $results->months[$r['Month']] = $obj;
                }            
            }
            
            
            /**
             * Now, iterate each month of the year. If there is no object present at
             * all make a new object and set both values to zero.
             * If there is an object but one of the properties is missing, then set
             * it to zero.
             */
            for($i=1;$i<=12;$i++){
                if(array_key_exists($i, $results->months)){
                    if(!$this->checkProperty($results->months[$i],"active_observers")){
                        $results->months[$i]->active_observers = array();
                    }
                    
                    if(!$this->checkProperty($results->months[$i],"new_observers")){
                        $results->months[$i]->new_observers = array();
                    }
                    
                }else{
                    $obj = new stdClass();
                    $obj->new_observers = array();
                    $obj->active_observers = array();
                    $results->months[$i] = $obj;
                }
            }
            
            /**
             * Sort the array by number and return the results
             */
            ksort($results->months, SORT_NUMERIC);
            

            
            $db_results = $this->Network->find('first',array(
                'fields' => array('Network.Name')
                    ,
                'conditions' => array('Network.Network_ID' => $network_id)
            ));       
            
            if($db_results != null){
                $results->network_name = $db_results['Network']['Name'];
            }
        } elseif (!empty($station_ids)) {
            $conditions = array(
                'YEAR(Observation_Date)' => $year
            );

            foreach($station_ids as $station_id){
                $conditions['OR'][] = array('Network_Station.Station_ID' => $station_id);
            }
            
            $conditions[] = '(Observation.Deleted IS NULL OR Observation.Deleted <> 1  )';
            
            $joins[] = array(
                'table' => 'Network_Station',
                'type' => 'left',
                'conditions' => array(
                    'Network_Station.Network_ID = Network.Network_ID'                       
                )                
            );
            
            $joins[] = array(
                'table' => 'Station_Species_Individual',
                'type' => 'left',
                'conditions' => array(
                    'Station_Species_Individual.Station_ID = Network_Station.Station_ID'                       
                )                
            );        
            
            $joins[] = array(
                'table' => 'Observation',
                'type' => 'left',
                'conditions' => array(
                    'Observation.Individual_ID = Station_Species_Individual.Individual_ID'                       
                )                
            );        
            
            $active_observers_results = $this->Network->find('all', array(
            'conditions' => $conditions,
                'fields' => array(
                    'GROUP_CONCAT(DISTINCT Observer_ID) `Observers`',
                    'MONTH(Observation_Date) `Month`'
                ),
                'joins' => $joins,
                'group' => 'MONTH(Observation_Date)'
            ));
            
            
            /**
             * This now has the active observer results stored but will go ahead
             * and run the other query before doing anything else. The second
             * query will get the number of new observers added in the month/year.
             */
            
            $conditions = array(
                'YEAR(Create_Date)' => $year
            );

            foreach($station_ids as $station_id){
                $conditions['OR'][] = array('Network_Station.Station_ID' => $station_id);
            }
            
            $joins = array();
            $joins[] = array(
                'table' => 'Network_Person',
                'type' => 'left',
                'conditions' => array(
                    'Network_Person.Network_ID = Network.Network_ID'                       
                )                
            );
            $joins[] = array(
                'table' => 'Person',
                'type' => 'left',
                'conditions' => array(
                    'Person.Person_ID = Network_Person.Person_ID'                       
                )                
            );        
            
            $new_observers_results = $this->Network->find('all', array(
            'conditions' => $conditions,
                'fields' => array(
                    'GROUP_CONCAT(Person.Person_ID) `Observers`',
                    'MONTH(Person.Create_Date) `Month`',
                    'Network.Name'
                ),
                'joins' => $joins,
                'group' => 'MONTH(Create_Date)'
            ));
            
            /**
             * Iterate over the results for active observers per month and populate
             * the results array accordingly.
             */
            foreach($active_observers_results as $r){
                $r = $r[0];

                $obj = new stdClass();

                
                $obj->active_observers = explode(",",$r['Observers']);
                $results->months[$r['Month']] = $obj;
                
            }
            
            
            /**
             * Do the same iteration over the new observers per month array and
             * populate the results array. This loop has to check first to see if
             * a key has already been set and either creates a new object or
             * sets the property on the existing object accordingly.
             */
            foreach($new_observers_results as $r){
                
                $r = $r[0];
                
                if(array_key_exists($r['Month'], $results->months)){
                    $results->months[$r['Month']]->new_observers = explode(",",$r['Observers']);
                }else{
                    $obj = new stdClass();
                    $obj->new_observers = explode(",",$r['Observers']);
                    $results->months[$r['Month']] = $obj;
                }            
            }
            
            
            /**
             * Now, iterate each month of the year. If there is no object present at
             * all make a new object and set both values to zero.
             * If there is an object but one of the properties is missing, then set
             * it to zero.
             */
            for($i=1;$i<=12;$i++){
                if(array_key_exists($i, $results->months)){
                    if(!$this->checkProperty($results->months[$i],"active_observers")){
                        $results->months[$i]->active_observers = array();
                    }
                    
                    if(!$this->checkProperty($results->months[$i],"new_observers")){
                        $results->months[$i]->new_observers = array();
                    }
                    
                }else{
                    $obj = new stdClass();
                    $obj->new_observers = array();
                    $obj->active_observers = array();
                    $results->months[$i] = $obj;
                }
            }
            
            /**
             * Sort the array by number and return the results
             */
            ksort($results->months, SORT_NUMERIC);
            

            
            $db_results = $this->Network->find('first',array(
                'fields' => array('Network.Name')
                    ,
                'conditions' => array('Network_Station.Station_ID' => $station_id)
            ));       
            
            if($db_results != null){
                $results->network_name = $db_results['Network']['Name'];
            }
        }
        
        
        
        $this->set('results', $results);
        return $results;        
    }
    
    public function getSpeciesForNetwork($params=null){
        
        $network_id = null;
        $results = array();
        $joins = array();
        
        /**
         * Limit results from querying the network model.s
         */
        $this->Network->
                unbindModel(
                        array('hasMany' =>
                            array('network2networkperson', 'network2networkstation'),
                              'hasAndBelongsToMany' =>
                            array('network2species')
                ), false);        
        
        
        /**
         * If user doesn't specify both a network and a year,
         * throw out an empty response
         */
        if($this->checkProperty($params, "network_id")){
            $network_id = $params->network_id;
        }
        
        
        $conditions = array(
            'Network.Network_ID' => $network_id
        );
        
       if($network_id == null){
            $this->set('results', $results);
            return $results;
        }        
        
        $joins = array();
        $joins[] = array(
            'table' => 'Network_Station',
            'type' => 'left',
            'conditions' => array(
                'Network_Station.Network_ID = Network.Network_ID'                       
            )                
        );
        $joins[] = array(
            'table' => 'Station_Species_Individual',
            'type' => 'left',
            'conditions' => array(
                'Station_Species_Individual.Station_ID = Network_Station.Station_ID'                       
            )                
        );
        
        $joins[] = array(
            'table' => 'Species',
            'type' => 'left',
            'conditions' => array(
                'Station_Species_Individual.Species_ID = Species.Species_ID'                       
            )                
        );        
        
        $species = $this->Network->find('all', array(
           'conditions' => $conditions,
            'fields' => array(
                'Species.Species_ID',
                'Species.Genus',
                'Species.Species',
                'Species.Common_Name',
                'CONCAT(\'https://www.usanpn.org\', Species.About_URL) `url`'
            ),
            'joins' => $joins,
            'group' => array(
                'Species.Species_ID',
                'Species.Species_ID HAVING Species.Species_ID IS NOT NULL'
                )
        ));
        
        foreach($species as $sp){
            $obj = new stdClass();
            $obj->species_id = $sp['Species']['Species_ID'];
            $obj->genus = $sp['Species']['Genus'];
            $obj->species = $sp['Species']['Species'];
            $obj->common_name = $sp['Species']['Common_Name'];
            $obj->url = $sp[0]['url'];
            
            $results[] = $obj;
        }
        
        
        $this->set('results', $results);
        return $results;        
    }
    
    
    public function getAdminsForNetwork($params=null){
        
        $network_id = null;
        $results = array();

        /**
         * If user doesn't specify both a network and a year,
         * throw out an empty response
         */
        if($this->checkProperty($params, "network_id")){
            $network_id = $params->network_id;
        }
        
       if($network_id == null){
            $this->set('results', $results);
            return $results;
        }             

        $conditions = array(
            'vwGroupAdmins.Network_ID' => $network_id
        );

        
        
        $users = $this->vwGroupAdmins->find('all', array(
           'conditions' => $conditions,
            'fields' => array(
                'vwGroupAdmins.uid'
            )
        ));
        
        foreach($users as $u){
            $this->log($u);
            $results[] = $u['vwGroupAdmins']['uid'];
        }
        
        
        $this->set('results', $results);
        return $results;        
    }    
    
    
    public function getSiteVisitFrequency($params=null){
        $network_id = null;
        $year = null;
        $results = array();
        $joins = array();
        
        /**
         * Limit results from querying the network model.s
         */
        $this->Network->
                unbindModel(
                        array('hasMany' =>
                            array('network2networkperson', 'network2networkstation'),
                              'hasAndBelongsToMany' =>
                            array('network2species')
                ), false);        
        
        
        /**
         * If user doesn't specify both a network and a year,
         * throw out an empty response
         */
        if($this->checkProperty($params, "network_id")){
            $network_id = $params->network_id;
        }
        
        if($this->checkProperty($params, "year")){
            $year = $params->year;
        }
        
        
        if($network_id == null || $year == null){            
            $this->set('results', $results);
            return $results;
        }
        
        $results = new stdClass();
        $results->year = $year;
        $results->network_id = $network_id;
        $results->stations = array();
        
        $conditions = array(
            'Network_Station.Network_ID' => $network_id,
            'Year(Observation_Date)' => $year
        );
        
        $conditions [] = '(Observation.Deleted IS NULL OR Observation.Deleted <> 1  )';
        
        
        
        $grouping = array(
            'Station_Species_Individual.Station_ID',
            '`Month`',
            'Station_Species_Individual.Station_ID HAVING Station_Species_Individual.Station_ID IS NOT NULL'
        );
        
        $joins = array();
        $joins[] = array(
            'table' => 'Network_Station',
            'type' => 'left',
            'conditions' => array(
                'Network.Network_ID = Network_Station.Network_ID'                       
            )                
        );
        
        $joins[] = array(
            'table' => 'Station',
            'type' => 'left',
            'conditions' => array(
                'Station.Station_ID = Network_Station.Station_ID'                       
            )                
        );        
        
        $joins[] = array(
            'table' => 'Station_Species_Individual',
            'type' => 'left',
            'conditions' => array(
                'Station_Species_Individual.Station_ID = Network_Station.Station_ID'                       
            )                
        );

        $joins[] = array(
            'table' => 'Observation',
            'type' => 'left',
            'conditions' => array(
                'Observation.Individual_ID = Station_Species_Individual.Individual_ID'                       
            )                
        );

        $site_visit_db_results = $this->Network->find('all', array(
           'conditions' => $conditions,
            'fields' => array(
                'COUNT(DISTINCT Observation.Observation_Group_ID) `Site_Visits`',
                'Station_Species_Individual.Station_ID',
                'Station.Station_Name',
                'MONTH(Observation_Date) `Month`',
                'Network.Name'
            ),
            'joins' => $joins,
            'group' => $grouping,
            'order' => array('Station_Species_Individual.Station_ID', 'Month')
        ));
        
        foreach($site_visit_db_results as $db_result){
            $station_id = $db_result['Station_Species_Individual']['Station_ID'];
            
            if(key_exists($station_id, $results->stations)){
                $results->stations[$station_id]->months[$db_result[0]['Month']] = $db_result[0]['Site_Visits'];
            }else{
                $station = new stdClass();
                
                $station->months = array();
                $station->station_name = $db_result['Station']['Station_Name'];
                $station->station_id = $db_result['Station_Species_Individual']['Station_ID'];
                $station->months[$db_result[0]['Month']] = $db_result[0]['Site_Visits'];
                $results->stations[$station_id] = $station;
            }   
        }
        
        foreach($results->stations as $station){
            for($i=1;$i<=12;$i++){
                if(!key_exists($i, $station->months)){
                    $station->months[$i] = 0;
                }
            }
            
            ksort($station->months);
            $station->months = array_values($station->months);
        }
        
        $results->stations = array_values($results->stations);
        if(count($site_visit_db_results) > 0){
            $results->network_name = $site_visit_db_results[0]['Network']['Name'];
        }else{
            $results->network_name = null;
        }

        $this->set('results',$results);
        return $results;
        
        
    }
    
    public function getNetworkTree(){
        
        $primary_networks = $this->getTopLevelNetworks();
        $networks = array();

        foreach($primary_networks as $network){
            
            
            $package = new stdClass();
            $package->parent_network_id = $network->network_id;
            $children = $this->getChildNetworks($package);
                        
            if(!empty($children)){
                $secondary_networks = array();
                
                foreach($children as $child){
                    $package = new stdClass();
                    $package->parent_network_id = $child->network_id;
                    $tertiary_children = $this->getChildNetworks($package);
                    
                    if(!empty($tertiary_children)){
                        $tertiary_networks = array();
                        foreach($tertiary_children as $tn){
                            $package = new stdClass();
                            $package->parent_network_id = $tn->network_id;
                            $quaternary_children = $this->getChildNetworks($package);
                            if(!empty($quaternary_children)){
                                $quaternary_networks = array();
                                foreach($quaternary_children as $qn){
                                    if(!empty($qn->network_id)){
                                        $quaternary_networks[] = $qn;
                                    }
                                }

                                if(!empty($quaternary_networks)){
                                    $tn->quaternary_network = $quaternary_networks;
                                }

                            }
                            if(!empty($tn->network_id)){
                                $tertiary_networks[] = $tn;
                            }
                        }
                        
                        if(!empty($tertiary_networks)){
                            $child->tertiary_network = $tertiary_networks;
                        }
                        
                    }
                    if(!empty($child->network_id)){
                        $secondary_networks[] = $child;
                    }

                }
                
                if(!empty($secondary_networks)){
                    $network->secondary_network = $secondary_networks;
                }
            }
            
            if(!empty($network->network_id)){
                $networks[] = $network;
            }
        }
        
        $this->set('networks', $networks);
        return $networks;
    }    
} 

