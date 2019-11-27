<?php

class OauthCommonToken extends Appmodel{

	var $useTable = 'oauth_common_token';
	var $primaryKey = 'token_key';
	var $displayField = 'token_key';

        var $useDbConfig = 'drupal';

}

