<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @license https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace TestApp;

use Authentication\AuthenticationService;
use Authentication\AuthenticationServiceInterface;
use Authentication\AuthenticationServiceProviderInterface;
use Cake\Http\BaseApplication;
use Cake\Http\MiddlewareQueue;
use Psr\Http\Message\ServerRequestInterface;

class Application extends BaseApplication implements AuthenticationServiceProviderInterface
{
    public function middleware(MiddlewareQueue $middleware): MiddlewareQueue
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

    /**
     * Returns a service provider instance.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request
     * @return \Authentication\AuthenticationServiceInterface
     */
    public function getAuthenticationService(ServerRequestInterface $request): AuthenticationServiceInterface
    {
        $service = new AuthenticationService();
        $service->loadIdentifier('Authentication.Password');
        $service->loadAuthenticator('Authentication.Form');

        return $service;
    }
}
