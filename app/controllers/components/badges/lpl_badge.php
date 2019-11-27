<?php

require_once('abstract_badge.php');

/**
 * This badge looks to see if the user has been marked as having completed
 * the LPL certification course through their Drupal profile.
 * Still takes a USANPN Person_ID as input.
 * Uses a HAVING clause so that the number of results can be measured.
 */
class LplBadge extends AbstractBadge{
        
    
    public function __construct($person_id){
        $this->person_id = $person_id;
    }
    
    public function validate(){
        
        $badgeModel = ClassRegistry::init('Badge');
                
        $res = $badgeModel->query("SELECT profile_value.value FROM usanpn2.Person
                                    LEFT JOIN drupal5.users
                                    ON users.name = Person.UserName
                                    LEFT JOIN drupal5.profile_field
                                    ON profile_field.name = 'profile_LPL'
                                    LEFT JOIN drupal5.profile_value
                                    ON profile_value.fid = profile_field.fid AND profile_value.uid = users.uid
                                    WHERE Person_ID = " . $this->person_id . "
                                    HAVING profile_value.value IS NOT NULL AND profile_value.value = 1"
                );

        return !empty($res);


    }
    
}

