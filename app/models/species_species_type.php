<?php
class SpeciesSpeciesType extends Appmodel{

	var $useTable = 'Species_Species_Type';

        var $belongsTo = array('Species', 'SpeciesType');


}

