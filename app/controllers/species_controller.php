<?php

class Spec{}
class UpDate{}
class Type{}
class Tax{}
/**
 * Controller for retreiving species information.
 */
class SpeciesController extends AppController{
    
    
    public $uses = array('Species', 'UpdateDate', 'SpeciesType', 'Network', 
         'Observation', 'SpeciesStateLocation', 'SpeciesSpeciesType', 'VwSpeciesAllNames', 'SpeciesTaxon');
    
    public $components = array(
    	'Soap' => array(
        	'wsdl' => 'NPN', //the file name in the view folder
            	'action' => 'service', //soap service method / handler
        ),
        'RequestHandler'
    );    
    
    public function soap_wsdl(){
    	//will be handled by SoapComponent
    }
	
	
	
	public function soap_service(){
        //no code here
        }
    
   /**
    * Simple function. Shows all species' information.
    */
    public function getSpecies($params=null){
        
        $conditions = array("Active" => 1);
        
        if($this->checkProperty($params, "include_restricted")){
            if($this->resolveBooleanText($params, "include_restricted")){
                $conditions = array();
            }
        }

        $this->Species->
                unbindModel(
                        array('hasMany' =>
                            array('species2sspi', 'species2StationSpeciesIndividual', 'species2SpeciesProtocol'),
                              'hasAndBelongsToMany' =>
                            array('species2network')
                ));//'species2speciesType',
        
        $joins = array(
            array(
                'table' => 'Species_Taxon',
                'alias' => 'Species_Class',
                'type' => 'LEFT',
                'conditions' => 'Species_Class.Taxon_ID = Species.Class_ID'
            ),
            array(
                'table' => 'Species_Taxon',
                'alias' => 'Species_Order',
                'type' => 'LEFT',
                'conditions' => 'Species_Order.Taxon_ID = Species.Order_ID'
            ),
            array(
                'table' => 'Species_Taxon',
                'alias' => 'Species_Family',
                'type' => 'LEFT',
                'conditions' => 'Species_Family.Taxon_ID = Species.Family_ID'
            ),
            array(
                'table' => 'Species_Taxon',
                'alias' => 'Species_Genus',
                'type' => 'LEFT',
                'conditions' => 'Species_Genus.Taxon_ID = Species.Genus_ID'
            )
        );
        
        
        $species = array();
        $sps = $this->Species->find('all',
                array('fields' => array("Species_ID", "Common_Name", "Genus", "Species", "ITIS_Taxonomic_SN", "Functional_Type", "Kingdom", 
                    'Class_ID','Species_Class.Common_Name','Species_Class.Name',
                    'Order_ID','Species_Order.Common_Name','Species_Order.Name',
                    'Family_ID','Species_Family.Common_Name','Species_Family.Name','Species_Genus.Taxon_ID'
                    ),
                      'order' => 'Species.Common_Name',
                      'conditions' => $conditions,
                      'joins' => $joins
                    ));

        foreach($sps
                as $sp){
            $obj = new Spec();
            $obj->species_id = $sp["Species"]["Species_ID"];
            $obj->common_name = $sp["Species"]["Common_Name"];
            $obj->genus = $sp["Species"]["Genus"];
            $obj->genus_id = $sp["Species_Genus"]["Taxon_ID"];
            $obj->genus_common_name = $sp["Species"]["Genus_Common_Name"];

            $obj->species = $sp["Species"]["Species"];
            $obj->kingdom = $sp["Species"]["Kingdom"];
            $obj->itis_taxonomic_sn = $sp["Species"]["ITIS_Taxonomic_SN"];
            $obj->functional_type = $sp["Species"]["Functional_Type"];
            $obj->class_id = $sp["Species"]["Class_ID"];
            $obj->class_common_name = $sp["Species_Class"]["Common_Name"];
            $obj->class_name = $sp["Species_Class"]["Name"];
            $obj->order_id = $sp["Species"]["Order_ID"];
            $obj->order_common_name = $sp["Species_Order"]["Common_Name"];
            $obj->order_name = $sp["Species_Order"]["Name"];
            $obj->family_id = $sp["Species"]["Family_ID"];
            $obj->family_name = $sp["Species_Family"]["Name"];
            $obj->family_common_name = $sp["Species_Family"]["Common_Name"];

            $obj->species_type = [];
            foreach($sp["species2speciesType"] as $spType) {
                $obj->species_type[] = $spType;
            }
            
            $species[] = $obj;
        }

        $this->set('species', $species);
        return $species;
        
        
        
    }
    
    
    public function getTaxon($params=null){
      
        $include_species = false;
        $conditions = array("SpeciesTaxon.Level != 'Kingdom'");
        
        if($this->checkProperty($params, "include_species")){
            if($this->resolveBooleanText($params, "include_species")){
                $include_species = true;
            }
        }
        
        if($this->checkProperty($params, "level")){
            $params->level = ucfirst(strtolower($params->level));
            if($params->level == "Class" || $params->level == "Order" || $params->level == "Family" || $params->level == "Genus"){
                $conditions[] = "SpeciesTaxon.Level = '" . $params->level . "'";
            }
            
        }
        
        $fields =  array('Taxon_ID', 'SpeciesTaxon.Name', 'SpeciesTaxon.Common_Name');
        
        if($include_species){
            $joins = array(
              array(
                'table' => 'Species',
                'alias' => 'Species_Class',
                'type' => 'LEFT',
                'conditions' => 'Species_Class.Class_ID = SpeciesTaxon.Taxon_ID'              
              ),
              array(
                'table' => 'Species',
                'alias' => 'Species_Order',
                'type' => 'LEFT',
                'conditions' => 'Species_Order.Order_ID = SpeciesTaxon.Taxon_ID'              
              ),
              array(
                'table' => 'Species',
                'alias' => 'Species_Family',
                'type' => 'LEFT',
                'conditions' => 'Species_Family.Family_ID = SpeciesTaxon.Taxon_ID'              
              ),
              array(
                'table' => 'Species',
                'alias' => 'Species_Genus',
                'type' => 'LEFT',
                'conditions' => 'Species_Genus.Genus_ID = SpeciesTaxon.Taxon_ID'              
              )              
            );
            
            $fields = array_merge($fields, array('Species_Class.Species_ID','Species_Order.Species_ID','Species_Family.Species_ID','Species_Genus.Species_ID'));
            
        }else{
            $joins = array();
        }
        
        $taxon = array();
        $results = $this->SpeciesTaxon->find('all', array(
            'joins' => $joins,
            'order' => 'Taxon_ID',
            'fields' => $fields,
            'conditions' => $conditions
        ));
        
        $obj = null;
        $prev_id = null;

        foreach($results
                as $result){
            if($obj == null || $result['SpeciesTaxon']['Taxon_ID'] != $prev_id){
                if($obj != null){
                    $taxon[] = $obj;
                }
                
                $obj = new Tax();

                $prev_id = $obj->taxon_id = $result['SpeciesTaxon']['Taxon_ID'];
                $obj->name = $result['SpeciesTaxon']['Name'];
                $obj->common_name = $result['SpeciesTaxon']['Common_Name'];
                
                
                if($include_species){
                    if($result['Species_Class']['Species_ID'] != null){
                        $obj->species_ids = array($result['Species_Class']['Species_ID']);
                    }else if($result['Species_Order']['Species_ID'] != null){
                        $obj->species_ids = array($result['Species_Order']['Species_ID']);
                    }else if($result['Species_Family']['Species_ID'] != null){
                        $obj->species_ids = array($result['Species_Family']['Species_ID']);
                    }else if($result['Species_Genus']['Species_ID'] != null){
                        $obj->species_ids = array($result['Species_Genus']['Species_ID']);
                    }
                }
                
            }else{
                if($include_species){
                    if($result['Species_Class']['Species_ID'] != null){
                        $obj->species_ids[] = $result['Species_Class']['Species_ID'];
                    }else if($result['Species_Order']['Species_ID'] != null){
                        $obj->species_ids[] = $result['Species_Order']['Species_ID'];
                    }else if($result['Species_Family']['Species_ID'] != null){
                        $obj->species_ids[] = $result['Species_Family']['Species_ID'];
                    }else if($result['Species_Genus']['Species_ID'] != null){
                        $obj->species_ids[] = $result['Species_Genus']['Species_ID'];
                    }
                }

            }  
        }

        $this->set('taxon', $taxon);
        return $taxon;

    }
    
    

    /**
     * Takes a state abbreviation as input and returns a list of all species
     * in that state. Optionally allows to filter by kingdom as well as state.
     */
    public function getSpeciesByState($params=null){
        $conditions = array();


        $this->SpeciesStateLocation->bindModel(array(
                'belongsTo' => array('Species')
        ));

        if($this->checkProperty($params, 'state')){
            $conditions['SpeciesStateLocation.State_Code'] = $params->state;
        }else{
            $this->set('species', array());
            return array();
        }

        if($this->checkProperty($params, "kingdom")){
            $conditions['Species.Kingdom'] = $params->kingdom;
        }

        $conditions['Active'] = 1;

        $species = array();
        $sps = $this->SpeciesStateLocation->find('all',
                array(
                    'fields' => array("Species.Species_ID",
                                      "Species.Common_Name",
                                      "Species.Genus",
                                      "Species.Species",
                                      "Species.Kingdom",
                                      "Species.ITIS_Taxonomic_SN"
                               ),
                    'conditions' => $conditions,
                    'order' => 'Species.Common_Name'
        ));

        foreach($sps
                as $sp){
            $obj = new Spec();
            $obj->species_id = $sp["Species"]["Species_ID"];
            $obj->common_name = $sp["Species"]["Common_Name"];
            $obj->genus = $sp["Species"]["Genus"];
            $obj->kingdom = $sp["Species"]["Kingdom"];
            $obj->species = $sp["Species"]["Species"];
            $obj->itis_taxonomic_sn = $sp["Species"]["ITIS_Taxonomic_SN"];

            $species[] = $obj;
        }

        $this->set('species', $species);
        return $species;

    }

    /**
     * Function used to find the last time any updates were made to the species table.
     * Uses update table to store this information. Table must be manually maintained.
     * This is useful for clients wishing to cache species data. Can query this function
     * with smaller payload to see if changes are made and only then re-fetch all species.
     */
    public function getSpeciesUpdateDate(){
        $date = $this->UpdateDate->find("first", array("conditions" => array("Table_Name = " => "species")));
        
        $updateDate = new UpDate();
        $updateDate->update_date = $date["UpdateDate"]["Update_Date"];

        $this->set('update_date', $updateDate);
        return $updateDate;
    }
    
    
    private function getTypes($kingdom){
        
        $types = array();

        $this->SpeciesType->bindModel(array(
            'hasOne' => array(
                'SpeciesSpeciesType' => array(
                    'conditions' => array(
                        'SpeciesSpeciesType.Species_Type_ID = SpeciesType.Species_Type_ID'
                    ),
                    'foreignKey' => false
                )
            )
        ));

        $plant_types_results = $this->SpeciesType->find('all', array(
            'conditions' => array(
                                'SpeciesType.Kingdom' => $kingdom
            ),
            'fields' => array('Species_Type', 'Species_Type_ID', 'COUNT(SpeciesType.Species_Type_ID) cnt'),
            'group' => 'SpeciesType.Species_Type_ID'
        ));
        
        foreach($plant_types_results as $result){
            $type = new Type();
            $type->species_type = $result['SpeciesType']['Species_Type'];
            $type->species_type_id = $result['SpeciesType']['Species_Type_ID'];
            $type->species_count = $result[0]['cnt'];

            $types[] = $type;
            
        }

        
        return $types;        
    }
    


    /**
     * Gets all plant types (decidous, cactus, etc)
     * Also shows count of species in that group.
     */
    public function getPlantTypes(){
        $types = $this->getTypes("Plantae");
        
        $this->set('types', $types);
        return $types;
    }

    /**
     * Gets all animal types (mammals, reptiles, etc)
     * Also shows count of species in that group.
     */
    public function getAnimalTypes(){
        $types = $this->getTypes("Animalia");
        
        $this->set('types', $types);
        return $types;        
    }
    
    public function getSpeciesFunctionalTypes(){
   
        $types = array();
        
        $this->Species->
                unbindModel(
                        array('hasMany' =>
                            array('species2sspi', 'species2StationSpeciesIndividual', 'species2SpeciesProtocol'),
                              'hasAndBelongsToMany' =>
                            array('species2speciesType', 'species2network')
                ));        
        
        $results = $this->Species->find('all', array(
            'fields' => array('DISTINCT Functional_Type'),
            'conditions' => array('Functional_Type IS NOT NULL')
            )
        );
        
        foreach($results as $result){
            $type = new Type();
            $type->type_name = $result['Species']['Functional_Type'];
            $types[] = $type;
        }
        
        $this->set('types', $types);
        return $types;
        
    }
    
    public function getSpeciesFilter($search_params=null){
        
        $network_ids = null;
        $group_ids = null;
        $station_ids = null;
        $start_date = null;
        $end_date = null;
        $taxon = null;

        if($this->checkProperty($search_params, "network_id")){     
            $network_ids = $this->arrayWrap($search_params->network_id);            
        }
        
        if($this->checkProperty($search_params, "group_ids")){     
            $group_ids = $this->arrayWrap($search_params->group_ids);            
        }
        
        if($this->checkProperty($search_params, "station_ids")){     
            $station_ids = $this->arrayWrap($search_params->station_ids);            
        }                      
        
        if($this->checkProperty($search_params, "start_date")){
            $start_date = $search_params->start_date;
        }
        
        if($this->checkProperty($search_params, "end_date")){
            $end_date = $search_params->end_date;
        }
        
        if($this->checkProperty($search_params, "taxon")){
            
            $taxon = strtolower($search_params->taxon);
            
            if($taxon != "family" && $taxon != "order" && $taxon != "class" && $taxon != "genus"){
                $taxon = "species";
            }
            
        }else{
            $taxon = "species";
        }
        
        
        
        $query = "SELECT SQL_CACHE COUNT(co.Observation_ID) c, csd.Kingdom, csd.Site_ID, csd.Individual_ID, csd.Phenophase_ID,csd.Family_ID, csd.Family_Name, csd.Family_Common_Name," .
                "csd.Order_ID, csd.Order_Name, csd.Order_Common_Name,csd.Class_ID, csd.Class_Name, csd.Class_Common_Name,csd.Species_ID, csd.Common_Name, csd.Genus, csd.Species, " .
                "s.ITIS_Taxonomic_SN, s.Functional_Type, csd.Genus_ID";
        
        $query .= "
                    FROM usanpn2.Cached_Summarized_Data csd
                    LEFT JOIN usanpn2.Cached_Observation co
                    ON co.Series_ID = csd.Series_ID
                    LEFT JOIN usanpn2.Species s
                    ON csd.Species_ID = s.Species_ID";
                    
        
        
        if($group_ids != null && !empty($group_ids)){
            $query .= " LEFT JOIN 
                        (
                        SELECT DISTINCT sst.Species_ID
                        FROM usanpn2.Species_Species_Type sst
                        LEFT JOIN usanpn2.Species_Type st
                        ON st.Species_Type_ID = sst.Species_Type_ID
                        WHERE st.Species_Type_ID IN (";
            
            $i=0;
            foreach($group_ids as $group_id){
                $query .= ($i == 0) ? $group_id : (", " . $group_id);
                $i++;                                
            }            
            
            $query .=   ")
                        ) st
                        ON st.Species_ID = s.Species_ID";
        }
        
        $query .= " WHERE s.Active = 1 ";
        
        if($group_ids != null && !empty($group_ids)){
            $query .= " AND st.Species_ID IS NOT NULL";
        }
        
        if($network_ids != null && !empty($network_ids)){
            $query .= " AND (";
            $net_iterate = 0;
            foreach($network_ids as $network_id){
                
                $query .= ($net_iterate++ == 0) ? "" : " OR ";
                $query .= "csd.Network_IDs LIKE '%," . $network_id . ",%' ";
                $query .= "OR csd.Network_IDs = '" . $network_id . "' ";
                $query .= "OR csd.Network_IDs LIKE '" . $network_id . ",%' ";
                $query .= "OR csd.Network_IDs LIKE '%," . $network_id . "' ";
            }
            
            $query .= ")";
        }

        if($start_date != null && $end_date != null){
            $query .= " AND co.Observation_Date BETWEEN '" . $start_date . "' AND '" . $end_date . "'";
        }
        
        if($station_ids != null && !empty($station_ids)){
            $query .= " AND csd.Site_ID IN (";
            $i=0;
            foreach($station_ids as $station_id){
                $query .= ($i == 0) ? $station_id : (", " . $station_id);
                $i++;
            }            
            $query .= ")";
        }
        
        if($taxon == "family"){
            $query .= " GROUP BY csd.Family_ID"
                    . " ORDER BY csd.Family_Common_Name";
        }else if($taxon == "order"){
            $query .= " GROUP BY csd.Order_ID"
                    . " ORDER BY csd.Order_Common_Name";
        }else if($taxon == "class"){
            $query .= " GROUP BY csd.Class_ID"
                    . " ORDER BY csd.Class_Common_Name";
        }elseif($taxon=='genus'){
            $query .= " GROUP BY csd.Genus_ID"
                    . " ORDER BY csd.Genus_Common_Name";
        }
        else{
            $query .= " GROUP BY csd.Species_ID"
                    . " ORDER BY Common_Name";

        }


        $this->log($query);
                       
        $results = $this->Observation->query($query);

        $species = array();
        
        
        foreach($results as $result){

            $obj = new Spec();
            
            if($taxon == "family"){
                
                if($result['csd']['Family_ID'] == null){
                    continue;
                }
                
                $obj->family_id = $result['csd']['Family_ID'];
                $obj->family_name = $result['csd']['Family_Name'];
                $obj->family_common_name = $result['csd']['Family_Common_Name'];
            }else if($taxon == "class"){
                
                if($result['csd']['Class_ID'] == null){
                    continue;
                }                
                
                $obj->class_id = $result['csd']['Class_ID'];
                $obj->class_name = $result['csd']['Class_Name'];
                $obj->class_common_name = $result['csd']['Class_Common_Name'];    
                
            }else if($taxon == "order"){
                
                if($result['csd']['Order_ID'] == null){
                    continue;
                }                  
                
                $obj->order_id = $result['csd']['Order_ID'];
                $obj->order_name = $result['csd']['Order_Name'];
                $obj->order_common_name = $result['csd']['Order_Common_Name'];                                
            }else if($taxon == "genus"){

                if($result['csd']['Genus_ID'] == null){
                    continue;
                }                  
                
                $obj->genus_id = $result['csd']['Genus_ID'];
                $obj->genus_name = $result['csd']['Genus'];
                $obj->genus_common_name = $result['csd']['Genus_Common_Name'];
            }
            else{
                
                $obj->common_name = $result['csd']['Common_Name'];
                $obj->genus = $result['csd']['Genus'];

                // TODO: Add this once Genus_Common_Name is filled 
                $obj->genus_common_name = $result['csd']['Genus_Common_Name'];
                // $obj->genus_common_name = $result['csd']['Genus'];

                $obj->genus_id = $result['csd']['Genus_ID'];
                
                $obj->species = $result['csd']['Species'];                
                $obj->species_id = $result['csd']['Species_ID'];
                
                $obj->family_id = $result['csd']['Family_ID'];
                $obj->family_name = $result['csd']['Family_Name'];
                $obj->family_common_name = $result['csd']['Family_Common_Name'];
                
                $obj->class_id = $result['csd']['Class_ID'];
                $obj->class_name = $result['csd']['Class_Name'];
                $obj->class_common_name = $result['csd']['Class_Common_Name'];     
                
                $obj->order_id = $result['csd']['Order_ID'];
                $obj->order_name = $result['csd']['Order_Name'];
                $obj->order_common_name = $result['csd']['Order_Common_Name'];     
                
                $obj->itis_taxonomic_sn = $result["s"]["ITIS_Taxonomic_SN"];
                $obj->functional_type = $result["s"]["Functional_Type"];
                
            }

            $obj->kingdom = $result['csd']['Kingdom'];
            $obj->number_observations = $result[0]["c"];
            $species[] = $obj;            
        }
        
        $this->set('species', $species);
        return $species;                
    }
    
 
    /**
     *
     * Simple function to get the rest of a species' information from its ID.
     */
    function getSpeciesById($params=null){
        $conditions = array();

        $this->Species->
                unbindModel(
                        array('hasMany' =>
                            array('species2sspi', 'species2StationSpeciesIndividual', 'species2SpeciesProtocol'),
                              'hasAndBelongsToMany' =>
                            array('species2speciesType', 'species2network')
                ));

        if($this->checkProperty($params, 'species_id')){
            $conditions['Species.Species_ID'] = $params->species_id;
        }else{
            $this->set('species', new stdClass());
            return array();
        }

        $sp = $this->Species->find('first', array(
            'conditions' => $conditions,
            'fields' => array(
                'Species.Common_Name',
                'Species.Genus',
                'Species.Species',
                'Species.Kingdom',
                'Species.ITIS_Taxonomic_SN'
            )
        ));

        $obj = new Spec();
        $obj->common_name = $sp["Species"]["Common_Name"];
        $obj->genus = $sp["Species"]["Genus"];
        $obj->species = $sp["Species"]["Species"];
        $obj->kingdom = $sp["Species"]["Kingdom"];
        $obj->itis_taxonomic_sn = $sp["Species"]["ITIS_Taxonomic_SN"];
        $this->set('species', $obj);
        return $obj;

    }


    /**
     *
     * Find species by ITIS SNs.
     */
    function getSpeciesByITIS($params=null){
        $conditions = array();

        $this->Species->
                unbindModel(
                        array('hasMany' =>
                            array('species2sspi', 'species2StationSpeciesIndividual', 'species2SpeciesProtocol'),
                              'hasAndBelongsToMany' =>
                            array('species2speciesType', 'species2network')
                ));

        if($this->checkProperty($params, 'itis_sn')){
            $conditions['Species.ITIS_Taxonomic_SN'] = $params->itis_sn;
        }else{
            $this->set('species', array());
            return array();
        }

        $sp = $this->Species->find('first', array(
            'conditions' => $conditions,
            'fields' => array(
                'Species.Common_Name',
                'Species.Genus',
                'Species.Species',
                'Species.Kingdom',
                'Species.Species_ID'
            )
        ));

        $obj = new Spec();
        $obj->common_name = $sp["Species"]["Common_Name"];
        $obj->genus = $sp["Species"]["Genus"];
        $obj->species = $sp["Species"]["Species"];
        $obj->species_id = $sp["Species"]["Species_ID"];
        $obj->kingdom = $sp["Species"]["Kingdom"];
        $this->set('species', $obj);
        return $obj;

    }


    /**
     *
     * Find species by Scientific Name
     */
    function getSpeciesByScientificName($params=null){
        $conditions = array();

        $this->Species->
                unbindModel(
                        array('hasMany' =>
                            array('species2sspi', 'species2StationSpeciesIndividual', 'species2SpeciesProtocol'),
                              'hasAndBelongsToMany' =>
                            array('species2speciesType', 'species2network')
                ));

        if($this->checkProperty($params, 'genus')){
            $conditions['Species.Genus'] = $params->genus;
        }else{
            $this->set('species', array());
            return array();
        }

        if($this->checkProperty($params, 'species')){
            $conditions['Species.Species'] = $params->species;
        }else{
            $this->set('species', array());
            return array();
        }

        $sp = $this->Species->find('first', array(
            'conditions' => $conditions,
            'fields' => array(
                'Species.Common_Name',
                'Species.ITIS_Taxonomic_SN',
                'Species.Species_ID',
                'Species.Kingdom'
            )
        ));

        $obj = new Spec();
        $obj->common_name = $sp["Species"]["Common_Name"];
        $obj->itis_taxonomic_sn = $sp["Species"]["ITIS_Taxonomic_SN"];
        $obj->kingdom = $sp["Species"]["Kingdom"];
        $obj->species_id = $sp["Species"]["Species_ID"];
        $this->set('species', $obj);
        return $obj;
    }


    /**
     *
     * Find species by Common Name
     */
    function getSpeciesByCommonName($params=null){
        $conditions = array();



        if($this->checkProperty($params, 'common_name')){
            $conditions['VwSpeciesAllNames.All_Names LIKE'] = ('%' . $params->common_name . '%');
        }else{
            $this->set('species', array());
            return array();
        }


        $sp = $this->VwSpeciesAllNames->find('first', array(
            'conditions' => $conditions,
            'fields' => array(
                'speciesAllNames2species.Genus',
                'speciesAllNames2species.Species',
                'speciesAllNames2species.ITIS_Taxonomic_SN',
                'speciesAllNames2species.Species_ID'
            )
        ));

        $obj = new Spec();
        $obj->genus = $sp["speciesAllNames2species"]["Genus"];
        $obj->itis_taxonomic_sn = $sp["speciesAllNames2species"]["ITIS_Taxonomic_SN"];
        $obj->species = $sp["speciesAllNames2species"]["Species"];
        $obj->species_id = $sp["speciesAllNames2species"]["Species_ID"];
        $this->set('species', $obj);
        return $obj;
    }

 
} 
