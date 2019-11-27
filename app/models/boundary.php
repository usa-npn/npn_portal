<?php
class Boundary extends Appmodel{

	var $useTable = 'Boundary';
	var $primaryKey = 'Boundary_ID';
	var $displayField = 'Name';

        
	

        
	var $belongsTo = array(

            "boundary2boundaryType" =>
                    array(
                            "className" => "BoundaryType",
                            "foreignKey" => "Type_ID"
                    )
                 
	);
        



	
}
