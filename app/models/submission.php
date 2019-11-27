<?php
class Submission extends Appmodel{

	var $useTable = 'Submission';
	var $primaryKey = 'Submission_ID';
	var $displayField = 'Submission_DateTime';

        var $actsAs = array( 'Containable' );

        
        
        
        
	var $hasOne = array(
		"submission2Session" =>
			array(
				"className" => "Session",
				"foreignKey" => "Session_ID"
			)
	);
        
        
        var $hasMany = array(
                "submission2Observation" =>
                        array(
                                "className" => "Observation",
                                "foreignKey" => "Submission_ID"
                        )
        );
        
        

        
	
}
