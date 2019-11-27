<?php

class MetaData{}

/**
 * Controller for getting information about NPN networks.
 */
class MetadataController extends AppController{
    
    
    public $uses = array('MetadataField');
    
    public $components = array(
    	'Soap' => array(
        	'wsdl' => 'NPN', //the file name in the view folder
            	'action' => 'service', //soap service method / handler
        ),
        'RequestHandler'
    );
    
    private $allowed_types = array('individual_summarized', 'raw', 'site_summarized', 'magnitude', 'dataset', 'person', 
        'station', 'plant', 'protocol', 'species_protocol', 'phenophase', 'phenophase_definition', 'sspi', 'intensity', 'observation_group');

    public function soap_wsdl(){
    	//will be handled by SoapComponent
    }
	
	
	
    public function soap_service(){
    //no code here
    }
    
    
    public function getMetadataFields($params=null){
        
        
        
        
        $conditions = array();                
        
        $this->addBooleanCondition("quality_check", "Quality_Check", $params, $conditions);
        $this->addBooleanCondition("climate", "Climate", $params, $conditions);
        $this->addBooleanCondition("required", "Required", $params, $conditions);

        
        if($this->checkProperty($params, "type") && in_array($params->type, $this->allowed_types)){
            $conditions['Type'] = $params->type;
        }
        
        $mdfs = array();
        
        
        $mdfr = $this->MetadataField->find('all', array(
            'conditions' => $conditions,
            'order' => array(
                'Type' => 'ASC',
                'Seq_Num' => 'ASC'
                )
            )
        );
        
        foreach($mdfr
                as $md){
     
            $obj = new MetaData();
            $obj->metadata_field_id = $md["MetadataField"]["Metadata_Field_ID"];
            $obj->field_name = $md["MetadataField"]["Field_Name"];
            $obj->field_description = $md["MetadataField"]["Field_Description"];
            $obj->seq_num = $md["MetadataField"]["Seq_Num"];
            $obj->type = $md["MetadataField"]["Type"];
            $obj->quality_check = $md["MetadataField"]["Quality_Check"];
            $obj->climate = $md["MetadataField"]["Climate"];
            $obj->required = $md["MetadataField"]["Required"];
            $obj->machine_name = $md["MetadataField"]["Machine_Name"];
            
            $cv_string = "";
            $i=0;
            foreach($md["Controlled_Values"] as $cv){
                $cv_string .= ($i++ != 0) ? "|" : "";
                $cv_string .= $cv["Value"];
            }
            
            $obj->controlled_values = $cv_string;

            $mdfs[] = $obj;
            
        }

        $this->set('metadata_fields', $mdfs);
        return $mdfs;        
    }
    
    
    private function addBooleanCondition($param_name, $field_name, $params, &$conditions){

        if($this->checkProperty($params, $param_name)){
            if($this->resolveBooleanText($params, $param_name)){
                $conditions[$field_name] = 1;
            }
        }        
    }
    


    
} 

