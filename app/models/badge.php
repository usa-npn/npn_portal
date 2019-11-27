<?php
class Badge extends Appmodel{

	var $useTable = 'Badge';
	var $primaryKey = 'Badge_ID';
	var $displayField = 'Name_Functional';

        
	

        
        var $hasAndBelongsToMany  = array(
            

                "badge2badgehook" =>
                            array(
                                    "className" => "BadgeHook",
                                    "joinTable" => "Badge_Badge_Hook",
                                    "foreignKey" => "Badge_ID",
                                    "associationForeignKey" => "Hook_ID",
                                    "unique" => true
                                )
                                                    
        );

	
}
