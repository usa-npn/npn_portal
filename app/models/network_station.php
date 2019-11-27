<?php
class NetworkStation extends Appmodel{

	var $useTable = 'Network_Station';
	
	var $belongsTo = array(
		"networkstation2station" =>
			array(
				"className" => "Station",
				"foreignKey" => "Station_ID"
			),
		"networkstation2network" =>
			array(
				"className" => "Network",
				"foreignKey" => "Network_ID"
			)
	);
	
	

}
