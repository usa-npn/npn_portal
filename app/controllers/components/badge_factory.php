<?php

class BadgeFactoryComponent extends Object{
    
    
    
    public function createBadge($badge_data, $person_id){
        $badge = null;
        try{
            $file_name = strtolower($badge_data['Name_Functional']) . "_badge.php";        
            $class_name = Inflector::camelize($badge_data['Name_Functional'] . "_badge");

            require_once 'badges/' . $file_name;

            $reflector = new ReflectionClass($class_name);

            $badge = $reflector->newInstanceArgs(array($person_id));
            
            $badge->setBadgeId($badge_data['Badge_ID']);
            $badge->setNameInternal($badge_data['Name_Internal']);
            $badge->setNameExternal($badge_data['Name_External']);
            $badge->setNameFunctional($badge_data['Name_Functional']);
            
        }catch(Exception $ex){
            
            $badge = null;
            $this->log($ex);
        }
        
        return $badge;
        
        
        
    }
}
?>
