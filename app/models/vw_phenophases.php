<?php
class VwPhenophases extends Appmodel{

	var $useTable = 'vw_Phenophases';
	var $primaryKey = 'Phenophase_ID';
	var $displayField = 'Phenophase_Name';
        
        
        var $hasMany = array(
                
                "phenophase2protocolPhenophase" => array(
                            "className" => "ProtocolPhenophase",
                            "foreignKey" => "Phenophase_ID"
                ),
                "phenophase2sspi" => array(
                            "className" => "SpeciesSpecificPhenophaseInformation",
                            "foreignKey" => "Phenophase_ID"
                )
        );

	
}