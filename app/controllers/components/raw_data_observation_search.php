<?php

App::import('Component', 'GenericObservationSearch');

class RawDataObservationSearch extends GenericObservationSearch{
    
    public function __construct($emitter){

        $this->fields = array(
            "Observation_ID",
            "Dataset_ID",
            "ObservedBy_Person_ID",
            "Submission_ID",
            "SubmittedBy_Person_ID",
            "Submission_Datetime",
            "UpdatedBy_Person_ID",
            "IF(Update_Datetime IS NOT NULL, Update_Datetime, '-9999') AS Update_Datetime",
            "Partner_Group",
            "Site_ID",
            "Site_Name",
            "Latitude",
            "Longitude",
            "Elevation_in_Meters",
            "State",
            "Species_ID",
            "Genus",
            "Genus_ID",
            "Genus_Common_Name",
            "Species",
            "Common_Name",
            "Kingdom",
            "Species_Functional_Type",
            "Species_Category",
            "USDA_PLANTS_Symbol",
            "ITIS_Number",
            "Growth_Habit",
            "Lifecycle_Duration",
            "Individual_ID",
            "Plant_Nickname",
            "Patch",
            "Protocol_ID",
            "Phenophase_ID",
            "Phenophase_Category",
            "Phenophase_Description",
            "Phenophase_Name",
            "Phenophase_Definition_ID",
            "Secondary_Species_Specific_Definition_ID",                        
            "Pheno_Class_ID",
            "Pheno_Class_Name",                  
            "Observation_Date",
            "Observation_Time",
            "Day_of_Year",
            "Phenophase_Status",
            "Intensity_Category_ID",
            "Intensity_Value",
            "Abundance_Value",
            "Observation_Group_ID",
            "Observation_Comments",
            "Observed_Status_Conflict_Flag",
            "Status_Conflict_Related_Records",
            "Class_ID",
            "Class_Name",
            "Class_Common_Name",
            "Order_ID",
            "Order_Name",
            "Order_Common_Name",
            "Family_ID",
            "Family_Name",
            "Family_Common_Name",
            "gdd",
            "gddf",            
            "tmax_winter",
            "tmax_spring",
            "tmax_summer",
            "tmax_fall",
            "tmax",
            "tmaxf",            
            "tmin_winter",
            "tmin_spring",
            "tmin_summer",
            "tmin_fall",
            "tmin",
            "tminf",            
            "prcp_winter",
            "prcp_spring",
            "prcp_summer",
            "prcp_fall",
            "prcp",
            "acc_prcp",
            "daylength",
            
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
            'dataset_id' => 'Dataset_ID',
            'observedby_person_id' => 'ObservedBy_Person_ID',
            'submission_id' => 'Submission_ID',
            'submittedby_person_id' => 'SubmittedBy_Person_ID',
            'submission_datetime' => 'Submission_Datetime',
            'updatedby_person_id' => 'UpdatedBy_Person_ID',
            'update_datetime' => 'Update_Datetime',
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
            'protocol_id' => 'Protocol_ID',
            'phenophase_category' => 'Phenophase_Category',
            'phenophase_name' => 'Phenophase_Name',
            'phenophase_definition_id' => 'Phenophase_Definition_ID',
            'secondary_species_specific_definition_id' => 'Secondary_Species_Specific_Definition_ID',
            'pheno_class_id' => 'Pheno_Class_ID',
            'pheno_class_name' => 'Pheno_Class_Name',            
            'observation_time' => 'Observation_Time',
            'observation_group_id' => 'Observation_Group_ID',
            'observation_comments' => 'Observation_Comments',
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
            // 'genus' => 'Genus',
            'genus_id' => 'Genus_ID',
            'genus_common_name' => 'Genus_Common_Name',
            'tmax_winter' => 'tmax_winter',
            'tmax_spring' => 'tmax_spring',
            'tmax_summer' => 'tmax_summer',
            'tmax_fall' => 'tmax_fall',
            'tmax' => 'tmax',
            'tmin' => 'tmin',
            'tmaxf' => 'tmaxf',
            'tminf' => 'tminf',            
            'tmin_winter' => 'tmin_winter',
            'tmin_spring' => 'tmin_spring',
            'tmin_summer' => 'tmin_summer',
            'tmin_fall' => 'tmin_fall',
            'prcp_winter' => 'prcp_winter',
            'prcp_spring' => 'prcp_spring',
            'prcp_summer' => 'prcp_summer',
            'prcp_fall' => 'prcp_fall',
            'prcp' => 'prcp',
            'gdd' => 'gdd',
            'gddf' => 'gddf',
            'daylength' => 'daylength',
            'acc_prcp' => 'acc_prcp',
            
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
        
        $this->ancillary_urls = array();
        
        $this->group_by = array('Observation_ID');
                
        parent::__construct($emitter);
    }
    
    public function processInputParameters($params){
        
        if($this->CheckProperty->checkProperty($params,'start_date') && $this->CheckProperty->checkProperty($params, 'end_date')){

                $this->search_params['DataSearchParams']['Start_Date'] = $params->start_date;
                $this->search_params['DataSearchParams']['End_Date'] = $params->end_date;

                $this->conditions["CachedObservation.Observation_Date BETWEEN ? AND ?"] = array($params->start_date, $params->end_date);
        }
        
        if($this->CheckProperty->checkProperty($params,'additional_field') && in_array("abundance_link", $params->additional_field)){
            $this->ancillary_urls[] = 'abundance_link';
        }       

        if($this->CheckProperty->checkProperty($params,'additional_field') && in_array("dataset_link", $params->additional_field)){
            $this->ancillary_urls[] = 'dataset_link';
        }
        
        if($this->CheckProperty->checkProperty($params,'additional_field') && in_array("individual_link", $params->additional_field)){
            $this->ancillary_urls[] = 'individual_link';
        }
        
        if($this->CheckProperty->checkProperty($params,'additional_field') && in_array("phenophase_definition_link", $params->additional_field)){
            $this->ancillary_urls[] = 'phenophase_definition_link';
        }         

        $this->cleanTaxonomyParameters($params);
        $this->cleanPhenoClassParams($params);
        
        if($this->CheckProperty->checkProperty($params,'pheno_class_id')){
            $params->pheno_class_id = $this->ArrayWrap->arrayWrap($params->pheno_class_id);

            $this->search_params['DataSearchParams']['Pheno_Class_IDs'] = implode(",",$params->pheno_class_id);
            $this->conditions["CachedSummarizedData.Pheno_Class_ID"] = $params->pheno_class_id;
        }         
        
        parent::processInputParameters($params);

        
        
    }
    
    
    public function preprocessResults($data, &$result_set){
        
        if(in_array("Status_Conflict_Related_Records", $this->fields)){
            $data['Status_Conflict_Related_Records'] = "'" . $data['Status_Conflict_Related_Records']. "'";
        }
        //strip non utf-8
        if(in_array("Plant_Nickname", $this->fields)) {
            $data['Plant_Nickname'] =  preg_replace('/[^(\x20-\x7F)]*/','',  $data['Plant_Nickname']);
        }
        if(in_array("Site_Name", $this->fields)) {
            $data['Site_Name'] =  preg_replace('/[^(\x20-\x7F)]*/','',  $data['Site_Name']);
        }
        if(in_array("Observation_Comments", $this->fields)) {
            $data['Observation_Comments'] =  preg_replace('/[^(\x20-\x7F)]*/','',  $data['Observation_Comments']);
        }

        if(in_array('dataset_link', $this->ancillary_urls)){
            $data['Datasets'] = 'https://www.usanpn.org/npn_portal/observations/getDatasetDetails.json?pretty=1';
        }

        if(in_array('individual_link', $this->ancillary_urls)){
            $data['Individual'] = 'https://www.usanpn.org/npn_portal/individuals/getPlantDetails.json?individual_id=' . $data['Individual_ID'];
        }
        
        if(in_array('phenophase_definition_link', $this->ancillary_urls)){
            $data['Phenophase'] = 'https://www.usanpn.org/npn_portal/phenophases/getPhenophasesForSpecies.json?species_id[0]=' . $data['Species_ID'] . 
                    '&date=' . $data['Observation_Date'] . 
                    '&phenophase_id=' . $data['Phenophase_ID'];
        }

        if(in_array("abundance_link", $this->ancillary_urls)){
            $data['Intensity_Categories'] = 'https://www.usanpn.org/npn_portal/phenophases/getAbundanceCategories.json?pretty=1';
        }        
		
        return array($data);
    }
    
    public function getAllowedValues(){
        return $this->allowed_values;
    }
    
    
    

    




}