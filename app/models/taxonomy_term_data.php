<?php

class TaxonomyTermData extends Appmodel{

	var $useTable = 'taxonomy_term_data';
	var $primaryKey = 'tid';
	var $displayField = 'name';

        var $useDbConfig = 'drupal';
        
        var $actsAs = array(
            'Containable'
        );
        
        
        /*
	var $hasMany = array(
		"termdata2parents" =>
			array(
				"className" => "TaxonomyTermHierarchy",
				"foreignKey" => "tid"
			)
	);
         * 
         */        

}

