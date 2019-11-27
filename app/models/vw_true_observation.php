<?php
class VwTrueObservation extends Appmodel{

	var $useTable = 'vw_True_Observations';
	var $primaryKey = 'Observation_ID';
	var $displayField = 'Observation_ID';


	var $belongsTo = array(
               "vtTrueObs2Ssi" =>
                        array(
                                "className" => "StationSpeciesIndividual",
                                "foreignKey" => "Individual_ID"
                        )
	);


}
