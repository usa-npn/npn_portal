<?php


class GetRestParamRoute extends CakeRoute {
    function parse($url) {

        $params = parent::parse($url);
        $obj = null;

        if (count($_POST) > 0){
            $get_url = $_GET['url'];
            $_GET = $_POST;
            $_GET['url'] = $get_url;
        }        
        
        if(count($_GET) > 1){
            
            
            $obj = new stdClass();
            foreach($_GET as $get_var_name => $get_var_value){

                if($get_var_name == "url") continue;

                $obj->$get_var_name = $get_var_value;
            }

            foreach($_GET as $get_var_name => $get_var_value){

                if($get_var_name == "url") continue;

                if(is_array($obj->$get_var_name)){
                    $keys = array_keys($obj->$get_var_name);

                    if(is_numeric($keys[0])){
                        $c = count($obj->$get_var_name);

                        $a =& $obj->$get_var_name;
                        foreach($a as $v){
                            if(is_array($v)){
                                $v = $this->array2object($v);
                            }                            
                        }

                    }else{
                        $obj->$get_var_name = $this->array2object($obj->$get_var_name);
                    }
                }
            }
            
            $params['pass'][] = $obj;
        }
        
        

        $func = substr($url, strrpos($url, "/") + 1);
        if($this->hasLargeRequest($func)){ 
            if($obj == null) $params['pass'][] = $obj;
         
            $class = substr($url, 1, strpos($url, "/", 2) - 1);
            
            $format = substr($_GET['url'], strpos($_GET['url'], ".") + 1);

            $params['controller'] = 'large';
            $params['action'] = 'handleLargeRequest';
            $params['pass'][] = $class;
            $params['pass'][] = $func;
            $params['pass'][] = $format;
        

        }

        return $params;
    }

    function hasLargeRequest($function_name){
        return (in_array($function_name, Configure::read('LARGE_FUNCTIONS')));
    }

    function array2object($array) {

        if (is_array($array)) {
            $obj = new StdClass();

            foreach ($array as $key => $val){
                $obj->$key = $val;
            }
        }
        else { $obj = $array; }

        return $obj;
    }


}
