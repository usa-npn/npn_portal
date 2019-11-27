<?php
class BadgeHook extends Appmodel{

	var $useTable = 'Badge_Hook';
	var $primaryKey = 'Hook_ID';
	var $displayField = 'Name_Functional';

        
	

        
        var $hasAndBelongsToMany  = array(
            

                "badgehook2badge" =>
                            array(
                                    "className" => "Badge",
                                    "joinTable" => "Badge_Badge_Hook",
                                    "foreignKey" => "Hook_ID",
                                    "associationForeignKey" => "Badge_ID",
                                    "unique" => true
                                )
                                                    
        );

	
}
