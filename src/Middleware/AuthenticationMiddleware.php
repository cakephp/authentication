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
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Middleware;

use Authentication\AuthenticationService;
use Authentication\AuthenticationServiceInterface;
use Authentication\Authenticator\UnauthorizedException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Stream;

/**
 * Authentication Middleware
 */
class AuthenticationMiddleware
{

    /**
     * Authentication Service
     *
     * @var \Authentication\AuthenticationServiceInterface
     */
    protected $_authenticationService;

    /**
     * Constructor
     *
     * @param \Authentication\AuthenticationServiceInterface $authenticationService Authentication service instance.
     */
    public function __construct(AuthenticationServiceInterface $authenticationService)
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
        try {
            $result = $this->_authenticationService->authenticate($request, $response);
        } catch (UnauthorizedException $e) {
            $body = new Stream('php://memory', 'rw');
            $body->write($e->getBody());
            $response = $response->withStatus($e->getCode())
                ->withBody($body);
            foreach ($e->getHeaders() as $header => $value) {
                $response = $response->withHeader($header, $value);
            }

            return $response;
        }
        $request = $request->withAttribute('identity', $this->_authenticationService->getIdentity());
        $request = $request->withAttribute('authentication', $this->_authenticationService);
        $request = $request->withAttribute('authenticationResult', $result);

        return $next($request, $response);
    }
}
