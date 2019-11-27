<?php

class PhenPhase{}
class UpDate{}
class Spec{}
class AbundanceCategories{}
class AbundanceCat{}
class AbundanceValue{}
class SpecProtocol{}
class Proto{}
class AddDef{}
class AbunDetail{}
class PhenoDef{}

/**
 *
 * Contoller for retreiving any information about phenophases.
 */
class PhenophasesController extends AppController{
    
    
    public $uses = array('Phenophase', 'SpeciesProtocol', 'Protocol', 'UpdateDate', 'AbundanceCategory', 
        'SpeciesSpecificPhenophaseInformation', 'VwProtocolDetails', 'VwAbundanceDetails', 'VwPhenophases', 'PhenophaseDefinition', 'PhenoClass');
    
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
     *
     * Simple function. Returns all phenophases from the database.
     */
    public function getPhenophases(){

        $this->VwPhenophases->unbindModel(
                array(
                    'hasMany' => array(
                        'phenophase2protocolPhenophase', 
                        'phenophase2sspi'
                        )
                    )
                );
        $pps = array();
        
        foreach($this->VwPhenophases->find('all', array(
                'fields' => array(
                            "Phenophase_ID", 
                            "Phenophase_Name",
                            "Short_Name",
                            "Pheno_Class_ID",
                            "Color")
            )) as $phenophase){
            $p = new PhenPhase();
            $p->phenophase_id = $phenophase['VwPhenophases']['Phenophase_ID'];
            $p->phenophase_name = $this->CleanText->cleanText($phenophase['VwPhenophases']['Phenophase_Name']);
            //$p->phenophase_name = $phenophase['VwPhenophases']['Phenophase_Name'];
            $p->phenophase_category = $phenophase['VwPhenophases']['Short_Name'];
            $p->color = $phenophase['VwPhenophases']['Color'];
            $p->pheno_class_id = $phenophase['VwPhenophases']['Pheno_Class_ID'];

            $pps[] = $p;
        }

        $this->set('phenophases', $pps);
        return $pps;
    }
    

    public function getPhenophaseDetails($params, $out, $format){
        App::import('Model','VwPhenophaseDetails');
        $this->VwPhenophaseDetails =  new VwPhenophaseDetails();

        if($this->checkProperty($params, "noHtmlEncoding"))
            $noHtmlEncoding = true;
        else
            $noHtmlEncoding = false;

        $emitter = $this->getEmitter($format, $out, "phenophases", "getPhenophaseDetailsResponse", $noHtmlEncoding);
        $conditions = array();

        if($this->checkProperty($params, "phenophase_id")){
            if(!is_array($params->phenophase_id)){
                $params->phenophase_id = array($params->phenophase_id);
            }

            $conditions["VwPhenophaseDetails.Phenophase_ID "] = $params->phenophase_id;
        }
        
        if($this->checkProperty($params, "ids")){
            $ids = urldecode($params->ids);
            $ids = explode(",", $ids);
            
            $conditions["VwPhenophaseDetails.Phenophase_ID "] = $ids;
        }        

        $results = null;

        $emitter->emitHeader();

        $dbo = $this->VwPhenophaseDetails->getDataSource();
        $query = $dbo->buildStatement(array(
            "fields" => array('*'),
            "table" => $dbo->fullTableName($this->VwPhenophaseDetails),
            "alias" => 'VwPhenophaseDetails',
            "conditions" => $conditions,
            "group" => null,
            "limit" => null,
            "order" => null
            ),
        $this->VwPhenophaseDetails
        );

        $results = mysql_unbuffered_query($query);

        while($result = mysql_fetch_array($results, MYSQL_ASSOC)){
            $result['node_name'] = "phenophase";
            if(!$noHtmlEncoding) {
                $result['Phenophase_Revision_Comments'] = htmlentities(preg_replace('/[^(\x20-\x7F)]*/','',  $result['Phenophase_Revision_Comments']));
                $result['Phenophase_Description'] =  htmlentities(preg_replace('/[^(\x20-\x7F)]*/','',  $result['Phenophase_Description']));
                $result['Phenophase_Names'] =  htmlentities(preg_replace('/[^(\x20-\x7F)]*/','',  $result['Phenophase_Names']));
            }
            else {
                $result['Phenophase_Revision_Comments'] = preg_replace('/[^(\x20-\x7F)]*/','',  $result['Phenophase_Revision_Comments']);
                $result['Phenophase_Description'] =  preg_replace('/[^(\x20-\x7F)]*/','',  $result['Phenophase_Description']);
                $result['Phenophase_Names'] =  preg_replace('/[^(\x20-\x7F)]*/','',  $result['Phenophase_Names']);
            }
            $result['Definition_IDs'] = "'" . $result['Definition_IDs'] . "'";
            $emitter->emitNode($result);
            $out->flush();
        }


        $emitter->emitFooter();
    }    
    
    
    /**
     *
     * Simple function. Returns all phenophases from the database.
     */
    public function getPhenophaseDefinitionDetails(){
        $pds = array();
        $results = $this->PhenophaseDefinition->find('all', array(
                'fields' => array(
                            "Definition_ID",
                            "Phenophase_ID", 
                            "Phenophase_Name",
                            "Definition",
                            "Start_Date",
                            "End_Date",
                            "Dataset_ID",
                            "Comments"),
                'conditions' => array(
                    'NOT' => array(
                        'PhenophaseDefinition.Start_Date' => null
                    )
                )
        ));
        
        foreach($results as $definition){
            $p = new PhenoDef();
            $p->definition_id = $definition['PhenophaseDefinition']['Definition_ID'];
            $p->dataset_id = $definition['PhenophaseDefinition']['Dataset_ID'];
            $p->phenophase_id = $definition['PhenophaseDefinition']['Phenophase_ID'];
            $p->phenophase_name = $definition['PhenophaseDefinition']['Phenophase_Name'];
            $p->definition = $definition['PhenophaseDefinition']['Definition'];
            $p->start_date = $definition['PhenophaseDefinition']['Start_Date'];
            $p->end_date = $definition['PhenophaseDefinition']['End_Date'];            
            $p->comments = $definition['PhenophaseDefinition']['Comments'];

            $pds[] = $p;
        }

        $this->set('phenophase_definitions', $pds);
        
        return $pds;
    }    
    
    
    /**
     * Function to return all phenophases for a given set of species. Can return
     * this a few different ways. Can return all phenophases ever used by species
     * in any protocol, or a date can be provided and only phenophases returned
     * are those belonging to the protocol applicable to species for that date.
     *
     * To get all phenophases, must provide returnAll input parameter flag as true.
     * Overrides any date parameters.
     */
    public function getPhenophasesForSpecies($species_info=null){

        
        $return_all = $this->resolveBooleanText($species_info, "return_all");
        
        if(!$this->checkProperty($species_info, "date") && !$return_all){
            $this->set('phenophases', array());
            return;
        }
        
        $date = null;
        if($this->checkProperty($species_info, "date")){
            $date = date("Y-m-d", strtotime($species_info->date));
        }

        $final_results = array();

        if($this->checkProperty($species_info, "species_id")){
            $species_info->species_id = $this->arrayWrap($species_info->species_id);
        }else{
            $this->set('phenophases', array());
            return;
        }
        
        $phenophase_id = null;
        if($this->checkProperty($species_info, "phenophase_id")){
            $phenophase_id = $species_info->phenophase_id;
        }      


        /*
         * Go through each species provided and find the applicable information.
         * This function is sloppy. Should be refactored. Uses native queries
         * simply because didn't understand Cake ORM at the time. Still complex
         * queries.
         */
        foreach($species_info->species_id as $species_id){


            $res = array();

            if(!$return_all){
                /**
                 * First try to find all phenophases within the date range. If no phenophases / protocol
                 * can be found, instead just return the data for the current protocol / phenophases.
                 */
                $res = $this->Phenophase->query("SELECT DISTINCT pp.Phenophase_ID, pd.Phenophase_Name, pp.Short_Name, s.Common_Name, pd.Definition, sspi.Additional_Definition, ppp.Seq_Num, pp.Color, pp.Pheno_Class_ID, pc.Name `Pheno_Class_Name`, pc.Sequence `Pheno_Class_Sequence`, sspi.Abundance_Category, sspi.Extent_Min FROM usanpn2.Species_Protocol sp " .
                                                "LEFT JOIN usanpn2.Protocol p " .
                                                "ON p.Protocol_ID = sp.Protocol_ID " .
                                                "LEFT JOIN usanpn2.Protocol_Phenophase ppp " .
                                                "ON ppp.Protocol_ID = p.Protocol_ID " .
                                                "LEFT JOIN usanpn2.Phenophase pp  " .
                                                "ON pp.Phenophase_ID = ppp.Phenophase_ID  " .
                                                "LEFT JOIN usanpn2.Species_Specific_Phenophase_Information sspi  " .
                                                "ON sspi.Phenophase_ID = pp.Phenophase_ID AND sp.Species_ID = sspi.Species_ID  " .
                                                "AND sspi.Effective_Datetime <= '" . $date . "' " .
                                                "AND (sspi.Deactivation_Datetime IS NULL OR sspi.Deactivation_Datetime >= '" . $date . "') " .
                                                "LEFT JOIN usanpn2.Species s " .
                                                "ON s.Species_ID = sp.Species_ID " .
                                                "LEFT JOIN usanpn2.Phenophase_Definition pd " .
                                                "ON pd.Phenophase_ID = pp.Phenophase_ID " .
                                                "AND '" . $date . "' >= pd.Start_Date " .
                                                "AND (pd.End_Date IS NULL OR pd.End_Date >= '" . $date . "') " .
                                                "LEFT JOIN usanpn2.Pheno_Class pc " .
                                                "ON pc.Pheno_Class_ID = pp.Pheno_Class_ID " .                               
                                                "WHERE sp.Species_ID = " . $species_id . " " .
                                                "AND sp.Start_Date <= '" . $date . "' AND " .
                                                "( (sp.End_Date is null AND sp.Active = 1) OR " .
                                                "(sp.End_Date is not null AND sp.End_Date > '" . $date . "')) " .
                                                "AND pd.Dataset_ID IS NULL " .
                                                (($phenophase_id) ? "AND pp.Phenophase_ID = " . $phenophase_id . " " : "") .  
                                                "ORDER BY ppp.Seq_Num");

                if(count($res) == 0){
                    $res = $this->Phenophase->query("SELECT DISTINCT pp.Phenophase_ID, pd.Phenophase_Name, pp.Short_Name, pd.Definition, sspi.Additional_Definition, ppp.Seq_Num, s.Common_Name, pp.Color, pp.Pheno_Class_ID, pc.Name `Pheno_Class_Name`, pc.Sequence `Pheno_Class_Sequence`,  sspi.Abundance_Category, sspi.Extent_Min FROM usanpn2.Species_Protocol sp " .
                                                    "LEFT JOIN usanpn2.Protocol p " .
                                                    "ON p.Protocol_ID = sp.Protocol_ID " .
                                                    "LEFT JOIN usanpn2.Protocol_Phenophase ppp " .
                                                    "ON ppp.Protocol_ID = p.Protocol_ID " .
                                                    "LEFT JOIN usanpn2.Phenophase pp " .
                                                    "ON pp.Phenophase_ID = ppp.Phenophase_ID " .
                                                    "LEFT JOIN usanpn2.Species_Specific_Phenophase_Information sspi " .
                                                    "ON sspi.Phenophase_ID = pp.Phenophase_ID AND sp.Species_ID = sspi.Species_ID " .
                                                    "AND sspi.Active = 1 " .
                                                    "LEFT JOIN usanpn2.Species s " .
                                                    "ON s.Species_ID = sp.Species_ID " .
                                                    "LEFT JOIN usanpn2.Phenophase_Definition pd " .
                                                    "ON pd.Phenophase_ID = pp.Phenophase_ID " .
                                                    "AND pd.End_Date IS NULL " .                          
                                                    "LEFT JOIN usanpn2.Pheno_Class pc " .
                                                    "ON pc.Pheno_Class_ID = pp.Pheno_Class_ID " .                                   
                                                    "WHERE sp.Species_ID = " . $species_id . " " .
                                                    "AND sp.Active = 1 " .
                                                    "AND pd.Dataset_ID IS NULL " .
                                                    "ORDER BY ppp.Seq_Num");
                }
            }else{
                /**
                 * Alternatively, if using returnAll, find all phenophases. They are not grouped by protocol.
                 */
                $res = $this->Phenophase->query("SELECT DISTINCT pp.Phenophase_ID, pd.Phenophase_Name, pp.Short_Name, pd.Definition, sspi.Additional_Definition, ppp.Seq_Num, s.Common_Name, pp.Color, pp.Pheno_Class_ID, pd.Pheno_Class_Name, pd.Pheno_Class_Sequence, sspi.Abundance_Category, sspi.Extent_Min FROM usanpn2.Species_Protocol sp LEFT JOIN usanpn2.Protocol p ON p.Protocol_ID = sp.Protocol_ID " .
                                                "LEFT JOIN usanpn2.Protocol_Phenophase ppp ON ppp.Protocol_ID = p.Protocol_ID LEFT JOIN usanpn2.Phenophase pp " .
                                                "ON pp.Phenophase_ID = ppp.Phenophase_ID LEFT JOIN usanpn2.Species s ON s.Species_ID = sp.Species_ID " .
                                                "LEFT JOIN usanpn2.Species_Specific_Phenophase_Information sspi ON sspi.Phenophase_ID = pp.Phenophase_ID " .
                                                "AND sp.Species_ID = sspi.Species_ID AND sspi.Effective_Datetime <= sp.Start_Date " .
                                                "AND (sspi.Deactivation_Datetime IS NULL OR sspi.Deactivation_Datetime >= sp.Start_Date) AND sspi.Active = 1 " .
                                                "LEFT JOIN " .
                                                "usanpn2.vw_Phenophases pd " .
                                                "ON pd.Phenophase_ID = pp.Phenophase_ID " .
                                                "WHERE sp.Species_ID = " . $species_id  . " ORDER BY ppp.Seq_Num");

            }


            $pps = array();
            foreach($res as $phenophase){
                $pp = new PhenPhase();
                $pp->phenophase_id = $phenophase["pp"]["Phenophase_ID"];
                $pp->phenophase_name = $this->CleanText->cleanText($phenophase["pd"]["Phenophase_Name"]);
                $pp->phenophase_category = $this->CleanText->cleanText($phenophase["pp"]["Short_Name"]);
                $pp->phenophase_definition = $this->CleanText->cleanText($phenophase["pd"]["Definition"]);
                $pp->phenophase_additional_definition = $this->CleanText->cleanText($phenophase["sspi"]["Additional_Definition"]);
                $pp->seq_num = $phenophase["ppp"]["Seq_Num"];
                $pp->color = $phenophase['pp']['Color'];
                $pp->pheno_class_id = $phenophase['pp']['Pheno_Class_ID'];
                $pp->pheno_class_name = (isset($phenophase['pc']['Pheno_Class_Name'])) ? $phenophase['pc']['Pheno_Class_Name']: $phenophase["pd"]['Pheno_Class_Name'];
                $pp->pheno_class_sequence = (isset($phenophase['pc']['Pheno_Class_Sequence'])) ? $phenophase['pc']['Pheno_Class_Sequence']: $phenophase["pd"]['Pheno_Class_Sequence'];
                $pp->abundance_category = (!empty($phenophase['sspi']['Abundance_Category'])) ? $phenophase['sspi']['Abundance_Category'] : -1;
                $pp->raw_abundance = ($phenophase['sspi']['Extent_Min'] != null) ? true : false;



                $pps[] = $pp;


            }
            $spec = new Spec();
            $spec->species_id = $species_id;
            $spec->species_name = $phenophase["s"]["Common_Name"];
            $spec->phenophases = $pps;
            $final_results[] = $spec;


        }
                       
        $this->set('phenophases', $final_results);
        return $final_results;
    }
    
    
public function getPhenophasesForTaxon($species_info=null){
        $return_all = $this->resolveBooleanText($species_info, "return_all");
        
        if(!$this->checkProperty($species_info, "date") && !$return_all){
            $this->set('phenophases', array());
            return;
        }
        
        $date = null;
        if($this->checkProperty($species_info, "date")){
            $date = date("Y-m-d", strtotime($species_info->date));
        }

        $final_results = array();
        $join_field = "";

        // handle genus separately since it is not stored as genus_id in usanpn2.Species

        if(
                $this->checkProperty($species_info, "family_id") || 
                $this->checkProperty($species_info, "order_id") || 
                $this->checkProperty($species_info, "class_id") || 
                $this->checkProperty($species_info, "genus_id")
          ){
            
            
            if($this->checkProperty($species_info, "family_id")){            
                $species_info->taxon_id = $this->arrayWrap($species_info->family_id);
                $join_field = "Family_ID";
                
            }else if($this->checkProperty($species_info, "order_id")){
                $species_info->taxon_id = $this->arrayWrap($species_info->order_id);
                $join_field = "Order_ID";
                
            }
            else if($this->checkProperty($species_info, "class_id")){
                $species_info->taxon_id = $this->arrayWrap($species_info->class_id);
                $join_field = "Class_ID";
                
            }
            else if($this->checkProperty($species_info, "genus_id")){
                $species_info->taxon_id = $this->arrayWrap($species_info->genus_id);
                $join_field = "Genus_ID";
                
            }
            
        }else{
            $this->set('phenophases', array());
            return;
        }
        

        /*
         * Go through each species provided and find the applicable information.
         * This function is sloppy. Should be refactored. Uses native queries
         * simply because didn't understand Cake ORM at the time. Still complex
         * queries.
         * 
         * If we have taxon_id (such as class_id, family_id, or order_id), otherwise, if we have taxon_non_id (such as genus_id), we do another query.
         */
        if(isset($species_info->taxon_id)){
            foreach($species_info->taxon_id as $taxon_id){


                $res = array();
    
                if(!$return_all){
                    /**
                     * First try to find all phenophases within the date range. If no phenophases / protocol
                     * can be found, instead just return the data for the current protocol / phenophases.
                     */
                    $query_string = "SELECT DISTINCT pp.Phenophase_ID, pd.Phenophase_Name, pp.Short_Name, Species_Taxon.Name, Species_Taxon.Taxon_ID, pd.Definition, ppp.Seq_Num, pp.Color, pp.Pheno_Class_ID, pc.Name `Pheno_Class_Name`,pc.Sequence `Pheno_Class_Sequence`, pd.Definition_ID " .
                    "FROM usanpn2.Species s " .
                    "LEFT JOIN usanpn2.Species_Taxon " .
                    "ON Species_Taxon.Taxon_ID = s.{$join_field} " .
                    "LEFT JOIN usanpn2.Species_Protocol sp " .
                    "ON sp.Species_ID = s.Species_ID " .
                    "LEFT JOIN usanpn2.Protocol_Phenophase ppp " .
                    "ON ppp.Protocol_ID = sp.Protocol_ID " .
                    "LEFT JOIN usanpn2.Phenophase pp " .
                    "ON pp.Phenophase_ID = ppp.Phenophase_ID " .
                    "LEFT JOIN usanpn2.Phenophase_Definition pd " .
                    "ON pd.Phenophase_ID = pp.Phenophase_ID " .
                        "AND '{$date}' >= pd.Start_Date " .
                        "AND (pd.End_Date IS NULL OR pd.End_Date >= '{$date}') " .
                    "LEFT JOIN usanpn2.Pheno_Class pc " .
                    "ON pc.Pheno_Class_ID = pp.Pheno_Class_ID " .
                    "WHERE s.{$join_field} = {$taxon_id} " .
                        "AND sp.Start_Date <= '{$date}' " .
                        "AND ( " .
                            "(sp.End_Date is null AND sp.Active = 1) " .
                            "OR (sp.End_Date is not null AND sp.End_Date > '{$date}')" .
                            ") " .
                        "AND pd.Dataset_ID IS NULL " .
                    "ORDER BY ppp.Seq_Num";
                    $res = $this->Phenophase->query($query_string);
    
    
                    if(count($res) == 0){
                        $query_string = "SELECT DISTINCT pp.Phenophase_ID, pd.Phenophase_Name, pp.Short_Name, Species_Taxon.Name, Species_Taxon.Taxon_ID, pd.Definition, ppp.Seq_Num, pp.Color, pp.Pheno_Class_ID, pc.Name `Pheno_Class_Name`,pc.Sequence `Pheno_Class_Sequence`, pd.Definition_ID " .
                        "FROM usanpn2.Species s " .
                        "LEFT JOIN usanpn2.Species_Taxon " .
                        "ON Species_Taxon.Taxon_ID = s.{$join_field} " .
                        "LEFT JOIN usanpn2.Species_Protocol sp " .
                        "ON sp.Species_ID = s.Species_ID " .
                        "LEFT JOIN usanpn2.Protocol_Phenophase ppp " .
                        "ON ppp.Protocol_ID = sp.Protocol_ID " .
                        "LEFT JOIN usanpn2.Phenophase pp " .
                        "ON pp.Phenophase_ID = ppp.Phenophase_ID " .
                        "LEFT JOIN usanpn2.Phenophase_Definition pd " .
                        "ON pd.Phenophase_ID = pp.Phenophase_ID " .
                            "AND pd.End_Date IS NULL " .
                        "LEFT JOIN usanpn2.Pheno_Class pc " .
                        "ON pc.Pheno_Class_ID = pp.Pheno_Class_ID " .
                        "WHERE s.{$join_field} = {$taxon_id} " .
                            "AND sp.Active = 1 " .
                            "AND pd.Dataset_ID IS NULL " .
                        "ORDER BY ppp.Seq_Num";
                        $res = $this->Phenophase->query($query_string);
                    }
                }else{
                    /**
                     * Alternatively, if using returnAll, find all phenophases. They are not grouped by protocol.
                     */
  
                    $query_string = "SELECT DISTINCT pp.Phenophase_ID, pd.Phenophase_Name, pp.Short_Name, Species_Taxon.Name, Species_Taxon.Taxon_ID, pd.Definition, ppp.Seq_Num, pp.Color, pp.Pheno_Class_ID, pc.Name `Pheno_Class_Name`,pc.Sequence `Pheno_Class_Sequence`, pd.Definition_ID " .
                    "FROM usanpn2.Species s " .
                    "LEFT JOIN usanpn2.Species_Taxon " .
                    "ON Species_Taxon.Taxon_ID = s.{$join_field} " .
                    "LEFT JOIN usanpn2.Species_Protocol sp " .
                    "ON sp.Species_ID = s.Species_ID " .
                    "LEFT JOIN usanpn2.Protocol_Phenophase ppp " .
                    "ON ppp.Protocol_ID = sp.Protocol_ID " .
                    "LEFT JOIN usanpn2.Phenophase pp " .
                    "ON pp.Phenophase_ID = ppp.Phenophase_ID " .
                    "LEFT JOIN usanpn2.Phenophase_Definition pd " .
                    "ON pd.Phenophase_ID = pp.Phenophase_ID " .
                    "LEFT JOIN usanpn2.Pheno_Class pc " .
                    "ON pc.Pheno_Class_ID = pp.Pheno_Class_ID " .
                    "WHERE s.{$join_field} = {$taxon_id} " .
                    "ORDER BY ppp.Seq_Num";
                    
                    $res = $this->Phenophase->query($query_string);               
                }
                
                
    
    
                $pps = array();
                foreach($res as $phenophase){
                    $pp = new PhenPhase();
                    $pp->phenophase_id = $phenophase["pp"]["Phenophase_ID"];
                    $pp->phenophase_name = $this->CleanText->cleanText($phenophase["pd"]["Phenophase_Name"]);
                    $pp->phenophase_category = $this->CleanText->cleanText($phenophase["pp"]["Short_Name"]);
                    $pp->phenophase_definition = $this->CleanText->cleanText($phenophase["pd"]["Definition"]);
                    $pp->seq_num = $phenophase["ppp"]["Seq_Num"];
                    $pp->color = $phenophase['pp']['Color'];
                    $pp->pheno_class_id = $phenophase['pp']['Pheno_Class_ID'];
                    $pp->pheno_class_name = (isset($phenophase['pc']['Pheno_Class_Name'])) ? $phenophase['pc']['Pheno_Class_Name']: $phenophase["pd"]['Pheno_Class_Name'];
                    $pp->pheno_class_sequence = (isset($phenophase['pc']['Pheno_Class_Sequence'])) ? $phenophase['pc']['Pheno_Class_Sequence']: $phenophase["pd"]['Pheno_Class_Sequence'];

                    $pp->phenophase_definition_id = $phenophase['pd']['Definition_ID'];
    
                    $pps[] = $pp;
    
    
                }
                $spec = new Spec();
                $join_field = strtolower($join_field);
                $name_field = str_replace("id", "name", $join_field);
                $spec->$join_field = $phenophase["Species_Taxon"]["Taxon_ID"];
                $spec->$name_field = $phenophase["Species_Taxon"]["Name"];
                $spec->phenophases = $pps;
                $final_results[] = $spec;
    
    
            }
        } 

                       
        $this->set('phenophases', $final_results);
        return $final_results;
    }    
    

    public function getAbundanceCategory($category_info=null){
        
        if(!$this->checkProperty($category_info, "category_id")){
            $this->set('category', new stdClass());
            return null;
        }
        $category_id = $category_info->category_id;

        $result = $this->AbundanceCategory->find('first', array(
           'conditions' => array(
               'Abundance_Category_ID' => $category_id
           )
        ));

        if(empty($result)){
            $this->set('category', new stdClass());
            return null;
        }
        $category = new AbundanceCat();
        $category->category_id = $result['AbundanceCategory']['Abundance_Category_ID'];
        $category->category_name = $result['AbundanceCategory']['Name'];
        $category->category_description = $result['AbundanceCategory']['Description'];
        $values = array();
        foreach($result['abundance_category_2_abundance_values'] as $cat_value){
            $val = new AbundanceValue();
            $val->value_id = $cat_value['Abundance_Value_ID'];
            $val->value_description = $cat_value['Abundance_Value'];
            $val->value_name = $cat_value['Short_Name'];

            $values[] = $val;
        }

        $category->category_values = $values;
        $this->set('category', $category);
        return $category;

    }


    public function getAbundanceCategories($params=null){

        $this->AbundanceCategory->unbindModel(array('hasAndBelongsToMany' => array('abundance_category_2_abundance_values')));
        $categories = array();

        $results = $this->AbundanceCategory->find('all', array(
           'fields' => array(
               'Abundance_Category_ID'
           )
        ));

        foreach($results as $result){
            $id = $result['AbundanceCategory']['Abundance_Category_ID'];
            $package = new stdClass();
            $package->category_id = $id;
            $response = new stdClass();
            $response = $this->getAbundanceCategory($package);
            $categories[] = $response;
        }

        $this->set('categories', $categories);
        
        if($this->checkProperty($params, "pretty") && $params->pretty == 1){
            $this->set('pretty', true);
        }


        return $categories;
    }
    
    public function getPhenoClasses($params=null){
        
        $this->PhenoClass->unbindModel(array('hasMany' => array('phenoclass2phenophase')));
        $pheno_classes = array();

        $results = $this->PhenoClass->find('all', array(
        ));

        foreach($results as $result){
            
            $pheno_class = new stdClass();
            $pheno_class->id = $result['PhenoClass']['Pheno_Class_ID'];
            $pheno_class->name = $result['PhenoClass']['Name'];
            $pheno_class->description = $result['PhenoClass']['Description'];
            $pheno_class->sequence = $result['PhenoClass']['Sequence'];
            $pheno_classes[] = $pheno_class;
        }

        $this->set('pheno_classes', $pheno_classes);

        return $pheno_classes;        
    }
    
    public function getPhenoClass($params=null){
        
        if(!$this->checkProperty($params, "pheno_class_id")){
            $this->set('pheno_class', new stdClass());
            return null;
        }
        
        $result = $this->PhenoClass->find('all', array(
            'conditions' => array(
                'Pheno_Class_ID' => $params->pheno_class_id
            )
        ));
        
        $pheno_class = new stdClass();
        
        if($result && !empty($result)){
            $pheno_class->id = $result[0]['PhenoClass']['Pheno_Class_ID'];
            $pheno_class->name = $result[0]['PhenoClass']['Name'];
            $pheno_class->description = $result[0]['PhenoClass']['Description'];
            $pheno_class->sequence = $result[0]['PhenoClass']['Sequence'];
            
            $phenophases = array();
            
            foreach( $result[0]['phenoclass2phenophase'] as $pp){
                $phenophase = new stdClass();
                
                $phenophase->phenophase_id = $pp['Phenophase_ID'];
                $phenophase->short_name = $pp['Short_Name'];
                $phenophase->description = $pp['Description'];
                $phenophase->action = $pp['Preferred_Action'];
                
                $phenophases[] = $phenophase;
            }
            
            $pheno_class->phenophases = $phenophases;
                
        }
            
        $this->set('pheno_class', $pheno_class);
        
        return $pheno_class;

    }



    /**
     * Function used to find the last time any updates were made to the phenophase table.
     * Uses update table to store this information. Table must be manually maintained.
     * This is useful for clients wishing to cache phenophase data. Can query this function
     * with smaller payload to see if changes are made and only then re-fetch all phenophases.
     */
    public function getPhenophasesUpdateDate(){
        $db_date = $this->UpdateDate->find('first', array(
            'conditions' => array(
                'Table_Name' => array('phenophase', 'species_protocol', 'species_specific_phenophase_information')
            ),
            'order' => array('Update_Date' => 'DESC')
        ));
        
        $obj = new UpDate();
        $obj->update_date = $db_date["UpdateDate"]["Update_Date"];
 
        $this->set('update_date', $obj);

        return $obj;
    }
    
    /**
     * Function used to find the last time any updates were made to the ANY relevant tables.
     * Uses update table to store this information.
     * This is useful for clients wishing to cache phenophase data. Can query this function
     * with smaller payload to see if changes are made and only then re-fetch all phenophases.
     */
    public function getAnyUpdateDate(){
        $db_date = $this->UpdateDate->find('first', array(
            'order' => array('Update_Date' => 'DESC')
        ));
        
        $obj = new UpDate();
        $obj->update_date = $db_date["UpdateDate"]["Update_Date"];
 
        $this->set('update_date', $obj);

        return $obj;
    }    
    
    public function getSpeciesProtocolDetails(){
        
        $sp_prs = array();

        $results = $this->SpeciesProtocol->find('all');

        foreach($results as $result){
            
            $package = new SpecProtocol();
            $package->dataset_id = $result['SpeciesProtocol']['Dataset_ID'];
            $package->species_id = $result['SpeciesProtocol']['Species_ID'];
            $package->protocol_id = $result['SpeciesProtocol']['Protocol_ID'];
            $package->start_date = $result['SpeciesProtocol']['Start_Date'];
            $package->end_date = $result['SpeciesProtocol']['End_Date'];
            
            
          
            $sp_prs[] = $package;
        }

        
        $this->set('species_protocols', $sp_prs);


        return $sp_prs;        
    }
    
    public function getProtocolDetails(){
        
        $protocols = array();

        $results = $this->VwProtocolDetails->find('all');

        foreach($results as $result){
            
            $package = new Proto();
            $package->protocol_id = $result['VwProtocolDetails']['Protocol_ID'];
            $package->protocol_name = $result['VwProtocolDetails']['Protocol_Name'];
            $package->primary_name = $result['VwProtocolDetails']['Primary_Name'];
            $package->secondary_name = $result['VwProtocolDetails']['Secondary_Name'];
            $package->phenophase_list = str_replace(",", ", ", $result['VwProtocolDetails']['Phenophases']);
            $package->protocol_comments = $result['VwProtocolDetails']['Comment'];
                      
            $protocols[] = $package;
        }

        $this->set('protocols', $protocols);


        return $protocols;        
    }
    
    
    public function getSecondaryPhenophaseDetails($params=null){
        
        $sspis = array();

        $results = $this->SpeciesSpecificPhenophaseInformation->find('all');

        foreach($results as $result){
            
            $package = new AddDef();
            $package->sspi_id = $result['SpeciesSpecificPhenophaseInformation']['Species_Specific_Phenophase_ID'];
            $package->phenophase_id = $result['SpeciesSpecificPhenophaseInformation']['Phenophase_ID'];
            $package->species_id = $result['SpeciesSpecificPhenophaseInformation']['Species_ID'];
            $package->additional_definition = strip_tags($result['SpeciesSpecificPhenophaseInformation']['Additional_Definition']);
            $package->abundance_category = $result['SpeciesSpecificPhenophaseInformation']['Abundance_Category'];
            $package->effective_datetime = $result['SpeciesSpecificPhenophaseInformation']['Effective_Datetime'];
            $package->deactivation_datetime = $result['SpeciesSpecificPhenophaseInformation']['Deactivation_Datetime'];
            
            
            $sspis[] = $package;
        }

        $this->set('sspis', $sspis);
        
        if($this->checkProperty($params, "pretty") && $params->pretty == 1){
            $this->set('pretty', true);
        }        


        return $sspis;        
    }
    
    
    public function getAbundanceDetails(){
        
        $ads = array();

        $results = $this->VwAbundanceDetails->find('all');

        foreach($results as $result){
            
            $package = new AbunDetail();
            $package->abundance_category_id = $result['VwAbundanceDetails']['Abundance_Category_ID'];
            $package->abundance_category_name = $result['VwAbundanceDetails']['Abundance_Category_Name'];
            $package->abundance_category_description = strip_tags($result['VwAbundanceDetails']['Abundance_Category_Description']);
            $package->intensity_value_options = $result['VwAbundanceDetails']['Intensity_Value_Options'];

            
            $ads[] = $package;
        }

        $this->set('abundance_details', $ads);


        return $ads;        
    }    
    
    
} 