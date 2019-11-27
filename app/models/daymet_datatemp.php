<?php
class DaymetDatatemp extends Appmodel{

	var $useTable = 'Daymet_Data_Temp';
	var $primaryKey = 'Daymet_Data_ID';
	var $displayField = 'Daymet_Data_ID';

        
	

        
	var $belongsTo = array(
		"daymetdata2daymet" =>
			array(
				"className" => "Daymettemp",
				"foreignKey" => "Daymet_ID"
			)
	);

	
}
