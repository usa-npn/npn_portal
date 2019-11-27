<?php
class Observation extends Appmodel{

	var $useTable = 'Observation';
	var $primaryKey = 'Observation_ID';
	var $displayField = 'Observation_ID';
        var $actsAs = array('Containable');
        
        
        
	var $hasOne = array(
                "observation2phenophase" =>
                        array(
                                "className" => "Phenophase",
                                "foreignKey" => "Phenophase_ID"
                        )
	);

        var $belongsTo = array(
            
                "observation2submission" =>
                        array(
                                "className" => "Submission",
                                "foreignKey" => "Submission_ID"
                        ),
                "observation2person" =>
                        array(
                                "className" => "Person",
                                "foreignKey" => "Observer_ID"
                        ),
                "observation2StationSpeciesIndividual" =>
			array(
				"className" => "StationSpeciesIndividual",
				"foreignKey" => "Individual_ID"
			),
                "observation2ObservationGroup" =>
                        array(
                                "className" => "ObservationGroup",
                                "foreignKey" => "Observation_Group_ID"
                        )
        );


        var $hasAndBelongsToMany  = array(


                "observation2dataset" =>
                            array(
                                    "className" => "Dataset",
                                    "joinTable" => "Dataset_Observation",
                                    "foreignKey" => "Observation_ID",
                                    "associationForeignKey" => "Dataset_ID",
                                    "unique" => true
                                )

        );
 
        
	var $validate = array(
            
		"Observation_Date" => array(
                    
			"observationDateRule1" => array(
				"rule" => "dateValid",
				"message" => "Observation Date is not valid.",
			),
                        "observationDateRule2" => array(
                                "rule" => "observationNotFuture",
                                "message" => "Observation Date cannot be set in the future."
                        )
 			
		
		),
            
                "Observation_Extent" => array(
                        "observationExtentRule1" => array(
                                "rule" => "extentValid",
                                "message" => "Observation Extent must be either 1,0, or -1."
                        )
                )

		
	);
        
        
        function extentValid($check){
            $check = array_values($check);
            $check = $check[0];
            $isInt=preg_match('/^\s*([0-9]+|-[0-9]+)\s*$/', $check, $int_val);
            $check=(int)$check;
            return ($isInt && ($check === 0 || $check === 1 || $check === -1)) ? true : false;
        }
        
        function dateValid($check){
            
            $pieces = explode(" ", $check['Observation_Date']);
            if(count($pieces) == 1){
                $pieces[] = "00:00:00";
            }
            $datetime = implode(" ", $pieces);
            return preg_match("/(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})/", $datetime);
            
        }
        
        /**
         * This custom validator will check to make sure that incoming observations are
         * not being set in the future. The tricky part about this is making sure
         * that this validation pays respect to the timezone that the observation
         * is being made in.
         * 
         * @param type $check date to check
         * @return boolean true if date is in the past, false if in the future
         */
        function observationNotFuture($check){
            
            /**
             * First we must get some information. Use model associations to get
             * the station, and hence the state and timezone of the location.
             */
            App::import('model','StationSpeciesIndividual');
            $ssi_model = new StationSpeciesIndividual();
            $ssi = $ssi_model->findByIndividualId($this->data['observationgroup2Observation']['Individual_ID']);
            $gmt_diff = $ssi['ssi2Station']['GMT_Difference'];
            $state = $ssi['ssi2Station']['State'];
            
            
            /**
             * Switch to a timezone where DST is observed and if the date currently
             * is in DST then modify the gmt difference accordingly.
             */
            date_default_timezone_set('America/Anchorage');
            if(date("I") == 1 && ( empty($state) || $state != "AZ" )  ){
                $gmt_diff++;
            }
            
            $check = array_values($check);
            
            
            /**
             * Modify the observer's date/time based on their time zone and 
             * compare their timestamp to the current timestamp.
             */
            $current_date = time();
            $test_time = strtotime($check[0] . " GMT" . $gmt_diff);
            return ($current_date >= $test_time) ? true : false;
        }
        
        

        
	
}
