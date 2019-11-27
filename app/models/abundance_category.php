<?php
class AbundanceCategory extends Appmodel{

	var $useTable = 'Abundance_Category';
	var $primaryKey = 'Abundance_Category_ID';
	var $displayField = 'Name';



        var $hasAndBelongsToMany  = array(


                "abundance_category_2_abundance_values" =>
                            array(
                                    "className" => "AbundanceValues",
                                    "joinTable" => "Abundance_Category_Abundance_Values",
                                    "foreignKey" => "Abundance_Category_ID",
                                    "associationForeignKey" => "Abundance_Value_ID",
                                    "order" => "Seq_Num"
                                )

        );

}
