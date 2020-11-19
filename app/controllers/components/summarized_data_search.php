<?php

App::import('Component', 'GenericObservationSearch');

class SummarizedDataSearch extends GenericObservationSearch{
    
    private $num_days_quality_filter_individual;
   
    private static $group_data_aliases = array(
            "gtmax_winter",
            "gtmax_spring",
            "gtmax_summer",
            "gtmax_fall",
            "gtmin_winter",
            "gtmin_spring",
            "gtmin_summer",
            "gtmin_fall",        
            "gprcp_winter",
            "gprcp_spring",
            "gprcp_summer",
            "gprcp_fall",        
            "gtmax",
            "gtmin",
            "gtmaxf",
            "gtminf",        
            "gprcp",
            "ggdd",
            "ggddf",        
            "gacc_prcp",
            "gdaylength",        
            "gd",
            "ge",
            "go",
            "gc",
            "gds"        
    );
    
    
    public function __construct($emitter){
        
        $this->num_days_quality_filter_individual = null;
        
        $this->fields = array(
            "Dataset_ID",
            "ObservedBy_Person_ID",
            "Partner_Group",
            "Site_ID",
            "Site_Name",
            "Latitude",
            "Longitude",
            "Elevation_in_Meters",
            "State",
            "Species_ID",
            "Genus", //todo: kev
            "Species",
            "Common_Name",
            "Kingdom",
            
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
            
            "Species_Functional_Type",
            "Species_Category",
            "Lifecycle_Duration",
            "Growth_Habit",
            "USDA_PLANTS_Symbol",
            "ITIS_Number",
            "Individual_ID",
            "Plant_Nickname",
            "Patch",
            "Phenophase_ID",
            "Phenophase_Category",
            "Phenophase_Description",
            "Pheno_Class_ID",
            "Pheno_Class_Name",              
            "GROUP_CONCAT(CachedObservation.Observation_Date ORDER BY Observation_Date ASC, Phenophase_Status DESC) AS gd",
            "GROUP_CONCAT(CachedObservation.Phenophase_Status ORDER BY Observation_Date ASC, Phenophase_Status DESC) AS ge",
            "GROUP_CONCAT(CachedObservation.ObservedBy_Person_ID ORDER BY Observation_Date ASC, Phenophase_Status DESC) AS go",
            "GROUP_CONCAT(CachedObservation.Observed_Status_Conflict_Flag ORDER BY Observation_Date ASC, Phenophase_Status DESC) AS gc",
            "GROUP_CONCAT(CachedObservation.Dataset_ID ORDER BY Observation_Date ASC, Phenophase_Status DESC) AS gds",
            "GROUP_CONCAT(CachedObservation.tmax_winter ORDER BY Observation_Date ASC, Phenophase_Status DESC) AS gtmax_winter",
            "GROUP_CONCAT(CachedObservation.tmax_spring ORDER BY Observation_Date ASC, Phenophase_Status DESC) AS gtmax_spring",
            "GROUP_CONCAT(CachedObservation.tmax_summer ORDER BY Observation_Date ASC, Phenophase_Status DESC) AS gtmax_summer",
            "GROUP_CONCAT(CachedObservation.tmax_fall ORDER BY Observation_Date ASC, Phenophase_Status DESC) AS gtmax_fall",
            "GROUP_CONCAT(CachedObservation.tmax ORDER BY Observation_Date ASC, Phenophase_Status DESC) AS gtmax",
            "GROUP_CONCAT(CachedObservation.tmin ORDER BY Observation_Date ASC, Phenophase_Status DESC) AS gtmin",            
            "GROUP_CONCAT(CachedObservation.tmaxf ORDER BY Observation_Date ASC, Phenophase_Status DESC) AS gtmaxf",
            "GROUP_CONCAT(CachedObservation.tminf ORDER BY Observation_Date ASC, Phenophase_Status DESC) AS gtminf",                        
            "GROUP_CONCAT(CachedObservation.prcp ORDER BY Observation_Date ASC, Phenophase_Status DESC) AS gprcp",
            "GROUP_CONCAT(CachedObservation.gdd ORDER BY Observation_Date ASC, Phenophase_Status DESC) AS ggdd",
            "GROUP_CONCAT(CachedObservation.gddf ORDER BY Observation_Date ASC, Phenophase_Status DESC) AS ggddf",            
            "GROUP_CONCAT(CachedObservation.daylength ORDER BY Observation_Date ASC, Phenophase_Status DESC) AS gdaylength",
            "GROUP_CONCAT(CachedObservation.acc_prcp ORDER BY Observation_Date ASC, Phenophase_Status DESC) AS gacc_prcp",            
            "GROUP_CONCAT(CachedObservation.tmin_winter ORDER BY Observation_Date ASC, Phenophase_Status DESC) AS gtmin_winter",
            "GROUP_CONCAT(CachedObservation.tmin_spring ORDER BY Observation_Date ASC, Phenophase_Status DESC) AS gtmin_spring",
            "GROUP_CONCAT(CachedObservation.tmin_summer ORDER BY Observation_Date ASC, Phenophase_Status DESC) AS gtmin_summer",
            "GROUP_CONCAT(CachedObservation.tmin_fall ORDER BY Observation_Date ASC, Phenophase_Status DESC) AS gtmin_fall",
            "GROUP_CONCAT(CachedObservation.prcp_winter ORDER BY Observation_Date ASC, Phenophase_Status DESC) AS gprcp_winter",
            "GROUP_CONCAT(CachedObservation.prcp_spring ORDER BY Observation_Date ASC, Phenophase_Status DESC) AS gprcp_spring",
            "GROUP_CONCAT(CachedObservation.prcp_summer ORDER BY Observation_Date ASC, Phenophase_Status DESC) AS gprcp_summer",
            "GROUP_CONCAT(CachedObservation.prcp_fall ORDER BY Observation_Date ASC, Phenophase_Status DESC) AS gprcp_fall",
            "NumYs_in_Series",
            "NumDays_in_Series",
            "Multiple_Observers",
            "Multiple_FirstY",
            "Observed_Status_Conflict_Flag",

            "Greenup_0",
            "Greenup_1",
            "MidGreenup_0",
            "MidGreenup_1",
            "Peak_0",
            "Peak_1",
            "NumCycles",
            "Maturity_0",
            "Maturity_1",
            "MidGreendown_0",
            "MidGreendown_1",
            "Senescence_0",
            "Senescence_1",
            "Dormancy_0",
            "Dormancy_1",
            "EVI_Minimum_0",
            "EVI_Minimum_1",
            "EVI_Amplitude_0",
            "EVI_Amplitude_1",
            "EVI_Area_0",
            "EVI_Area_1",
            "QA_Detailed_0",
            "QA_Detailed_1",
            "QA_Overall_0",
            "QA_Overall_1"
        );
        
        $this->allowed_values = array(
            'observedby_person_id' => 'ObservedBy_Person_ID',
            'partner_group' => 'Partner_Group',
            'site_name' => 'Site_Name',
            'species_functional_type' => 'Species_Functional_Type',
            'species_category' => 'Species_Category',
            'usda_plants_symbol' => 'USDA_PLANTS_Symbol',
            'itis_number' => 'ITIS_Number',
            'growth_habit' => 'Growth_Habit',
            'lifecycle_duration' => 'Lifecycle_Duration',
            'plant_nickname' => 'Plant_Nickname',
            'patch' => 'Patch',
            'phenophase_category' => 'Phenophase_Category',
            'pheno_class_id' => 'Pheno_Class_ID',
            'pheno_class_name' => 'Pheno_Class_Name',
            'observed_status_conflict_flag' => 'Observed_Status_Conflict_Flag',
            'status_conflict_related_records' => 'Status_Conflict_Related_Records',
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
            'genus_common_name' => 'Genus_Common_Name',   
            
            "greenup_0" => "Greenup_0",
            "greenup_1" => "Greenup_1",
            "midgreenup_0" => "MidGreenup_0",
            "midgreenup_1" => "MidGreenup_1",
            "peak_0" => "Peak_0",
            "peak_1" => "Peak_1",
            "numcycles" => "NumCycles",
            "maturity_0" => "Maturity_0",
            "maturity_1" => "Maturity_1",
            "midgreendown_0" => "MidGreendown_0",
            "midgreendown_1" => "MidGreendown_1",
            "senescence_0" => "Senescence_0",
            "senescence_1" => "Senescence_1",
            "dormancy_0" => "Dormancy_0",
            "dormancy_1" => "Dormancy_1",
            "evi_minimum_0" => "EVI_Minimum_0",
            "evi_minimum_1" => "EVI_Minimum_1",
            "evi_amplitude_0" => "EVI_Amplitude_0",
            "evi_amplitude_1" => "EVI_Amplitude_1",
            "evi_area_0" => "EVI_Area_0",
            "evi_area_1" => "EVI_Area_1",
            "qa_detailed_0" => "QA_Detailed_0",
            "qa_detailed_1" => "QA_Detailed_1",
            "qa_overall_0" => "QA_Overall_0",
            "qa_overall_1" => "QA_Overall_1"
        );
        
        $this->aggregate_fields = array(
            'dataset_id' => 'Dataset_ID',
            'numys_in_series' => 'NumYs_in_Series',
            'numdays_in_series' => 'NumDays_in_Series',
            'multiple_observers' => 'Multiple_Observers',
            'multiple_firsty' => 'Multiple_FirstY'
        );
        
        $this->group_by = array('CachedObservation.Series_ID');

        parent::__construct($emitter);
    }
    
    public function processInputParameters($params){
        
        
        if($this->CheckProperty->checkProperty($params,'num_days_quality_filter_individual')){
            $this->num_days_quality_filter_individual = $params->num_days_quality_filter_individual;
           
            $this->search_params['DataSearchParams']['Number_Days_Quality_Filter'] = $this->num_days_quality_filter_individual;
        }
        
        if($this->CheckProperty->checkProperty($params,'start_date') && $this->CheckProperty->checkProperty($params, 'end_date')){

                $this->search_params['DataSearchParams']['Start_Date'] = $params->start_date;
                $this->search_params['DataSearchParams']['End_Date'] = $params->end_date;

                $this->conditions["CachedObservation.Observation_Date BETWEEN ? AND ?"] = array($params->start_date, $params->end_date);
        }
        
        if($this->CheckProperty->checkProperty($params, 'individual_ids')){
            
            //$params->individual_ids = $this->ArrayWrap->arrayWrap($params->individual_ids);
            
            $this->search_params['DataSearchParams']['Individual_IDs'] = $params->individual_ids;
            $individual_ids = explode(",", $params->individual_ids);
            
            if($individual_ids && count($individual_ids) > 0){
                $this->conditions["CachedSummarizedData.Individual_ID"] = $individual_ids;
            }
                        
        }
        
        $this->cleanTaxonomyParameters($params);
        $this->cleanPhenoClassParams($params);
        
        if($this->CheckProperty->checkProperty($params,'pheno_class_id')){
            $params->pheno_class_id = $this->ArrayWrap->arrayWrap($params->pheno_class_id);
            
            
            $params->additional_field = array_merge( 
                    ($this->CheckProperty->checkProperty($params,'additional_field')) ? $params->additional_field : array(), 
                    array("Pheno_Class_ID", "Pheno_Class_Name")
            );               

            $this->search_params['DataSearchParams']['Pheno_Class_IDs'] = implode(",",$params->pheno_class_id);
            $this->conditions["CachedSummarizedData.Pheno_Class_ID"] = $params->pheno_class_id;
        }
        
        
        if($this->CheckProperty->checkProperty($params,'order_id')){
            $params->additional_field = array_merge( 
                    ($this->CheckProperty->checkProperty($params,'additional_field')) ? $params->additional_field : array(), 
                    array("Order_ID", "Order_Name", "Order_Common_Name")
            );            
        }
        
        if($this->CheckProperty->checkProperty($params,'class_id')){
            $params->additional_field = array_merge( 
                    ($this->CheckProperty->checkProperty($params,'additional_field')) ? $params->additional_field : array(), 
                    array("Class_ID", "Class_Name", "Class_Common_Name")
            );            
        }
        
        if($this->CheckProperty->checkProperty($params,'family_id')){
            $params->additional_field = array_merge( 
                    ($this->CheckProperty->checkProperty($params,'additional_field')) ? $params->additional_field : array(), 
                    array("Family_ID", "Family_Name", "Family_Common_Name")
            );            
        }   

        if($this->CheckProperty->checkProperty($params,'genus_id')){
            $params->additional_field = array_merge( 
                    ($this->CheckProperty->checkProperty($params,'additional_field')) ? $params->additional_field : array(), 
                    array("Genus_ID", "Genus", "Genus_Common_Name")
            );            
        }  
        
        
        
        parent::processInputParameters($params);
    }
    
    
    public function preprocessResults($data, &$result_set){
        date_default_timezone_set('UTC');
        $groups = array(
            'extents' => explode(",",$data['ge']),
            'dates' => explode(",", $data['gd']),
            'observer_ids' => explode(",", $data['go']),
            'conflicts' => explode(",", $data['gc']),
            'dataset_ids' => explode(",", $data['gds']),

            'tmax_winters' => explode(",",$data['gtmax_winter']),
            'tmax_springs' => explode(",", $data['gtmax_spring']),
            'tmax_summers' => explode(",", $data['gtmax_summer']),
            'tmax_falls' => explode(",", $data['gtmax_fall']),
            
            'tmin_winters' => explode(",",$data['gtmin_winter']),
            'tmin_springs' => explode(",", $data['gtmin_spring']),
            'tmin_summers' => explode(",", $data['gtmin_summer']),
            'tmin_falls' => explode(",", $data['gtmin_fall']),
            
            'prcp_winters' => explode(",",$data['gprcp_winter']),
            'prcp_springs' => explode(",", $data['gprcp_spring']),
            'prcp_summers' => explode(",", $data['gprcp_summer']),
            'prcp_falls' => explode(",", $data['gprcp_fall']),            
            
            'tmaxs' => explode(",", $data['gtmax']),
            'tmins' => explode(",", $data['gtmin']),            
            'tmaxfs' => explode(",", $data['gtmaxf']),
            'tminfs' => explode(",", $data['gtminf']),            
            'prcps' => explode(",", $data['gprcp']),
            'acc_prcps' => explode(",", $data['gacc_prcp']),
            'daylengths' => explode(",", $data['gdaylength']),            
            'gdds' => explode(",", $data['ggdd']),
            'gddfs' => explode(",", $data['ggddf'])            
        );

        
        
        $results_array = array();              
        
        $this->createSeries($groups, $data, $results_array);        
        
        return array_reverse($results_array, true);
    }
    
    
    protected function createSeries($group_data, $other_fields, &$results_array, $previous_year = null){
        
        $extents = $group_data['extents'];
        $dates = $group_data['dates'];
        $observer_ids = $group_data['observer_ids'];
        $conflicts = $group_data['conflicts'];
        $dataset_ids = $group_data['dataset_ids'];
        
        $tmaxs = $group_data['tmaxs'];
        $tmins = $group_data['tmins'];
        
        $tmaxfs = $group_data['tmaxfs'];
        $tminfs = $group_data['tminfs'];        
        
        $tmax_winters = $group_data['tmax_winters'];
        $tmax_springs = $group_data['tmax_springs'];
        $tmax_summers = $group_data['tmax_summers'];
        $tmax_falls = $group_data['tmax_falls'];
        
        $tmin_winters = $group_data['tmin_winters'];
        $tmin_springs = $group_data['tmin_springs'];
        $tmin_summers = $group_data['tmin_summers'];
        $tmin_falls = $group_data['tmin_falls'];
        
        $prcp_winters = $group_data['prcp_winters'];
        $prcp_springs = $group_data['prcp_springs'];
        $prcp_summers = $group_data['prcp_summers'];
        $prcp_falls = $group_data['prcp_falls'];        
        
        $prcps = $group_data['prcps'];
        $gdds = $group_data['gdds'];
        $gddfs = $group_data['gddfs'];        
        
        
        $daylengths = $group_data['daylengths'];
        $acc_prcps = $group_data['acc_prcps'];        
        
        
        $c = count($extents);
        
        $first_yes_index = -1;
        $previous_no_index = -1;
        $last_yes_index = -1;
        $next_no_index = -1;
        $num_yes = 0;
        $first_yes_year = null;
        $parent_same_year = 0;
        $other_fields['Multiple_Observers'] = 0;
        

        /**
         * Start iterating through each of the extent values. 
         */
        for($i=0;$i<$c;$i++){
            /**
             * Keep iterating until you find the first '1' extent value.
             */
            if($extents[$i] == 1){
                $days_identical = 0;
                while(($days_identical + $i + 1) < $c && $dates[$i] == $dates[($days_identical + $i + 1)]){
                    $days_identical++;
                }
                                
                $num_yes++;
                
                

                /**
                 * Assign first yes index. Last yes is the same until proven otherwise 
                 */
                $first_yes_index = $i;
                $last_yes_index = $i;

                $first_yes_year = date("Y", strtotime($dates[$first_yes_index]));
 
                /**
                 * If this series' first yes happened in the same year as the previous
                 * series' first yes, then mark the flag as such and prepare to return
                 * the same value to the calling instance of the createSeries function. 
                 */
                if($previous_year && $previous_year == $first_yes_year){
                    $other_fields['Multiple_FirstY'] = 1;
                    $parent_same_year = 1;
                }

                /**
                 * Once the first yes is known, it's possible to determine that the
                 * previous no is, if there is one. Iterate backwards through the series
                 * at the current cursor's location until a '0' extent is found. 
                 */
                if($i > 0){
                    for($k = $i - 1; $k >= 0; $k--){
                        if($dates[$i] == $dates[$k]){
                            $k--;
                            continue;                            
                        }
                        
                        if($extents[$k] == 0){
                            $previous_no_index = $k;
                            break;
                        }
                    }
                }
                
                $i += $days_identical;

                /**
                 * Continue iterating through the series, forward. Need to continue
                 * iterating until the next no is found. 
                 */
                for($j=$i+1;$j<$c;$j++){
                    
                    if($dates[$j - 1] == $dates[$j]){
                        
                        if($j == ($c - 1)){
                            break 2;
                        }else{
                            continue;
                        }                        
                        
                    }
                    
                    if($extents[$j] == 0){
                        $next_no_index = $j;
                        $group_data_slices = array();
                        foreach($group_data as $key => $value){
                            $group_data_slices[$key] = array_slice($value, $j);
                        }


                        
                        /**
                         * Once the next no value is found, recursively call the createSeries
                         * function on the remainder of the sets data, as to determine
                         * if there are any more series in the set. 
                         */
                        $child_same_year = $this->createSeries($group_data_slices, $other_fields, $results_array, $first_yes_year);
                        /**
                         * Furthermore, the value returned is whether or not this series
                         * and any next series years match, meaning they occur in the same
                         * year, and the flag should be set as such. 
                         */
                        if($child_same_year == 1){
                            $other_fields['Multiple_FirstY'] = 1;
                        }

                        break 2;
                    /**
                     *Whilst iterating, we keep track of the number of '1' and '-1'
                     * extent values. 
                     */    
                    }else if($extents[$j] == 1){
                        $last_yes_index = $j;
                        
                        $num_yes++;
                    }

                    if($j == ($c - 1)){
                        break 2;
                    }
                }

            }

        }

        /**
         * Once the dates are determined that comprise the actual relevant part of the
         * set's data, calculations can be done.  
         */
        $first_yes_date = ($first_yes_index == -1) ? null : strtotime($dates[$first_yes_index] . " GMT");
        $previous_no_date = ($previous_no_index == -1) ? null : strtotime($dates[$previous_no_index] . " GMT");
        $last_yes_date = ($last_yes_index == -1) ? null : strtotime($dates[$last_yes_index]);
        $next_no_date = ($next_no_index == -1) ? null : strtotime($dates[$next_no_index]);
        
        
        $arr =
                array_slice(
                        $observer_ids, $first_yes_index,
                        (( $last_yes_index - $first_yes_index) + 1)
                );
        $unique_observers = array_unique($arr);
        $other_fields['Multiple_Observers'] = (count($unique_observers) > 1 ? 1 : 0);
        
        if(in_array("ObservedBy_Person_ID", $this->fields)){
            $other_fields['ObservedBy_Person_ID'] = "'" . implode(",", $unique_observers) . "'";
        }
        
        $arr =
                array_slice(
                        $conflicts, $first_yes_index,
                        (( $last_yes_index - $first_yes_index) + 1)
                );        
        if(in_array("Observed_Status_Conflict_Flag", $this->fields)){
            if(array_search("MultiObserver-StatusConflict", $arr) !== FALSE){
                $other_fields['Observed_Status_Conflict_Flag'] = "MultiObserver-StatusConflict";
            }else if(array_search("OneObserver-StatusConflict", $arr) !== FALSE){
                $other_fields['Observed_Status_Conflict_Flag'] = "OneObserver-StatusConflict";
            }else{
                $other_fields['Observed_Status_Conflict_Flag'] = "-9999";
            }
        }else{
            unset($other_fields['Observed_Status_Conflict_Flag']);
        }
        
        
        
        $arr =
                array_slice(
                        $dataset_ids, $first_yes_index,
                        (( $last_yes_index - $first_yes_index) + 1)
                );
        $unique_datasets = array_unique($arr);
        
        if(in_array("Dataset_ID", $this->fields)){
            $other_fields['Dataset_ID'] = "'" . implode(",", $unique_datasets) . "'";
        }        


        if($first_yes_date || $previous_no_date || $last_yes_date || $next_no_date){


            /**
             * Populate all of the fields that indicate the first and last yes
             * dates. 
             */
            $this->loadDateFields("Phenophase_Description", "First", $first_yes_date, $other_fields);
            $this->loadDateFields("First_Yes_Julian_Date", "Last", $last_yes_date, $other_fields);

            /**
             * If a next no date was found, then calculate and populate
             * the gap size field. Same for previous no date. 
             */
            
            if($next_no_date){
                $nnd = date_create($dates[$next_no_index]);
                $lyd = date_create($dates[$last_yes_index]);            
                $diff = date_diff($nnd, $lyd);
                //$other_fields["NumDays_Until_Next_No"] = $diff->days;
                $next_no_date = $diff->days;                
            }else{
                $next_no_date = -9999;
            }
            $this->array_insert($other_fields, "Last_Yes_Julian_Date", ["NumDays_Until_Next_No" =>  $next_no_date]);

            
            if($previous_no_date){
                $pnd = date_create($dates[$previous_no_index]);
                $fyd = date_create($dates[$first_yes_index]);            
                $diff = date_diff($pnd, $fyd);
                
                $previous_no_date = $diff->days;
            }else{
                $previous_no_date = -9999;
            }        
            $this->array_insert($other_fields, "First_Yes_Julian_Date", ["NumDays_Since_Prior_No" =>  $previous_no_date]);

            $other_fields['NumYs_in_Series'] = $num_yes;
            
            $num_days = ($other_fields['Last_Yes_Julian_Date'] - $other_fields['First_Yes_Julian_Date']);
            $other_fields['NumDays_in_Series'] =  ($num_days == 0) ? 1 : $num_days + 1;

            if($this->climate_data_selected['gdd'] || $this->climate_data_selected['se_gdd'] || $this->climate_data_selected['mean_gdd']) {
                $this->populateClimateDataField($other_fields, "gdds", $gdds, $first_yes_index, count($extents));
            }
            
            if($this->climate_data_selected['gddf'] || $this->climate_data_selected['se_gddf'] || $this->climate_data_selected['mean_gddf']) {
                $this->populateClimateDataField($other_fields, "gddfs", $gddfs, $first_yes_index, count($extents));
            }                        
            if($this->climate_data_selected['tmax_winter']) {
                $this->populateClimateDataField($other_fields, "tmax_winters", $tmax_winters, $first_yes_index, count($extents));
            }
            if($this->climate_data_selected['tmax_spring']) {
                $this->populateClimateDataField($other_fields, "tmax_springs", $tmax_springs, $first_yes_index, count($extents));
            }            
            if($this->climate_data_selected['tmax_summer']) {
                $this->populateClimateDataField($other_fields, "tmax_summers", $tmax_summers, $first_yes_index, count($extents));
            }
            if($this->climate_data_selected['tmax_fall']) {
                $this->populateClimateDataField($other_fields, "tmax_falls", $tmax_falls, $first_yes_index, count($extents));
            }
            if($this->climate_data_selected['tmax']) {
                $this->populateClimateDataField($other_fields, "tmaxs", $tmaxs, $first_yes_index, count($extents));
            }
            if($this->climate_data_selected['tmaxf']) {
                $this->populateClimateDataField($other_fields, "tmaxfs", $tmaxfs, $first_yes_index, count($extents));
            }            
            if($this->climate_data_selected['tmin_winter']) {
                $this->populateClimateDataField($other_fields, "tmin_winters", $tmin_winters, $first_yes_index, count($extents));
            }
            if($this->climate_data_selected['tmin_spring']) {
                $this->populateClimateDataField($other_fields, "tmin_springs", $tmin_springs, $first_yes_index, count($extents));
            }            
            if($this->climate_data_selected['tmin_summer']) {
                $this->populateClimateDataField($other_fields, "tmin_summers", $tmin_summers, $first_yes_index, count($extents));
            }
            if($this->climate_data_selected['tmin_fall']) {
                $this->populateClimateDataField($other_fields, "tmin_falls", $tmin_falls, $first_yes_index, count($extents));
            }
            if($this->climate_data_selected['tmin']) {
                $this->populateClimateDataField($other_fields, "tmins", $tmins, $first_yes_index, count($extents));
            }
            if($this->climate_data_selected['tminf']) {
                $this->populateClimateDataField($other_fields, "tminfs", $tminfs, $first_yes_index, count($extents));
            }            
            if($this->climate_data_selected['prcp_winter']) {
                $this->populateClimateDataField($other_fields, "prcp_winters", $prcp_winters, $first_yes_index, count($extents));
            }
            if($this->climate_data_selected['prcp_spring']) {
                $this->populateClimateDataField($other_fields, "prcp_springs", $prcp_springs, $first_yes_index, count($extents));
            }            
            if($this->climate_data_selected['prcp_summer']) {
                $this->populateClimateDataField($other_fields, "prcp_summers", $prcp_summers, $first_yes_index, count($extents));
            }
            if($this->climate_data_selected['prcp_fall']) {
                $this->populateClimateDataField($other_fields, "prcp_falls", $prcp_falls, $first_yes_index, count($extents));
            }
            if($this->climate_data_selected['prcp']) {
                $this->populateClimateDataField($other_fields, "prcps", $prcps, $first_yes_index, count($extents));
            }
            
            if($this->climate_data_selected['acc_prcp'] || $this->climate_data_selected['mean_accum_prcp'] || $this->climate_data_selected['se_accum_prcp']) {
                $this->populateClimateDataField($other_fields, "acc_prcps", $acc_prcps, $first_yes_index, count($extents));
            }
            if($this->climate_data_selected['daylength'] || $this->climate_data_selected['mean_daylength'] || $this->climate_data_selected['se_daylength']) {
                $this->populateClimateDataField($other_fields, "daylengths", $daylengths, $first_yes_index, count($extents));
            }
                      
            foreach(SummarizedDataSearch::$group_data_aliases as $alias_name){
                unset($other_fields[$alias_name]);
            }
            
            if(!in_array("NumYs_in_Series", $this->fields)){
                unset($other_fields['NumYs_in_Series']);
            }
            
            if(!in_array("NumDays_in_Series", $this->fields)){
                unset($other_fields['NumDays_in_Series']);
            }            
            
            if(!in_array("Multiple_FirstY", $this->fields)){
                unset($other_fields['Multiple_FirstY']);
            }
            
            if(!in_array("Multiple_Observers", $this->fields)){
                unset($other_fields['Multiple_Observers']);
            }
            
            if(!in_array("Dataset_ID", $this->fields)){
                unset($other_fields['Dataset_ID']);
            }
            
            if(!$this->num_days_quality_filter_individual || ($previous_no_date && $previous_no_date != "-9999" && $previous_no_date <= $this->num_days_quality_filter_individual)){            
                $results_array[] = $other_fields;
            }
        }

        return $parent_same_year;

    }
    
    private function populateClimateDataField(&$other_fields, $array_name, &$data_array, $pos, $count){
        
        $singular_array_name = substr($array_name, 0, -1);
        if(count($data_array) == $count){            
            $other_fields[$singular_array_name] = (empty($data_array[$pos])) ? "-9999" : $data_array[$pos];
        }else{
            $other_fields[$singular_array_name] = "-9999";
        }
    }

    
    protected function array_insert(&$array, $position, $insert)
    {
        if (is_int($position)) {
            array_splice($array, $position, 0, $insert);
        } else {
            $pos   = array_search($position, array_keys($array)) + 1;
            $array = array_merge(
                array_slice($array, 0, $pos),
                $insert,
                array_slice($array, $pos)
            );
        }
    }    
    
    
    private function julianDate($timestamp){
            return number_format($timestamp / 86400 + 2440587.5, 0, ".", "");
    }    
    
    
    /**
     * This function takes a refernces to the other fields array, as well as a
     * timestamp for the date, and a prefix indicating which fields that function 
     * is to fill in. It then just creates a date and then break it into different
     * parts and fills in the array appropriately.
     */
    private function loadDateFields($start_position, $prefix, $timestamp, &$other_fields){
        $date = date("Y-m-d H:i z", $timestamp);
        $date_parts = explode(" ", $date);
        $calendar = $date_parts[0];
        $time = $date_parts[1];
        $doy = $date_parts[2] + 1;

        $calendar_parts = explode("-", $calendar);
        //$time_parts = explode(":", $time);
        for($i=1;$i< count($calendar_parts);$i++){
            $calendar_parts[$i] = number_format($calendar_parts[$i]);
        }
        
        $this->array_insert($other_fields, $start_position, [$prefix . '_Yes_Year' => $calendar_parts[0]]);        
        $this->array_insert($other_fields, $prefix . '_Yes_Year', [$prefix . '_Yes_Month' => $calendar_parts[1]]);        
        $this->array_insert($other_fields, $prefix . '_Yes_Month', [$prefix . '_Yes_Day' => $calendar_parts[2]]);        
        $this->array_insert($other_fields, $prefix . '_Yes_Day', [$prefix . '_Yes_Doy' => $doy]);        
        $this->array_insert($other_fields, $prefix . '_Yes_Doy', [$prefix . '_Yes_Julian_Date' => $this->julianDate($timestamp)]);
    }    
    

}