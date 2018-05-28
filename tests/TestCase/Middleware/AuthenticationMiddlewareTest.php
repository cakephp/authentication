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
        'core.auth_users',
        'core.users'
    ];

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        parent::setUp();
        $this->service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password'
            ],
            'authenticators' => [
                'Authentication.Form'
            ]
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
            return $request;
        };

        $middleware = new AuthenticationMiddleware($this->application);
        $expected = 'identity';
        $actual = $middleware->getConfig("identityAttribute");
        $this->assertEquals($expected, $actual);

        $request = $middleware($request, $response, $next);

        /* @var $service AuthenticationService */
        $service = $request->getAttribute('authentication');
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
        $response = new Response();
        $next = function ($request, $response) {
            return $request;
        };

        $provider = $this->createMock(AuthenticationServiceProviderInterface::class);
        $provider
            ->method('getAuthenticationService')
            ->willReturn($this->service);

        $middleware = new AuthenticationMiddleware($provider);
        $expected = 'identity';
        $actual = $middleware->getConfig("identityAttribute");
        $this->assertEquals($expected, $actual);

        $request = $middleware($request, $response, $next);

        /* @var $service AuthenticationService */
        $service = $request->getAttribute('authentication');
        $this->assertInstanceOf(AuthenticationService::class, $service);
        $this->assertSame($this->service, $service);

        $this->assertTrue($service->identifiers()->has('Password'));
        $this->assertTrue($service->authenticators()->has('Form'));
    }

    public function testProviderInvalidService()
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

        $app = $this->createMock(BaseApplication::class);
        $provider = $this->createMock(AuthenticationServiceProviderInterface::class);
        $provider
            ->method('getAuthenticationService')
            ->willReturn($app);

        $middleware = new AuthenticationMiddleware($provider);
        $expected = 'identity';
        $actual = $middleware->getConfig("identityAttribute");
        $this->assertEquals($expected, $actual);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Service provided by a subject must be an instance of `Authentication\AuthenticationServiceInterface`, `Mock_BaseApplication_');

        $middleware($request, $response, $next);
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
        $next = function ($request, $response) {
            return $request;
        };

        $middleware = new AuthenticationMiddleware($this->application, [
            'identityAttribute' => 'customIdentity'
        ]);

        $expected = 'customIdentity';
        $actual = $middleware->getConfig("identityAttribute");
        $this->assertEquals($expected, $actual);

        $request = $middleware($request, $response, $next);

        /* @var $service AuthenticationService */
        $service = $request->getAttribute('authentication');
        $this->assertInstanceOf(AuthenticationService::class, $service);

        $this->assertTrue($service->identifiers()->has('Password'));
        $this->assertTrue($service->authenticators()->has('Form'));
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
                'response' => $response
            ]);

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

        $middleware = new AuthenticationMiddleware($this->service);

        $next = function ($request, $response) {
            return $request;
        };

        $request = $middleware($request, $response, $next);
        $identity = $request->getAttribute('identity');
        $service = $request->getAttribute('authentication');

        $this->assertInstanceOf(IdentityInterface::class, $identity);
        $this->assertInstanceOf(AuthenticationService::class, $service);
        $this->assertTrue($service->getResult()->isValid());
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

        $middleware = new AuthenticationMiddleware($this->service, [
            'identityAttribute' => 'customIdentity'
        ]);

        $next = function ($request, $response) {
            return $request;
        };

        $request = $middleware($request, $response, $next);
        $identity = $request->getAttribute('customIdentity');
        $service = $request->getAttribute('authentication');

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
        $response = new Response();

        $middleware = new AuthenticationMiddleware($this->application);

        $next = function ($request, $response) {
            return $request;
        };

        $request = $middleware($request, $response, $next);
        $identity = $request->getAttribute('identity');
        $service = $request->getAttribute('authentication');

        $this->assertInstanceOf(IdentityInterface::class, $identity);
        $this->assertInstanceOf(AuthenticationService::class, $service);
        $this->assertTrue($service->getResult()->isValid());
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

        $middleware = new AuthenticationMiddleware($this->service);

        $next = function ($request, $response) {
            return $request;
        };

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
                'Authentication.Password'
            ],
            'authenticators' => [
                'Authentication.HttpBasic'
            ]
        ]);

        $middleware = new AuthenticationMiddleware($service);

        $next = function ($request, $response) {
            $this->fail('next layer should not be called');
        };

        $response = $middleware($request, $response, $next);
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('WWW-Authenticate'));
        $this->assertSame('', $response->getBody()->getContents());
    }

    /**
     * test unauthenticated errors being bubbled up when not caught.
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

        $middleware = new AuthenticationMiddleware($this->service, [
            'unauthenticatedRedirect' => false,
        ]);

        $next = function ($request, $response) {
            throw new UnauthenticatedException();
        };

        $this->expectException(UnauthenticatedException::class);
        $middleware($request, $response, $next);
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

        $middleware = new AuthenticationMiddleware($this->service, [
            'unauthenticatedRedirect' => '/users/login',
        ]);

        $next = function ($request, $response) {
            throw new UnauthenticatedException();
        };

        $response = $middleware($request, $response, $next);
        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('/users/login', $response->getHeaderLine('Location'));
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
            'firstname' => 'larry'
        ];

        $token = JWT::encode($data, 'secretKey');

        $this->service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password',
                'Authentication.JwtSubject'
            ],
            'authenticators' => [
                'Authentication.Form',
                'Authentication.Jwt' => [
                    'secretKey' => 'secretKey'
                ]
            ]
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
            ['token' => $token]
        );

        $response = new Response();

        $middleware = new AuthenticationMiddleware($this->service);

        $next = function ($request, $response) {
            return $request;
        };

        $request = $middleware($request, $response, $next);
        $identity = $request->getAttribute('identity');
        $service = $request->getAttribute('authentication');

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
                'Authentication.Password'
            ],
            'authenticators' => [
                'Authentication.Form',
                'Authentication.Cookie'
            ]
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
            [],
            [
                'username' => 'mariano',
                'password' => 'password',
                'remember_me' => true
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
}
