<?php
class SpeciesSpecificPhenophaseInformation extends Appmodel{

	var $useTable = 'Species_Specific_Phenophase_Information';
	var $displayField = 'Additional_Definition';
        
        
        var $belongsTo = array(
                
                "sspi2Phenophase" => array(
                            "className" => "Phenophase",
                            "foreignKey" => "Phenophase_ID"
                ),
                "sspi2Species" => array(
                            "className" => "Species",
                            "foreignKey" => "Species_ID"
                ),
                'sspi2AbundanceCategory' => array(
                            "className" => "AbundanceCategory",
                            "foreignKey" => "Abundance_Category"
                )
        );
	
}
