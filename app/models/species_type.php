<?php
class SpeciesType extends Appmodel{

	var $useTable = 'Species_Type';
	var $primaryKey = 'Species_Type_ID';
	var $displayField = 'Species_Type';
	

        var $hasAndBelongsToMany  = array(
            
                "speciesType2species" =>
                            array(
                                    "className" => "Species",
                                    "joinTable" => "Species_Species_Type",
                                    "foreignKey" => "Species_Type_ID",
                                    "associationForeignKey" => "Species_ID",
                                    "unique" => true
                            )
        );

}
