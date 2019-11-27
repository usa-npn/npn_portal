<?php
class Network extends Appmodel{

	var $useTable = 'Network';
	var $primaryKey = 'Network_ID';
	var $displayField = 'Name';

        
	
	var $hasMany = array(
		"network2networkperson" =>
			array(
				"className" => "NetworkPerson",
				"foreignKey" => "Network_ID"
			),
                "network2networkstation" =>
                        array(
                                "className" => "NetworkStation",
                                "foreignKey" => "Station_ID"
                        )
	);
        
        var $hasAndBelongsToMany  = array(
            

                "network2species" =>
                            array(
                                    "className" => "Species",
                                    "joinTable" => "Network_Species",
                                    "foreignKey" => "Network_ID",
                                    "associationForeignKey" => "Species_ID",
                                    "unique" => true
                                )
                                                    
        );

	
}
