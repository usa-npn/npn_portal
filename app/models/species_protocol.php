<?php
class SpeciesProtocol extends Appmodel{

	var $useTable = 'Species_Protocol';
	
	var $belongsTo = array(
            
		"speciesprotocol2Species" =>
			array(
				"className" => "Species",
				"foreignKey" => "Species_ID"
			),
		"speciesprotocol2Protocol" =>
			array(
				"className" => "Protocol",
				"foreignKey" => "Protocol_ID"
			)
	);
	
	

}
