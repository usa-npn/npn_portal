<?php

require_once('abstract_badge.php');



class NumObsBadge extends AbstractBadge{
        
    
    protected $target;
    
    public function __construct($person_id, $num_obs){
        $this->person_id = $person_id;
        $this->target = $num_obs;
    }
    
    public function validate(){
        
        $badgeModel = ClassRegistry::init('Badge');
                
        $res = $badgeModel->query("SELECT p.Person_ID, COUNT(o.Observation_ID) c
                                    FROM usanpn2.Person p
                                    LEFT JOIN usanpn2.Observation o
                                    ON o.Observer_ID = p.Person_ID
                                    WHERE p.Person_ID = " . $this->person_id . "
                                    AND (o.Deleted IS NULL OR o.Deleted <> 1)
                                    GROUP BY p.Person_ID
                                    HAVING c >= " . $this->target
                );
        

        return count($res) > 0;

    }
    
}
?>
