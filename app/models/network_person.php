<?php


class NetworkPerson extends Appmodel{

	var $useTable = 'Network_Person';
	var $primaryKey = "Network_Person_ID";
	var $displayField = "Network_Person_ID";

	var $belongsTo = array(
		"networkperson2Person" =>
			array(
				"className" => "Person",
				"foreignKey" => "Person_ID"
			),
		"networkperson2Network" =>
			array(
				"className" => "Network",
				"foreignKey" => "Network_ID"
			)
	);

        var $hasAndBelongsToMany  = array(


                "netperson2approle" =>
                            array(
                                    "className" => "AppRole",
                                    "joinTable" => "App_Role_Network_Person",
                                    "foreignKey" => "Network_Person_ID",
                                    "associationForeignKey" => "Role_ID",
                                    "unique" => true
                                )

        );


        
        public function findNetworksForPerson($person_id){

            $network_data = $this->find('all', array(
               'conditions' => array(
                   "NetworkPerson.Person_ID" => $person_id
               ),
               'fields' => array(
                   "networkperson2Network.Network_ID",
                   "networkperson2Network.Name"
               )
            ));
            if(!$network_data) $network_data = array();
            return $network_data;

        }

	


}
