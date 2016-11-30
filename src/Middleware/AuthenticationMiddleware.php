<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         4.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Middleware;

use Authentication\AuthenticationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Authentication Middleware
 */
class AuthenticationMiddleware
{

    /**
     * Authentication Service
     */
    protected $_authenticationService;

    /**
     * Constructor
     *
     * @param \Auth\Authentication\AdapterService $authenticationService Authentication service instance.
     */
    public function __construct(AuthenticationService $authenticationService)
    {
        $this->_authenticationService = $authenticationService;
    }

    /**
     * Callable implementation for the middleware stack.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request.
     * @param \Psr\Http\Message\ResponseInterface $response The response.
     * @param callable $next The next middleware to call.
     * @return \Psr\Http\Message\ResponseInterface A response.
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $next)
    {
        $result = $this->_authenticationService->authenticate($request, $response);
        $request = $request->withAttribute('identity', $result->getIdentity());
        $request = $request->withAttribute('authentication', $result);

        return $next($request, $response);
    }
}
