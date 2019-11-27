<?php
class Session extends Appmodel{

	var $useTable = 'Session';
	var $primaryKey = 'Session_ID';
	var $displayField = 'Create_DateTime';
        
        
        
        
	var $belongsTo = array(
		"session2Person" =>
			array(
				"className" => "Person",
                                "foreignKey" => "Person_ID"
			)
	);
        
        
        var $hasMany = array(
                "session2Submission" =>
                        array(
                                "className" => "Submission",
                                "foreignKey" => "Session_ID"
                        )
        );
        
        

        
	
}
