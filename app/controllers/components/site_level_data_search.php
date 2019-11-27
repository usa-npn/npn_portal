<?php

App::import('Component', 'SummarizedDataSearch');
App::import('Model', 'IndividualEntity');




class SiteLevelDataSearch extends SummarizedDataSearch{
    
    private $accumulated_processed_results = array();
    
    private $num_days_quality_filter = 30;

    private $site_level_fields;
    
    private $agg_level;
    
    private $is_pheno_class_agg;
    
    private static $redundant_fields = array(
        'First_Yes_Year',
        'First_Yes_Month',
        'First_Yes_Day',
        'First_Yes_Doy',
        'First_Yes_Julian_Date',
        'NumDays_Since_Prior_No',
        'Last_Yes_Year',
        'Last_Yes_Month',
        'Last_Yes_Day',
        'Last_Yes_Doy',
        'Last_Yes_Julian_Date',
        'NumDays_Until_Next_No',
        'Individual_ID',
        'Dataset_ID',
        'ObservedBy_Person_ID',
        'Plant_Nickname',
        'Patch',
        'NumYs_in_Series',
        'NumDays_in_Series',
        'Multiple_FirstY',
        'Multiple_Observers',
        'tmax',
        'tmin',
        'prcp',
        'tminf',
        'tmaxf',
        'acc_prcp',
        'daylength',
        'gdd',
        'gddf'
    );
    
    
    public function __construct($emitter){            

        parent::__construct($emitter);

        $this->site_level_fields = array();
        
        $this->agg_level = "Species_ID";
        
        $this->is_pheno_class_agg = false;
    }
    
    public function processInputParameters($params){
        
        if($this->CheckProperty->checkProperty($params,'num_days_quality_filter')){
            $this->num_days_quality_filter = $params->num_days_quality_filter;
            

            $this->search_params['DataSearchParams']['Number_Days_Quality_Filter'] = $this->num_days_quality_filter;
        }

        foreach($params->additional_field as $af) {
            $this->site_level_fields[] = $af;
        }

        $this->cleanTaxonomyParameters($params);
        $this->cleanPhenoClassParams($params);
        
        if($this->CheckProperty->checkProperty($params,'taxonomy_aggregate')){
            $this->removeAggregatedTaxonomiesFields($params);
        }
        
        
        
        /**
         * These next four if blocks follow the same path, based on the higher
         * taxonomic level the user passed in as a paramater.
         */

        if($this->CheckProperty->checkProperty($params,'genus_id')){
            $params->genus_id = $this->ArrayWrap->arrayWrap($params->genus_id);
            
//            $this->search_params['DataSearchParams']['Phenophase_IDs'] = implode(",",$params->phenophase_id);            
            $this->conditions["CachedSummarizedData.Genus_ID"] = $params->genus_id;
            if($this->CheckProperty->checkProperty($params,'taxonomy_aggregate')){
                $params->additional_field = array_merge( 
                        ($this->CheckProperty->checkProperty($params,'additional_field')) ? $params->additional_field : array(), 
                        array("Genus_ID", "Genus", "Genus_Common_Name")
                );
                $this->agg_level = "Genus_ID";
                $this->order_by = array('CachedSummarizedData.Site_ID', 'CachedSummarizedData.Genus_ID', 'CachedSummarizedData.Phenophase_ID');
            }
        }
        if($this->CheckProperty->checkProperty($params,'family_id')){
            $params->family_id = $this->ArrayWrap->arrayWrap($params->family_id);

            //TODO: Add these new filters to the data search parma table
//            $this->search_params['DataSearchParams']['Phenophase_IDs'] = implode(",",$params->phenophase_id);            
            $this->conditions["CachedSummarizedData.Family_ID"] = $params->family_id;
            
            /**
             * Site level data can also take another optional param taxonomy_aggregate
             * that tells the search to actually aggregate at the higher level and
             * not just set a filter on the data searched.
             * This changes the query's group by clause and ALSO implicitely
             * adds the taxonomy id and name to the results because if they aren't
             * provided then you can't actually identify the data.
             */
            if($this->CheckProperty->checkProperty($params,'taxonomy_aggregate')){
                $params->additional_field = array_merge( 
                    ($this->CheckProperty->checkProperty($params,'additional_field')) ? $params->additional_field : array(), 
                    array("Family_ID", "Family_Name", "Family_Common_Name")
                );
            
                //$this->group_by = array('Family_ID', 'Site_ID', 'Phenophase_ID');                
                $this->agg_level = "Family_ID";
                $this->order_by = array('CachedSummarizedData.Site_ID', 'CachedSummarizedData.Family_ID', 'CachedSummarizedData.Phenophase_ID');
            }
        }


        if($this->CheckProperty->checkProperty($params,'order_id')){
            $params->order_id = $this->ArrayWrap->arrayWrap($params->order_id);
            
//            $this->search_params['DataSearchParams']['Phenophase_IDs'] = implode(",",$params->phenophase_id);            
            $this->conditions["CachedSummarizedData.Order_ID"] = $params->order_id;
            if($this->CheckProperty->checkProperty($params,'taxonomy_aggregate')){
                $params->additional_field = array_merge( 
                        ($this->CheckProperty->checkProperty($params,'additional_field')) ? $params->additional_field : array(), 
                        array("Order_ID", "Order_Name", "Order_Common_Name")
                );
                //$this->group_by = array('Order_ID', 'Site_ID', 'Phenophase_ID');                
                $this->agg_level = "Order_ID";
                $this->order_by = array('CachedSummarizedData.Site_ID', 'CachedSummarizedData.Order_ID', 'CachedSummarizedData.Phenophase_ID');
            }
        }

        if($this->CheckProperty->checkProperty($params,'class_id')){
            $params->class_id = $this->ArrayWrap->arrayWrap($params->class_id);
            
//            $this->search_params['DataSearchParams']['Phenophase_IDs'] = implode(",",$params->phenophase_id);            
            $this->conditions["CachedSummarizedData.Class_ID"] = $params->class_id;
            if($this->CheckProperty->checkProperty($params,'taxonomy_aggregate')){
                $params->additional_field = array_merge( 
                        ($this->CheckProperty->checkProperty($params,'additional_field')) ? $params->additional_field : array(), 
                        array("Class_ID", "Class_Name", "Class_Common_Name")
                );
                //$this->group_by = array('Class_ID', 'Site_ID', 'Phenophase_ID');                
                $this->agg_level = "Class_ID";
                $this->order_by = array('CachedSummarizedData.Site_ID', 'CachedSummarizedData.Class_ID', 'CachedSummarizedData.Phenophase_ID');
            }
        }

        $this->searchByPhenoClassID($params);
        if($this->CheckProperty->checkProperty($params,'pheno_class_aggregate')){
            
            // Setting this variable makes the pheno class aggregation option
            // available later during data pre processing and is necessary
            // to make some modifications to the process
            $this->is_pheno_class_agg = true;
            
            /*
             * When aggregating by pheno class its important to make sure that
             * the data handled is ordered correctly. Because this phenometric
             * is derivitive of the individual phenometrics, which is dependent
             * on first aggregating by series_id, which includes individual_id,
             * ordereing the data precisely is the only (good) way to re-aggregate
             * data in-code.
             * The pop statement is to remove phenophase_id from the ordering
             * clause and then replace it with pheno class id
             */
            array_pop($this->order_by);
            $this->order_by[] = 'CachedSummarizedData.Pheno_Class_ID';
        }

        parent::processInputParameters($params);
    }
    
    /**
     * This will extend SummarizedDataSearch's preprocessResults essencially
     * building upon it. However, a challenge is that while Summarized Data uses
     * a query to group individual records and then that grouped results can become
     * any number of resulting row results; this works very differently. It collects
     * a number of summarized data records and averages them into a single row.
     * Because of this, the signature for this method was changed, so that the mysql
     * handle could be passed in, and new records could be queried without interrupting
     * the process in the generic interface to this function (generic_observation_search). 
     */
    public function preprocessResults($data, &$result_set){
        
        /**
         * Take whatever data has been queried and perform the summarized
         * data preprocessing.
         * 
         */
        $rows = parent::preprocessResults($data, $result_set);

        /**
         * This do-loop will keep querying the db for new records until we either
         * run out of results or find a complete set of records to summarize at
         * the site level. A lot of this is coordinated by returing the results
         * in a predictible order.
         */
        do{
            /**
             * If our processed records don't actually contain any summarized
             * rows, then we can just continue searching the database.
             */
            if(count($rows) > 0){

                /**
                 * If we haven't yet populated the accumulated results then
                 * whatever we find first goes into this array.
                 */
                if(empty($this->accumulated_processed_results)){

                    $this->accumulated_processed_results = $rows;

                
                }else if(
                        $this->rowsMatch ($rows[0], $this->accumulated_processed_results[0])

                ){
                    /**
                     * If these records are part of this set of summarized records,
                     * i.e. it's an observation about the same species/phenophase
                     * at the same site, then add it to the accumulation
                     */
                    $this->accumulated_processed_results = array_merge($this->accumulated_processed_results, $rows);

                }else{
                    /**
                     * Otherwise we've found a complete set of records to summarize.
                     * Do the site-level summary processing on those records,
                     * then shuffle the assignments so that we're accumulating
                     * the records which weren't part of this set for the next
                     * iteration through this function.
                     */
                    $results = $this->doSiteLevelSummary($this->accumulated_processed_results);
                    $this->accumulated_processed_results = $rows;
                    $rows = $results;

                    break;
                }


            }
        }while($this->checkNextResults($rows, $result_set));
        
        if($rows === false){
            $this->processLastAccumulation($rows);
        }
        
        return $rows;
                
    }
    
    private function rowsMatch($row, $accumulated_results){
        if($row['Site_ID'] == $accumulated_results['Site_ID']
         && ( 
                 (!$this->is_pheno_class_agg && $row['Phenophase_ID'] == $accumulated_results['Phenophase_ID']) ||
                 ($this->is_pheno_class_agg && $row['Pheno_Class_ID'] == $accumulated_results['Pheno_Class_ID'])
             )
         && $row[$this->agg_level] == $accumulated_results[$this->agg_level]){   
            
            return true;
            
            
        }else{
            return false;
        }        
    }
    
    /**
     * Utility function to check the resultsets. Uses pass-by-reference
     * to update the $rows variable as appropriate and makes sure the do-while
     * loop continues so long as there are still results in the cursor.
     */
    private function checkNextResults(&$rows, &$result_set){
        
        $outcome = false;
        
        while($rows = mysql_fetch_array($result_set, MYSQL_ASSOC)){
            $rows['node_name'] = "observation";
            
            $rows = parent::preprocessResults($rows, $result_set);

            if(!$rows){
                continue;
            }else{                
                $outcome = true;
                break;
            }
        }
        
        return $outcome;
                
    }
    
    private function processLastAccumulation(&$rows){
        if(!empty($this->accumulated_processed_results)){
            $rows = $this->doSiteLevelSummary($this->accumulated_processed_results); 
        }else{
            $rows = array();
        }
    }
    

    
    private function doSiteLevelSummary($rows){
        
        $mult_observers = false;
        $first_yes_doy = array();
        $first_yes_julian = array();

        $days_since_last_no = array();        
        $last_yes_doy = array();
        $last_yes_julian = array();

        $days_until_next_no = array();
        
        $gdds = array();
        $accum_prcps = array();
        $daylengths = array();
        $gddfs = array();



        $num_individuals_multiple_first_y = 0;
        $individual_ids_conflicts = array();
        $individual_ids_mult_y = array();

        $individuals = array();
        $metrics_series = array();        
        

        /**
         * First loop to iterate through each row in the set to determine what
         * individuals are being worked with as well as what each of those
         * individuals first doy, etc values are
         */
        for($k=0; $k < count($rows); $k++){
            $series = $rows[$k];
            $series_multiple_set = false;

            /**
             * If the current individual has already been iterated over up
             * the num_series value otherwise create a new entity to work with.
             */
            if(array_key_exists($series['Individual_ID'], $individuals)){
                $individuals[$series['Individual_ID']]->num_series++;
            }else{
                $individuals[$series['Individual_ID']] = new IndividualEntity($series['Individual_ID']);
            }


            /**
             * If this is the oldest first yes we've seen for this individual and it's a valid value
             * then set all the first_yes values for this individual entity.
             */
            if($series['NumDays_Since_Prior_No'] > 0 && $series['NumDays_Since_Prior_No'] <= $this->num_days_quality_filter && 
                    ($individuals[$series['Individual_ID']]->first_yes == null || 
                    $series['First_Yes_Doy'] < $individuals[$series['Individual_ID']]->first_yes)
            ){
                $series_multiple_set = true;
                if($individuals[$series['Individual_ID']]->first_julian != null  || $individuals[$series['Individual_ID']]->last_julian != null){
                    $individuals[$series['Individual_ID']]->multiple_first_yes = true;                    
                }
                
                if(in_array("Observed_Status_Conflict_Flag", $this->fields)){
                    $conflict_value = $series['Observed_Status_Conflict_Flag'];
                    if(!empty($conflict_value) && $conflict_value != "-9999" && $conflict_value != -9999){
                        $individuals[$series['Individual_ID']]->setConflict($series['Observed_Status_Conflict_Flag']);
                    }
                }
                
                $individuals[$series['Individual_ID']]->first_yes = $series['First_Yes_Doy'];
                $individuals[$series['Individual_ID']]->first_julian = $series['First_Yes_Julian_Date'];

                $individuals[$series['Individual_ID']]->days_last_no = $series['NumDays_Since_Prior_No'];
                
                $individuals[$series['Individual_ID']]->gdd = $series['gdd'];
                
                
                if($this->climate_data_selected['gddf'] == 1 || $this->climate_data_selected['se_gddf'] == 1 || $this->climate_data_selected['mean_gddf'] == 1){  //|| $this->climate_data_selected['se_gddf'] == 1 || $this->climate_data_selected['mean_gddf'] == 1
                    $individuals[$series['Individual_ID']]->gddf = $series['gddf'];
                }
                

                $individuals[$series['Individual_ID']]->daylength = $series['daylength'];
                
                $individuals[$series['Individual_ID']]->accum_prcp = $series['acc_prcp'];

                

            }


            /**
             * If this is the newest yes value we've seen for the individual and it's valid
             * then mark all the last yes values for the entity.
             */
            if($series['NumDays_Until_Next_No'] > 0 && $series['NumDays_Until_Next_No'] <= $this->num_days_quality_filter &&
                    ($individuals[$series['Individual_ID']]->last_yes == null || 
                    $series['Last_Yes_Doy'] > $individuals[$series['Individual_ID']]->last_yes)                
            ){
                
                if(!$series_multiple_set && ($individuals[$series['Individual_ID']]->first_julian != null  || $individuals[$series['Individual_ID']]->last_julian != null) ){
                    $individuals[$series['Individual_ID']]->multiple_first_yes = true;
                }

                if(in_array("Observed_Status_Conflict_Flag", $this->fields)){
                    $conflict_value = $series['Observed_Status_Conflict_Flag'];
                    if(!empty($conflict_value) && $conflict_value != "-9999" && $conflict_value != -9999){
                        $individuals[$series['Individual_ID']]->setConflict($series['Observed_Status_Conflict_Flag']);
                    }
                }                

                $individuals[$series['Individual_ID']]->last_yes = $series['Last_Yes_Doy'];
                $individuals[$series['Individual_ID']]->last_julian = $series['Last_Yes_Julian_Date'];


                $individuals[$series['Individual_ID']]->days_next_no = $series['NumDays_Until_Next_No'];                

            }



        }

        /**
         * The first loop has been completed. In this block we iterate each of the
         * individual entities we've established and load their first yes/last yes
         * values into arrays so that they can be averaged later.
         */
        $individual_count = 0;
        $conflict_flag = "-9999";
        foreach($individuals as $individual){

            /*
            if($individual->mult_observers){
                $mult_observers = true;
            }
             * 
             */
            
            if($individual->multiple_first_yes){
                $num_individuals_multiple_first_y++;
                $individual_ids_mult_y[] = $individual->individual_id;
            }            

            /**
             * If the entity didn't have any valid series because all the no
             * values were outside the data precision filter, then it's disqualified
             * for site-level summary.
             */
            if($individual->num_series == 0 || (!$individual->first_yes && !$individual->last_yes) ){
                continue;
            }else{
                $individual_count++;
            }




            /**
             * If the entity did have a valid first yes value then we promote
             * those values into the averaging arrys.
             */
            if($individual->first_yes && $individual->first_yes > 0){

                $first_yes_doy[] = $individual->first_yes;
                $first_yes_julian[] = $individual->first_julian;
                
                $gdds[] = $individual->gdd;
                $gddfs[] = $individual->gddf;
                $daylengths[] = $individual->daylength;
                $accum_prcps[] = $individual->accum_prcp;
            }

            
            
            
            
            /**
             * Same as above but for days since last no ...
             */
            if($individual->days_last_no && $individual->days_last_no > 0){
                $days_since_last_no[] = $individual->days_last_no;
            }




            
            if($individual->last_yes && $individual->last_yes > 0){
                $last_yes_doy[] = $individual->last_yes;
                $last_yes_julian[] = $individual->last_julian;
                
            }

            
            
            
            
            if($individual->days_next_no && $individual->days_next_no > 0){
                $days_until_next_no[] = $individual->days_next_no;
            }


        }

        /**
         * If none of the individuals had valid series then we just return an
         * empty array.
         */
        if($individual_count == 0){
            return array();
        }

        /**
         * Array values must be sorted to get accurate median values
         */
        sort($first_yes_doy);
        sort($last_yes_doy);

        
        /**
         * We need to use julian date to calcualte most of the values because if user has something
         * like water year selected, and the values cut across multiple years then the resulting
         * average is misrepresented. Using the julian date allows to average the place in time
         * and later be converted to a gregorian doy value.
         */
        $first_julian_average = $this->getAverage($first_yes_julian);
        $first_yes_timestamp = ($first_julian_average == -9999) ? -9999 : strtotime(jdtogregorian($first_julian_average));
        
        $metrics_series['First_Yes_Sample_Size'] = count($first_yes_doy);
        $metrics_series['Mean_First_Yes_Year'] = ($first_julian_average == -9999) ? -9999 : date('Y', $first_yes_timestamp);
        $metrics_series['Mean_First_Yes_DOY'] = ($first_julian_average == -9999) ? -9999 : date('z', $first_yes_timestamp) + 1;
        $metrics_series['Mean_First_Yes_Julian_Date'] = $first_julian_average;

        $metrics_series['SE_First_Yes_in_Days'] = $this->standardError($first_yes_doy);
        $metrics_series['SD_First_Yes_in_Days'] = $this->standardDeviation($first_yes_doy);
        if(in_array("min_first_yes_doy", $this->site_level_fields)) {
            $metrics_series['Min_First_Yes_DOY'] = $this->getMin($first_yes_doy);
        }
        if(in_array("max_first_yes_doy", $this->site_level_fields)) {
            $metrics_series['Max_First_Yes_DOY'] = $this->getMax($first_yes_doy);
        }
        if(in_array("median_first_yes_doy", $this->site_level_fields)) {
            $metrics_series['Median_First_Yes_DOY'] = $this->getMedian($first_yes_doy);
        }


        $metrics_series['Mean_NumDays_Since_Prior_No'] = $this->getAverage($days_since_last_no, false);
        $metrics_series['SE_NumDays_Since_Prior_No'] = $this->standardError($days_since_last_no);
        $metrics_series['SD_NumDays_Since_Prior_No'] = $this->standardDeviation($days_since_last_no);

        
        $last_julian_average = $this->getAverage($last_yes_julian);
        $last_yes_timestamp = ($last_julian_average == -9999) ? -9999 : strtotime(jdtogregorian($last_julian_average));
        
        $metrics_series['Last_Yes_Sample_Size'] = count($last_yes_doy);
        $metrics_series['Mean_Last_Yes_Year'] = ($last_julian_average == -9999) ? -9999 : date('Y', $last_yes_timestamp);
        $metrics_series['Mean_Last_Yes_DOY'] = ($last_julian_average == -9999) ? -9999 : date('z', $last_yes_timestamp) + 1;
        $metrics_series['Mean_Last_Yes_Julian_Date'] = $last_julian_average;        

        $metrics_series['SE_Last_Yes_in_Days'] = $this->standardError($last_yes_julian);
        $metrics_series['SD_Last_Yes_in_Days'] = $this->standardDeviation($last_yes_julian);
        if(in_array("min_last_yes_doy", $this->site_level_fields)) {
            $metrics_series['Min_Last_Yes_DOY'] = $this->getMin($last_yes_doy);
        }
        if(in_array("max_last_yes_doy", $this->site_level_fields)) {
            $metrics_series['Max_Last_Yes_DOY'] = $this->getMax($last_yes_doy);
        }
        if(in_array("median_last_yes_doy", $this->site_level_fields)) {
            $metrics_series['Median_Last_Yes_DOY'] = $this->getMedian($last_yes_doy);
        }
        

        $metrics_series['Mean_NumDays_Until_Next_No'] = $this->getAverage($days_until_next_no, false);
        $metrics_series['SE_NumDays_Until_Next_No'] = $this->standardError($days_until_next_no);
        $metrics_series['SD_NumDays_Until_Next_No'] = $this->standardDeviation($days_until_next_no);
        
        $metrics_series['Num_Individuals_with_Multiple_FirstY'] = $num_individuals_multiple_first_y;
        $metrics_series['Individuals_IDs_with_Multiple_FirstY'] = "'" . implode(',', $individual_ids_mult_y) . "'";
        






        if(in_array("Observed_Status_Conflict_Flag", $this->fields)){            
            $metrics_series['Observed_Status_Conflict_Flag'] = $this->getSummarizedConflictFlag($individuals);
            $metrics_series['Observed_Status_Conflict_Flag_Individual_IDs'] = $this->getConflictIndividualIDs($individuals);
        }
        
        /**
         * If the user asked for cliamte variables, then those need to be calculated
         * and added separately.
         */        
        if($this->climate_data_selected['mean_gdd'] == 1){
            $metrics_series['Mean_GDD'] = $this->getAverage($gdds, false);
        }
        
        if($this->climate_data_selected['se_gdd'] == 1){
            $metrics_series['SE_GDD'] = $this->standardError($gdds);
        }

        if($this->climate_data_selected['mean_gddf'] == 1){
            $metrics_series['Mean_GDDF'] = $this->getAverage($gddfs, false);
        }

        if($this->climate_data_selected['se_gddf'] == 1){
            $metrics_series['SE_GDDF'] = $this->standardError($gddfs);
        }
        
        if($this->climate_data_selected['mean_accum_prcp'] == 1){
            $metrics_series['Mean_Accum_Prcp'] = $this->getAverage($accum_prcps, false);
        }

        if($this->climate_data_selected['se_accum_prcp'] == 1){
            $metrics_series['SE_Accum_Prcp'] = $this->standardError($accum_prcps);
        }


        if($this->climate_data_selected['mean_daylength'] == 1){
            $metrics_series['Mean_Daylength'] = $this->getAverage($daylengths, false);
        }

        if($this->climate_data_selected['se_daylength'] == 1){
            $metrics_series['SE_Daylength'] = $this->standardError($daylengths);
        }
    
        



        $this->cleanOutRow($rows[0]);
        
        
        /**
         * Append all the site-level summary values calculated in the class to
         * the data array. Since data is aggregated across multiple rows, row
         * index 0 can arbitrariy be populated with the site level sumamry data.
         * 
         */
        foreach($metrics_series as $metric => $v){
            $rows[0][$metric] = $v;
        }
        
        $this->rearrangeData($rows[0], 
                array(
                    "prcp_fall",
                    "prcp_summer",
                    "prcp_spring",
                    "prcp_winter",
                    "tmin_fall",
                    "tmin_summer",
                    "tmin_spring",
                    "tmin_winter",
                    "tmax_fall",
                    "tmax_summer",
                    "tmax_spring",
                    "tmax_winter",
                    "SE_GDDF",
                    "Mean_GDDF",                    
                    "SE_GDD",
                    "Mean_GDD",
                    "Observed_Status_Conflict_Flag_Individual_IDs",
                    "Observed_Status_Conflict_Flag",
                    "Individuals_IDs_with_Multiple_FirstY",
                    "Num_Individuals_with_Multiple_FirstY",
                    "SD_NumDays_Until_Next_No"
                )
        );
        
        return array($rows[0]);
    }
    
    /**
     * 
     * Utility function. Takes the finished list of individual entities and
     * finds which conflict status, if any, takes precedence for the site-
     * summary.
     * Precedence is given to MultiObserver-StatusConflcit
     */
    private function getSummarizedConflictFlag($individuals){
        $flag = "-9999";
        foreach($individuals as $individual){
            if(!empty($individual->conflict_flag) && $individual->conflict_flag != "-9999"){
                if($flag == "MultiObserver-StatusConflict"){
                    continue;
                }else{
                    $flag = $individual->conflict_flag;
                }
            }            
        }
        
        return $flag;
    }
    
    /**
     * 
     * Ultity function to acquire all Individual IDs affected by a status conflict
     * Takes the list of processed individual entities and returns a comma-seperated
     * string of all such affected individuals.
     */
    private function getConflictIndividualIDs($individuals){
        $str = "";
        $arr = array();
        
        foreach($individuals as $individual){
            if(!empty($individual->conflict_flag) && $individual->conflict_flag != "-9999"){
                $arr[] = $individual->individual_id;
            }
        }
        
        if(!empty($arr)){
            $str = implode(",", $arr);
        }
        
        return "'" . $str . "'";
    }

    /**
     * The requirements call for a specific order in which the data elements appear,
     * but we have no control over how they are ordered in the parent class before
     * being passed to this one. Since we need to interweave them, this function
     * allows for a preferred sequence of fields to be specified here. Whatever
     * array is passed in is re-sequenced based on the names in the $arr value
     * also passed in.
     * The scism point between the two calsses is on the SE_NumDays_Until_Next_No
     * field which is hardcoded below.
     */
    private function rearrangeData(&$data, $arr){
        
        $new_pos = "SE_NumDays_Until_Next_No";
        
        foreach($arr as $key){
            if(array_key_exists($key, $data)){
                $this->moveArrayElement($data, $key, $new_pos);
            }
        }
    }
    
    protected function moveArrayElement(&$arr, $key, $new_pos_key){        
        $i = array_search($key, array_keys($arr));
        $out = array_splice($arr, $i, 1);
        $this->array_insert($arr,$new_pos_key, [$key => $out[$key]]);
                
    }
    
    /**
     * This function clears out data variables that may have been passed in
     * from the parent class which aren't relevant anymore. 
     */    
    private function cleanOutRow(&$row){
        foreach(SiteLevelDataSearch::$redundant_fields as $redundant_field_name){
            
            if(array_key_exists($redundant_field_name, $row)){
                unset($row[$redundant_field_name]);
            }
        }
    }
    
    private function getAverage($arr, $do_rounding = true){

        $answer = -9999;
        
        $count = count($arr);
        if($count > 0){
            $answer = array_sum($arr) / $count;
        }

        return ($do_rounding) ? round($answer) : $answer;
    }

    
    /**
     * Note that the two functions below require that the php statistics extension
     * be installed. 
     */
    private function standardError($arr_val){

        $answer = -9999;
        $c = count($arr_val);
        if($c > 1){
            $answer = (stats_standard_deviation($arr_val, true) / sqrt($c)); 
        }

        return $answer;

    }

    private function standardDeviation($arr){
        $answer = -9999;
        $c = count($arr);
        if($c > 1){
            $answer = stats_standard_deviation($arr, true); 
        }

        return $answer;    
    }

    private function getMin($arr){
        return (!empty($arr)) ? $arr[0] : -9999;
    }

    private function getMax($arr){
        return (!empty($arr)) ? $arr[count($arr) - 1] : -9999;
    }

    private function getMedian($arr){
        $median = -9999;
        $c = count($arr);

        if($c == 0){
            $median = -9999;
        }
        else if ($c == 1){
            $median = $arr[0];
        }
        else if($c % 2 == 0){        
            $v1 = $arr[($c / 2) - 1];
            $v2 = $arr[$c/2];
            $median = $this->getAverage( array($v1,$v2));
        }else{
            $median = $arr[$c%2];
        }

        return $median;
    }    
    
    
        

}