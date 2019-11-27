<?php
class DaymetData extends Appmodel{

	var $useTable = 'Daymet_Data';
	var $primaryKey = 'Daymet_Data_ID';
	var $displayField = 'Daymet_Data_ID';

        
	

        
	var $belongsTo = array(
		"daymetdata2daymet" =>
			array(
				"className" => "Daymet",
				"foreignKey" => "Daymet_ID"
			)
	);

	
}
