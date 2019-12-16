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

use Authentication\AuthenticationService;
use Authentication\AuthenticationServiceInterface;
use Authentication\AuthenticationServiceProviderInterface;
use Authentication\Authenticator\AuthenticationRequiredException;
use Authentication\Authenticator\StatelessInterface;
use Authentication\Authenticator\UnauthenticatedException;
use Cake\Core\InstanceConfigTrait;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Zend\Diactoros\Response;
use Zend\Diactoros\Response\RedirectResponse;
use Zend\Diactoros\Stream;

/**
 * Authentication Middleware
 */
class AuthenticationMiddleware implements MiddlewareInterface
{
    use InstanceConfigTrait;

    /**
     * Configuration options
     *
     * The following keys are deprecated and should instead be set on the AuthenticationService
     *
     * - `identityAttribute` - The request attribute to store the identity in.
     * - `unauthenticatedRedirect` - The URL to redirect unauthenticated errors to. See
     *    AuthenticationComponent::allowUnauthenticated()
     * - `queryParam` - Set to true to have unauthenticated redirects contain a `redirect` query string
     *   parameter with the previously blocked URL.
     */
    protected $_defaultConfig = [];

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
     * @param array $config Array of configuration settings.
     * @throws \InvalidArgumentException When invalid subject has been passed.
     */
    public function __construct($subject, $config = null)
    {
        $this->setConfig($config);

        if (
            !($subject instanceof AuthenticationServiceInterface) &&
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
     * @param \Psr\Http\Server\RequestHandlerInterface $handler The request handler.
     * @return \Psr\Http\Message\ResponseInterface A response.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $service = $this->getAuthenticationService($request);

        try {
            $result = $service->authenticate($request);
        } catch (AuthenticationRequiredException $e) {
            $body = new Stream('php://memory', 'rw');
            $body->write($e->getBody());
            $response = new Response();
            $response = $response->withStatus((int)$e->getCode())
                ->withBody($body);
            foreach ($e->getHeaders() as $header => $value) {
                $response = $response->withHeader($header, $value);
            }

            return $response;
        }

        $request = $request->withAttribute($service->getIdentityAttribute(), $service->getIdentity());
        $request = $request->withAttribute('authentication', $service);
        $request = $request->withAttribute('authenticationResult', $result);

        try {
            $response = $handler->handle($request);
            $authenticator = $service->getAuthenticationProvider();

            if ($authenticator !== null && !$authenticator instanceof StatelessInterface) {
                $return = $service->persistIdentity($request, $response, $result->getData());
                $response = $return['response'];
            }
        } catch (UnauthenticatedException $e) {
            $url = $service->getUnauthenticatedRedirectUrl($request);
            if ($url) {
                return new RedirectResponse($url);
            }
            throw $e;
        }

        return $response;
    }

    /**
     * Returns AuthenticationServiceInterface instance.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Server request.
     * @return \Authentication\AuthenticationServiceInterface
     * @throws \RuntimeException When authentication method has not been defined.
     */
    protected function getAuthenticationService(ServerRequestInterface $request): AuthenticationServiceInterface
    {
        $subject = $this->subject;

        if ($subject instanceof AuthenticationServiceProviderInterface) {
            $subject = $subject->getAuthenticationService($request);
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
        $forwardKeys = ['identityAttribute', 'unauthenticatedRedirect', 'queryParam'];
        foreach ($forwardKeys as $key) {
            $value = $this->getConfig($key);
            if ($value) {
                deprecationWarning(
                    "The `{$key}` configuration key on AuthenticationMiddleware is deprecated. " .
                    "Instead set the `{$key}` on your AuthenticationService instance."
                );
                if ($subject instanceof AuthenticationService) {
                    $subject->setConfig($key, $value);
                } else {
                    throw new RuntimeException(
                        'Could not forward configuration to authentication service as ' .
                        'it does not implement `getConfig()`'
                    );
                }
            }
        }

        return $subject;
    }
}
