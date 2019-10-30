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
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link https://cakephp.org CakePHP(tm) Project
 * @since 1.0.0
 * @license https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Test\TestCase\Middleware;

use Authentication\AuthenticationService;
use Authentication\AuthenticationServiceInterface;
use Authentication\AuthenticationServiceProviderInterface;
use Authentication\Authenticator\ResultInterface;
use Authentication\Authenticator\UnauthenticatedException;
use Authentication\IdentityInterface;
use Authentication\Middleware\AuthenticationMiddleware;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;
use Firebase\JWT\JWT;
use TestApp\Application;
use TestApp\Http\TestRequestHandler;

class AuthenticationMiddlewareTest extends TestCase
{
    /**
     * Fixtures
     */
    public $fixtures = [
        'core.AuthUsers',
        'core.Users',
    ];

    /**
     * @inheritdoc
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password',
            ],
            'authenticators' => [
                'Authentication.Form',
            ],
        ]);
        $this->application = new Application('config');
    }

    public function testApplicationAuthentication()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $handler = new TestRequestHandler();

        $middleware = new AuthenticationMiddleware($this->application);
        $response = $middleware->process($request, $handler);

        /** @var AuthenticationService $service */
        $service = $handler->request->getAttribute('authentication');
        $this->assertInstanceOf(AuthenticationService::class, $service);

        $this->assertTrue($service->identifiers()->has('Password'));
        $this->assertTrue($service->authenticators()->has('Form'));
    }

    public function testProviderAuthentication()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $handler = new TestRequestHandler();

        $provider = $this->createMock(AuthenticationServiceProviderInterface::class);
        $provider
            ->method('getAuthenticationService')
            ->willReturn($this->service);

        $middleware = new AuthenticationMiddleware($provider);
        $response = $middleware->process($request, $handler);

        /** @var AuthenticationService $service */
        $service = $handler->request->getAttribute('authentication');
        $this->assertInstanceOf(AuthenticationService::class, $service);
        $this->assertSame($this->service, $service);
        $this->assertSame('identity', $service->getConfig("identityAttribute"));

        $this->assertTrue($service->identifiers()->has('Password'));
        $this->assertTrue($service->authenticators()->has('Form'));
    }

    public function testApplicationAuthenticationRequestResponse()
    {
        $request = ServerRequestFactory::fromGlobals();
        $handler = new TestRequestHandler();

        $service = $this->createMock(AuthenticationServiceInterface::class);

        $service->method('authenticate')
            ->willReturn($this->createMock(ResultInterface::class));
        $service->method('getIdentityAttribute')->willReturn('identity');

        $application = $this->getMockBuilder(Application::class)
            ->disableOriginalConstructor()
            ->setMethods(['getAuthenticationService', 'middleware'])
            ->getMock();

        $application->expects($this->once())
            ->method('getAuthenticationService')
            ->with($request)
            ->willReturn($service);

        $middleware = new AuthenticationMiddleware($application);
        $middleware->process($request, $handler);
    }

    public function testInvalidSubject()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Subject must be an instance of `Authentication\AuthenticationServiceInterface` or `Authentication\AuthenticationServiceProviderInterface`, `stdClass` given.');
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $handler = new TestRequestHandler();

        $middleware = new AuthenticationMiddleware(new \stdClass());
        $response = $middleware->process($request, $handler);
    }

    /**
     * testSuccessfulAuthentication
     *
     * @return void
     */
    public function testSuccessfulAuthentication()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $handler = new TestRequestHandler();

        $middleware = new AuthenticationMiddleware($this->service);
        $middleware->process($request, $handler);

        $this->assertTrue($this->service->getResult()->isValid());

        $identity = $this->service->getIdentity();
        $this->assertInstanceOf(IdentityInterface::class, $identity);
    }

    /**
     * Test that session authenticator and a clearIdentity (logout) don't
     * result in the user still being logged in.
     *
     * @return void
     */
    public function testAuthenticationAndClearIdentityInteraction()
    {
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/testpath',
        ]);
        // Setup the request with a session so we can test it being cleared
        $request->getSession()->write('Auth', ['username' => 'mariano']);
        $service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password',
            ],
            'authenticators' => [
                'Authentication.Session',
            ],
        ]);
        $handler = new TestRequestHandler(function ($request) {
            $this->assertNotEmpty($request->getAttribute('identity'), 'Should have an identity present.');
            $this->assertNotEmpty($request->getSession()->read('Auth'), 'Should have session data.');

            $response = new Response();
            $result = $request->getAttribute('authentication')->clearIdentity($request, $response);

            return $result['response'];
        });

        $middleware = new AuthenticationMiddleware($service);
        $middleware->process($request, $handler);
        $this->assertNull($service->getAuthenticationProvider(), 'no authenticator anymore.');
        $this->assertNull($request->getSession()->read('Auth'), 'no more session data.');
    }

    /**
     * test middleware call with custom identity attribute on the middleware
     *
     * @return void
     */
    public function testApplicationAuthenticationCustomIdentityAttributeDeprecatedOption()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $handler = new TestRequestHandler(function ($req) {
            /** @var \Authentication\AuthenticationService $service */
            $service = $req->getAttribute('authentication');
            $this->assertInstanceOf(AuthenticationService::class, $service);
            $this->assertEquals('customIdentity', $service->getConfig("identityAttribute"));
            $this->assertTrue($service->identifiers()->has('Password'));
            $this->assertTrue($service->authenticators()->has('Form'));

            return new Response();
        });
        $this->deprecated(function () use ($request, $handler) {
            // Using the middleware option requires this test to use deprecated()
            $middleware = new AuthenticationMiddleware($this->application, [
                'identityAttribute' => 'customIdentity',
            ]);
            $middleware->process($request, $handler);
        });
    }

    /**
     * testSuccessfulAuthentication with custom identity attribute
     *
     * @return void
     */
    public function testSuccessfulAuthenticationWithCustomIdentityAttribute()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $handler = new TestRequestHandler();

        $this->service->setConfig([
            'identityAttribute' => 'customIdentity',
        ]);
        $middleware = new AuthenticationMiddleware($this->service);

        $response = $middleware->process($request, $handler);
        $identity = $handler->request->getAttribute('customIdentity');
        $service = $handler->request->getAttribute('authentication');

        $this->assertInstanceOf(IdentityInterface::class, $identity);
        $this->assertInstanceOf(AuthenticationService::class, $service);
        $this->assertTrue($service->getResult()->isValid());
    }

    /**
     * testSuccessfulAuthenticationApplicationHook
     *
     * @return void
     */
    public function testSuccessfulAuthenticationApplicationHook()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $handler = new TestRequestHandler();

        $middleware = new AuthenticationMiddleware($this->application);

        $response = $middleware->process($request, $handler);
        $identity = $handler->request->getAttribute('identity');
        $service = $handler->request->getAttribute('authentication');

        $this->assertInstanceOf(IdentityInterface::class, $identity);
        $this->assertInstanceOf(AuthenticationService::class, $service);
        $this->assertTrue($service->getResult()->isValid());
    }

    /**
     * test success persist to session
     *
     * @return void
     */
    public function testSuccessfulAuthenticationPersistIdentity()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $this->service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password',
            ],
            'authenticators' => [
                'Authentication.Form',
                'Authentication.Session',
            ],
        ]);
        $middleware = new AuthenticationMiddleware($this->service);

        $handler = new TestRequestHandler(function ($request) {
            $service = $request->getAttribute('authentication');
            $this->assertNull($request->getAttribute('session')->read('Auth'));

            return new Response();
        });
        $middleware->process($request, $handler);

        $this->assertTrue($this->service->getResult()->isValid());

        // After the middleware is done session should be populated
        $this->assertSame('mariano', $request->getAttribute('session')->read('Auth.username'));
    }

    /**
     * testNonSuccessfulAuthentication
     *
     * @return void
     */
    public function testNonSuccessfulAuthentication()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'invalid', 'password' => 'invalid']
        );
        $handler = new TestRequestHandler();

        $middleware = new AuthenticationMiddleware($this->service);

        $response = $middleware->process($request, $handler);
        $identity = $handler->request->getAttribute('identity');
        $service = $handler->request->getAttribute('authentication');

        $this->assertNull($identity);
        $this->assertInstanceOf(AuthenticationService::class, $service);
        $this->assertFalse($service->getResult()->isValid());
    }

    /**
     * test non-successful auth with a challenger
     *
     * @return void
     */
    public function testNonSuccessfulAuthenticationWithChallenge()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath', 'SERVER_NAME' => 'localhost'],
            [],
            ['username' => 'invalid', 'password' => 'invalid']
        );
        $handler = new TestRequestHandler();

        $service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password',
            ],
            'authenticators' => [
                'Authentication.HttpBasic',
            ],
        ]);

        $middleware = new AuthenticationMiddleware($service);

        $response = $middleware->process($request, $handler);
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('WWW-Authenticate'));
        $this->assertSame('', $response->getBody()->getContents());
    }

    /**
     * test unauthenticated errors being bubbled up when not caught
     * using backwards compatible middleware configuration.
     *
     * @return void
     */
    public function testUnauthenticatedNoRedirectMiddlewareConfiguration()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $handler = new TestRequestHandler(function ($request) {
            throw new UnauthenticatedException();
        });

        $this->expectException(UnauthenticatedException::class);
        $this->expectExceptionCode(401);

        $middleware = new AuthenticationMiddleware($this->service, [
            'unauthenticatedRedirect' => false,
        ]);
        $this->deprecated(function () use ($middleware, $request, $handler) {
            $middleware->process($request, $handler);
        });
    }

    /**
     * test unauthenticated errors being bubbled up when not caught
     *
     * @return void
     */
    public function testUnauthenticatedNoRedirect()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );

        $this->expectException(UnauthenticatedException::class);
        $this->expectExceptionCode(401);

        $handler = new TestRequestHandler(function () {
            throw new UnauthenticatedException();
        });
        $middleware = new AuthenticationMiddleware($this->service);
        $response = $middleware->process($request, $handler);
    }

    /**
     * test unauthenticated errors being converted into redirects when configured
     * at the middleware (backwards compat)
     *
     * @return void
     */
    public function testUnauthenticatedRedirectBackwardsCompatibleOption()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $handler = new TestRequestHandler(function ($request) {
            throw new UnauthenticatedException();
        });
        $middleware = new AuthenticationMiddleware($this->service, [
            'unauthenticatedRedirect' => '/users/login',
        ]);
        $this->deprecated(function () use ($middleware, $request, $handler) {
            $response = $middleware->process($request, $handler);
            $this->assertSame(302, $response->getStatusCode());
            $this->assertSame('/users/login', $response->getHeaderLine('Location'));
            $this->assertSame('', $response->getBody() . '');
        });
    }

    /**
     * test unauthenticated errors being converted into redirects when configured
     *
     * @return void
     */
    public function testUnauthenticatedRedirect()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $handler = new TestRequestHandler(function ($request) {
            throw new UnauthenticatedException();
        });

        $this->service->setConfig('unauthenticatedRedirect', '/users/login');
        $middleware = new AuthenticationMiddleware($this->service);
        $response = $middleware->process($request, $handler);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/users/login', $response->getHeaderLine('Location'));
        $this->assertSame('', $response->getBody() . '');
    }

    /**
     * test unauthenticated errors being converted into redirects with a query param when configured
     * using backwards compatible configuration.
     *
     * @return void
     */
    public function testUnauthenticatedRedirectWithQueryBackwardsCompatible()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $handler = new TestRequestHandler(function ($request) {
            throw new UnauthenticatedException();
        });

        $next = function ($request, $response) {
            throw new UnauthenticatedException();
        };

        $this->service->setConfig([
            'unauthenticatedRedirect' => '/users/login',
            'queryParam' => 'redirect',
        ]);
        $middleware = new AuthenticationMiddleware($this->service);

        $response = $middleware->process($request, $handler);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/users/login?redirect=%2Ftestpath', $response->getHeaderLine('Location'));
        $this->assertSame('', $response->getBody() . '');
    }

    /**
     * test unauthenticated errors being converted into redirects with a query param when configured
     *
     * @return void
     */
    public function testUnauthenticatedRedirectWithExistingQuery()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $handler = new TestRequestHandler(function ($request) {
            throw new UnauthenticatedException();
        });

        $this->service->setConfig([
            'unauthenticatedRedirect' => '/users/login?hello=world',
            'queryParam' => 'redirect',
        ]);
        $middleware = new AuthenticationMiddleware($this->service);

        $response = $middleware->process($request, $handler);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/users/login?hello=world&redirect=%2Ftestpath', $response->getHeaderLine('Location'));
        $this->assertSame('', $response->getBody() . '');
    }

    /**
     * test unauthenticated errors being converted into redirects with a query param when configured
     *
     * @return void
     */
    public function testUnauthenticatedRedirectWithFragment()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );

        $middleware = new AuthenticationMiddleware($this->service);

        $handler = new TestRequestHandler(function ($request) {
            throw new UnauthenticatedException();
        });

        $this->service->setConfig([
            'unauthenticatedRedirect' => '/users/login?hello=world#frag',
            'queryParam' => 'redirect',
        ]);
        $response = $middleware->process($request, $handler);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(
            '/users/login?hello=world&redirect=%2Ftestpath#frag',
            $response->getHeaderLine('Location')
        );
        $this->assertSame('', (string)$response->getBody());
    }

    /**
     * test unauthenticated errors being converted into redirects when configured, with a different URL base
     *
     * @return void
     */
    public function testUnauthenticatedRedirectWithBase()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $uri = $request->getUri();
        $uri->base = '/base';
        $request = $request->withUri($uri);
        $handler = new TestRequestHandler(function ($request) {
            throw new UnauthenticatedException();
        });

        $this->service->setConfig([
            'unauthenticatedRedirect' => '/users/login',
            'queryParam' => 'redirect',
        ]);
        $middleware = new AuthenticationMiddleware($this->service);

        $response = $middleware->process($request, $handler);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/users/login?redirect=%2Fbase%2Ftestpath', $response->getHeaderLine('Location'));
        $this->assertSame('', $response->getBody() . '');
    }

    /**
     * test unauthenticated redirects preserving path and query
     *
     * @return void
     */
    public function testUnauthenticatedRedirectWithQueryStringData()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath', 'QUERY_STRING' => 'a=b&c=d'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $response = new Response();
        $next = function ($request, $response) {
            throw new UnauthenticatedException();
        };

        $this->service->setConfig([
            'unauthenticatedRedirect' => '/users/login',
            'queryParam' => 'redirect',
        ]);

        $handler = new TestRequestHandler(function ($request) {
            throw new UnauthenticatedException();
        });
        $middleware = new AuthenticationMiddleware($this->service);

        $response = $middleware->process($request, $handler);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/users/login?redirect=%2Ftestpath%3Fa%3Db%26c%3Dd', $response->getHeaderLine('Location'));
        $this->assertSame('', $response->getBody() . '');
    }

    /**
     * testJwtTokenAuthorizationThroughTheMiddlewareStack
     *
     * @return void
     */
    public function testJwtTokenAuthorizationThroughTheMiddlewareStack()
    {
        $data = [
            'sub' => 3,
            'id' => 3,
            'username' => 'larry',
            'firstname' => 'larry',
        ];

        $token = JWT::encode($data, 'secretKey');

        $this->service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password',
                'Authentication.JwtSubject',
            ],
            'authenticators' => [
                'Authentication.Form',
                'Authentication.Jwt' => [
                    'secretKey' => 'secretKey',
                ],
            ],
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
            ['token' => $token]
        );
        $handler = new TestRequestHandler();
        $middleware = new AuthenticationMiddleware($this->service);

        $response = $middleware->process($request, $handler);
        $identity = $handler->request->getAttribute('identity');
        $service = $handler->request->getAttribute('authentication');

        $this->assertInstanceOf(IdentityInterface::class, $identity);
        $this->assertInstanceOf(AuthenticationService::class, $service);
        $this->assertTrue($service->getResult()->isValid());
        $this->assertEquals($data, $identity->getOriginalData()->getArrayCopy());
    }

    /**
     * testCookieAuthorizationThroughTheMiddlewareStack
     *
     * @return void
     */
    public function testCookieAuthorizationThroughTheMiddlewareStack()
    {
        $this->service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password',
            ],
            'authenticators' => [
                'Authentication.Form',
                'Authentication.Cookie',
            ],
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
            [],
            [
                'username' => 'mariano',
                'password' => 'password',
                'remember_me' => true,
            ]
        );

        $handler = new TestRequestHandler();

        $middleware = new AuthenticationMiddleware($this->service);
        $response = $middleware->process($request, $handler);

        $this->assertStringContainsString('CookieAuth=%5B%22mariano%22', $response->getHeaderLine('Set-Cookie'));
    }

    /**
     * Test that the service will inherit middleware configuration if
     * its own configuration isn't set.
     *
     * @return void
     */
    public function testServiceConfigurationFallback()
    {
        $service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password',
            ],
            'authenticators' => [
                'Authentication.Form',
            ],
        ]);
        $this->assertSame('identity', $service->getConfig('identityAttribute'));
        $this->assertNull($service->getConfig('unauthenticatedRedirect'));
        $this->assertNull($service->getConfig('queryParam'));

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
            [],
            [
                'username' => 'mariano',
                'password' => 'password',
            ]
        );
        $response = new Response();
        $middleware = new AuthenticationMiddleware($service, [
            'identityAttribute' => 'user',
            'unauthenticatedRedirect' => '/login',
            'queryParam' => 'redirect',
        ]);
        $next = function ($request, $response) {
            return $response;
        };
        $this->deprecated(function () use ($request, $middleware) {
            $handler = new TestRequestHandler();
            $response = $middleware->process($request, $handler);
        });
        $this->assertSame('user', $service->getConfig('identityAttribute'));
        $this->assertSame('redirect', $service->getConfig('queryParam'));
        $this->assertSame('/login', $service->getConfig('unauthenticatedRedirect'));
    }
}
