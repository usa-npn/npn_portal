<?php
class VwSpeciesAllNames extends Appmodel{

	var $useTable = 'vw_Species_All_Names';
	var $primaryKey = 'Species_ID';
	var $displayField = 'Species_ID';


	var $hasOne = array(
		"speciesAllNames2species" =>
			array(
				"className" => "Species",
				"foreignKey" => "Species_ID",
			)
	);


}