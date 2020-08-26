<?php
/**
 * Short description for file.
 *
 * In this file, you set up routes to your controllers and their actions.
 * Routes are very important mechanism that allows you to freely connect
 * different urls to chosen controllers and their actions (functions).
 *
 * PHP versions 4 and 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright 2005-2010, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2005-2010, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       cake
 * @subpackage    cake.app.config
 * @since         CakePHP(tm) v 0.2.9
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
/**
 * Here, we are connecting '/' (base path) to controller called 'Pages',
 * its action called 'display', and we pass a param to select the view file
 * to use (in this case, /app/views/pages/home.ctp)...
 */
	Router::connect('/', array('controller' => 'pages', 'action' => 'display', 'home'));
/**
 * ...and connect the rest of 'Pages' controller's urls.
 */
	Router::connect('/pages/*', array('controller' => 'pages', 'action' => 'display'));

        /**
         * This code pulls in the custom route and passes all requests through it. This is necessary
         * to having the REST service work as this route is what parses the GET parameters into variables
         * the controllers can use.
         */

        App::import('Lib', 'routes/GetRestParamRoute');
        Router::connect('/:controller/:action', array(), 
                array('routeClass' => 'GetRestParamRoute'));

        /**
         * This will parse all /soap actions through the soap component. Necessary to parse soap
         * requests.
         */
	Router::connect('/soap/:controller/:action/*', array('prefix'=>'soap', 'soap'=>true));

        /**
         * This maps all controllers with extension parsing so that .xml and .json
         * requests will be sent the correct views in the REST side of the service.
         */
        Router::mapResources("species");
        Router::mapResources("metadata");
        Router::mapResources("phenophases");
        Router::mapResources("stations");
        Router::mapResources("observations");
        Router::mapResources("networks");
        Router::mapResources("individuals");
        Router::mapResources("badges");        
        Router::mapResources("enter_observation");
        Router::mapResources("create_user");
        Router::mapResources("create_station");
        Router::mapResources("create_individual");        
        Router::parseExtensions('xml', 'json', 'csv', 'ndjson');

        