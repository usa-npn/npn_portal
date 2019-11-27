<?php
class ProtocolPhenophase extends Appmodel{

	var $useTable = 'Protocol_Phenophase';

	var $belongsTo = array(
		"protocolPhenophase2Protocol" =>
			array(
				"className" => "Protocol",
				"foreignKey" => "Protocol_ID"
			),
                "protocolPhenohphase2Phenophase" =>
                        array(
                                "className" => "Phenophase",
                                "foreignKey" => "Phenophase_ID"
                        )
	);
	

}
