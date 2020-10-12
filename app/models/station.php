<?php

DEFINE('DAYMET_VARS', 'tmin,tmax,dayl,prcp,yday,year');
DEFINE('DAYMET_HEADER_LINE_COUNT', 8);

DEFINE('FIRST_DAY_WINTER', 335);
DEFINE('LAST_DAY_WINTER', 59);
DEFINE('FIRST_DAY_SPRING', 60);
DEFINE('LAST_DAY_SPRING', 151);
DEFINE('FIRST_DAY_SUMMER', 152);
DEFINE('LAST_DAY_SUMMER', 243);
DEFINE('FIRST_DAY_FALL', 244);
DEFINE('LAST_DAY_FALL', 334);

DEFINE('GDD_BASE_TEMP', 0);
DEFINE('GDD_BASE_TEMP_F', 32);

class Station extends Appmodel{

        var $useTable = "Station";
	var $primaryKey = "Station_ID"; 
	var $displayField = "Station_Name";

        
	var $hasOne = array(
		"station2NetworkStation" =>
			array(
				"className" => "NetworkStation",
				"foreignKey" => "Station_ID",
			)
	);
        
        var $belongsTo = array(
                "station2Person" =>
                        array(
                                "className" => "Person",
                                "foreignKey" => "Observer_ID"
                        )
        );
	
        
	var $hasMany = array(
                "station2ssi" =>
                        array(
                                "className" => "StationSpeciesIndividual",
                                "foreignKey" => "Station_ID"
                        )
	);
        

	var $validate = array(
            
		"Station_Name" => array(
                    
			"stationNameRule1" => array(
				"rule" => "/^[A-Za-z0-9' \.-]+$/",
				"message" => "Station name can only contain letters, numbers, whitespace, apostrophes, and fullstops.",
			),
			"stationNameRule2" => array(
				"rule" => array("maxlength", 64),
				"message" => "Station Name cannot be greater than 64 characters long."
			),
			"stationNameRue3" => array(
				"rule" => array("notEmpty"),
				"message" => "Station Name must be provided"
			) 
			
		
		),
		
		"Latitude" => array(
			"latitudeRule1" => array(
				"rule" => array("decimal",6),
				"message" => "Latitude is not valid, or to six decimal places"
			)
		),
		"Longitude" => array(
			"longitudeRule1" => array(
				"rule" => array("decimal",6),
				"message" => "Longitude is not valid, or to six decimal places"
			)
		)
		
	);


    function findStationNetwork($station_id){

        $station_ent = $this->findByStationId($station_id);
        return $station_ent["station2NetworkStation"]["Network_ID"];
    }
        
    function getStationWithClimateData($station_id, $year, $doy){

        $daymet_data = null;
       
        $station = $this->find('first', array(
           'conditions' => array(
               'Station.Station_ID' => $station_id
           ),
           'joins' => array(
               array(
                   'table' => 'Daymet',
                   'type' => 'left',
                   'conditions' => 'Station.Short_Longitude = Daymet.Longitude AND Station.Short_Latitude = Daymet.Latitude AND Daymet.Year = ' . $year
               )
           ),
           'fields' => array(
               'Station.Station_ID',
               'Station.Short_Latitude',
               'Station.Short_Longitude',
               'Daymet.tmax_winter',
               'Daymet.Year'
           )
        ));


        if(!$station){
            $daymet_data = null;
        }else if(isset($station['Daymet']['Year']) && isset($station['Daymet']['tmax_winter'] )){
            $latitude = $station['Station']['Short_Latitude'];
            $longitude = $station['Station']['Short_Longitude'];

            $daymet_data = $this->getCachedDaymet($latitude, $longitude, $year, $doy);

        }else{
            $latitude = $station['Station']['Short_Latitude'];
            $longitude = $station['Station']['Short_Longitude'];

            // don't want to create duplicate daymet rows for two stations with same lat/long
            $daymet_data = $this->getCachedDaymet($latitude, $longitude, $year, $doy);
            if($daymet_data && isset($daymet_data['Daymet']['tmax_winter'])) {
                return $daymet_data;
            } 

            $data = $this->getDaymetData($latitude, $longitude, $year);

            if($data){
                $this->cacheCoordinates($latitude, $longitude, $year, $data);
                $daymet_data = $this->getCachedDaymet($latitude, $longitude, $year, $doy);
            } elseif (is_null($data)){
                $daymet_data = null;
            }

        }
        return  $daymet_data;
    }


    function getStationWithClimateDataTemp($station_id, $year, $doy){

        $daymet_data = null;
       
        $station = $this->find('first', array(
           'conditions' => array(
               'Station.Station_ID' => $station_id
           ),
           'joins' => array(
               array(
                   'table' => 'Daymet',
                   'type' => 'left',
                   'conditions' => 'Station.Short_Longitude = Daymet.Longitude AND Station.Short_Latitude = Daymet.Latitude AND Daymet.Year = ' . $year
               )
           ),
           'fields' => array(
               'Station.Station_ID',
               'Station.Short_Latitude',
               'Station.Short_Longitude',
               'Daymet.Year'
           )
        ));


        if(!$station){
            $daymet_data = null;

        }else{

            $latitude = $station['Station']['Short_Latitude'];
            $longitude = $station['Station']['Short_Longitude'];

            $data = $this->getDaymetData($latitude, $longitude, $year);

            if($data){
                $this->cacheCoordinatesTemp($latitude, $longitude, $year, $data);
            }

        }

        return  $daymet_data;
    }
        
        
    public function cacheCoordinates($lat, $long, $year, $data){
        
        App::import('Model','Daymet');        
        App::import('Model','DaymetData');

        
        $this->Daymet =  new Daymet();
        $this->DaymetData = new DaymetData();
        
        $last_year_data = $this->getDaymetData($lat, $long, $year - 1);
        
        $is_leap_year = $this->isLeapYear($year);
        
        $winter_days = $this->getWinterDays($data, $last_year_data, $year, $is_leap_year);
        $spring_days = $this->getDays($data, FIRST_DAY_SPRING, LAST_DAY_SPRING, $is_leap_year);
        $summer_days = $this->getDays($data, FIRST_DAY_SUMMER, LAST_DAY_SUMMER, $is_leap_year);
        $fall_days = $this->getDays($last_year_data, FIRST_DAY_FALL, LAST_DAY_FALL, $this->isLeapYear($year - 1));
        
        $tmax_winter = $this->getAverage($winter_days, 'tmax');        
        $tmax_spring = $this->getAverage($spring_days, 'tmax');
        $tmax_summer = $this->getAverage($summer_days, 'tmax');
        $tmax_fall = $this->getAverage($fall_days, 'tmax');
        
        
        $tmin_winter = $this->getAverage($winter_days, 'tmin');        
        $tmin_spring = $this->getAverage($spring_days, 'tmin');
        $tmin_summer = $this->getAverage($summer_days, 'tmin');
        $tmin_fall = $this->getAverage($fall_days, 'tmin');
        
        $prcp_winter = $this->getAccumulation($winter_days, 'prcp');        
        $prcp_spring = $this->getAccumulation($spring_days, 'prcp');
        $prcp_summer = $this->getAccumulation($summer_days, 'prcp');
        $prcp_fall = $this->getAccumulation($fall_days, 'prcp');         
       
        $daymet_data = array(
            "Daymet" => array(
                "tmax_winter" => $tmax_winter,
                "tmax_spring" => $tmax_spring,
                "tmax_summer" => $tmax_summer,
                "tmax_fall" => $tmax_fall,
                "tmin_winter" => $tmin_winter,
                "tmin_spring" => $tmin_spring,
                "tmin_summer" => $tmin_summer,
                "tmin_fall" => $tmin_fall,
                "prcp_winter" => $prcp_winter,
                "prcp_spring" => $prcp_spring,
                "prcp_summer" => $prcp_summer,
                "prcp_fall" => $prcp_fall,
                "Latitude" => $lat,
                "Longitude" => $long,
                "Year" => $year,
                "Update_Date" => date("Y-m-d G:i:s")
            )         
        );
        
        // first check to see if an entry for this lat/long/year already exist
        $existing_daymet_data = $this->getCachedDaymet($lat, $long, $year, 1);
        if($daymet_data && isset($existing_daymet_data['Daymet']['Daymet_ID'])){
            // if yes then run update and set Daymet_ID according
            $daymet_id = $existing_daymet_data['Daymet']['Daymet_ID'];
            $daymet_data["Daymet"]["Daymet_ID"] = $daymet_id;
            $this->Daymet->save($daymet_data);
        }else{
            // else do save and set $daymet_id based on last insert
            $this->Daymet->save($daymet_data);
            $daymet_id = $this->Daymet->id;
        }

        $gdd = 0;
        $gddf = 0;
        $total_precip = 0;
        
        $i=1;
        foreach($data as $day){
            $this->DaymetData->create();
            $today_gdd = (($day['tmax'] + $day['tmin'])/2) - GDD_BASE_TEMP;
            $tmaxf = ($day['tmax'] * 1.8) + 32;
            $tminf = ($day['tmin'] * 1.8) + 32;
            $today_gddf = (($tmaxf + $tminf)/2) - GDD_BASE_TEMP_F;
            
            $gdd += ($today_gdd < 0) ? 0 : $today_gdd;
            $gddf += ($today_gddf < 0) ? 0 : $today_gddf;
            
            
            $total_precip += $day['prcp'];
            
            $day_data = array(
                "DaymetData" => array(
                    "Daymet_ID" => $daymet_id,
                    "doy" => $i++,
                    "tmax" => $day['tmax'],
                    "tmin" => $day['tmin'],
                    "tmaxf" => $tmaxf,
                    "tminf" => $tminf,                   
                    "prcp" => $day['prcp'],
                    "daylength" => $day['dayl'],
                    "gdd" => $gdd,
                    "gddf" => $gddf,
                    "acc_prcp" => $total_precip
                )
            );
            
            $this->DaymetData->save($day_data);            
        }
        
        return $daymet_id;
    }

    public function cacheCoordinatesTemp($lat, $long, $year, $data){
        
        App::import('Model','Daymettemp');        
        App::import('Model','DaymetDatatemp');

        
        $this->Daymettemp =  new Daymettemp();
        $this->DaymetDatatemp = new DaymetDatatemp();
        
        $last_year_data = $this->getDaymetData($lat, $long, $year - 1);
        
        $is_leap_year = $this->isLeapYear($year);
        
        $winter_days = $this->getWinterDays($data, $last_year_data, $year, $is_leap_year);
        $spring_days = $this->getDays($data, FIRST_DAY_SPRING, LAST_DAY_SPRING, $is_leap_year);
        $summer_days = $this->getDays($data, FIRST_DAY_SUMMER, LAST_DAY_SUMMER, $is_leap_year);
        $fall_days = $this->getDays($last_year_data, FIRST_DAY_FALL, LAST_DAY_FALL, $this->isLeapYear($year - 1));
        
        $tmax_winter = $this->getAverage($winter_days, 'tmax');        
        $tmax_spring = $this->getAverage($spring_days, 'tmax');
        $tmax_summer = $this->getAverage($summer_days, 'tmax');
        $tmax_fall = $this->getAverage($fall_days, 'tmax');
        
        
        $tmin_winter = $this->getAverage($winter_days, 'tmin');        
        $tmin_spring = $this->getAverage($spring_days, 'tmin');
        $tmin_summer = $this->getAverage($summer_days, 'tmin');
        $tmin_fall = $this->getAverage($fall_days, 'tmin');
        
        $prcp_winter = $this->getAccumulation($winter_days, 'prcp');        
        $prcp_spring = $this->getAccumulation($spring_days, 'prcp');
        $prcp_summer = $this->getAccumulation($summer_days, 'prcp');
        $prcp_fall = $this->getAccumulation($fall_days, 'prcp');
        
        $daymet_data = array(
            "Daymettemp" => array(
                "tmax_winter" => $tmax_winter,
                "tmax_spring" => $tmax_spring,
                "tmax_summer" => $tmax_summer,
                "tmax_fall" => $tmax_fall,
                "tmin_winter" => $tmin_winter,
                "tmin_spring" => $tmin_spring,
                "tmin_summer" => $tmin_summer,
                "tmin_fall" => $tmin_fall,
                "prcp_winter" => $prcp_winter,
                "prcp_spring" => $prcp_spring,
                "prcp_summer" => $prcp_summer,
                "prcp_fall" => $prcp_fall,
                "Latitude" => $lat,
                "Longitude" => $long,
                "Year" => $year,
                "Update_Date" => date("Y-m-d G:i:s")
            )         
        );
        
        $this->Daymettemp->save($daymet_data);

        $daymet_id = $this->Daymettemp->id;
        $gdd = 0;
        $gddf = 0;
        $total_precip = 0;
        
        $i=1;
        foreach($data as $day){
            $this->DaymetDatatemp->create();
            $today_gdd = (($day['tmax'] + $day['tmin'])/2) - GDD_BASE_TEMP;
            $tmaxf = ($day['tmax'] * 1.8) + 32;
            $tminf = ($day['tmin'] * 1.8) + 32;
            $today_gddf = (($tmaxf + $tminf)/2) - GDD_BASE_TEMP_F;
            
            $gdd += ($today_gdd < 0) ? 0 : $today_gdd;
            $gddf += ($today_gddf < 0) ? 0 : $today_gddf;
            
            
            $total_precip += $day['prcp'];
            
            $day_data = array(
                "DaymetDatatemp" => array(
                    "Daymet_ID" => $daymet_id,
                    "doy" => $i++,
                    "tmax" => $day['tmax'],
                    "tmin" => $day['tmin'],
                    "tmaxf" => $tmaxf,
                    "tminf" => $tminf,                   
                    "prcp" => $day['prcp'],
                    "daylength" => $day['dayl'],
                    "gdd" => $gdd,
                    "gddf" => $gddf,
                    "acc_prcp" => $total_precip
                )
            );
            
            $this->DaymetDatatemp->save($day_data);            
        }
        
        return $daymet_id;
    }
    
    public function getCachedDaymet($lat, $long, $year, $doy){
        App::import('Model','Daymet');
        $this->Daymet =  new Daymet();
        $this->Daymet->Behaviors->attach('Containable');
        
        
        $result = $this->Daymet->find('first', array(
           'conditions' => array(
               'Daymet.Latitude' => $lat,
               'Daymet.Longitude' => $long,
               'Daymet.Year' => $year
           ),
           'contain' => array(
               'daymet2daymetdata' => array(
                   'conditions' => array(
                       'doy' => $doy
                   )
               )
           )
        ));
        
        return $result;
        
    }
    
        
    public function getDaymetData($lat, $long, $year, $vars=DAYMET_VARS){
        $daymet_url = 'http://daymet.ornl.gov/data/send/saveData?';
        $daymet_url .= "lat=" . $lat; 
        $daymet_url .= "&lon=" . $long;
        $daymet_url .= "&year=" . $year;
        $daymet_url .= "&measuredParams=" . $vars;
        $response = null;
        $data = null;
        try{
            $response = @file_get_contents($daymet_url);

        } catch (Exception $ex) {
            $this->log("Problem fetching daymet data for coordinates:" . $lat . ", " . $long . " Year: " . $year . " Vars: " . $vars);
            $this->log($ex);
            return null;
        }
        
        if($response){
            $lines = preg_split("/\\r\\n|\\r|\\n/", $response);
            $c = count($lines);
            $data = array();
            $headers = explode(",", $lines[DAYMET_HEADER_LINE_COUNT-1]);
            for ($i = 0; $i < count($headers); ++$i) {
                $tempHeader = explode(" ", $headers[$i]);
                $headers[$i] = $tempHeader[0];
            }
            $var_keys = array_slice($headers, 0);
            $header_map = array_flip($headers);

            for($i=DAYMET_HEADER_LINE_COUNT;$i < $c - 1; $i++){

                $values = explode(',', $lines[$i]);
                if($values[$header_map['year']] != $year) {
                    $this->log('year received does not match the year given');
                    return null;
                }
                $data[$i-DAYMET_HEADER_LINE_COUNT+1] = array_combine($var_keys, $values);
            }
        }
        return $data;
    }

    
    private function getWinterDays($data, $last_year_data, $year, $leap_year){
        $previous_year_leap_year = $this->isLeapYear($year - 1);
        
        $winter_days_last_year = array_slice($last_year_data, FIRST_DAY_WINTER + $previous_year_leap_year - 1);
        $winter_days_this_year = array_slice($data, 0, LAST_DAY_WINTER + $leap_year);
        
        return array_merge($winter_days_last_year, $winter_days_this_year);        
    }
    
    private function getDays($data, $first_day, $last_day, $leap_year){
        return array_slice($data, $first_day + $leap_year - 1, $last_day - $first_day + $leap_year + 1);
    }
    
    private function getAverage($values, $variable){
        $total = 0;
        $c = count($values);
        
        for($i=0;$i < $c; $i++){
            $total += $values[$i][$variable];
        }
        
        return $total / $c;
    }
    
    private function getAccumulation($values, $variable){
        $total = 0;
        $c = count($values);
        
        for($i=0;$i < $c; $i++){
            $total += $values[$i][$variable];
        }        
        
        return $total;
    }
        
        
    private function isLeapYear($year){
        
        return (($year % 4 == 0) && ($year % 100 != 0)) || ($year % 400 == 0);
    }
    
    public function getModisDataFromService($lat,$long,$year){


        $url =  "http://" . $_SERVER['SERVER_NAME'] . "/npn_portal/stations/getModisForCoordinates.json?longitude=" . $long . "&latitude=" . $lat . "&year=" . $year;

        $response = null;
        $data = null;
        try{
            $response = @file_get_contents($url);
        } catch (Exception $ex) {
            $this->log("Problem fetching modis values for coordinates:" . $lat . ", " . $long . " Year: " . $year);
            $this->log($ex);
            return null;
        }
        
        if($response){
            $data = json_decode($response, true);
        }
        
        return $data;
    }
    
    public function getCachedModis($lat, $long, $year){
                
        App::import('Model','Daymet');
        $this->Daymet =  new Daymet();
        
        $this->Daymet->unbindModel(
                array('hasMany' => 
                    array(
                        'daymet2daymetdata')
                    ));        
        
        
        $result = $this->Daymet->find('first', array(
           'conditions' => array(
               'Daymet.Latitude' => $lat,
               'Daymet.Longitude' => $long,
               'Daymet.Year' => $year
           ),'fields' => array(
               
            'Greenup_0',
            'Greenup_1',
            'MidGreenup_0',
            'MidGreenup_1',
            'Peak_0',
            'Peak_1',
            'NumCycles',
            'Maturity_0',
            'Maturity_1',
            'MidGreendown_0',
            'MidGreendown_1',
            'Senescence_0',
            'Senescence_1',
            'Dormancy_0',
            'Dormancy_1',
            'EVI_Minimum_0',
            'EVI_Minimum_1',
            'EVI_Amplitude_0',
            'EVI_Amplitude_1',
            'EVI_Area_0',
            'EVI_Area_1',
            'QA_Detailed_0',
            'QA_Detailed_1',
            'QA_Overall_0',
            'QA_Overall_1'
           )
        ));
        
        return $result;

    }
    
    public function getStationModisValues($station_id, $year){
        $daymet_data = null;
       
        $station = $this->find('first', array(
           'conditions' => array(
               'Station.Station_ID' => $station_id
           ),
           'joins' => array(
               array(
                   'table' => 'Daymet',
                   'type' => 'left',
                   'conditions' => 'Station.Short_Longitude = Daymet.Longitude AND Station.Short_Latitude = Daymet.Latitude AND Daymet.Year = ' . $year
               )
           ),
           'fields' => array(
               'Station.Station_ID',
               'Station.Short_Latitude',
               'Station.Short_Longitude',
               'Daymet.Year',
               'Daymet.Greenup_0'
           )
        ));


        if(!$station){
            $daymet_data = null;

        }elseif( isset($station['Daymet']['Year']) && isset($station['Daymet']['Greenup_0'] )  ){

            $latitude = $station['Station']['Short_Latitude'];
            $longitude = $station['Station']['Short_Longitude'];

            $daymet_data = $this->getCachedModis($latitude, $longitude, $year);

        }else{

            $latitude = $station['Station']['Short_Latitude'];
            $longitude = $station['Station']['Short_Longitude'];

            $data = $this->getModisDataFromService($latitude, $longitude, $year);

            if($data){
                $this->cacheModisValues($latitude, $longitude, $year, $data);
                $daymet_data = $this->getCachedModis($latitude, $longitude, $year);
            }

        }

        return  $daymet_data;        
    }
    
    public function cacheModisValues($lat,$long,$year,$data){
        
        App::import('Model','Daymet');
        $daymet = new Daymet();
        
        $daymet_entry = $daymet->find('first', array(
            'conditions' => array(
                'Latitude' => $lat,
                'Longitude' => $long,
                'Year' => $year
            )
        ));
        
        try{
            
            if(!$daymet_entry){
                $empty_data = array(
                    "Daymet" => array(
                        "Latitude" => $lat,
                        "Longitude" => $long,
                        "Year" => $year
                    )
                );
                
                $daymet->save($empty_data);
                
                $daymet_entry = $daymet->find('first', array(
                    'conditions' => array(
                        'Latitude' => $lat,
                        'Longitude' => $long,
                        'Year' => $year
                    )
                ));                
                
            }
            
            if($daymet_entry){
                
                $daymet->id = $daymet_entry['Daymet']['Daymet_ID'];
                
                $daymet_data = array(
                    "Daymet" => array(
                        
                        "Greenup_0" => $data["Greenup_0"],
                        "Greenup_1" => $data["Greenup_1"],
                        "MidGreenup_0" => $data["MidGreenup_0"],
                        "MidGreenup_1" => $data["MidGreenup_1"],
                        "Peak_0" => $data["Peak_0"],
                        "Peak_1" => $data["Peak_1"],
                        "NumCycles" => $data["NumCycles"],
                        "Maturity_0" => $data["Maturity_0"],
                        "Maturity_1" => $data["Maturity_1"],
                        "MidGreendown_0" => $data["MidGreendown_0"],
                        "MidGreendown_1" => $data["MidGreendown_1"],
                        "Senescence_0" => $data["Senescence_0"],
                        "Senescence_1" => $data["Senescence_1"],
                        "Dormancy_0" => $data["Dormancy_0"],
                        "Dormancy_1" => $data["Dormancy_1"],
                        "EVI_Minimum_0" => $data["EVI_Minimum_0"],
                        "EVI_Minimum_1" => $data["EVI_Minimum_1"],
                        "EVI_Amplitude_0" => $data["EVI_Amplitude_0"],
                        "EVI_Amplitude_1" => $data["EVI_Amplitude_1"],
                        "EVI_Area_0" => $data["EVI_Area_0"],
                        "EVI_Area_1" => $data["EVI_Area_1"],                        
                        "QA_Detailed_0" => $data["QA_Detailed_0"],
                        "QA_Detailed_1" => $data["QA_Detailed_1"],
                        "QA_Overall_0" => $data["QA_Overall_0"],
                        "QA_Overall_1" => $data["QA_Overall_1"], 
                        "Update_Date" => date("Y-m-d G:i:s")
                    )         
                );

                $daymet->save($daymet_data);
            }else{                
                throw new Exception("No daymet entry available.");
            }
            
        }catch(Exception $ex){
            return false;
        }

        return true;
    }
        
        
}
