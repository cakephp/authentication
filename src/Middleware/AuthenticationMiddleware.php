<?php
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         1.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Middleware;

use Authentication\AuthenticationService;
use Authentication\AuthenticationServiceInterface;
use Authentication\Authenticator\UnauthorizedException;
use Cake\Core\HttpApplicationInterface;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Zend\Diactoros\Stream;

/**
 * Authentication Middleware
 */
class AuthenticationMiddleware
{

    /**
     * Authentication service or application instance.
     *
     * @var \Authentication\AuthenticationServiceInterface|\Cake\Core\HttpApplicationInterface
     */
    protected $subject;

    /**
     * Authentication service provider name.
     *
     * @var string
     */
    protected $name;

    /**
     * Constructor
     *
     * @param \Authentication\AuthenticationServiceInterface|\Cake\Core\HttpApplicationInterface $subject Authentication service or application instance.
     * @param string|null $name Authentication service provider name.
     * @throws \InvalidArgumentException When invalid subject has been passed.
     */
    public function __construct($subject, $name = null)
    {
        if (!$subject instanceof AuthenticationServiceInterface && !$subject instanceof HttpApplicationInterface) {
            $expected = implode('` or `', [
                AuthenticationServiceInterface::class,
                HttpApplicationInterface::class
            ]);
            $type = is_object($subject) ? get_class($subject) : gettype($subject);
            $message = sprintf('Subject must be an instance of `%s`, `%s` given.', $expected, $type);

            throw new InvalidArgumentException($message);
        }

        $this->subject = $subject;
        $this->name = $name;
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
        $service = $this->getAuthenticationService($request, $response);
        try {
            $result = $service->authenticate($request, $response);
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

        $request = $result['request'];
        $request = $request->withAttribute('identity', $service->getIdentity());
        $request = $request->withAttribute('authentication', $service);
        $request = $request->withAttribute('authenticationResult', $result['result']);

        $response = $result['response'];

        return $next($request, $response);
    }

    /**
     * Returns AuthenticationServiceInterface instance.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Server request.
     * @param \Psr\Http\Message\ResponseInterface $response Response.
     * @return \Authentication\AuthenticationServiceInterface
     * @throws \RuntimeException When authentication method has not been defined.
     */
    protected function getAuthenticationService($request, $response)
    {
        if ($this->subject instanceof AuthenticationServiceInterface) {
            return $this->subject;
        }

        $method = 'authentication' . ucfirst($this->name);
        if (!method_exists($this->subject, $method)) {
            if (strlen($this->name)) {
                $message = sprintf('Method `%s` for `%s` authentication service has not been defined in your `Application` class.', $method, $this->name);
            } else {
                $message = sprintf('Method `%s` has not been defined in your `Application` class.', $method);
            }
            throw new RuntimeException($message);
        }

        $service = new AuthenticationService();

        return $this->subject->$method($service, $request, $response);
    }
}
