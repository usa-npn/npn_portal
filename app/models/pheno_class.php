<?php
class PhenoClass extends Appmodel{

	var $useTable = 'Pheno_Class';
	var $primaryKey = 'Pheno_Class_ID';
	var $displayField = 'Name';
        
        
        var $hasMany = array(
                
                "phenoclass2phenophase" => array(
                            "className" => "Phenophase",
                            "foreignKey" => "Pheno_Class_ID"
                )
        );

	
}