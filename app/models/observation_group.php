<?php
class ObservationGroup extends Appmodel{

	var $useTable = 'Observation_Group';
	var $primaryKey = 'Observation_Group_ID';
	var $displayField = 'Observation_Group_ID';
        
        
        
       
        var $hasMany = array(
            
                "observationgroup2Observation" =>
                        array(
                                "className" => "Observation",
                                "foreignKey" => "Observation_Group_ID"
                        )
        );
        
        

        
	
}