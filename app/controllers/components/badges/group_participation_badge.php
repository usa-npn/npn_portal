<?php

require_once('abstract_badge.php');


class GroupParticipationBadge extends AbstractBadge{
            
    
    public function __construct($person_id){
        $this->person_id = $person_id;
    }
    
    public function validate(){
        
        $badgeModel = ClassRegistry::init('Badge');
                
        $res = $badgeModel->query("SELECT p.Person_ID FROM usanpn2.Person p
                                    LEFT JOIN usanpn2.Network_Person np
                                    ON np.Person_ID = p.Person_ID
                                    LEFT JOIN usanpn2.Network_Station ns
                                    ON ns.Network_ID = np.Network_ID
                                    LEFT JOIN usanpn2.Station_Species_Individual ssi
                                    ON ssi.Station_ID = ns.Station_ID
                                    LEFT JOIN usanpn2.Observation o
                                    ON o.Individual_ID = ssi.Individual_ID
                                    WHERE o.Observer_ID = p.Person_ID
                                    AND (o.Deleted IS NULL OR o.Deleted <> 1)
                                    AND p.Person_ID = " . $this->person_id
                                    
                );

        return count($res) > 0;

    }
    
}
?>
