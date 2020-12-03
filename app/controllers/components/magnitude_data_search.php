<?php

App::import('Component', 'GenericObservationSearch');
App::import('Component', 'MagnitudePeriod');



/**
 * This is the search type used to handle the observation data
 * that aggregates the abundance values of the observations by
 * time periods and species and phenophase. This is used in
 * the acitivity curves in the viz tool and (eventually) the
 * POP.
 */
class MagnitudeDataSearch extends GenericObservationSearch{
    
    
    /**
     *These are variables to keep track of whatever the period of time interval
     * is supposed to be for grouping the observation records in the query.
     * 
     * frequency = whatever the user passed in, e.g. 'months', 14, 30 , etc.
     * frequencstr = that value resolved to a PHP time interval string, e.g. PD30
     * period_array = array to store magnitude_period objects which just abstracts
     * the start and end period of each of the periods we look at in the search.
     */
    private $frequency;
    private $frequency_str;
    private $period_array;
    
    
    /**
     * The number of days in each month. Needed to determine the period of 
     * interest if the user uses the special 'months' frequency value
     */
    private $months = array(
        1 => 31,
        2 => 28,
        3 => 31,
        4 => 30,
        5 => 31,
        6 => 30,
        7 => 31,
        8 => 31,
        9 => 30,
        10 => 31,
        11 => 30,
        12 => 31
    );
    
    /**
     * Array for keeping track of calculated fields that have been requested by
     * the end user;
     */
    private $magnitude_fields;
    
    public function __construct($emitter){    
        
        
        /**
         * All of the fields we're searching the database for. Some of these
         * fields are aggregated values described below in greater detail
         * as necessary.
         */
        $this->fields = array(
            "Species_ID",
            "Genus",
            "Species",
            "Common_Name",
            "Kingdom",
            "Species_Functional_Type",
            "Species_Category",
            "USDA_PLANTS_Symbol",
            "ITIS_Number",
            "Class_ID",
            "Class_Name",
            "Class_Common_Name",
            "Order_ID",
            "Order_Name",
            "Order_Common_Name",
            "Family_ID",
            "Family_Name",
            "Family_Common_Name",
            "Genus_ID",
            "Genus_Common_Name",         
            "Phenophase_ID",
            "Phenophase_Category",
            "Phenophase_Description",
            "Pheno_Class_ID",
            "Pheno_Class_Name",
            "COUNT(Phenophase_Status) `Status_Records_Sample_Size`",
            "COUNT(DISTINCT Individual_ID) `Individuals_Sample_Size`",
            "COUNT(DISTINCT Site_ID) `Sites_Sample_Size`",
            "SUM(CASE WHEN Phenophase_Status = 1 THEN 1 ELSE 0 END) `Num_Yes_Records`",
/**
 * This SQL Magic works to calculate the number of distinct individuals which ALSO have
 * a positive observation. Because phenophase status is always 1 or 0 we can figure this
 * number of counting the number of distinct values we get by multiplying the phenophase status
 * by the individual's id. The special case is when there is a zero value in that set. In that case
 * we reduce our count by 1.
 */            
            "IF(MIN(Phenophase_Status) = 0, COUNT(DISTINCT Individual_ID * Phenophase_Status ) - 1, COUNT(DISTINCT Individual_ID * Phenophase_Status )) `NumIndividuals_with_Yes_Record`",
            "IF(MIN(Phenophase_Status) = 0, COUNT(DISTINCT Site_ID * Phenophase_Status ) - 1, COUNT(DISTINCT Site_ID * Phenophase_Status )) `NumSites_with_Yes_Record`",

/**
 * These GROUP_CONCAT statements are all ordered by the same variable, so when we later split these strings into an
 * array of values, you always know which values correspond with one another. 
 * 
 */                        
            "GROUP_CONCAT(IF(Phenophase_Status = 1, IF(Abundance_Value = -9999, -1, Abundance_Value), 0) ORDER BY CachedObservation.Observation_ID) `Abundances`",
            "GROUP_CONCAT(IF(Search_Time IS NULL, 0, Search_Time) ORDER BY CachedObservation.Observation_ID) `Search_Times`",
            "GROUP_CONCAT(Observation_Group_ID ORDER BY CachedObservation.Observation_ID) `Observation_Group_IDs`",
            "GROUP_CONCAT(Search_Method ORDER BY CachedObservation.Observation_ID) `Search_Methods`",
            "GROUP_CONCAT(Site_ID ORDER BY CachedObservation.Observation_ID) `Site_IDs`",
            "GROUP_CONCAT(IF(Site_Area IS NULL, 0, Site_Area) ORDER BY CachedObservation.Observation_ID) `Site_Areas`",
            "Observation_Date"
        );
        
        $this->allowed_values = array(
            'species_functional_type' => 'Species_Functional_Type',
            'species_category' =>'Species_Category',
            'usda_plants_symbol' => 'USDA_PLANTS_Symbol',
            'itis_number' => 'ITIS_Number',
            'class_id' => 'Class_ID',
            'class_name' => 'Class_Name',
            'class_common_name' => 'Class_Common_Name',
            'order_id' => 'Order_ID',
            'order_name' => 'Order_Name',
            'order_common_name' => 'Order_Common_Name',
            'family_id' => 'Family_ID',
            'family_name' => 'Family_Name',
            'family_common_name' => 'Family_Common_Name',
            'genus_id' => 'Genus_ID',
            // 'genus' => 'Genus', 
            'genus_common_name' => 'Genus_Common_Name',       
            'phenophase_category' => 'Phenophase_Category',
            'pheno_class_id' => 'Pheno_Class_ID',
            'pheno_class_name' => 'Pheno_Class_Name'
        );
        

        $this->aggregate_fields = array(
            "year" => "Year",
            "start_date" => "Start_Date",
            "start_date_doy" => "Start_Date_DOY",
            "end_date" => "End_Date",
            "end_date_doy" => "End_Date_DOY",
            "in-phase_search_method" => "In-Phase_Search_Method",
            "in-phase_per_hr_search_method" => "In-Phase_per_Hr_Search_Method"
        );
        
        $this->group_by = array('Phenophase_ID', 'Species_ID');
        
        $this->magnitude_fields = array(            
        );
        

        parent::__construct($emitter);
        
        /**
         * In all calculated values we are ignoring phenophase status records = -1
         */
        $this->conditions['CachedObservation.Phenophase_Status >='] = 0;
    }
    
    /**
     * Child class specific logic for procesing the input parameters specified
     * by the end user
     */
    public function processInputParameters($params){
        
        
        if($this->CheckProperty->checkProperty($params,'start_date') && $this->CheckProperty->checkProperty($params, 'end_date')){
                $this->search_params['DataSearchParams']['Start_Date'] = $params->start_date;
                $this->search_params['DataSearchParams']['End_Date'] = $params->end_date;

                $this->conditions["CachedObservation.Observation_Date BETWEEN ? AND ?"] = array($params->start_date, $params->end_date);

        }
        /**
         * This search doesn't do climate variables at all, so just unset
         * anything the user tries to pass in.
         */
        if($this->CheckProperty->checkProperty($params, 'climate')){
            unset($params->climate);
        }
        
        /**
         * Frequency determines the period of days used to aggregate each bunch
         * of observation records. Default is 30, but user can provide any number
         * of days. There's also a cheat value "months", which is handled differently
         * and breaks it into calendar months.
         */
        if($this->CheckProperty->checkProperty($params, 'frequency')){
            if($params->frequency != "months"){
                $this->frequency = $params->frequency - 1;
                $this->frequency_str = "P" . $this->frequency . "D";
            }else{
                $this->frequency = $params->frequency;                   
            }
        }else{
            $this->frequency = 30;
            $this->frequency_str = "P30D";
        }
        
        /**
         * This uses the previously determined frequncy to expand the GROUP BY
         * statement to be used in this query/search.
         */
        if($this->frequency == "months"){
            $this->buildPeriodArrayMonths($params->start_date, $params->end_date);
        }else{        
            $this->buildPeriodArray($params->start_date, $params->end_date, $this->frequency);
        }
        
        $this->createGroupBy();
        
        /**
         * Make sure that any optional calculated fields user has requested
         * are acknowledged.
         */
        if($params->additional_field != null){
            foreach($params->additional_field as $af) {
                $this->magnitude_fields[] = strtolower($af);
            }
        }
        
        $this->cleanTaxonomyParameters($params);
        
        if($this->CheckProperty->checkProperty($params,'genus_id')){
            $params->genus_id = $this->ArrayWrap->arrayWrap($params->genus_id);
            $this->conditions["CachedSummarizedData.Genus_ID"] = $params->genus_id;
            
            
            if($this->CheckProperty->checkProperty($params,'taxonomy_aggregate')){
                
                $this->removeAggregatedTaxonomiesFields($params);
                $params->additional_field = array_merge( 
                        ($this->CheckProperty->checkProperty($params,'additional_field')) ? $params->additional_field : array(), 
                        array("Genus_ID", "Genus", "Genus_Common_Name")
                );

                $this->group_by[] = 'Genus_ID';
                $this->removeGroupBy('Species_ID');
            }

        }
        
        
        if($this->CheckProperty->checkProperty($params,'family_id')){
            $params->family_id = $this->ArrayWrap->arrayWrap($params->family_id);
            $this->conditions["CachedSummarizedData.Family_ID"] = $params->family_id;
            
            
            if($this->CheckProperty->checkProperty($params,'taxonomy_aggregate')){
                
                $this->removeAggregatedTaxonomiesFields($params);
                $params->additional_field = array_merge( 
                        ($this->CheckProperty->checkProperty($params,'additional_field')) ? $params->additional_field : array(), 
                        array("Family_ID", "Family_Name", "Family_Common_Name")
                );

                $this->group_by[] = 'Family_ID';
                $this->removeGroupBy('Species_ID');
            } else {
                $params->additional_field = array_merge( 
                    ($this->CheckProperty->checkProperty($params,'additional_field')) ? $params->additional_field : array(),
                    array("Genus"));
            }

        }
        
        
        
        if($this->CheckProperty->checkProperty($params,'order_id')){
            $params->order_id = $this->ArrayWrap->arrayWrap($params->order_id);
            $this->conditions["CachedSummarizedData.Order_ID"] = $params->order_id;

            
            if($this->CheckProperty->checkProperty($params,'taxonomy_aggregate')){
                
                $this->removeAggregatedTaxonomiesFields($params);
                $params->additional_field = array_merge( 
                        ($this->CheckProperty->checkProperty($params,'additional_field')) ? $params->additional_field : array(), 
                        array("Order_ID", "Order_Name", "Order_Common_Name")
                );

                $this->group_by[] = 'Order_ID';
                $this->removeGroupBy('Species_ID');
            } else {
                $params->additional_field = array_merge( 
                    ($this->CheckProperty->checkProperty($params,'additional_field')) ? $params->additional_field : array(),
                    array("Genus"));
            }
        }        
        
        
        
        if($this->CheckProperty->checkProperty($params,'class_id')){
            $params->class_id = $this->ArrayWrap->arrayWrap($params->class_id);
            $this->conditions["CachedSummarizedData.Class_ID"] = $params->class_id;
            
            if($this->CheckProperty->checkProperty($params,'taxonomy_aggregate')){
                
                $this->removeAggregatedTaxonomiesFields($params);
                $params->additional_field = array_merge( 
                        ($this->CheckProperty->checkProperty($params,'additional_field')) ? $params->additional_field : array(), 
                        array("Class_ID", "Class_Name", "Class_Common_Name")
                );
                $this->group_by[] = 'Class_ID';
                $this->removeGroupBy("Species_ID");
            } else {
                $params->additional_field = array_merge( 
                    ($this->CheckProperty->checkProperty($params,'additional_field')) ? $params->additional_field : array(),
                    array("Genus"));
            }
        }
        
        $this->searchByPhenoClassID($params);
        
        
        parent::processInputParameters($params);
    }
    
    
    
    
    /**
     * Function used to expand the group by function if the user specified
     * the frequency as 'months' instead of a number of days.
     */
    private function buildPeriodArrayMonths($start_date, $end_date){
        $start_date = new DateTime($start_date);
        $end_date = new DateTime($end_date);
        
        do{
            
            $month = $start_date->format('n');
            $year = $start_date->format('Y');
            
            /*
             * If this year is a leap year, add a day to February's extent.
             */
            $leap_year = $this->isLeapYear($year);            
            $last_day = ($month == 2 && $leap_year) ? $this->months[$month] + 1 : $this->months[$month];
            
            /**
             * Set a MagnitudePeriod object as the start date and end date of the
             * conhsidered month, and then advance the do-while loop by adding
             * a month to the start_date value.
             */
            $next_start_date = new DateTime($year . "-" . $month . "-1");
            $next_end_date = new DateTime($year . "-" . $month . "-" . $last_day);            
            $new_period = new MagnitudePeriod($next_start_date, $next_end_date);
            $this->period_array[] = $new_period;
            
            $start_date->add(new DateInterval('P1M'));
        }while($start_date < $end_date);
        
        
    }
    
    
 
    
    /**
     * Function used to expand the group by statments clustering of data
     * if the user specified the period as a number of days.
     */
    private function buildPeriodArray($start_date, $end_date, $frequency){
        
        $start_date = new DateTime($start_date);
        $end_date = new DateTime($end_date);
        $num_periods = -1;
        while($start_date <= $end_date){
            
            $period_end_date = clone $start_date;
            $period_end_date->add(new DateInterval($this->frequency_str));
            
            $new_period = new MagnitudePeriod($start_date, $period_end_date);
            $this->period_array[] = $new_period;
            $start_date = clone $period_end_date;
            $start_date->add(new DateInterval('P1D'));
            $num_periods++;
        }
        
        /**
         * All this does is looks at the last period created, and if it's too
         * long (goes outside the date range specified by the user), then cut
         * that period from the array, and instead expand the last period to 
         * cover the entire specified period.
         * 
         * This was based on a decision we made that on uneven periods of time,
         * the last period would encompass any of the remaining days.
         */
        if($this->period_array[$num_periods]->end_date > $end_date){
            unset($this->period_array[$num_periods--]);
            $this->period_array[$num_periods]->end_date = $end_date;
        }

    }
    
    
    /**
     * We can use a bunch of group by clauses in our SQL query to cluser all
     * the data points. This function just iterates the period array and adds
     * those group by clauses based on the periods of activity we determined
     * to be valid.
     */
    private function createGroupBy(){
        
        foreach($this->period_array as $period){
            $group_str = "(Observation_Date BETWEEN '" . $period->start_date->format('Y-m-d') . "'AND '" . $period->end_date->format('Y-m-d') . "')";
            array_unshift($this->group_by, $group_str);

        }
    }
    

    
    /**
     * This adds a bunch of fields and does cleanup on the database results. 
     * Most of the function names are self-explanatory.
     */
    public function preprocessResults($data, &$result_set){
        
        
        $this->findStartEndDates($data);
        
        $this->splitArrays($data);
        
        $this->calculateProprotions($data);
        
        $this->calculateTotalAnimalInPhase($data);
        
        $this->calculateAnimalsInPhasePerHour($data);         
        
        $this->shuffleKingdomFields($data);
        
        $this->cleanupGroupData($data);
        
        $this->moveElements($data);
        
        $this->unsetOptionalFields($data);
        
        return array($data);
    }
    
    /*
     * Some of the fields are GROUP_CONCAT statements, so we can use this function
     * to split them apart once here, instead of doing it each time in every
     * function that needs one of these variables to caluclate its metrics.
     */
    private function splitArrays(&$data){
        $data['Abundances'] = explode(",", $data['Abundances']);
        $data['Search_Times'] = explode(",", $data['Search_Times']);
        
        $data['Observation_Group_IDs'] = explode(",", $data['Observation_Group_IDs']);
        $data['Search_Methods'] = explode(",", $data['Search_Methods']);
        $data['Site_IDs'] = explode(",", $data['Site_IDs']);
        $data['Site_Areas'] = explode(",", $data['Site_Areas']);
    }
    
    
    /**
     * We're very specific about the order in which the fields appear in the results
     * so this funcion and the next moveElement allows us to rearrange them as 
     * needed.
     */
    private function moveElements(&$data){
        if(in_array("Phenophase_Description", $this->fields)){
        	$data = $this->moveElement($data, "Year", "Phenophase_Description");        
        }else{
            $data = $this->moveElement($data, "Year", "Pheno_Class_Name");
        }
        $data = $this->moveElement($data, "Start_Date", "Year");
        $data = $this->moveElement($data, "Start_Date_DOY", "Start_Date");
        $data = $this->moveElement($data, "End_Date", "Start_Date_DOY");
        $data = $this->moveElement($data, "End_Date_DOY", "End_Date");
        
        $data = $this->moveElement($data, "NumSites_with_Yes_Record", "NumIndividuals_with_Yes_Record");
        $data = $this->moveElement($data, "Proportion_Sites_with_Yes_Record", "Proportion_Individuals_with_Yes_Record");

    }
    
    private function unsetOptionalFields(&$data){
        if(!in_array("start_date_doy", $this->magnitude_fields)) {
            unset($data['Start_Date_DOY']);
        }
        
        if(!in_array("end_date_doy", $this->magnitude_fields)) {
            unset($data['End_Date_DOY']);
        }
        
        if(!in_array("sd_numanimals_in-phase", $this->magnitude_fields)) {
            unset($data['SD_NumAnimals_In-Phase']);
        }
        
        if(!in_array("sd_numanimals_in-phase_per_hr", $this->magnitude_fields)) {
            unset($data['SD_NumAnimals_In-Phase_per_Hr']);
        }
        
        if(!in_array("sd_numanimals_in-phase_per_hr_per_acre", $this->magnitude_fields)) {
            unset($data['SD_NumAnimals_In-Phase_per_Hr_per_Acre']);
        }
        
        if(!in_array("in-phase_search_method", $this->magnitude_fields)) {
            unset($data['In-Phase_Search_Method']);
        }
        
        if(!in_array("in-phase_per_hr_search_method", $this->magnitude_fields)){
            unset($data["In-Phase_per_Hr_Search_Method"]);
        }
        
    }
    
    /**
     * Since the keys we're working with are non-numeric, this works by re-building
     * the arrawy completely.
     */
    private function moveElement(&$data, $move_key, $new_pos_key){
        $new_array = array();
        
        foreach($data as $key => $value){
            if($key == $move_key){
                continue;
            }
            
            $new_array[$key] = $value;
            
            if($key == $new_pos_key){
                $new_array[$move_key] = $data[$move_key];
            }
        }
        
        return $new_array;
        
        
        
        
    }
    
    /**
     * This actually calcualtes both animals in phase per hour and -in phase
     * per hour per acre.
     */
    private function calculateAnimalsInPhasePerHour(&$data){
        if($data['Kingdom'] != "Animalia"){
            $data['In-Phase_per_Hr_Search_Method'] = -9999;
            $data['In-Phase_per_Hr_Sites_Sample_Size'] = -9999;
            $data['In-Phase_per_Hr_Site_Visits_Sample_Size'] = -9999;
            
            $data['Mean_NumAnimals_In-Phase_per_Hr'] = -9999;
            $data['SE_NumAnimals_In-Phase_per_Hr'] = -9999;
            $data['SD_NumAnimals_In-Phase_per_Hr'] = -9999;
            
            
            
            $data['In-Phase_per_Hr_per_Acre_Sites_Sample_Size'] = -9999;
            $data['In-Phase_per_Hr_per_Acre_Site_Visits_Sample_Size'] = -9999;
            $data['Mean_NumAnimals_In-Phase_per_Hr_per_Acre'] = -9999;
            $data['SE_NumAnimals_In-Phase_per_Hr_per_Acre'] = -9999;
            $data['SD_NumAnimals_In-Phase_per_Hr_per_Acre'] = -9999;            
            
        }else{
            
            
            $abundance_averages_grouped_by_site_visit = array();
            $area_abundance_averages_grouped_by_site_visit = array();
            $site_visit_to_time_spent = array();
            $site_visit_to_site_area = array();
            $search_methods_unique = array();
            $site_visits_count = array();
            $site_ids_count = array();
            
            $area_site_ids_count = array();
            $area_site_visits_count = array();
            
            /*
             * We are making these variables simply so we can reference them
             * in shorthand, hence the assignment by reference.
             */
            $abundances = &$data['Abundances'];
            $time_spent = &$data['Search_Times'];
            $search_methods = &$data['Search_Methods'];
            $site_vists_ids = &$data['Observation_Group_IDs'];
            $site_ids = &$data['Site_IDs'];
            $site_areas = &$data['Site_Areas'];
            
            /*
             * We have to consider each abundance record in the cluster.
             */
            $c = count($abundances);           
            for($i=0; $i < $c; $i++){
                /**
                 * We don't consider data if there's not abundance provided or
                 * the time spent observing wasn't provided or if that value
                 * is greater than 2 hours, OR if the search type wasn't provided
                 * or the search type was incidental.
                 */
                if($abundances[$i] > -1 && $time_spent[$i] > 0 && $time_spent[$i] <= 180 && $search_methods[$i] != -9999 && $search_methods[$i] != "Incidental" && $search_methods[$i] != ""){
                    
                    //hash site_visit_id to time_spent and site_area
                    $site_visit_to_time_spent[$site_vists_ids[$i]] = $time_spent[$i];
                    $site_visit_to_site_area[$site_vists_ids[$i]] = $site_areas[$i];

                    // bin abundances by site visit id
                    if(array_key_exists($site_vists_ids[$i], $abundance_averages_grouped_by_site_visit)) {
                        $abundance_averages_grouped_by_site_visit[$site_vists_ids[$i]] += $abundances[$i];
                    } else {
                        $abundance_averages_grouped_by_site_visit[$site_vists_ids[$i]] = $abundances[$i];
                    }
                    
                    if($search_methods[$i] != ""){
                        $search_methods_unique[$search_methods[$i]] = 1;
                    }
                    $site_visits_count[$site_vists_ids[$i]] = 1;
                    $site_ids_count[$site_ids[$i]] = 1;
                    
                    
                    /**
                     * We have to do additional processing to figure out the same
                     * metric per acre, because the criteria for using data is different
                     * We are then only concerned with if the search was an area search.
                     */
                    if(($search_methods[$i] == "Area search" || $search_methods[$i] == "Area Search") && $site_areas[$i] > 0){
                        $area_site_ids_count[$site_ids[$i]] = 1;
                        $area_site_visits_count[$site_vists_ids[$i]] = 1;
                        
                        // bin abundances by site visit id
                        if(array_key_exists($site_vists_ids[$i], $area_abundance_averages_grouped_by_site_visit)) {
                            $area_abundance_averages_grouped_by_site_visit[$site_vists_ids[$i]] += $abundances[$i];
                        } else {
                            $area_abundance_averages_grouped_by_site_visit[$site_vists_ids[$i]] = $abundances[$i];
                        }
                    }

                }
            }

            // Each division will only happen once as site_visit_id is a unqiue key
            // The time spent observing is measured in minutes, so we must divide by 60 to get # of hours.
            foreach ($site_visit_to_time_spent as $site_visit_id => $timespent) {
                $abundance_averages_grouped_by_site_visit[$site_visit_id] = $abundance_averages_grouped_by_site_visit[$site_visit_id] / ($timespent / 60);
                if(array_key_exists($site_visit_id, $area_abundance_averages_grouped_by_site_visit)) {
                    $area_abundance_averages_grouped_by_site_visit[$site_visit_id] = ($area_abundance_averages_grouped_by_site_visit[$site_visit_id] / ($timespent / 60)) / $site_visit_to_site_area[$site_visit_id];
                }
            }
            
            
            $data['In-Phase_per_Hr_Search_Method'] = implode(",", array_keys($search_methods_unique));
            $data['In-Phase_per_Hr_Sites_Sample_Size'] = count($site_ids_count);
            $data['In-Phase_per_Hr_Site_Visits_Sample_Size'] = count($site_visits_count);
            
            if(count($abundance_averages_grouped_by_site_visit) > 0){
                $data['Mean_NumAnimals_In-Phase_per_Hr'] = round(array_sum($abundance_averages_grouped_by_site_visit) / count($abundance_averages_grouped_by_site_visit), 2);                
                $data['SE_NumAnimals_In-Phase_per_Hr'] = $this->standardError($abundance_averages_grouped_by_site_visit);
                $data['SD_NumAnimals_In-Phase_per_Hr'] = round(stats_standard_deviation($abundance_averages_grouped_by_site_visit, false), 2);    
            }else{
                $data['Mean_NumAnimals_In-Phase_per_Hr'] = -9999;
                $data['SE_NumAnimals_In-Phase_per_Hr'] = -9999;
                $data['SD_NumAnimals_In-Phase_per_Hr'] = -9999;
                
            }
            
            $data['In-Phase_per_Hr_per_Acre_Sites_Sample_Size'] = count($area_site_ids_count);
            $data['In-Phase_per_Hr_per_Acre_Site_Visits_Sample_Size'] = count($area_site_visits_count);
            
            if(count($area_abundance_averages_grouped_by_site_visit) > 0){
                $data['Mean_NumAnimals_In-Phase_per_Hr_per_Acre'] = round(array_sum($area_abundance_averages_grouped_by_site_visit) / count($area_abundance_averages_grouped_by_site_visit), 2);
                $data['SE_NumAnimals_In-Phase_per_Hr_per_Acre'] = $this->standardError($area_abundance_averages_grouped_by_site_visit);
                $data['SD_NumAnimals_In-Phase_per_Hr_per_Acre'] = round(stats_standard_deviation($area_abundance_averages_grouped_by_site_visit, false), 2);
                
            }else{
                $data['Mean_NumAnimals_In-Phase_per_Hr_per_Acre'] = -9999;
                $data['SE_NumAnimals_In-Phase_per_Hr_per_Acre'] = -9999;
                $data['SD_NumAnimals_In-Phase_per_Hr_per_Acre'] = -9999;
                
            }

        }
    }    
    
    private function calculateTotalAnimalInPhase(&$data){
        if($data['Kingdom'] != "Animalia"){
            
            $data['In-Phase_Search_Method'] = -9999;
            $data['In-Phase_Sites_Sample_Size'] = -9999;
            $data['In-Phase_Site_Visits_Sample_Size'] = -9999;            
            $data['Total_NumAnimals_In-Phase'] = -9999;            
            $data['Mean_NumAnimals_In-Phase'] = -9999;
            $data['SE_NumAnimals_In-Phase'] = -9999;
            $data['SD_NumAnimals_In-Phase'] = -9999;
            
            
        }else{
            
            /*
             * We are making these variables simply so we can reference them
             * in shorthand, hence the assignment by reference.
             */
            $abundances = &$data['Abundances'];
            $search_methods = &$data['Search_Methods'];
            $site_vists_ids = &$data['Observation_Group_IDs'];
            $site_ids = &$data['Site_IDs'];

            
            $c = count($abundances);
            $site_visits_count = array();
            $site_id_count = array();
            $search_methods_unique = array();
            $abundances_used = array();
            $abundances_grouped_by_site_visit = array();
            
            $total = 0;
            
            /*
             * We have to consider each abundance record in the cluster.
             */
            for($i=0; $i < $c; $i++){
                
                /**
                 * We are only interested in data points where abundance was provided.
                 */
                if($abundances[$i] > -1){
                    $site_visits_count[$site_vists_ids[$i]] = 1;
                    
                    // group the abundances by site visit
                    if(array_key_exists($site_vists_ids[$i], $abundances_grouped_by_site_visit)) {
                        $abundances_grouped_by_site_visit[$site_vists_ids[$i]] += $abundances[$i];
                    } else {
                        $abundances_grouped_by_site_visit[$site_vists_ids[$i]] = $abundances[$i];
                    }
                    
                    $site_id_count[$site_ids[$i]] = 1;
                    
                    if($search_methods[$i] != "" && $search_methods[$i] != -9999){
                        $search_methods_unique[$search_methods[$i]] = 1;
                    }
                    
                    $total += $abundances[$i];
                    $abundances_used[] = $abundances[$i];
                }
            }
            
            $search_methods_used = implode(",", array_keys($search_methods_unique));
            $site_visits_count = count($site_visits_count);
            $site_id_count = count($site_id_count);
            
            $data['In-Phase_Search_Method'] = $search_methods_used;
            $data['In-Phase_Sites_Sample_Size'] = $site_id_count;
            $data['In-Phase_Site_Visits_Sample_Size'] = $site_visits_count;
            
            $data['Total_NumAnimals_In-Phase'] = $total;
            
            if($site_visits_count > 0){
                $data['Mean_NumAnimals_In-Phase'] = round($total / $site_visits_count, 2);
                $data['SE_NumAnimals_In-Phase'] = $this->standardError($abundances_grouped_by_site_visit);
                $data['SD_NumAnimals_In-Phase'] = round(stats_standard_deviation($abundances_grouped_by_site_visit, false), 2);
            }else{
                $data['Mean_NumAnimals_In-Phase'] = -9999;
                $data['SE_NumAnimals_In-Phase'] = -9999;          
                $data['SD_NumAnimals_In-Phase'] = -9999;
                
            }
            
        }
    }
    
    /**
     * Since each data point is tied to a specific date, we have to use this function
     * to figure out which period the date falls between.
     */
    private function findStartEndDates(&$data){
        
        $obs_date = new DateTime($data['Observation_Date']);
        
        foreach($this->period_array as $period){
            if($obs_date >= $period->start_date && $obs_date <= $period->end_date){
                $data['Start_Date'] = $period->start_date->format('Y-m-d');
                $data['End_Date'] = $period->end_date->format('Y-m-d');
                
                $data['Year'] = $period->start_date->format('Y');
                $data['Start_Date_DOY'] = $period->start_date->format('z') + 1;
                
                $data['End_Date_DOY'] = $period->end_date->format('z') + 1;
                
                unset($data['Observation_Date']);
                break;
            }            
        }        
    }
    
    private function calculateProprotions(&$data){
        
        if($data['Status_Records_Sample_Size'] > 0){
            $data['Proportion_Yes_Records'] = round($data['Num_Yes_Records'] / $data['Status_Records_Sample_Size'], 2);
        }else{
            $data['Proportion_Yes_Records'] = -9999;
        }
        
        if($data['Individuals_Sample_Size'] > 0){
            $data['Proportion_Individuals_with_Yes_Record'] = round($data['NumIndividuals_with_Yes_Record'] / $data['Individuals_Sample_Size'], 2);
        }else{
            $data['Proportion_Individuals_with_Yes_Record'] = -9999;
        }
    }
    
    /**
     * The requirements specify some fields as being for animals or for plants
     * and gives each one a different title. In reality, these metrics are calculated
     * exactly the same, so this function looks at the kingdom to determine which
     * fields should be populated with the values.
     */
    private function shuffleKingdomFields(&$data){
        
        $is_animal = ($data['Kingdom'] == "Animalia");
        
        if($is_animal){
            $data['NumIndividuals_with_Yes_Record'] = -9999;
            
            $data['Proportion_Sites_with_Yes_Record'] = round($data['NumSites_with_Yes_Record'] / $data['Sites_Sample_Size'], 2);
            $data['Proportion_Individuals_with_Yes_Record'] = -9999;
        }else{
            $data['NumSites_with_Yes_Record'] = -9999;
            $data['Proportion_Sites_with_Yes_Record'] = -9999;
        }
        
        
    }
    
    /**
     * We want to remove all the GROUP_CONCAT fields before sending the data to
     * the user.
     */
    private function cleanupGroupData(&$data){
        unset($data['Abundances']);
        unset($data['Search_Times']);
        unset($data['Observation_Group_IDs']);
        
        unset($data['Search_Methods']);
        unset($data['Site_IDs']);
        unset($data['Site_Areas']);
        
    }
    
    private function isLeapYear($year) {
        return ((($year % 4) == 0) && ((($year % 100) != 0) || (($year % 400) == 0)));
    }
    
    private function standardError($arr_val){

        $answer = -9999;
        $c = count($arr_val);
        if($c > 1){
            $answer = round((stats_standard_deviation($arr_val, false) / sqrt($c)), 2); 
        }

        return $answer;
    }

}
