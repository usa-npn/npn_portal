<?php
class NetworkSpecies extends Appmodel{

	var $useTable = 'Network_Species';
	
	var $belongsTo = array(
		"networkspecies2species" =>
			array(
				"className" => "Species",
				"foreignKey" => "Species_ID"
			),
		"networkspecies2network" =>
			array(
				"className" => "Network",
				"foreignKey" => "Network_ID"
			)
	);
	
	

}
