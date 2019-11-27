<?php
class Species extends Appmodel{

	var $useTable = 'Species';
	var $primaryKey = 'Species_ID';
	var $displayField = 'Common_Name';

        var $actsAs = array(
            'Containable'
        );
        
	
        
        var $hasMany = array(
            
                "species2StationSpeciesIndividual" =>
                            array(
                                    "className" => "StationSpeciesIndividual",
                                    "foreignKey" => "Species_ID"
                            ),
                "species2SpeciesProtocol" =>
                            array(
                                    "className" => "SpeciesProtocol",
                                    "foreignKey" => "Species_ID"
                            ),
                "species2sspi" =>
                            array(
                                    "className" => "SpeciesSpecificPhenophaseInformation",
                                    "foreignKey" => "Species_ID"
                            )
        );

        var $hasAndBelongsToMany  = array(
            
                "species2speciesType" =>
                            array(
                                    "className" => "SpeciesType",
                                    "joinTable" => "Species_Species_Type",
                                    "associationForeignKey" => "Species_Type_ID",
                                    "foreignKey" => "Species_ID",
                                    "unique" => true
                            ),
                "species2network" =>
                            array(
                                    "className" => "Network",
                                    "joinTable" => "Network_Species",
                                    "associationForeignKey" => "Network_ID",
                                    "foreignKey" => "Species_ID",
                                    "unique" => true
                                )
                                                    
        );

}
