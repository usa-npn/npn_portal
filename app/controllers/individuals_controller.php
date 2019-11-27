<?php

class Indiv{}
class ShadeStatus{}

/**
 * Controller for retreiving data related to individuals.
 */
class IndividualsController extends AppController{
    
    
    public $uses = array('StationSpeciesIndividual', 'Observation', 'Lookup');
    
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
    
    /**
     * This function will find all of the individuals, members of a specified
     * species, at a specied series of locations.
     */
    public function getIndividualsOfSpeciesAtStations($search_params=null){
        $ssis = array();
        $species_id = null;
        
        if($this->checkProperty($search_params, "species_id")){
            $species_id = $search_params->species_id;
        }
        
        /**
         * Ensures the station ids parameter is an array,
         * if only one station was provided.
         */
        $station_ids = array();
        
        if($this->checkProperty($search_params, "station_ids")){
            $station_ids = $this->arrayWrap($search_params->station_ids);
        }
         
        /**
         * If required parameters aren't present then reject request.
         */
        if(!$species_id || !$station_ids){
            $this->set('individuals', $ssis);
            return null;
        }

        /**
         * Since we want to also find the count of observations made about said
         * individuals within a given year's time, we have to go through the
         * observation table. Simplifies the ORM. First step is setup the
         * conditions on the find. Year is optional parameter.
         */
        $conditions = array(
            "observation2StationSpeciesIndividual.Species_ID" => $species_id,
            "observation2StationSpeciesIndividual.Station_ID" => $station_ids
        );
        
        $conditions [] = '(Observation.Deleted IS NULL OR Observation.Deleted <> 1  )';
        
        

        if(!empty($search_params->year)){
            $conditions["YEAR(Observation.Observation_Date) = "] = $search_params->year;
        }


        /**
         * Next step, is to unbind model relationships not needed.
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
         * Finally, run the actual query.
         */
        $results = $this->Observation->find('all', array(
            'fields' => array(
                        "observation2StationSpeciesIndividual.Individual_UserStr",
                        "observation2StationSpeciesIndividual.Individual_ID",
                        "COUNT(DISTINCT Observation_Date) cnt"
            ),
            "conditions" => $conditions,
            "group" => "Observation.Individual_ID"
        ));

        /**
         * Lastly, parse the results into objects.
         */
        foreach($results as $ssi){
            $obj = new Indiv();
            
            $obj->individual_id = $ssi['observation2StationSpeciesIndividual']['Individual_ID'];
            $obj->individual_name = $this->CleanText->cleanText($ssi['observation2StationSpeciesIndividual']['Individual_UserStr']);
            $obj->number_observations = $ssi[0]['cnt'];
            
            $ssis[] = $obj;
        }

        $this->set('individuals', $ssis);
        return $ssis;

    }

    /**
     *
     * Function to get a list of all individuals, at a specified
     * series of stations.
     */
    public function getIndividualsAtStations($search_params=null){
        $ssis = array();
        $station_ids = null;
        
        /**
         * Ensure stations is an array, not a singular item.
         */
        if($this->checkProperty($search_params, "station_ids")){
            $station_ids = $this->arrayWrap($search_params->station_ids);
        }
     
        
        /**
         * Send empty response if required params not present
         */
        if(!$station_ids){
            $this->set('individuals', $ssis);
            return null;            
        }


        $conditions = array(
            "StationSpeciesIndividual.Station_ID" => $station_ids
        );



        /**
         * Unbind junk relationships.
         */
        $this->StationSpeciesIndividual->unbindModel(
                array('hasMany' =>
                    array(
                        'stationspeciesindividual2Observation'
                    )
                )
        );
        
        $joins = array(
            array(
                "table" => "Image_Station_Species_Individual",
                "alias" => "image_station_species_individual",
                "type" => "LEFT",
                "conditions" => "image_station_species_individual.Individual_ID = StationSpeciesIndividual.Individual_ID"
            ),
            array(
                "table" => "Image_Image_Source",
                "alias" => "image_image_source",
                "type" => "LEFT",
                "conditions" => array(
                    "image_image_source.Image_Source_ID = 1",
                    "image_image_source.Image_ID = image_station_species_individual.Image_ID"
                )
            )
        );        

        $results = $this->StationSpeciesIndividual->find('all', array(
            'fields' => array(
                        "StationSpeciesIndividual.Individual_UserStr",
                        "StationSpeciesIndividual.Individual_ID",
                        "ssi2Species.Kingdom",
                        "StationSpeciesIndividual.Species_ID",
                        "StationSpeciesIndividual.Active",
                        "StationSpeciesIndividual.Seq_Num",
                        "image_image_source.File_URL"
            ),
            "conditions" => $conditions,
            "joins" => $joins
        ));

        foreach($results as $ssi){
            $obj = new Indiv();

            $obj->individual_id = $ssi['StationSpeciesIndividual']['Individual_ID'];
            $obj->individual_name = $this->CleanText->cleanText($ssi['StationSpeciesIndividual']['Individual_UserStr']);
            $obj->species_id = $ssi['StationSpeciesIndividual']['Species_ID'];
            $obj->kingdom = $ssi['ssi2Species']['Kingdom'];
            $obj->active = $ssi['StationSpeciesIndividual']['Active'];
            $obj->seq_num = $ssi['StationSpeciesIndividual']['Seq_Num'];
            $obj->file_url = $ssi['image_image_source']['File_URL'];
            $ssis[] = $obj;

        }
        $this->set('individuals', $ssis);
        return $ssis;
    }


    /**
     *
     * Simple function to get the rest of an individuals information from its
     * ID.
     */
    public function getIndividualById($params=null){
        $conditions = array();

        if($this->checkProperty($params, "individual_id") && !empty($params->individual_id)){
            $conditions['StationSpeciesIndividual.Individual_ID ='] = $params->individual_id;
        }else{
            $this->set('individual', new stdClass());
            return null;
        }

        $results = $this->StationSpeciesIndividual->find('all', array(
            'fields' => array(
                        "StationSpeciesIndividual.Individual_UserStr",
                        "StationSpeciesIndividual.Species_ID",
                        "ssi2Species.Kingdom"),
            'conditions' => $conditions
        ));

        if(!empty($results)){
            $ssi = new Indiv();
            $ssi->individual_name = $results[0]["StationSpeciesIndividual"]["Individual_UserStr"];
            $ssi->kingdom = $results[0]["ssi2Species"]["Kingdom"];
            $ssi->species_id = $this->CleanText->cleanText($results[0]['StationSpeciesIndividual']['Species_ID']);
        }

        $this->set('individual', $ssi);
        return $ssi;
    }

    public function getShadeStatuses(){
        $results = $this->Lookup->find('all', array(
           'conditions' => array(
               'Table_Name' => 'Station_Species_Individual',
               'Column_Name' => 'Shade_Status'
           ),
           'fields' => array(
               'Allowed_Value'
           ),
           'order' => array('Seq_Num')
        ));

        $statuses = array();

        if(!empty($results)){
            foreach($results as $result){
                $status = new ShadeStatus();
                $status->status = $result['Lookup']['Allowed_Value'];
                $statuses[] = $status;
            }
        }

        $this->set('statuses', $statuses);

        return $statuses;
    }

    public function getPlantDetails($params,$out, $format){
        App::import('Model','VwPlantDetails');
        $this->VwPlantDetails =  new VwPlantDetails();

        $emitter = $this->getEmitter($format, $out, "individuals", "getPlantDetailsResponse");
        $conditions = array();

        if($this->checkProperty($params, "individual_id")){
            if(!is_array($params->individual_id)){
                $params->individual_id = array($params->individual_id);
            }

            $conditions["VwPlantDetails.Individual_ID "] = $params->individual_id;
        }
        
        if($this->checkProperty($params, "ids")){
            $ids = urldecode($params->ids);
            $ids = explode(",", $ids);
            
            $conditions["VwPlantDetails.Individual_ID "] = $ids;
        }        

        $results = null;

        $emitter->emitHeader();

        $dbo = $this->VwPlantDetails->getDataSource();
        $query = $dbo->buildStatement(array(
            "fields" => array('*'),
            "table" => $dbo->fullTableName($this->VwPlantDetails),
            "alias" => 'VwPlantDetails',
            "conditions" => $conditions,
            "group" => null,
            "limit" => null,
            "order" => null
            ),
        $this->VwPlantDetails
        );

        $results = mysql_unbuffered_query($query);

        while($result = mysql_fetch_array($results, MYSQL_ASSOC)){
            $result['node_name'] = "individual";
            $result['Comment'] =  str_replace(">", "less than", str_replace("<", "greater than", str_replace("&", "and", strip_tags(preg_replace('/[^(\x20-\x7F)]*/','',  $result['Comment'])))));
            $emitter->emitNode($result);
            $out->flush();
        }


        $emitter->emitFooter();
    }
} 
