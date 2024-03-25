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
use Cake\Core\ContainerApplicationInterface;
use Cake\Core\ContainerInterface;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\RedirectResponse;
use Laminas\Diactoros\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Authentication Middleware
 */
class AuthenticationMiddleware implements MiddlewareInterface
{
    /**
     * Authentication service or application instance.
     *
     * @var \Authentication\AuthenticationServiceInterface|\Authentication\AuthenticationServiceProviderInterface
     */
    protected AuthenticationServiceInterface|AuthenticationServiceProviderInterface $subject;

    /**
     * The container instance from the application
     *
     * @var \Cake\Core\ContainerInterface|null
     */
    protected ?ContainerInterface $container;

    /**
     * Constructor
     *
     * @param \Authentication\AuthenticationServiceInterface|\Authentication\AuthenticationServiceProviderInterface $subject Authentication service or application instance.
     * @param \Cake\Core\ContainerInterface|null $container The container instance from the application.
     * @throws \InvalidArgumentException When invalid subject has been passed.
     */
    public function __construct(
        AuthenticationServiceInterface|AuthenticationServiceProviderInterface $subject,
        ?ContainerInterface $container = null
    ) {
        $this->subject = $subject;
        $this->container = $container;
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

        if ($this->subject instanceof ContainerApplicationInterface) {
            $container = $this->subject->getContainer();
            $container->add(AuthenticationService::class, $service);
        } elseif ($this->container) {
            $this->container->add(AuthenticationService::class, $service);
        }

        try {
            $result = $service->authenticate($request);
        } catch (AuthenticationRequiredException $e) {
            $body = new Stream('php://memory', 'rw');
            $body->write($e->getBody());
            $response = new Response();
            $response = $response->withStatus($e->getCode())
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
                /**
                 * @psalm-suppress PossiblyNullArgument
                 * @phpstan-ignore-next-line
                 */
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

        return $subject;
    }
}
