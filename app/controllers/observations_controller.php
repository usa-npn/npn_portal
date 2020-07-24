<?php

class Obs{}
class PhenPhase{}
class Day{}
class Extent{}
class Site{}
class Spec{}
class Response{}
class Comment{}
class FileURL{}
class DataSourceObj{}
class Year{}
class DatasetDetails{}

/**
 *
 * Controller for retreiving data about observations.
 */
class ObservationsController extends AppController{
    
    
    public $uses = array('VwObservationsBySpeciesStation', 'Observation', 'VwObservationPublic', 'CachedObservation', 'CachedNetworkObservation', "Dataset", "PhenophaseDefinition", "VwDatasetDetails");
    
    public $components = array(
    	'Soap' => array(
        	'wsdl' => 'NPN', //the file name in the view folder
            	'action' => 'service', //soap service method / handler
        ),
        'CleanText',
        'RequestHandler'
        
    );

    public function soap_wsdl(){
    	//will be handled by SoapComponent
    }
	
	
	
    public function soap_service(){
    //no code here
    }
    
    

    
    public function getAllObservationsForSpecies($search_params=null){
        
        if($this->checkProperty($search_params, "start_date") && $this->checkProperty($search_params, "end_date")){
            $start_date = $search_params->start_date;
            $end_date = $search_params->end_date;            
        }else{
            $this->set('data', new stdClass());
            return null;            
        }

        
        $network_id = null;        
        if($this->checkProperty($search_params, "network_id")){
            $network_id = $this->arrayWrap($search_params->network_id);               
        }

        $species_id = null;
        if($this->checkProperty($search_params, "species_id")){
            $species_id = $this->arrayWrap($search_params->species_id);
        }

        $stations = array();
        $phenophases = array();

        $conditions = array(
                        'VwObservationsBySpeciesStation.Observation_Date <= ' => $end_date,
                        'VwObservationsBySpeciesStation.Observation_Date >= ' => $start_date
        );
        
        if($species_id){
            $conditions['VwObservationsBySpeciesStation.Species_ID'] = $species_id;          
        }
        
        if($network_id){
            
            foreach($network_id as $nid){
                $conditions['OR'][] = array('VwObservationsBySpeciesStation.Network_IDs LIKE' => "%," . $nid . ",%");
                $conditions['OR'][] = array('VwObservationsBySpeciesStation.Network_IDs' => $nid);
                $conditions['OR'][] = array('VwObservationsBySpeciesStation.Network_IDs LIKE' => $nid . ",%");
                $conditions['OR'][] = array('VwObservationsBySpeciesStation.Network_IDs LIKE' => "%," . $nid);
            }
        }
        
        $dbo = $this->VwObservationsBySpeciesStation->getDataSource();
        $query = $dbo->buildStatement(array(
            "fields" => array(
                'VwObservationsBySpeciesStation.Species_ID', 
                'VwObservationsBySpeciesStation.Phenophase_ID', 
                'VwObservationsBySpeciesStation.Station_ID', 
                'VwObservationsBySpeciesStation.Phenophase_Name', 
                'VwObservationsBySpeciesStation.Station_Name', 
                'VwObservationsBySpeciesStation.Latitude',
                'VwObservationsBySpeciesStation.Longitude', 
                'SUM(Observation_Extent = 1) y', 
                'SUM(Observation_Extent = 0) n', 
                'SUM(Observation_Extent = -1) q',
                'VwObservationsBySpeciesStation.Network_IDs'
           ),
            "table" => $dbo->fullTableName($this->VwObservationsBySpeciesStation),
            "alias" => 'VwObservationsBySpeciesStation',
            "conditions" => $conditions,
            "group" => array('Station_ID', 'Species_ID', 'Phenophase_ID HAVING `y` > 0 OR `n` > 0 OR `q` > 0'),
            "limit" => null,
            "order" => array('Station_ID', 'Species_ID')
            ),
        $this->VwObservationsBySpeciesStation
        );

        $results = mysql_unbuffered_query($query);        
        
        
        $result = mysql_fetch_array($results, MYSQL_ASSOC);
        while($result){

            $station = array();
            $station['station_id'] = intval($result['Station_ID']);
            $station['station_name'] = $result['Station_Name'];
            $station['latitude'] = floatval($result['Latitude']);
            $station['longitude'] = floatval($result['Longitude']);
            if($result['Network_IDs']){
                $nets = explode(",", $result['Network_IDs']);
                $nets = array_map('intval', $nets);
                $station['networks'] = $nets;
 

            }
            
            $station['species'] = new Spec();
            
            $species = null;

            do{

                $species_id = $result['Species_ID'];                 
                $station['species']->$species_id = new PhenPhase();
           

                do{                        
                    if($species_id != $result['Species_ID'] || $station['station_id'] != $result['Station_ID']){
                        break;
                    }



                    $station['species']->$species_id->$result['Phenophase_ID'] = new PhenPhase();

                    if($result['y'] > 0){
                        $station['species']->$species_id->$result['Phenophase_ID']->y = intval($result['y']);                        
                    }

                    if($result['n'] > 0){
                        $station['species']->$species_id->$result['Phenophase_ID']->n = intval($result['n']);                        
                    }

                    if($result['q'] > 0){
                        $station['species']->$species_id->$result['Phenophase_ID']->q = intval($result['q']);                        
                    }


                    if(!isset($phenophases[$result['Phenophase_ID']])){

                        $pheno_ent = array();
                        $pheno_ent['phenophase_id'] = intval($result['Phenophase_ID']);
                        $pheno_ent['phenophase_name'] = $result['Phenophase_Name'];
                        $phenophases[$result['Phenophase_ID']] = $pheno_ent;
                    }                        


                }while($result = mysql_fetch_array($results, MYSQL_ASSOC));


                if($station['station_id'] != $result['Station_ID']){
                    break;
                }

            }while(true);

            $stations[] = $station;
        }


        $response = new stdClass();
        
        $response->station_list = $stations;
        $phenophases = array_values($phenophases);
        $response->phenophase_list = $phenophases;

        $this->set('data', $response);
        return $response;

    }
    

	
	
    public function getObservationsCount($search_params=null){
        App::import('Model','CachedSummarizedData');
        $CachedSummarizedData = new CachedSummarizedData();

        $search_params = json_decode(file_get_contents("php://input"));

        $joins[] =  array(
            'table' => 'Cached_Observation',
            'alias' => 'CachedObservation',
            'type' => 'LEFT',
            'conditions' => array(
                'CachedObservation.Series_ID = CachedSummarizedData.Series_ID'
            )
        );
        
        $conditions = array();


        if($this->checkProperty($search_params, 'start_date') && $this->checkProperty($search_params, 'end_date')){
                $conditions['CachedObservation.Observation_Date BETWEEN ? AND ?'] = Array($search_params->start_date, $search_params->end_date);
        }
        
        if($this->checkProperty($search_params, 'stations'))
            $conditions['CachedSummarizedData.Site_ID'] = $search_params->stations;
        if($this->checkProperty($search_params, 'state'))
            $conditions['CachedSummarizedData.State'] = $search_params->state;
        if($this->checkProperty($search_params, 'dataset_ids'))
            $conditions['CachedObservation.Dataset_ID'] = $search_params->dataset_ids;
        if($this->checkProperty($search_params, 'network'))
            $conditions['CachedSummarizedData.Partner_Group'] = $search_params->network;
        if($this->checkProperty($search_params, 'species_id'))
            $conditions['CachedSummarizedData.Species_ID'] = $search_params->species_id;
		if ( $this->checkProperty($search_params, 'bottom_left_x1') && $this->checkProperty($search_params, 'bottom_left_y1') && 
			 $this->checkProperty($search_params, 'upper_right_x2') && $this->checkProperty($search_params, 'upper_right_y2') && 
             is_numeric($search_params->bottom_left_x1) && is_numeric($search_params->bottom_left_y1) &&
             is_numeric($search_params->upper_right_x2) && is_numeric($search_params->upper_right_y2)){
                $conditions["CachedSummarizedData.Latitude BETWEEN ? AND ?"] = array($search_params->bottom_left_x1, $search_params->upper_right_x2);
                $conditions["CachedSummarizedData.Longitude BETWEEN ? AND ?"] = array($search_params->bottom_left_y1, $search_params->upper_right_y2);
        }
        if($this->checkProperty($search_params, 'phenophase_category')){
            $conditions['CachedSummarizedData.Phenophase_Category'] = $search_params->phenophase_category;
        }
        
        $fields = array();
        
        if($this->checkProperty($search_params, 'is_magnitude') && $search_params->is_magnitude == 1){
            $fields[] = 'count(DISTINCT Species_ID, Phenophase_ID) `count`';
        }else{
            $fields[] = 'SQL_CACHE count(CachedObservation.Observation_ID) `count`';
        }

        $obsCount = $CachedSummarizedData->find('first', Array(
            'fields' => $fields,
            'joins' => $joins,
            'conditions' => $conditions
        ));


        $obsCount = $obsCount[0]['count'];

        $result = array();
        $result['obsCount'] = $obsCount;
        
        $response = array();
        $this->set('response', $result);
        return $response;
    }	
    
    public function getObservationDates($search_params=null){

        
        $species_ids = null;
        $station_ids = null;
        $phenophase_ids = null;
        $person_id = null;
        
        if($this->checkProperty($search_params, "year")){
            $search_params->year = $this->arrayWrap($search_params->year);
        }else{
            $response = array();
            $response['error_message'] = "`year` required input parameter";
            
            $this->set('response', $response);
            return $response;
        }

        
        $has_species_filter = false;
        if($this->checkProperty($search_params, "species_id")){
            
            $search_params->species_id = $this->arrayWrap($search_params->species_id);            
            $species_ids = implode(",", $search_params->species_id);
            
            $has_species_filter = true;
            
        }
        
        if($this->CheckProperty->checkProperty($search_params,'family_id')){
            $search_params->family_id = $this->arrayWrap($search_params->family_id);            
            $family_ids = implode(",", $search_params->family_id);
            
            $species_ids = null;
            
            $has_species_filter = true;
        }
        
        if($this->CheckProperty->checkProperty($search_params,'order_id')){
            $search_params->order_id = $this->arrayWrap($search_params->order_id);            
            $order_ids = implode(",", $search_params->order_id);
            
            $family_ids = null;
            $species_ids = null;
            
            $has_species_filter = true;
        }        
        
        if($this->CheckProperty->checkProperty($search_params,'class_id')){
            $search_params->class_id = $this->arrayWrap($search_params->class_id);            
            $class_ids = implode(",", $search_params->class_id);
            
            $order_ids = null;
            $family_ids = null;
            $species_ids = null;
            
            $has_species_filter = true;
        }

        if($this->CheckProperty->checkProperty($search_params,'genus_id')){
            $search_params->genus_id = $this->arrayWrap($search_params->genus_id);            
            $genus_ids = implode(",", $search_params->genus_id);
            
            $order_ids = null;
            $family_ids = null;
            $species_ids = null;
            
            $has_species_filter = true;
        }
        
        /**
         * This is applicable when the user hasn't supplied ANY kind of taxonomic
         * filter. Not a species id nor a class/family/order id
         */
        if(!$has_species_filter){
            $response = array();
            $response['error_message'] = "`species_id` required input parameter";

            $this->set('response', $response);
            return $response;                      
        }
        
        
        $taxonomy_aggregate = ($this->CheckProperty->checkProperty($search_params,'taxonomy_aggregate')) ? true : false;        
        $pheno_class_aggregate = ($this->CheckProperty->checkProperty($search_params,'pheno_class_aggregate')) ? true : false;
        
        
        if($this->checkProperty($search_params, "group_id")){
            $search_params->network_id = $search_params->group_id;
        }
        
        $network_ids = null;        
        if($this->checkProperty($search_params, "network_id")){
            $network_ids = $this->arrayWrap($search_params->network_id);
        }        
        
        if($this->checkProperty($search_params, "phenophase_id")){

            $search_params->phenophase_id = $this->arrayWrap($search_params->phenophase_id);            
            
            $phenophase_ids = implode(",", $search_params->phenophase_id);
            
        }
        
        if($this->checkProperty($search_params, "pheno_class_id")){
            $search_params->pheno_class_id = $this->arrayWrap($search_params->pheno_class_id);                  
            $pheno_class_ids = implode(",", $search_params->pheno_class_id);
        }
        
        if($this->checkProperty($search_params, "person_id")){

            $search_params->person_id = $this->arrayWrap($search_params->person_id);            
            
            $person_id = implode(",", $search_params->person_id);
            
        }        
        
        if($this->checkProperty($search_params, "station_id")){
            
            $search_params->station_id = $this->arrayWrap($search_params->station_id);                        
            $station_ids = implode(",", $search_params->station_id);
        }
        
        
        
        $query = "
            SELECT YEAR(co.Observation_Date) `year`,
            COUNT(co.Observation_ID) `count`, pp.Phenophase_ID, pp.Phenophase_Name, ppp.Seq_Num, pp.Color, csd.Pheno_Class_ID, csd.Pheno_Class_Name, ";
        

        
        
        if($species_ids || !$taxonomy_aggregate){
            $query .= "csd.Species_ID, csd.Common_Name,";
        }else if($family_ids && $taxonomy_aggregate){
            $query .= "csd.Family_ID, csd.Family_Common_Name, csd.Family_Name,";
        }else if($order_ids && $taxonomy_aggregate){
            $query .= "csd.Order_ID, csd.Order_Common_Name, csd.Order_Name,";
        }else if ($class_ids && $taxonomy_aggregate){
            $query .= "csd.Class_ID, csd.Class_Common_Name, csd.Class_Name,";
        }else if ($genus_ids && $taxonomy_aggregate){
            $query .= "csd.Genus_ID, csd.Genus_Common_Name, csd.Genus,";
        }

            $query .= "
            GROUP_CONCAT(DISTINCT IF(co.Phenophase_Status=1,DATE_FORMAT(co.Observation_Date,'%j'),null) ORDER BY co.Observation_Date) `Dates_Positive`,
            GROUP_CONCAT(DISTINCT IF(co.Phenophase_Status=0,DATE_FORMAT(co.Observation_Date,'%j'),null) ORDER BY co.Observation_Date) `Dates_Negative`
            FROM usanpn2.Cached_Observation co
            
            LEFT JOIN usanpn2.Cached_Summarized_Data csd
            ON csd.Series_ID = co.Series_ID           
            
            LEFT JOIN usanpn2.Cached_Phenophase pp
            ON pp.Phenophase_ID = csd.Phenophase_ID

            LEFT JOIN usanpn2.Protocol_Phenophase ppp
            ON ppp.Phenophase_ID = csd.Phenophase_ID AND ppp.Protocol_ID = co.Protocol_ID
            WHERE";
        
        if($species_ids){        
            $query .= " csd.Species_ID IN (". $species_ids . ")";
        }else if($family_ids){
            $query .= " csd.Family_ID IN (". $family_ids . ")";
        }else if($order_ids){
            $query .= " csd.Order_ID IN (". $order_ids . ")";
        }else if($class_ids){
            $query .= " csd.Class_ID IN (" . $class_ids . ")";
        }else if($genus_ids){
            $query .= " csd.Genus_ID IN (" . $genus_ids . ")";
        }
        
        if($station_ids){
            $query .= " AND csd.Site_ID IN (" . $station_ids . ") ";
        }
        
        if($phenophase_ids && !$pheno_class_ids){
            $query .= " AND csd.Phenophase_ID IN (" . $phenophase_ids . ")";
        }
        
        if($pheno_class_ids){
            $query .= " AND csd.Pheno_Class_ID IN (" . $pheno_class_ids . ")";
        }
        
        if($person_id){
            $query .= " AND co.ObservedBy_Person_ID = " . $person_id;
        }
        
        if($network_ids){
            $query .= " AND (";
            $z=0;
            foreach($network_ids as $nid){
                if($z++ != 0){
                    $query .= " OR ";
                }
                
                $query .= "(";
                    $query .= "Network_IDs LIKE '%," . $nid . ",%' OR ";
                    $query .= "Network_IDs LIKE '" . $nid . ",%' OR ";
                    $query .= "Network_IDs LIKE '%," . $nid . "' OR ";
                    $query .= "Network_IDs = '". $nid . "'";
                $query .= ")";
            }
            
            $query .= ") ";
        }
        
        $query .= " AND (co.Phenophase_Status = 1 OR co.Phenophase_Status = 0) ";
        $query .= "GROUP BY";
        
            
        if($family_ids  && $taxonomy_aggregate){
            $query .= " csd.Family_ID";
        }else if($order_ids  && $taxonomy_aggregate){
            $query .= " csd.Order_ID";
        }else if($class_ids && $taxonomy_aggregate){
            $query .= " csd.Class_ID";
        }else if($genus_ids && $taxonomy_aggregate){
            $query .= " csd.Genus_ID";
        }else{
            $query .= " csd.Species_ID";
        }
        
        if(!$pheno_class_aggregate){
            $query .= ", csd.Phenophase_ID";
        }else{
            $query .= ", csd.Pheno_Class_ID";
        }
        
        $query .= ", YEAR(co.Observation_Date)"
                . " HAVING (";
        
        
        $i=0;
        foreach($search_params->year as $year){
            $query .= ((($i++ > 0) ? " OR " : "") . "`year` = " . $year);
        }
        
        $query .= " ) 
            ORDER BY ";
        
        if($species_ids){
            $query .= "csd.Species_ID";
        }else if($family_ids && $taxonomy_aggregate){
            $query .= "csd.Family_ID";
        }else if($order_ids && $taxonomy_aggregate){
            $query .= "csd.Order_ID";
        }else if($class_ids && $taxonomy_aggregate){
            $query .= "csd.Class_ID";
        }else if($genus_ids && $taxonomy_aggregate){
            $query .= "csd.Genus_ID";
        }else{
            $query .= "csd.Species_ID";
        }

        $query .= ", csd.Phenophase_ID, `year`";
        

        $found_species = array();
        $res = $this->Observation->query($query);
        
        $count = count($res);
        $response = array();
  
        if($count == 0){
            $response = array();
            $response['error_message'] = "No results found";
            
            $this->set('response', $response);
            return $response;                 
        }
        

        $i = $j = $k = $l = 0;
        
        for($i=0;$i < $count; $i++){

            if($species_ids || !$taxonomy_aggregate){
                $species = array();
                $species['species_id'] = intval($res[$i]['csd']['Species_ID']);
                $found_species[] = $res[$i]['csd']['Species_ID'];

                $species['common_name'] = $res[$i]['csd']['Common_Name'];
                $species['phenophases'] = array();
                $phenophase = null;
            
            }else if($genus_ids && $taxonomy_aggregate){
                $species = array();
                $species['genus_id'] = intval($res[$i]['csd']['Genus_ID']);
                $found_species[] = $res[$i]['csd']['Genus_ID'];
                
                $species['genus_common_name'] = $res[$i]['csd']['Genus_Common_Name'];
                $species['genus'] = $res[$i]['csd']['Genus'];
                $species['phenophases'] = array();
                $phenophase = null;                
            }else if($family_ids && $taxonomy_aggregate){
                $species = array();
                $species['family_id'] = intval($res[$i]['csd']['Family_ID']);
                $found_species[] = $res[$i]['csd']['Family_ID'];
                
                $species['family_common_name'] = $res[$i]['csd']['Family_Common_Name'];
                $species['family_name'] = $res[$i]['csd']['Family_Name'];
                $species['phenophases'] = array();
                $phenophase = null;                
            }else if($order_ids && $taxonomy_aggregate){
                $species = array();
                $species['order_id'] = intval($res[$i]['csd']['Order_ID']);
                $found_species[] = $res[$i]['csd']['Order_ID'];
                
                $species['order_common_name'] = $res[$i]['csd']['Order_Common_Name'];
                $species['order_name'] = $res[$i]['csd']['Order_Name'];
                $species['phenophases'] = array();
                $phenophase = null;                
            }else if($class_ids && $taxonomy_aggregate){
                $species = array();
                $species['class_id'] = intval($res[$i]['csd']['Class_ID']);
                $found_species[] = $res[$i]['csd']['Class_ID'];
                
                $species['class_common_name'] = $res[$i]['csd']['Class_Common_Name'];
                $species['class_name'] = $res[$i]['csd']['Class_Name'];
                
                
                
                if(!$pheno_class_aggregate){
                    $species['phenophases'] = array();
                }else{
                    $species['pheno_classes'] = array();
                }
                $phenophase = null;                
            }
            
            
            //$phenophases = array();
            /**
             * The way this loop works is by walking through the recordset and collecting each phenophase/phenoclass for each species.
             * The condition that breaks the loop is based on the next species/family/class/order id being different from the last iteration
             * of the loop.
             * This complex logic is then necessary because there's a different property in the result set to inspect for this condition based
             * on what the client is searching for. By default, it's set to use species_id.
             */
            for($j=$i;$j < $count && 
                    (
                        (($species_ids || !$taxonomy_aggregate) && ($species['species_id'] == $res[$j]['csd']['Species_ID'])) ||
                        (($family_ids && $taxonomy_aggregate) && ($species['family_id'] == $res[$j]['csd']['Family_ID'])) ||
                        (($class_ids && $taxonomy_aggregate) && ($species['class_id'] == $res[$j]['csd']['Class_ID'])) ||
                        (($order_ids && $taxonomy_aggregate) && ($species['order_id'] == $res[$j]['csd']['Order_ID'])) ||
                        (($genus_ids && $taxonomy_aggregate) && ($species['genus_id'] == $res[$j]['csd']['Genus_ID']))
                    )
                    
                    ;$j++){

                $phenophase = new PhenPhase();
                
                if(!$pheno_class_aggregate){
                    $phenophase->phenophase_id = intval($res[$j]['pp']['Phenophase_ID']);
                    $phenophase->phenophase_name = $res[$j]['pp']['Phenophase_Name'];
                    $phenophase->seq_num = intval($res[$j]['ppp']['Seq_Num']);
                }else{
                    $phenophase->pheno_class_id = intval($res[$j]['csd']['Pheno_Class_ID']);
                    $phenophase->pheno_class_name = $res[$j]['csd']['Pheno_Class_Name'];
                }
                
                       
                $phenophase->years = array();
                
                for($k=$j;$k < $count && 
                        (
                            (($species_ids || !$taxonomy_aggregate) && ($species['species_id'] == $res[$k]['csd']['Species_ID'])) ||
                            (($family_ids && $taxonomy_aggregate) && ($species['family_id'] == $res[$k]['csd']['Family_ID'])) ||
                            (($class_ids && $taxonomy_aggregate) && ($species['class_id'] == $res[$k]['csd']['Class_ID'])) ||
                            (($order_ids && $taxonomy_aggregate) && ($species['order_id'] == $res[$k]['csd']['Order_ID'])) ||
                            (($genus_ids && $taxonomy_aggregate) && ($species['genus_id'] == $res[$k]['csd']['Genus_ID']))
                        ) &&
                        (
                            (!$pheno_class_aggregate && $res[$k]['pp']['Phenophase_ID'] == $phenophase->phenophase_id) ||
                            ($pheno_class_aggregate && $res[$k]['csd']['Pheno_Class_ID'] == $phenophase->pheno_class_id  )
                        )
                        ;$k++){
                    
                    
                    //variable variable for year number                
                    $prop_name = $res[$k][0]['year'];
                    $phenophase->years[$prop_name] = new stdClass();
                    
                    $dates = array_map('intval',explode(",",$res[$k][0]['Dates_Positive']));
                    
                    //client is expecting empty payloads to be an empty array but array_map/intval will set nulls to 0.
                    if(count($dates) == 1 && $dates[0] == 0){
                        $dates = array();
                    }
                    
                    $phenophase->years[$prop_name]->positive = $dates;
                    
                    $dates_negative = array_map('intval',explode(",",$res[$k][0]['Dates_Negative']));
                    
                    //This prevents a case where array_values will empty out negative date array if positive date array is empty
                    if(!empty($dates)){
                        $dates_negative = array_values(array_diff($dates_negative, $dates));
                    }

                    $phenophase->years[$prop_name]->negative = ((count($dates) == 1 && $dates[0] == 0) ? array():  $dates_negative);
                    
                    
                }
                
                $j = ($k-1);
                

                if(!$pheno_class_aggregate){
                    $species['phenophases'][] = $phenophase;
                }else{
                    $species['pheno_classes'][] = $phenophase;
                }
                
            }
            $i = ($j-1);
            $response[] = $species;
            
        }
        
        /**
         * The viz tool does not handle well responses which exclude species which were
         * requested. This will create an empty species entry in the response for
         * any species which didn't return results in the database.
         * 
         * This logic was later expanded to capture things 'missing' in the 
         * search results depending on if the client is seeking
         * families/order/classes or species
         */
        $compare_array = array();
        if($species_ids){
            $compare_array = $search_params->species_id;
        }else if($family_ids && $taxonomy_aggregate){
            $compare_array = $family_ids;
        }else if($order_ids && $taxonomy_aggregate){
            $compare_array = $order_ids;
        }else if($class_ids && $taxonomy_aggregate){
            $compare_array = $class_ids;
        }else if($genus_ids && $taxonomy_aggregate){
            $compare_array = $genus_ids;
        }else if(!$taxonomy_aggregate){
            $compare_array = array();
        }
        
        
        
        $species_not_found = array_diff( $found_species, $compare_array );
        if($species_not_found){
            foreach($species_not_found as $absent_species){
                $s = array();
                $s['species_id'] = $absent_species;
                $s['common_name'] = '';
                $s['phenophases'] = array();
                $response[] = $s;
            }
        }
        
        $this->log("Should be exiting this function");
        $this->set('response', $response);
        return $response;        

    }

    /**
     * This function takes an observation id as input and returns the observation's
     * comment.
     */
    public function getObservationComment($params=null){

        if(!$this->checkProperty($params, "observation_id")){
            $this->set('comment', new stdClass());
            return null;
        }
        $comment = new Comment();
        $comment->observation_comment = null;

        $results = $this->Observation->find('first', array(
            'fields' => array('Comment'),
            'conditions' => array('Observation.Observation_ID' => $params->observation_id)
        ));


        $comment->observation_comment = $this->CleanText->cleanText($results['Observation']['Comment']);

        $this->set("comment", $comment);
        return $comment;



    }


     
     private function executeSearch($search_component, $params, $out){
         $search_component->setConfigParameters();
         $search_component->importModels();
         
         if($search_component->checkSourceParameter($params)){
            $search_component->processInputParameters($params);
            $search_component->appendFields($params, $search_component->getAllowedValues());
            $search_component->outputData($params, $out);
            
            $search_component->logSearch($params);
         }
         
     }
     
    public function getObservationById($params=null){
        
        if(!$this->checkProperty($params, "observation_id") || !$this->checkProperty($params, "request_src")){
            $this->set('data','');
            return;
        }
        
        $domain = $_SERVER['HTTP_HOST'];
        $scheme = "https";
        if($domain == "localhost"){
            $scheme = "http";
        }
        
        App::import('Component', 'RawDataObservationSearch');
        $search = new RawDataObservationSearch(null);
        
        
        
       
        $url = $scheme . '://' . $domain . '/npn_portal/observations/getObservations.json?request_src=' . $params->request_src . '&observation_id=' . strval($params->observation_id);
        $url .= '&additional_field[0]=abundance_link&additional_field[1]=dataset_link';
        $url .= '&additional_field[2]=individual_link&additional_field[3]=phenophase_definition_link';
        
        $i = 4;
        foreach($search->getAllowedValues() as $k => $v){
            $url .= "&additional_field[" . $i++ . "]=" . $k;
        }
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $results = curl_exec($curl);
        
        if($this->checkProperty($params, 'pretty') && $params->pretty == 1){        
            $results = json_encode(json_decode($results), JSON_PRETTY_PRINT);
        }
        
        $this->set('data',$results);

    }      
     
     public function getObservations($params, $out, $format){
         App::import('Component', 'RawDataObservationSearch');

         if($this->checkProperty($params, "noHtmlEncoding")){
             $noHtmlEncoding = true;
         }else{
             $noHtmlEncoding = false;
         }

         $emitter = $this->getEmitter($format, $out, "observations", "getObservationsResponse", $noHtmlEncoding);
         $search = new RawDataObservationSearch($emitter);
         $this->executeSearch($search, $params, $out);
     }
     
     public function getSummarizedData($params, $out, $format){
         App::import('Component', 'SummarizedDataSearch');

         if($this->checkProperty($params, "noHtmlEncoding")){
             $noHtmlEncoding = true;
         }else{
             $noHtmlEncoding = false;
         }

         $emitter = $this->getEmitter($format, $out, "observations", "getObservationsResponse", $noHtmlEncoding);
         $search = new SummarizedDataSearch($emitter);
         $this->executeSearch($search, $params, $out);         
     }
     
     public function getSiteLevelData($params, $out, $format){
         App::import('Component', 'SiteLevelDataSearch');

         if($this->checkProperty($params, "noHtmlEncoding")){
             $noHtmlEncoding = true;
         }else{
             $noHtmlEncoding = false;
         }

         $emitter = $this->getEmitter($format, $out, "observations", "getObservationsResponse", $noHtmlEncoding);
         $search = new SiteLevelDataSearch($emitter);
         $this->executeSearch($search, $params, $out);         
     }
     
     public function getMagnitudeData($params, $out, $format){
         
         App::import('Component', 'MagnitudeDataSearch');

         if($this->checkProperty($params, "noHtmlEncoding")){
             $noHtmlEncoding = true;
         }else{
             $noHtmlEncoding = false;
         }

         $emitter = $this->getEmitter($format, $out, "observations", "getObservationsResponse", $noHtmlEncoding);
         $search = new MagnitudeDataSearch($emitter);
         $this->executeSearch($search, $params, $out);
     }     
    
    public function getDatasetDetails($params=null){
        
        $datasets = array();

        $results = $this->VwDatasetDetails->find('all');

        foreach($results as $result){
            
            $package = new DatasetDetails();
            $package->dataset_id = $result['VwDatasetDetails']['Dataset_ID'];
            $package->dataset_name = $result['VwDatasetDetails']['Dataset_Name'];
            $package->dataset_description = $result['VwDatasetDetails']['Dataset_Description'];
            $package->contact_name = $result['VwDatasetDetails']['Contact_Name'];
            $package->contact_institution = $result['VwDatasetDetails']['Contact_Institution'];
            $package->contact_email = $result['VwDatasetDetails']['Contact_Email'];
            $package->contact_phone = $result['VwDatasetDetails']['Contact_Phone'];
            $package->contact_address = $result['VwDatasetDetails']['Contact_Address'];
            $package->dataset_comments = $result['VwDatasetDetails']['Dataset_Comments'];
            $package->dataset_documentation_url = $result['VwDatasetDetails']['Dataset_Documentation_URL'];
            
            
            $datasets[] = $package;
        }

        $this->set('datasets', $datasets);
        
        if($this->checkProperty($params, "pretty") && $params->pretty == 1){
            $this->set('pretty', true);
        }        


        return $datasets;        
    }    
    
    public function getObservationGroupDetails($params,$out, $format){
        
        ini_set('max_execution_time', -1);
        ini_set('memory_limit', -1);
        ini_set('default_socket_timeout', 10000);
        
        
        App::import('Model','VwObservationGroupDetails');
        $this->VwObservationGroupDetails =  new VwObservationGroupDetails();

        $emitter = $this->getEmitter($format, $out, "observation_groups", "getObservationGroupDetailsResponse");
        $conditions = array();

        if($this->checkProperty($params, "observation_group_id")){
            $params->observation_group_id = $this->arrayWrap($params->observation_group_id);

            $conditions["VwObservationGroupDetails.Observation_Group_ID "] = $params->observation_group_id;
        }
        
        if($this->checkProperty($params, "ids")){
            $ids = urldecode($params->ids);
            $ids = explode(",", $ids);
            
            $conditions["VwObservationGroupDetails.Observation_Group_ID "] = $ids;
        }        
       

        $results = null;

        $emitter->emitHeader();

        $dbo = $this->VwObservationGroupDetails->getDataSource();
        $query = $dbo->buildStatement(array(
            "fields" => array('*'),
            "table" => $dbo->fullTableName($this->VwObservationGroupDetails),
            "alias" => 'VwObservationGroupDetails',
            "conditions" => $conditions,
            "group" => null,
            "limit" => null,
            "order" => null
            ),
        $this->VwObservationGroupDetails
        );

        $results = mysql_unbuffered_query($query);
        
        while($result = mysql_fetch_array($results, MYSQL_ASSOC)){               
            $result['node_name'] = "observation_group";
            $emitter->emitNode($result);
            $out->flush();
            }

     
        $emitter->emitFooter();
    }    
     

     /**
      * Just breaks up the input parameter dates into month, date, year, and
      * uses native checkdate function to verify.
      */
     private function validateDate($date){
         try{
             
            $s_date = str_replace(array('\'', '-', '.', ',', ' '), '/', $date);
            $a_date = explode('/', $s_date);
            if(count($a_date) != 3) throw new Exception();
            return checkdate($a_date[1], $a_date[2], $a_date[0]);

         }catch(Exception $ex){
             return false;
         }
     }
    
} 
