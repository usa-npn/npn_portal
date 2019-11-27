<?php
class Protocol extends Appmodel{

	var $useTable = 'Protocol';
	var $primaryKey = 'Protocol_ID';
	var $displayField = 'Protocol_Name';
        
	var $hasMany = array(
		"protocol2SpeciesProtocol" =>
			array(
				"className" => "SpeciesProtocol",
				"foreignKey" => "Protocol_ID"
			),
                "protocol2protocolPhenophase" =>
                        array(
                                "className" => "ProtocolPhenophase",
                                "foreignKey" => "Protocol_ID"
                        )
	);
        

        
	
}
