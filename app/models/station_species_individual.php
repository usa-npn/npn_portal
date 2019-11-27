<?php
class StationSpeciesIndividual extends Appmodel{

	var $useTable = 'Station_Species_Individual';
	var $primaryKey = 'Individual_ID';
	var $displayField = 'Individual_UserStr';
        
	var $belongsTo = array(
            
		"ssi2Station" =>
			array(
				"className" => "Station",
				"foreignKey" => "Station_ID"
			),
                "ssi2Species" =>
                        array(
                                "className" => "Species",
                                "foreignKey" => "Species_ID"
                        )
                         
                         
	);
        
        
        var $hasMany = array(
            
                "stationspeciesindividual2Observation" =>
                            array(
                                    "className" => "Observation",
                                    "foreignKey" => "Individual_ID"
                            )
        );

        
	var $validate = array(
            
		"Individual_UserStr" => array(
                    
			"stationNameRule1" => array(
				"rule" => "/^[A-Za-z0-9' \.-]+$/",
				"message" => "Individual name can only contain letters, numbers, whitespace, apostrophes, and fullstops.",
			),
			"stationNameRule2" => array(
				"rule" => array("maxlength", 48),
				"message" => "Individual Name cannot be greater than 48 characters long."
			),
			"stationNameRue3" => array(
				"rule" => array("notEmpty"),
				"message" => "Individual Name must be provided"
			) 
			
		
		)
		
	);
        

        
	
}
