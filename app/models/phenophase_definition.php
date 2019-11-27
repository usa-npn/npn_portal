<?php
class PhenophaseDefinition extends Appmodel{

	var $useTable = 'Phenophase_Definition';
	var $primaryKey = 'Definition_ID';
	var $displayField = 'Phenophase_Name';
        
        
        var $belongsTo = array(
                
                "phenophasedefinition2phenophase" => array(
                            "className" => "Phenophase",
                            "foreignKey" => "Phenophase_ID"
                )
        );

}
