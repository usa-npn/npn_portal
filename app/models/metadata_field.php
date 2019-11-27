<?php
class MetadataField extends Appmodel{

	var $useTable = 'Metadata_Field';
	var $primaryKey = 'Metadata_Field_ID';
	var $displayField = 'Field_Name';

        
	

	var $hasMany = array(

                "Controlled_Values" =>
                        array(
                                "className" => "MetadataControlledValue",
                                "foreignKey" => "Metadata_Field_ID"
                        )                 
	);
	
}
