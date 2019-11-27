<?php
class Phenophase extends Appmodel{

	var $useTable = 'Phenophase';
	var $primaryKey = 'Phenophase_ID';
	var $displayField = 'Phenophase_Name';
        
        
        var $hasMany = array(
                
                "phenophase2Observation" => array(
                            "className" => "Observation",
                            "foreignKey" => "Phenophase_ID"
                ),
                "phenophase2protocolPhenophase" => array(
                            "className" => "ProtocolPhenophase",
                            "foreignKey" => "Phenophase_ID"
                ),
                "phenophase2sspi" => array(
                            "className" => "SpeciesSpecificPhenophaseInformation",
                            "foreignKey" => "Phenophase_ID"
                ),
                "phenophase2phenophaseDefinition" => array(
                            "className" => "PhenophaseDefinition",
                            "foreignKey" => "Phenophase_ID",
                            "order" => array(
                                "PhenophaseDefinition.Start_Date" => "DESC"
                            )
                )
        );

	
}
