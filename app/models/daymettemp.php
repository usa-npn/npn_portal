<?php
class Daymettemp extends Appmodel{

	var $useTable = 'Daymet_Temp';
	var $primaryKey = 'Daymet_ID';
	var $displayField = 'Daymet_ID';

        
	

	var $hasMany = array(

                "daymet2daymetdata" =>
                        array(
                                "className" => "DaymetDatatemp",
                                "foreignKey" => "Daymet_ID"
                        )
                 
	);
	
}
