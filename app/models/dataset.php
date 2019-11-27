<?php
class Dataset extends Appmodel{

	var $useTable = 'Dataset';
	var $primaryKey = "Dataset_ID";
        var $displayField = 'Dataset_Name';


	var $hasOne = array(
                "dataset2source" =>
                        array(
                                "className" => "Source",
                                "foreignKey" => "Source_ID"
                        )
	);


        var $hasAndBelongsToMany  = array(


                "dataset2observation" =>
                            array(
                                    "className" => "Observation",
                                    "joinTable" => "Dataset_Observation",
                                    "foreignKey" => "Dataset_ID",
                                    "associationForeignKey" => "Observation_ID",
                                    "unique" => false
                                )

        );



}
