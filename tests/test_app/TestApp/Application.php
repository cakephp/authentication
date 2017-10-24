<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace TestApp;

use Authentication\AuthenticationServiceInterface;
use Cake\Http\BaseApplication;

class Application extends BaseApplication
{

    public function middleware($middleware)
    {
        return $middleware;
    }

    public function authentication(AuthenticationServiceInterface $service)
    {
        $service->loadIdentifier('Authentication.Password');
        $service->loadAuthenticator('Authentication.Form');

        return $service;
    }

    public function authenticationApi(AuthenticationServiceInterface $service)
    {
        $service->loadIdentifier('Authentication.Token');
        $service->loadAuthenticator('Authentication.Token');

        return $service;
    }
}
