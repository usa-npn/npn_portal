<?php

abstract class GenericObservationSearch extends Object{
    
    protected $search_params;
    protected $conditions;
    protected $ors;
    protected $emitter;
    protected $fields;
    protected $allowed_values;
    protected $group_by;
    protected $aggregate_fields;
    
    protected $order_by = array();
    
    protected $climate_data_selected = ["tmax_winter" => false,
            "tmax_spring" => false,
            "tmax_summer" => false,
            "tmax_fall" => false,
            "tmax" => false,
            "tmin" => false,
            "tmaxf" => false,
            "tminf" => false,        
            "tmin_winter" => false,
            "tmin_spring" => false,
            "tmin_summer" => false,
            "tmin_fall" => false,
            "prcp_winter" => false,
            "prcp_spring" => false,
            "prcp_summer" => false,
            "prcp_fall" => false,
            "prcp" => false,
            "gdd" => false,
            "se_gdd" => false,
            "mean_gdd" => false,
            "gddf" => false,
            "mean_gddf" => false,
            "se_gddf" => false,
            "daylength" => false,
            "mean_daylength" => false,
            "se_daylength" => false,
            "se_accum_prcp" => false,
            "mean_accum_prcp" => false,
            "acc_prcp" => false];
    
    public function __construct($emitter){
        $this->search_params = array('DataSearchParams' => array());
        $this->conditions = array();
        $this->ors = array();
        $this->emitter = $emitter;
        
        App::import('Component', 'CheckProperty');
        $this->CheckProperty = new CheckPropertyComponent();
        
        App::import('Component', 'ArrayWrap');
        $this->ArrayWrap = new ArrayWrapComponent();        
        
        parent::__construct();
    }
    
    abstract public function preprocessResults($data, &$result_set);
    
    
    public function setConfigParameters(){
        ini_set('max_execution_time', -1);
        ini_set('memory_limit', -1);
        ini_set('default_socket_timeout', 10000);

    }
    
    public function importModels(){
        App::import('Model','CachedObservation');
        App::import('Model','CachedNetworkObservation');
        App::import('Model','DataSearchParams');
        App::import('Model','CachedSummarizedData');
        
        $this->CachedObservation =  new CachedObservation();
        $this->DataSearchParams = new DataSearchParams();
        $this->CachedNetworkObservation = new CachedNetworkObservation();
        $this->CachedSummarizedData = new CachedSummarizedData();
        

        
    }
    
    public function checkSourceParameter($params){
        return $this->CheckProperty->checkProperty($params,'request_src');
                    
    }
    
    
    /**
     * Unsets phenophase_id from the search params if pheno_class_id is set.
     * That is, you can only search by one or the other and pheno class id
     * takes precedence.
     */
    public function cleanPhenoClassParams($params){
        if($this->CheckProperty->checkProperty($params,'pheno_class_id')){
            if($this->CheckProperty->checkProperty($params,'phenophase_id')){
                unset($params->phenophase_id);
            }
        }
    }
    
    
    /**
     * This function will ensure that if the user has selected more than
     * one higher taxonomic level for which to filter/aggregate data that
     * only the highest level will actually apply. It does this by implicitely
     * removing the other parameters from the user's request.
     */
    public function cleanTaxonomyParameters($params){
        if($this->CheckProperty->checkProperty($params,'class_id')){
            
            if($this->CheckProperty->checkProperty($params,'order_id')){
                unset($params->order_id);
            }
            
            if($this->CheckProperty->checkProperty($params,'family_id')){
                unset($params->family_id);
            }

            if($this->CheckProperty->checkProperty($params,'genus_id')){
                unset($params->genus_id);
            }
            
        }
        
        if($this->CheckProperty->checkProperty($params,'order_id')){
            if($this->CheckProperty->checkProperty($params,'family_id')){
                unset($params->family_id);
            }
            
            if($this->CheckProperty->checkProperty($params,'genus_id')){
                unset($params->genus_id);
            }
        }

        if($this->CheckProperty->checkProperty($params,'family_id')){
            if($this->CheckProperty->checkProperty($params,'genus_id')){
                unset($params->genus_id);
            }
        }
                        
    }
    
    
    /**
     * Usually takes Species_ID or Phenophase_ID as input. This is used to
     * clear a specific field from the group by clause. This is useful when
     * a user aggregated by pheno class id or a higher taxonomic level and
     * the above mentioned fields are no longer relevant.
     */
    public function removeGroupBy($field_id){
        $c = count($this->group_by);
        for($i=0;$i < $c;$i++){
            if($this->group_by[$i] == $field_id){
                unset($this->group_by[$i]);
                break;
            }
        }        
    }
    

    /**
     * Cleans any parameters in the search results that are phenophase specific
     * and this is used when a user is aggregating by pheno class id and the
     * phenophase specific fields are not longer meaningful.
     */
    public function removeAggregatedPhenoClassFields($params){
        if($this->CheckProperty->checkProperty($params,'pheno_class_id')){
            $this->fields = array_diff(
                    $this->fields, 
                    array(
                        "Phenophase_ID",
                        "Phenophase_Description",
                        "Phenophase_Category"
                        )
                    );            
        }
    }
    
    
    /*
     * This is the logic to handle the pheno_class_id parameter. The logic is 
     * exactly the same between site level phenometric and magntiude phenometrics
     * so it's shared here.
     */
    public function searchByPhenoClassID($params){
        
        if($this->CheckProperty->checkProperty($params,'pheno_class_id') || $this->CheckProperty->checkProperty($params,'pheno_class_aggregate')){
            $params->additional_field = array_merge( 
                    ($this->CheckProperty->checkProperty($params,'additional_field')) ? $params->additional_field : array(), 
                    array("Pheno_Class_ID", "Pheno_Class_Name")
            );                   
        }
        
        if($this->CheckProperty->checkProperty($params,'pheno_class_id')){
            $params->pheno_class_id = $this->ArrayWrap->arrayWrap($params->pheno_class_id);

            $this->search_params['DataSearchParams']['Pheno_Class_IDs'] = implode(",",$params->pheno_class_id);
            $this->conditions["CachedSummarizedData.Pheno_Class_ID"] = $params->pheno_class_id;
                              
        }
        
        if($this->CheckProperty->checkProperty($params,'pheno_class_aggregate')){

            $this->removeAggregatedPhenoClassFields($params);                              

            $this->group_by[] = 'Pheno_Class_ID';
            $this->removeGroupBy("Phenophase_ID");
                
                
            /**
             * This block of code looks to see if the user is trying to
             * split the data by a higher taxonomic level, and if they are
             * not, then it acknwoledges that what they are trying to do
             * is look at something like 'all leaves' for all speices that
             * share that pheno class id and changes the search to only
             * aggregate by pheno class id.
             */
            if(!$this->CheckProperty->checkProperty($params,'taxonomy_aggregate')){
                $this->removeGroupBy("Species_ID");
                    
                if(!$this->CheckProperty->checkProperty($params,'species_id')){
                    $this->fields = array_diff(
                            $this->fields, 
                            array(
                                "Species_ID",
                                "Species_Functional_Type",
                                "Species_Category",
                                "Lifecycle_Duration",
                                "Growth_Habit",
                                "USDA_PLANTS_Symbol",
                                "ITIS_Number",
                                "Genus", // todo: kev
                                "Species",
                                "Common_Name"
                                )
                            );
                }
                    
            }      
        }
    }        

    
    /*
     * This function is for removing fields from the results that would otherwise
     * be included because we are aggregating data at a higher taxonomic level
     * and those values are no longer meaningful, i.e. they are present in a SQL
     * query results, but it's just the value in the first row to be returned.
     * 
     * This is typically called in the processingInputParams calls.
     */
    public function removeAggregatedTaxonomiesFields($params){
        
        /**
         * This first if block most closely matches the function comment
         * and removes species specific values if any of the higher taxonomic
         * ids are persent in the search params.
         */
        if($this->CheckProperty->checkProperty($params,'class_id') ||
                $this->CheckProperty->checkProperty($params,'order_id') || 
                $this->CheckProperty->checkProperty($params,'family_id') 
                || $this->CheckProperty->checkProperty($params,'genus_id')
                ){
            
            $this->fields = array_diff(
                    $this->fields, 
                    array(
                        "Species_ID",
                        "Species_Functional_Type",
                        "Species_Category",
                        "USDA_PLANTS_Symbol",
                        "ITIS_Number",
                        "Growth_Habit",
                        "Lifecycle_Duration",
                        "Genus",
                        "Species",
                        "Common_Name"
                        )
                    );
            
        }

        /*
         * This part more specifically removes genus specific values from the
         * results if class or order or family are provided as search params.
         */
        if($this->CheckProperty->checkProperty($params,'additional_field') && 
                ($this->CheckProperty->checkProperty($params,'class_id') ||
                $this->CheckProperty->checkProperty($params,'order_id') || 
                $this->CheckProperty->checkProperty($params,'family_id'))){

            $this->fields = array_diff(
                    $this->fields,
                    array(
                        "Genus_ID",
                        "Genus",
                        "Genus_Common_Name"
                    ));
            
        }
        
        /*
         * This part more specifically removes family specific values from the
         * results if class or order are provided as search params.
         */
        if($this->CheckProperty->checkProperty($params,'additional_field') && 
                ($this->CheckProperty->checkProperty($params,'class_id') ||
                $this->CheckProperty->checkProperty($params,'order_id'))){

            $this->fields = array_diff(
                    $this->fields,
                    array(
                        "Family_ID",
                        "Family_Name",
                        "Family_Common_Name"
                    ));
            
        }

        /**
         * Same as above, but only affecting the order values if class_id is
         * present.
         */
        if($this->CheckProperty->checkProperty($params,'additional_field') && 
                ($this->CheckProperty->checkProperty($params,'class_id'))){
            
            $this->fields = array_diff(
                    $this->fields,
                    array(
                        "Order_ID",
                        "Order_Name",
                        "Order_Common_Name"
                    ));
            
        } 

        
    }
    
    public function processInputParameters($params){
        if($this->CheckProperty->checkProperty($params,'bottom_left_x1') && $this->CheckProperty->checkProperty($params,'bottom_left_y1') &&
                $this->CheckProperty->checkProperty($params,'upper_right_x2') && $this->CheckProperty->checkProperty($params,'upper_right_y2')){
            
            if(is_numeric($params->bottom_left_x1) && is_numeric($params->bottom_left_y1) &&
               is_numeric($params->upper_right_x2) && is_numeric($params->upper_right_y2)){
                $this->search_params['DataSearchParams']['Bottom_Left_X1'] = $params->bottom_left_x1;
                $this->search_params['DataSearchParams']['Bottom_Left_Y1'] = $params->bottom_left_y1;
                $this->search_params['DataSearchParams']['Upper_Right_X2'] = $params->upper_right_x2;
                $this->search_params['DataSearchParams']['Upper_Right_Y2'] = $params->upper_right_y2;

                
                $this->conditions["CachedSummarizedData.Latitude BETWEEN ? AND ?"] = array($params->bottom_left_x1, $params->upper_right_x2);
                $this->conditions["CachedSummarizedData.Longitude BETWEEN ? AND ?"] = array($params->bottom_left_y1, $params->upper_right_y2);
            }

        }else if($this->CheckProperty->checkProperty($params, 'state') != null){
            $params->state = $this->ArrayWrap->arrayWrap($params->state);

            $this->search_params['DataSearchParams']['States'] = implode(",",$params->state);
            $this->conditions["CachedSummarizedData.State"] = $params->state;
            
        }

        if($this->CheckProperty->checkProperty($params,'dataset_ids')){
            $params->dataset_ids = $this->ArrayWrap->arrayWrap($params->dataset_ids);
            $tmp_array = array();
            
            foreach($params->dataset_ids as $dataset_id){
                $tmp_array[] = $dataset_id;
                
                if($dataset_id == -9999){
                    $tmp_array = array_merge($tmp_array, array(3,9,10));
                }
            }

            $this->search_params['DataSearchParams']['Dataset_ID'] = implode(",",$tmp_array);
            $this->conditions["CachedObservation.Dataset_ID"] = $tmp_array;
        }
        
        if($this->CheckProperty->checkProperty($params,'species_id')){
            $params->species_id = $this->ArrayWrap->arrayWrap($params->species_id);

            $this->search_params['DataSearchParams']['Species_ID'] = implode(",",$params->species_id);
            $this->conditions["CachedSummarizedData.Species_ID"] = $params->species_id;
        }
        
        if($this->CheckProperty->checkProperty($params,'station_id')){
            $params->site_id = $this->ArrayWrap->arrayWrap($params->station_id);

            $this->search_params['DataSearchParams']['Site_IDs'] = implode(",",$params->site_id);
            $this->conditions["CachedSummarizedData.Site_ID"] = $params->site_id;
        }        
        
        
        if($this->CheckProperty->checkProperty($params,'species_type')){

            if(!is_array($params->species_type)){

                $this->conditions["CachedSummarizedData.Species_Category LIKE"] = '%' . $params->species_type . '%';
                $this->search_params['DataSearchParams']['Species_Category'] = $params->species_type;

            }else{
                $or_conditions = array();
                foreach($params->species_type as $st){

                    $like = array('CachedSummarizedData.Species_Category LIKE' => '%' . $st . '%');

                    $or_conditions[] = $like;

                }

                $this->ors[] = array('OR' => $or_conditions);
                $this->search_params['DataSearchParams']['Species_Types'] = implode(",",$params->species_type);
               
            }

        }
        
        if($this->CheckProperty->checkProperty($params,'functional_type')){

            if(!is_array($params->functional_type)){

                $this->conditions["CachedSummarizedData.Species_Functional_Type"] = $params->functional_type;
                $this->search_params['DataSearchParams']['Species_Functional_Type'] = $params->functional_type;

            }else{
                $or_conditions = array();
                foreach($params->functional_type as $ft){

                    $like = array('CachedSummarizedData.Species_Functional_Type' => $ft);

                    $or_conditions[] = $like;

                }

                $this->ors[] = array('OR' => $or_conditions);
                $this->search_params['DataSearchParams']['Species_Functional_Type'] = implode(",",$params->functional_type);
               
            }

        }        
        
        
        if($this->CheckProperty->checkProperty($params,'phenophase_category')){

            if(!is_array($params->phenophase_category)){

                $this->conditions["CachedSummarizedData.Phenophase_Category LIKE"] = '%' . $params->phenophase_category . '%';
                $this->search_params['DataSearchParams']['Phenophase_Categories'] = $params->phenophase_category;

            }else{
                $or_conditions = array();
                foreach($params->phenophase_category as $pc){

                    $like = array('CachedSummarizedData.Phenophase_Category LIKE' => '%' . $pc . '%');

                    $or_conditions[] = $like;

                }

                $this->ors[] = array('OR' => $or_conditions);
                $this->search_params['DataSearchParams']['Phenophase_Categories'] = implode(",",$params->phenophase_category);

            }

        }

        if($this->CheckProperty->checkProperty($params,'phenophase_id')){
            $params->phenophase_id = $this->ArrayWrap->arrayWrap($params->phenophase_id);

            $this->search_params['DataSearchParams']['Phenophase_IDs'] = implode(",",$params->phenophase_id);
            $this->conditions["CachedSummarizedData.Phenophase_ID"] = $params->phenophase_id;
        }
        
        if($this->CheckProperty->checkProperty($params,'genus_id')){
            $params->genus_id = $this->ArrayWrap->arrayWrap($params->genus_id);

            $this->search_params['DataSearchParams']['Genus_IDs'] = implode(",",$params->genus_id);
            $this->conditions["CachedSummarizedData.Genus_ID"] = $params->genus_id;
        }
        
        if($this->CheckProperty->checkProperty($params,'family_id')){
            $params->family_id = $this->ArrayWrap->arrayWrap($params->family_id);

            $this->search_params['DataSearchParams']['Family_IDs'] = implode(",",$params->family_id);
            $this->conditions["CachedSummarizedData.Family_ID"] = $params->family_id;
        }
        
        if($this->CheckProperty->checkProperty($params,'order_id')){
            $params->order_id = $this->ArrayWrap->arrayWrap($params->order_id);

            $this->search_params['DataSearchParams']['Order_IDs'] = implode(",",$params->order_id);
            $this->conditions["CachedSummarizedData.Order_ID"] = $params->order_id;
        }        
        
        if($this->CheckProperty->checkProperty($params,'class_id')){
            $params->class_id = $this->ArrayWrap->arrayWrap($params->class_id);

            $this->search_params['DataSearchParams']['Class_IDs'] = implode(",",$params->class_id);
            $this->conditions["CachedSummarizedData.Class_ID"] = $params->class_id;
        }        

        
        if($this->CheckProperty->checkProperty($params,'observation_id')){
            $params->observation_id = $this->ArrayWrap->arrayWrap($params->observation_id);

            $this->conditions["CachedObservation.Observation_ID"] = $params->observation_id;
        }        
        
        if($this->CheckProperty->checkProperty($params,'person_id')){
            $params->person_id = $this->ArrayWrap->arrayWrap($params->person_id);

            $this->conditions["CachedObservation.ObservedBy_Person_ID"] = $params->person_id;
        }        
        
        if(!empty($this->ors)){
            $this->conditions['AND'] = array($this->ors);
        }
        
        if($this->CheckProperty->checkProperty($params, 'network') && !$this->CheckProperty->checkProperty($params, 'network')){
            $params->network = $this->ArrayWrap->arrayWrap($params->network);
            $this->search_params['DataSearchParams']['Networks'] = implode(",", $params->network);
        }
        
        if($this->CheckProperty->checkProperty($params, 'group_id') && !$this->CheckProperty->checkProperty($params, 'network_id')){
            $params->network_id = $params->group_id;
        }
        
        if($this->CheckProperty->checkProperty($params, 'network_id')){
            $params->network_id = $this->ArrayWrap->arrayWrap($params->network_id);
            
            $this->conditions["CachedNetworkObservation.Network_ID"] = $params->network_id;
            $this->search_params['DataSearchParams']['Networks'] = implode(",", $params->network_id);
        }        
        
        if($this->CheckProperty->checkProperty($params, 'IP_Address')){
            $this->search_params['DataSearchParams']['IP_Address'] = $params->IP_Address;
        }
        
        if($this->CheckProperty->checkProperty($params, 'user_email')){
            $this->search_params['DataSearchParams']['user_email'] = $params->user_email;
        }        

        //set climate_data_selected values to true that match incoming climate data params
        if($this->CheckProperty->checkProperty($params,'additional_field')){
            $params->additional_field = $this->ArrayWrap->arrayWrap($params->additional_field);
            
            foreach($params->additional_field as $extra_field){
                $this->climate_data_selected[$extra_field] = true;
            }
        }

        //set all climate_data_selected values to true if incoming param climate_data = 1
        if($this->CheckProperty->checkProperty($params,'climate_data')){
            if($params->climate_data == 1){    
                foreach ($this->climate_data_selected as $i => $value) {
                    $this->climate_data_selected[$i] = true;
                }
            }
        }
        
    }
    
    public function outputData($params, $out){
        $this->emitter->emitHeader();
        $joins = array();
        
        $dbo = $this->CachedObservation->getDataSource();
                
        /**
         * These directives are set for the following reasons:
         *  - group_concat_max_length - some of the queries are string together
         * long sets of variables in GROUP_CONCAT functions, which have a max-length,
         * so it needs to be extended here when the records aggregated are many
         * 
         *  - net_write_timeout and net_read_timeout - default values are too short
         * for some services that take a long time to read the query result set
         * This is particularly true in the R library when it is acquiring AGDD
         * values for each point. Adding this keeps the connection alive while it
         * waits for the client to do any work on their side.
         */
        $query = "SET group_concat_max_len = 16384";
        mysql_unbuffered_query($query);
        
        $query = "SET net_write_timeout = 300";
        mysql_unbuffered_query($query);

        $query = "SET net_read_timeout = 300";
        mysql_unbuffered_query($query);
        
        
        if(!empty($params->network) && $params->network != "" && empty($params->network_id)){
            $conditionsSubQuery['Name'] = $params->network;

            $subQuery = $dbo->buildStatement(
                array(
                    'fields'     => array('Observation_ID'),
                    'table'      => $dbo->fullTableName($this->CachedNetworkObservation),
                    'alias'      => 'netob',
                    'limit'      => null,
                    'offset'     => null,
                    'joins'      => array(),
                    'conditions' => $conditionsSubQuery,
                    'order'      => null,
                    'group'      => null
                ),
                $this->CachedNetworkObservation
            );
            
            $subQuery = ' Observation_ID IN (' . $subQuery . ') ';
            $subQueryExpression = $dbo->expression($subQuery);
            
            $this->conditions[] = $subQueryExpression;
            
        }
        
        $joins[] =  array(
                            'table' => 'Cached_Observation',
                            'alias' => 'CachedObservation',
                            'type' => 'LEFT',
                            'conditions' => array(
                                'CachedObservation.Series_ID = CachedSummarizedData.Series_ID'
                            )
                    );
        
        if(!empty($params->network_id) && $params->network_id != ""){
            $joins[] =  array(
                                'table' => 'Cached_Network_Observation',
                                'alias' => 'CachedNetworkObservation',
                                'type' => 'LEFT',
                                'conditions' => array(
                                    'CachedNetworkObservation.Observation_ID = CachedObservation.Observation_ID'
                                )
                        );
            
            for($i=0;$i < count($this->fields);$i++){                
                if($this->fields[$i] == "Observation_ID"){               
                    $this->fields[$i] = "CachedObservation.Observation_ID `Observation_ID`";
                    break;
                }
            }    
        }

        $query_array = array(
            "fields" => $this->fields,
            "table" => $dbo->fullTableName($this->CachedSummarizedData),
            "alias" => 'CachedSummarizedData',
            "conditions" => $this->conditions,
            "limit" => null,
            "joins" => $joins
        );

        if(!empty($this->group_by)){
            $query_array["group"] = $this->group_by;
        }
        
        /**
         * One way to prevent using filesort, temporary tables/files
         * is to have group by and order by clauses match.
         * In most cases this is easy to do, but if order by is populated
         * then that is used, otherwise attempt to use group by for order
         * if it is set.
         */
        if(!empty($this->order_by)){
            $query_array["order"] = $this->order_by;
        }else if(!empty($this->group_by)){
            $query_array["order"] = $this->group_by;
        }

        $query = $dbo->buildStatement($query_array,
            $this->CachedSummarizedData
        );


        $this->log($query);
        
        $results = mysql_unbuffered_query($query);

        $this->appendFields($params, $this->getAggregateFields());
        
        $i=0;      
        while($result = mysql_fetch_array($results, MYSQL_ASSOC)){
            $result['node_name'] = "observation";

            
            if(array_key_exists('User_Time', $result)){
                $result['User_Time'] = ord($result['User_Time']);
            }
            
            $results_set = $this->preprocessResults($result, $results);           
            
            
            foreach($results_set as $r){                
                $this->emitter->emitNode($r);
                $i++;
            }
            
            
            if($i >= 5000 ){
                $out->flush();
                usleep(500000);
                $i=0;
            }
        }
        
        $out->flush();

        $this->emitter->emitFooter();
        
    }
    
    
    public function logSearch($params){
        $this->search_params['DataSearchParams']['Search_Date'] = date('Y-m-d');
        $this->search_params['DataSearchParams']['Request_Source'] = $params->request_src;
        $this->DataSearchParams->save($this->search_params);        
    }
    
    public function appendFields($params, $allowed_values_array) {
        if($this->CheckProperty->checkProperty($params,'additional_field')){
            $params->additional_field = $this->ArrayWrap->arrayWrap($params->additional_field);
            
        }else{
            $params->additional_field = array();
        }
        
        for($i=0;$i<count($params->additional_field);$i++){
            $params->additional_field[$i] = strtolower($params->additional_field[$i]);
        }

        //filter out unrequested fields
        foreach($allowed_values_array as $sys_name => $name){
            if(!in_array($sys_name, $params->additional_field)){
                if(($key = array_search($name, $this->fields)) !== false){
                    //don't filter requested climate data or genus
                    $skip = (
                        array_key_exists($name, $this->climate_data_selected) 
                        && $this->climate_data_selected[$name]
                    );
                        // || $name=='Genus';
                    if($skip) {
                        continue;
                    }
                    unset($this->fields[$key]);
                    // if(!(array_key_exists($name, $this->climate_data_selected) && $this->climate_data_selected[$name])) {
                    //     unset($this->fields[$key]);
                    // }
                }                
            }
        }
    }
    
    
    public function getAllowedValues() {
        return $this->allowed_values;
    }

    public function setAllowedValues($allowed_values) {
        $this->allowed_values = $allowed_values;
    }

    public function getAggregateFields() {
        return $this->aggregate_fields;
    }

    public function setAggregateFields($aggregate_fields) {
        $this->aggregate_fields = $aggregate_fields;
    }
}