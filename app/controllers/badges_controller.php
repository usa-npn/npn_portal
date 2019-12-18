<?php
 
class Response{}
class Message{}
class UserBadge{}
class BadgesCollection{}


class BadgesController extends AppController{
    
    public $uses = array('Badge', 'BadgeHook', 'BadgePerson', 'Person');
    
    public $components = array(
    	'Soap' => array(
        	'wsdl' => 'NPN', //the file name in the view folder
            	'action' => 'service', //soap service method / handler
        ),
        'RequestHandler',
        'BadgeFactory'
    );    
    
    
    public function soap_wsdl(){
    	//will be handled by SoapComponent
    }
	

    public function soap_service(){
    //no code here
    }
    
    public function checkUserBadge($params=null){
        date_default_timezone_set('America/Phoenix');
        if(empty($params) || !$this->checkProperty($params, "person_id") || !$this->checkProperty($params, "hook_name")){
            return $this->createResponse(0, array("INVALID_INPUT"));
        }
        
        $hook = $this->BadgeHook->find('first', array(
           'conditions' => array(
               "Name_Functional" => $params->hook_name
           ) 
        ));
        
        if(!$hook){
            return $this->createResponse(0, array("INVALID_HOOK"));
        }

        $check_person = $this->Person->find('first', array(
           'conditions' => array(
               'Person_ID' => $params->person_id
           ) 
        ));
        
        if(!$check_person){
            return $this->createResponse(0, array("INVALID_PERSON"));
        }
                
        $badge_messages = array();
        $success = 0;
        
        $user_badges = $this->getBadgesForUserArray($params->person_id, true);
        
        foreach($hook["badgehook2badge"] as $badge_entry){
            
            if(in_array($badge_entry['Badge_ID'], $user_badges)){
                continue;
            }
            
            $badge = $this->BadgeFactory->createBadge($badge_entry, $params->person_id);
                        
            if(!$badge){
                continue;
            }
            
            if($badge->validate()){
                $badge_messages[] = $badge->getNameFunctional() . " QUALIFIED";
                try{
                    $data = array(
                        "BadgePerson" => array(
                            "Person_ID" => $params->person_id,
                            "Badge_ID" => $badge->getBadgeId(),
                            "Date_Earned" => date("Y-m-d H:i:s")
                        )
                    );

                    $this->BadgePerson->create();
                    $this->BadgePerson->saveAll($data);
                    $success++;
                }catch(Exception $ex){
                    $this->log("Error create badge-person entity");
                    $this->log($ex);
                    
                    $badge_messages[] = $badge->getNameFunctional() . " CREATE_ERR";
                }
                
            }
            
            
        }
        
        $status = ($success > 0) ? 1 : 0;
        return $this->createResponse($status, $badge_messages);
        

    }
    
    
    public function getUserBadges($params=null){
        
        if(empty($params) || !$this->checkProperty($params, "person_id") ){
            
            $this->set('badges', array());
            return null;
            
        }
        
        $badge_arr = $this->getBadgesForUserArray($params->person_id, false);
        
        $response = new BadgesCollection();
        $badge_coll = array();
        foreach($badge_arr as $b){
            $user_badge = new UserBadge();
            $user_badge->badge_id = $b['badgeperson2badge']['Badge_ID'];
            $user_badge->name = $b['badgeperson2badge']['Name_External'];
            $user_badge->description = $b['badgeperson2badge']['Description'];
            $user_badge->image_url = $b['badgeperson2badge']['Image_URL'];
            $badge_coll[] = $user_badge;
        }
        
        $response->badges = $badge_coll;
        
        
        $this->set('badges', $response);
        return $response;                
    }
    
    
    public function getBadges(){
        
        $badge_arr = $this->Badge->find('all');
                
        $response = new BadgesCollection();
        $badge_coll = array();
        
        foreach($badge_arr as $b){
            $user_badge = new UserBadge();
            $user_badge->badge_id = $b['Badge']['Badge_ID'];
            $user_badge->name = $b['Badge']['Name_External'];
            $user_badge->description = $b['Badge']['Description'];
            $user_badge->image_url = $b['Badge']['Image_URL'];
            $badge_coll[] = $user_badge;
        }
        
        $response->badges = $badge_coll;
        
        
        $this->set('badges', $response);
        return $response;                
    }    
    
    
    private function getBadgesForUserArray($person_id, $id_only=true){
        $search_params = array(
            'conditions' => array(
                'BadgePerson.Person_ID' => $person_id
            )
        );
        
        if($id_only){
            $search_params['fields'] = array('Badge_ID');
            
            
            $this->BadgePerson->unbindModel(
                            array('hasMany' =>
                                array('badgeperson2badge')
                            ));

            
        }

   
        
        $results = $this->BadgePerson->find('all', $search_params);

        if($id_only){
            $ids = array();   
            
            foreach($results as $result){
                $ids[] = $result['BadgePerson']['Badge_ID'];
            }
            
            return $ids;
        }
        
        return $results;
        
        
    }
    
    
    private function createResponse($status, $badges){
        
        $response = new Response();
        $badge_messages = array();
        
        foreach($badges as $badge){
            $msg = new Message();
            $msg->message = $badge;
            $badge_messages[] = $msg;
        }
        
        $response->status_code = $status;
        
        $response->badge_messages = $badge_messages;
        
        
        $this->set('response', $response);
        return $response;
    }
    
    
}
?>
