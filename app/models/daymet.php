<?php
class Daymet extends Appmodel{

	var $useTable = 'Daymet';
	var $primaryKey = 'Daymet_ID';
	var $displayField = 'Daymet_ID';

        
	

	var $hasMany = array(

                "daymet2daymetdata" =>
                        array(
                                "className" => "DaymetData",
                                "foreignKey" => "Daymet_ID"
                        )
                 
	);
	
}
