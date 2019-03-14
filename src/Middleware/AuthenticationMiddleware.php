<?php
declare(strict_types=1);
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

use Authentication\AuthenticationServiceInterface;
use Authentication\AuthenticationServiceProviderInterface;
use Authentication\Authenticator\UnauthenticatedException;
use Authentication\Authenticator\UnauthorizedException;
use Cake\Core\InstanceConfigTrait;
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
    use InstanceConfigTrait;

    /**
     * Configuration options
     *
     * - `identityAttribute` - The request attribute to store the identity in.
     * - `name` the application hook method to call. Will be prefixed with `authentication`
     * - `unauthenticatedRedirect` - The URL to redirect unauthenticated errors to. See
     *    AuthenticationComponent::allowUnauthenticated()
     * - `queryParam` - Set to true to have unauthenticated redirects contain a `redirect` query string
     *   parameter with the previously blocked URL.
     */
    protected $_defaultConfig = [
        'identityAttribute' => 'identity',
        'name' => null,
        'unauthenticatedRedirect' => null,
        'queryParam' => null,
    ];

    /**
     * Authentication service or application instance.
     *
     * @var \Authentication\AuthenticationServiceInterface|\Authentication\AuthenticationServiceProviderInterface
     */
    protected $subject;

    /**
     * Constructor
     *
     * @param \Authentication\AuthenticationServiceInterface|\Authentication\AuthenticationServiceProviderInterface $subject Authentication service or application instance.
     * @param array|string $config Array of configuration settings or string with authentication service provider name.
     * @throws \InvalidArgumentException When invalid subject has been passed.
     */
    public function __construct($subject, $config = null)
    {
        if (is_string($config)) {
            $config = ['name' => $config];
        }
        $this->setConfig($config);

        if (!($subject instanceof AuthenticationServiceInterface) &&
            !($subject instanceof AuthenticationServiceProviderInterface)
            ) {
            $expected = implode('` or `', [
                AuthenticationServiceInterface::class,
                AuthenticationServiceProviderInterface::class,
            ]);
            $type = is_object($subject) ? get_class($subject) : gettype($subject);
            $message = sprintf('Subject must be an instance of `%s`, `%s` given.', $expected, $type);

            throw new InvalidArgumentException($message);
        }

        $this->subject = $subject;
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
        $request = $request->withAttribute($this->getConfig('identityAttribute'), $service->getIdentity());
        $request = $request->withAttribute('authentication', $service);
        $request = $request->withAttribute('authenticationResult', $result['result']);

        $response = $result['response'];

        try {
            return $next($request, $response);
        } catch (UnauthenticatedException $e) {
            $target = $this->getConfig('unauthenticatedRedirect');
            if ($target) {
                $url = $this->getRedirectUrl($target, $request);

                return $response
                    ->withStatus(302)
                    ->withHeader('Location', $url);
            }
            throw $e;
        }
    }

    /**
     * Returns redirect URL.
     *
     * @param string $target Redirect target.
     * @param \Psr\Http\Message\ServerRequestInterface $request Request instance.
     * @return string
     */
    protected function getRedirectUrl($target, ServerRequestInterface $request)
    {
        $param = $this->getConfig('queryParam');
        if ($param === null) {
            return $target;
        }

        $uri = $request->getUri();
        if (property_exists($uri, 'base')) {
            $uri = $uri->withPath($uri->base . $uri->getPath());
        }
        $query = urlencode($param) . '=' . urlencode((string)$uri);

        if (strpos($target, '?') !== false) {
            $query = '&' . $query;
        } else {
            $query = '?' . $query;
        }

        return $target . $query;
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
        $subject = $this->subject;

        if ($subject instanceof AuthenticationServiceProviderInterface) {
            $subject = $this->subject->getAuthenticationService($request, $response);
        }

        if (!$subject instanceof AuthenticationServiceInterface) {
            $type = is_object($subject) ? get_class($subject) : gettype($subject);
            $message = sprintf(
                'Service provided by a subject must be an instance of `%s`, `%s` given.',
                AuthenticationServiceInterface::class,
                $type
            );

            throw new RuntimeException($message);
        }

        return $subject;
    }
}
