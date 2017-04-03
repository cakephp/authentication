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
namespace Authentication\Test\TestCase\Middleware;

use Authentication\AuthenticationService;
use Authentication\Middleware\AuthenticationMiddleware;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Datasource\EntityInterface;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;
use Firebase\JWT\JWT;

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

        $this->assertInstanceOf(EntityInterface::class, $identity);
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

        $this->assertInstanceOf(EntityInterface::class, $identity);
        $this->assertInstanceOf(AuthenticationService::class, $service);
        $this->assertTrue($service->getResult()->isValid());
        $this->assertEquals($data, $identity->toArray());
    }
}
