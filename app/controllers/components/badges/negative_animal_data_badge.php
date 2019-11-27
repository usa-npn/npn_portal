<?php

require_once('abstract_badge.php');


class NegativeAnimalDataBadge extends AbstractBadge{
        
    
    public function __construct($person_id){
        $this->person_id = $person_id;
    }
    
    public function validate(){
        
        $badgeModel = ClassRegistry::init('Badge');
        
/**
 *This query handled the case where zero hero was instead based on the user entering at least one zero for each animal
 * at a site, rather than the way it works now, where it depends on the user answering all No's to at least one animal. 
 * Documenting this here in case we decide to go back to using this logic.
 */        
/*                
        $res = $badgeModel->query("SELECT COUNT(Individual_ID) ci, COUNT(Observation_ID) co, Station_ID
                                    FROM
                                    (
                                    SELECT ssi.Station_ID, ssi.Individual_ID, o.Observation_ID
                                    FROM
                                    usanpn2.Person p
                                    LEFT JOIN usanpn2.Station st
                                    ON st.Observer_ID = p.Person_ID
                                    LEFT JOIN usanpn2.Station_Species_Individual ssi
                                    ON ssi.Station_ID = st.Station_ID
                                    LEFT JOIN usanpn2.Species s
                                    ON s.Species_ID = ssi.Species_ID
                                    LEFT JOIN usanpn2.Observation o
                                    ON o.Individual_ID = ssi.Individual_ID
                                    AND o.Observation_Extent = 0
                                    WHERE s.Kingdom = 'Animalia'
                                    AND p.Person_ID = " . $this->person_id . "
                                    GROUP BY ssi.Individual_ID) tbl
                                    GROUP BY tbl.Station_ID
                                    HAVING ci = co AND ci > 0"
                );
*/
        
        $res = $badgeModel->query("SELECT COUNT(o.Observation_Extent = 0) c, 
                                    ppp.pps, 
                                    o.* FROM usanpn2.Observation o
                                    LEFT JOIN usanpn2.Station_Species_Individual ssi
                                    ON ssi.Individual_ID = o.Individual_ID
                                    LEFT JOIN usanpn2.Species s
                                    ON s.Species_ID = ssi.Species_ID
                                    LEFT JOIN usanpn2.Protocol p
                                    ON p.Protocol_ID = o.Protocol_ID

                                    LEFT JOIN 
                                    (
                                    SELECT ppp.Protocol_ID, COUNT(ppp.Phenophase_ID) pps 
                                    FROM usanpn2.Protocol_Phenophase ppp
                                    GROUP BY ppp.Protocol_ID
                                    ) ppp
                                    ON ppp.Protocol_ID = p.Protocol_ID
                                    WHERE s.Kingdom = 'Animalia'
                                    AND o.Observer_ID = " . $this->person_id . "
                                    AND (o.Deleted IS NULL OR o.Deleted <> 1)
                                    GROUP BY o.Observation_Group_ID, Individual_ID
                                    HAVING c = pps"
        );
        
        return count($res) > 0;

    }
    
}
?>
