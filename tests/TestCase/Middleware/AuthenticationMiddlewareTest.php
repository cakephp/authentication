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
use Authentication\Authenticator\ResultInterface;
use Authentication\IdentityInterface;
use Authentication\Middleware\AuthenticationMiddleware;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Http\BaseApplication;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;
use Firebase\JWT\JWT;
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

    public function testApplicationAuthenticationApi()
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

        $middleware = new AuthenticationMiddleware($this->application, 'api');

        $request = $middleware($request, $response, $next);

        /* @var $service AuthenticationService */
        $service = $request->getAttribute('authentication');
        $this->assertInstanceOf(AuthenticationService::class, $service);

        $this->assertTrue($service->identifiers()->has('Token'));
        $this->assertTrue($service->authenticators()->has('Token'));
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

        $application = $this->getMockBuilder(BaseApplication::class)
            ->disableOriginalConstructor()
            ->setMethods(['authentication', 'middleware'])
            ->getMock();

        $application->expects($this->once())
            ->method('authentication')
            ->with(
                $this->isInstanceOf(AuthenticationServiceInterface::class),
                $request,
                $response
            )
            ->willReturn($service);

        $middleware = new AuthenticationMiddleware($application);

        $middleware($request, $response, $next);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Subject must be an instance of `Authentication\AuthenticationServiceInterface` or `Cake\Core\HttpApplicationInterface`, `stdClass` given.
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
     * @expectedException RuntimeException
     * @expectedExceptionMessage Method `authentication` has not been defined in your `Application` class.
     */
    public function testInvalidApplication()
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

        $application = $this->createMock(BaseApplication::class);
        $middleware = new AuthenticationMiddleware($application);
        $middleware($request, $response, $next);
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage Method `authenticationMissing` for `missing` authentication service has not been defined in your `Application` class.
     */
    public function testMissingMethod()
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

        $middleware = new AuthenticationMiddleware($this->application, 'missing');
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
