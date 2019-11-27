<?php

abstract class AbstractBadge extends Object{
    
    protected $person_id;
    
    private $badge_id;
    private $name_internal;
    private $name_external;
    private $name_functional;
    
    
    abstract public function validate();
    
    
    public function getBadgeId() {
        return $this->badge_id;
    }

    public function setBadgeId($badge_id) {
        $this->badge_id = $badge_id;
    }

    public function getNameInternal() {
        return $this->name_internal;
    }

    public function setNameInternal($name_internal) {
        $this->name_internal = $name_internal;
    }

    public function getNameExternal() {
        return $this->name_external;
    }

    public function setNameExternal($name_external) {
        $this->name_external = $name_external;
    }

    public function getNameFunctional() {
        return $this->name_functional;
    }

    public function setNameFunctional($name_functional) {
        $this->name_functional = $name_functional;
    }


}


?>
