<?php
class Person extends Appmodel{

	var $useTable = "Person";
	var $primaryKey = "Person_ID";
        var $displayField = 'email';


        
	var $hasMany = array(

                "person2Station" =>
                        array(
                                "className" => "Station",
                                "foreignKey" => "Observer_ID"
                            ),
                "person2badge" =>
                        array(
                                "className" => "BadgePerson",
                                "foreignKey" => "Person_ID"
                            )            
                         
	);

        var $hasAndBelongsToMany = array(
          'person2NetworkPerson' => array(

              'className' => 'Network',
              'joinTable' => 'Network_Person',
              'foreignKey' => "Person_ID",
              'associationForeignKey' => "Network_ID"
          )
        );
	

	var $validate = array(
            
		"First_Name" => array(
                    
			"firstNameRule1" => array(
				"rule" => "/^[A-Za-z0-9' \.-]+$/",
				"message" => "First name can only contain letters, numbers, whitespace, aposraphies, and fullstops.",
			),
			"firstNameRule2" => array(
				"rule" => array("maxlength", 64),
				"message" => "First Name cannot be greater than 64 characters long."
			),
			"firstNameRuel3" => array(
				"rule" => array("notEmpty"),
				"message" => "First Name must be provided"
			) 
			
		
		),
		
		"Last_Name" => array(
			"lastNameRule1" => array(
				"rule" => "/^[A-Za-z0-9' \.-]+$/",
				"message" => "Last name can only contain letters, numbers, whitespace, aposraphies, and fullstops.",
			),
			"lastNameRule2" => array(
				"rule" => array("maxlength", 64),
				"message" => "Last Name cannot be greater than 64 characters long."
			),
			"lastNameRuel3" => array(
				"rule" => "notEmpty",
				"message" => "Last Name must be provided"
			) 
		),
		"Create_Date" => array(
			"createDateRule1" => array(
				"rule" => "date",
				"message" => "Create date is not valid"
			),
			"createDateRule2" => array(
				"rule" => "notEmpty",
				"message" => "Create date must be provided."
			)
		),
                "email" => array(
                    "emailRule1" => array(
                        "rule" => "isUnique",
                        "message" => "There is already an account using that email."
                    ),
                    "emailRule2" => array(
                        "rule" => "email",
                        "message" => "Not a valid email address"
                    )
                )
	);

}
