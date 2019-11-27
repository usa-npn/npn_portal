<?php
class BadgePerson extends Appmodel{

	var $useTable = 'Badge_Person';
	var $primaryKey = 'Badge_Person_ID';
	var $displayField = 'Badge_ID';

        


        
	var $belongsTo = array(

                "badgeperson2badge" =>
                        array(
                                "className" => "Badge",
                                "foreignKey" => "Badge_ID"
                        )
                 
	);

	
}
