<?php

    /**
     * This component standardizes how the input controllers handle validating/verifying
     * user credentials.
     *
     * This supports two methods by which to validate. Standard username/pw and oauth token.
     *
     * As a secondary function, will transparently provide controllers with a person id
     * in cases where oauth credentials are provided.
     */
    class ValidateUserComponent extends Object{
        
        
        /**
         * All input paramaters are optional, but certain combinations are required to work at all.
         */
        public function verifyUser($person_id=null, $user_pw=null, $personInstance=null, $access_token=null, $consumer_key=null){
            /**
             * Person instance is 'required'.
             */
            if(!$personInstance) return false;
            $OauthToken =& ClassRegistry::init('OauthCommonToken');
            $OauthToken->recursive = -1;
            $Person =& ClassRegistry::init('Person');
            $Person->recursive = -1;

            /**
             * OAuth access takes precedence over standard authentication.
             * Check the database, to see if there is a matching token/consumer
             * as per the provided credentials.
             */
            if($access_token && $consumer_key){
                $valid = $OauthToken->find('first', array(
                   'conditions' => array(
                       'token_key' => $access_token,
                       'consumer_key' => $consumer_key),
                   'joins' => array(
                        array(
                            'table' => 'drupal5.users',
                            'alias' => 'users',
                            'type' => 'LEFT',
                            'conditions' => array(
                                'users.uid = OauthCommonToken.uid'
                            )

                        ),
                        array(
                            'table' => 'drupal5.oauth_common_consumer',
                            'alias' => 'occ',
                            'type' => 'LEFT',
                            'conditions' => array(
                                'occ.csid = OauthCommonToken.csid'
                            )
                        )                       
                    ),
                    'fields' => array(
                        'users.name',
                        'OauthCommonToken.expires'
                    )
                   )
                );

                if(!$valid) return false;

                /**
                 * Also invalid if the token is expired.
                 */
                if(time() >=  $valid['OauthCommonToken']['expires'] && $valid['OauthCommonToken']['expires'] != 0){
                    return false;
                }

                /**
                 * Since the credentials check out, the controller needs a person id to work with.
                 * Find the matching Person id corresponding with the token provided and return it.
                 *
                 * First remove some person relationships, not necessary and just bog down memory.
                 */
                $Person->
                        unbindModel(
                                array('hasMany' =>
                                    array('person2Observation', 'person2Station')
                        ));

                $person = $Person->find('first', array(
                    'conditions' => array(
                        'UserName LIKE' => $valid['users']['name']
                    )
                ));

                return $person['Person']['Person_ID'];
            }

            /**
             * If provided standard un/pw, then just check the table to see if a matching
             * combination exists.
             */
            $person = $personInstance->field("UserName",
                        array("Person_ID = " => $person_id,
                              "Passwd_Hash = " => $user_pw)
                    );

            return ($person == null) ? false : $person_id;
        }                
        
        
    }


