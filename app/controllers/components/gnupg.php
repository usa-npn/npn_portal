<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of gnupg
 *
 * @author Lee
 */
class GnupgComponent  extends Object{
    

    
    var $gpg;
    var $fingerprint;

    public function __construct(){
        if(Configure::read('gpg_enabled') == 1){
            putenv("GNUPGHOME=" . Configure::read('gnupg_home'));
            $this->gpg = new gnupg();
        }
    }
    
    public function encryptData(&$data){
            
        $enc_data = "";
        if(Configure::read('gpg_enabled') == 1){
            $this->gpg->addencryptkey($this->fingerprint);		
            $enc_data = $this->gpg->encrypt($data);
        }        
        return $enc_data;
        

    }
    
    public function setFingerPrint($name){
        
        if(Configure::read('gpg_enabled') == 0){
            return;
        }
        
        $info = $this->gpg->keyinfo('');
        
        foreach($info as $key){
            $key_name = $key['uids'][0]['name'];
            
            if($key_name == $name){
                
                foreach($key['subkeys'] as $subkey){
                    if($subkey['can_sign'] == 1){
                        $this->fingerprint = $subkey['fingerprint'];
                        break 2;
                    }
                }
            }
        }        
    }
}
