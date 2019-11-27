<?php

class OauthCommonConsumer extends Appmodel{

	var $useTable = 'oauth_common_consumer';
	var $primaryKey = 'consumer_key';
	var $displayField = 'callback_url';

        var $useDbConfig = 'drupal';

}

