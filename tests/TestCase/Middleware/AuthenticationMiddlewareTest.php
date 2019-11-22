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
namespace Authentication\Test\TestCase\Middleware;

use Authentication\AuthenticationService;
use Authentication\AuthenticationServiceInterface;
use Authentication\AuthenticationServiceProviderInterface;
use Authentication\Authenticator\ResultInterface;
use Authentication\Authenticator\UnauthenticatedException;
use Authentication\IdentityInterface;
use Authentication\Middleware\AuthenticationMiddleware;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Http\BaseApplication;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;
use Firebase\JWT\JWT;
use RuntimeException;
use TestApp\Application;

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
    public function setUp()
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
        $response = new Response();
        $next = function ($request, $response) {
            /* @var $service AuthenticationService */
            $service = $request->getAttribute('authentication');
            $this->assertInstanceOf(AuthenticationService::class, $service);

            $this->assertTrue($service->identifiers()->has('Password'));
            $this->assertTrue($service->authenticators()->has('Form'));
            $this->assertEquals('identity', $service->getConfig("identityAttribute"));

            return $response;
        };

        $middleware = new AuthenticationMiddleware($this->application);
        $middleware($request, $response, $next);
    }

    public function testProviderAuthentication()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $response = new Response();
        $next = function ($request, $response) {
            /* @var $service AuthenticationService */
            $service = $request->getAttribute('authentication');
            $this->assertInstanceOf(AuthenticationService::class, $service);
            $this->assertSame($this->service, $service);

            $this->assertTrue($service->identifiers()->has('Password'));
            $this->assertTrue($service->authenticators()->has('Form'));
            $this->assertSame('identity', $service->getConfig("identityAttribute"));

            return $response;
        };

        $provider = $this->createMock(AuthenticationServiceProviderInterface::class);
        $provider
            ->method('getAuthenticationService')
            ->willReturn($this->service);

        $middleware = new AuthenticationMiddleware($provider);
        $middleware($request, $response, $next);
    }

    public function testProviderInvalidService()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $response = new Response();
        $app = $this->createMock(BaseApplication::class);
        $provider = $this->createMock(AuthenticationServiceProviderInterface::class);
        $provider
            ->method('getAuthenticationService')
            ->willReturn($app);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Service provided by a subject must be an instance of `Authentication\AuthenticationServiceInterface`, `Mock_BaseApplication_');

        $next = function ($request, $response) {
            return $response;
        };
        $middleware = new AuthenticationMiddleware($provider);
        $middleware($request, $response, $next);
    }

    public function testApplicationAuthenticationRequestResponse()
    {
        $request = ServerRequestFactory::fromGlobals();
        $response = new Response();
        $next = function ($request, $response) {
            return $request;
        };

        $service = $this->createMock(AuthenticationServiceInterface::class);

        $service->method('authenticate')
            ->willReturn([
                'result' => $this->createMock(ResultInterface::class),
                'request' => $request,
                'response' => $response,
            ]);
        $service->method('getIdentityAttribute')->willReturn('identity');

        $application = $this->getMockBuilder(Application::class)
            ->disableOriginalConstructor()
            ->setMethods(['getAuthenticationService', 'middleware'])
            ->getMock();

        $application->expects($this->once())
            ->method('getAuthenticationService')
            ->with(
                $request,
                $response
            )
            ->willReturn($service);

        $middleware = new AuthenticationMiddleware($application);
        $middleware($request, $response, $next);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Subject must be an instance of `Authentication\AuthenticationServiceInterface` or `Authentication\AuthenticationServiceProviderInterface`, `stdClass` given.
     */
    public function testInvalidSubject()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $response = new Response();
        $next = function ($request, $response) {
            return $request;
        };

        $middleware = new AuthenticationMiddleware(new \stdClass());
        $middleware($request, $response, $next);
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
        $response = new Response();

        $next = function ($request, $response) {
            $identity = $request->getAttribute('identity');
            $service = $request->getAttribute('authentication');

            $this->assertInstanceOf(IdentityInterface::class, $identity);
            $this->assertInstanceOf(AuthenticationService::class, $service);
            $this->assertTrue($service->getResult()->isValid());

            return $response;
        };

        $middleware = new AuthenticationMiddleware($this->service);
        $middleware($request, $response, $next);
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
        $next = function ($request) {
            $this->assertNotEmpty($request->getAttribute('identity'), 'Should have an identity present.');
            $this->assertNotEmpty($request->getSession()->read('Auth'), 'Should have session data.');
            $response = new Response();
            $result = $request->getAttribute('authentication')->clearIdentity($request, $response);

            return $result['response'];
        };

        $middleware = new AuthenticationMiddleware($service);
        $response = new Response();
        $response = $middleware($request, $response, $next);
        $this->assertNull($service->getAuthenticationProvider(), 'no authenticator anymore.');
        $this->assertNull($request->getSession()->read('Auth'), 'no more session data.');
    }

    /**
     * test middleware call with custom identity attribute
     *
     * @return void
     */
    public function testApplicationAuthenticationCustomIdentityAttribute()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $response = new Response();

        $next = function ($req, $resp) {
            /* @var $service AuthenticationService */
            $service = $req->getAttribute('authentication');
            $this->assertInstanceOf(AuthenticationService::class, $service);
            $this->assertEquals('customIdentity', $service->getConfig("identityAttribute"));
            $this->assertTrue($service->identifiers()->has('Password'));
            $this->assertTrue($service->authenticators()->has('Form'));

            return $resp;
        };
        $this->deprecated(function () use ($request, $response, $next) {
            // Using the middleware option requires this test to use deprecated()
            $middleware = new AuthenticationMiddleware($this->application, [
                'identityAttribute' => 'customIdentity',
            ]);
            $middleware($request, $response, $next);
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
        $response = new Response();

        $this->service->setConfig('identityAttribute', 'customIdentity');
        $middleware = new AuthenticationMiddleware($this->service);

        $next = function ($request, $response) {
            $identity = $request->getAttribute('customIdentity');
            $service = $request->getAttribute('authentication');

            $this->assertInstanceOf(IdentityInterface::class, $identity);
            $this->assertInstanceOf(AuthenticationService::class, $service);
            $this->assertTrue($service->getResult()->isValid());

            return $response;
        };
        $middleware($request, $response, $next);
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
        $response = new Response();

        $middleware = new AuthenticationMiddleware($this->application);

        $next = function ($request, $response) {
            $identity = $request->getAttribute('identity');
            $service = $request->getAttribute('authentication');

            $this->assertInstanceOf(IdentityInterface::class, $identity);
            $this->assertInstanceOf(AuthenticationService::class, $service);
            $this->assertTrue($service->getResult()->isValid());

            return $response;
        };
        $middleware($request, $response, $next);
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
        $response = new Response();

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

        $next = function ($request, $response) {
            $service = $request->getAttribute('authentication');
            $this->assertInstanceOf(AuthenticationService::class, $service);

            $this->assertTrue($service->getResult()->isValid());
            $this->assertNull($request->getAttribute('session')->read('Auth'));

            return $response;
        };
        $middleware($request, $response, $next);

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
        $response = new Response();

        $next = function ($request, $response) {
            return $request;
        };

        $middleware = new AuthenticationMiddleware($this->service);
        $request = $middleware($request, $response, $next);
        $identity = $request->getAttribute('identity');
        $service = $request->getAttribute('authentication');

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
        $response = new Response();

        $service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password',
            ],
            'authenticators' => [
                'Authentication.HttpBasic',
            ],
        ]);

        $next = function ($request, $response) {
            $this->fail('next layer should not be called');
        };

        $middleware = new AuthenticationMiddleware($service);
        $response = $middleware($request, $response, $next);
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
        $response = new Response();

        $next = function ($request, $response) {
            throw new UnauthenticatedException();
        };

        $this->expectException(UnauthenticatedException::class);
        $this->expectExceptionCode(401);

        $middleware = new AuthenticationMiddleware($this->service, [
            'unauthenticatedRedirect' => false,
        ]);
        $this->deprecated(function () use ($middleware, $request, $response, $next) {
            $middleware($request, $response, $next);
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
        $response = new Response();

        $next = function ($request, $response) {
            throw new UnauthenticatedException();
        };

        $this->service->setConfig('unauthenticatedRedirect', false);
        $middleware = new AuthenticationMiddleware($this->service);

        $this->expectException(UnauthenticatedException::class);
        $this->expectExceptionCode(401);

        $this->deprecated(function () use ($middleware, $request, $response, $next) {
            $middleware($request, $response, $next);
        });
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
        $response = new Response();

        $next = function ($request, $response) {
            throw new UnauthenticatedException();
        };

        $middleware = new AuthenticationMiddleware($this->service, [
            'unauthenticatedRedirect' => '/users/login',
        ]);
        $this->deprecated(function () use ($middleware, $request, $response, $next) {
            $response = $middleware($request, $response, $next);
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
        $response = new Response();

        $next = function ($request, $response) {
            throw new UnauthenticatedException();
        };

        $this->service->setConfig('unauthenticatedRedirect', '/users/login');
        $middleware = new AuthenticationMiddleware($this->service);
        $response = $middleware($request, $response, $next);

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
        $response = new Response();

        $next = function ($request, $response) {
            throw new UnauthenticatedException();
        };

        $middleware = new AuthenticationMiddleware($this->service, [
            'unauthenticatedRedirect' => '/users/login',
            'queryParam' => 'redirect',
        ]);

        $this->deprecated(function () use ($middleware, $request, $response, $next) {
            $response = $middleware($request, $response, $next);
            $this->assertSame(302, $response->getStatusCode());
            $this->assertSame('/users/login?redirect=%2Ftestpath', $response->getHeaderLine('Location'));
            $this->assertSame('', $response->getBody() . '');
        });
    }

    /**
     * test unauthenticated errors being converted into redirects with a query param when configured
     *
     * @return void
     */
    public function testUnauthenticatedRedirectWithQuery()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
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
        $middleware = new AuthenticationMiddleware($this->service);
        $response = $middleware($request, $response, $next);

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
        $response = new Response();

        $next = function ($request, $response) {
            throw new UnauthenticatedException();
        };

        $this->service->setConfig([
            'unauthenticatedRedirect' => '/users/login?hello=world',
            'queryParam' => 'redirect',
        ]);
        $middleware = new AuthenticationMiddleware($this->service);
        $response = $middleware($request, $response, $next);

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
        $response = new Response();
        $next = function ($request, $response) {
            throw new UnauthenticatedException();
        };

        $this->service->setConfig([
            'unauthenticatedRedirect' => '/users/login?hello=world#frag',
            'queryParam' => 'redirect',
        ]);
        $middleware = new AuthenticationMiddleware($this->service);
        $response = $middleware($request, $response, $next);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(
            '/users/login?hello=world&redirect=%2Ftestpath#frag',
            $response->getHeaderLine('Location')
        );
        $this->assertSame('', $response->getBody() . '');
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

        $response = new Response();
        $next = function ($request, $response) {
            throw new UnauthenticatedException();
        };

        $this->service->setConfig([
            'unauthenticatedRedirect' => '/users/login',
            'queryParam' => 'redirect',
        ]);
        $middleware = new AuthenticationMiddleware($this->service);
        $response = $middleware($request, $response, $next);

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
        $middleware = new AuthenticationMiddleware($this->service);

        $response = $middleware($request, $response, $next);
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
        $response = new Response();

        $next = function ($request, $response) use ($data) {
            $identity = $request->getAttribute('identity');
            $service = $request->getAttribute('authentication');

            $this->assertInstanceOf(IdentityInterface::class, $identity);
            $this->assertInstanceOf(AuthenticationService::class, $service);
            $this->assertTrue($service->getResult()->isValid());
            $this->assertEquals($data, $identity->getOriginalData()->getArrayCopy());

            return $response;
        };
        $middleware = new AuthenticationMiddleware($this->service);
        $middleware($request, $response, $next);
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

        $response = new Response();

        $middleware = new AuthenticationMiddleware($this->service);

        $next = function ($request, $response) {
            return $response;
        };

        $response = $middleware($request, $response, $next);

        $this->assertContains('CookieAuth=%5B%22mariano%22', $response->getHeaderLine('Set-Cookie'));
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
        $this->deprecated(function () use ($request, $response, $next, $middleware) {
            $response = $middleware($request, $response, $next);
        });
        $this->assertSame('user', $service->getConfig('identityAttribute'));
        $this->assertSame('redirect', $service->getConfig('queryParam'));
        $this->assertSame('/login', $service->getConfig('unauthenticatedRedirect'));
    }
}
