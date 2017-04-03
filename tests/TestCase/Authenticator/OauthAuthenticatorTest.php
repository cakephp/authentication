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
namespace Authentication\Test\TestCase\Authenticator;

use Authentication\Authenticator\OauthAuthenticator;
use Authentication\Identifier\IdentifierCollection;
use Authentication\Result;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\Http\ServerRequestFactory;
use Cake\Routing\Router;
use SocialConnect\Auth\Service;
use SocialConnect\Common\Http\Client\Curl;
use SocialConnect\OAuth2\Accesstoken;
use SocialConnect\OAuth2\Provider\Github;
use SocialConnect\Provider\Session\Session;

class OauthAuthenticatorTest extends TestCase
{

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'core.auth_users',
        'core.users'
    ];

    /**
     * setup
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->identifiers = new IdentifierCollection([
           'Authentication.Orm'
        ]);

        $this->sessionMock = $this->createMock(Session::class);

        Router::reload();
        Router::scope('/', function ($routes) {
            $routes->connect('/users/login', ['controller' => 'Users', 'action' => 'login']);
            $routes->connect('/users/callback', ['controller' => 'Users', 'action' => 'callback']);
            $routes->fallbacks();
        });
    }

    /**
     * testAuthenticate
     *
     * @return void
     */
    public function testAuthenticate()
    {
        $entity = new \stdClass;
        $entity->username = 'mariano';

        $accessToken = $this->createMock(Accesstoken::class);
        $accessToken->method('getToken')
            ->willReturn('1234');

        $provider = $this->createMock(Github::class);
        $provider->method('getIdentity')
            ->with($accessToken)
            ->willReturn($entity);
        $provider->method('makeAuthUrl')
            ->willReturn('example.com');
        $provider->method('getAccessTokenByRequestParameters')
            ->willReturn($accessToken);

        $authService = $this->createMock(Service::class);
        $authService->method('getprovider')
            ->willReturn($provider);
        $authService->method('getConfig')
            ->willReturn([
                'redirectUri' => 'http://app.dev/users/callback',
                'provider' => [
                    'github' => [
                        'applicationId' => '1234',
                        'applicationSecret' => '1234',
                    ]
                ]
            ]);

        $request = new ServerRequest([
            'params' => [
                'plugin' => null,
                'controller' => 'Users',
                'action' => 'callback',
                '_ext' => null,
                'pass' => [
                    'github'
                ]
            ],
            'environment' => [
                'REQUEST_URI' => '/users/callback/github/'
            ]
        ]);
        $response = new Response();

        $oauth = new OauthAuthenticator($this->identifiers, [
            'loginUrl' => [
                'controller' => 'Users',
                'action' => 'login'
            ],
            'redirectUrl' => [
                'controller' => 'Users',
                'action' => 'callback'
            ],
            'authService' => $authService
        ]);

        $result = $oauth->authenticate($request, $response);

        $this->assertInstanceOf('\Authentication\Result', $result);
        $this->assertEquals(Result::SUCCESS, $result->getCode());
    }

    /**
     * testWorngUrls
     *
     * @return void
     */
    public function testWorngUrls()
    {
        $authService = $this->createMock(Service::class);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/does-not-match']
        );
        $response = new Response();

        $oauth = new OauthAuthenticator($this->identifiers, [
            'loginUrl' => [
                'controller' => 'Users',
                'action' => 'login'
            ],
            'redirectUrl' => [
                'controller' => 'Users',
                'action' => 'callback'
            ],
            'authService' => $authService
        ]);

        $result = $oauth->authenticate($request, $response);

        $this->assertInstanceOf('\Authentication\Result', $result);
        $this->assertEquals(Result::FAILURE_OTHER, $result->getCode());
        $this->assertEquals([0 => 'Login URL or Redirect URL does not macth.'], $result->getErrors());
    }

    /**
     * testMissingCredentials
     *
     * @return void
     */
    public function testMissingCredentials()
    {
        $entity = new \stdClass;

        $accessToken = $this->createMock(Accesstoken::class);
        $accessToken->method('getToken')
            ->willReturn('1234');

        $provider = $this->createMock(Github::class);
        $provider->method('getIdentity')
            ->with($accessToken)
            ->willReturn($entity);
        $provider->method('makeAuthUrl')
            ->willReturn('example.com');
        $provider->method('getAccessTokenByRequestParameters')
            ->willReturn($accessToken);

        $authService = $this->createMock(Service::class);
        $authService->method('getprovider')
            ->willReturn($provider);
        $authService->method('getConfig')
            ->willReturn([
                'redirectUri' => 'http://app.dev/users/callback',
                'provider' => [
                    'github' => [
                        'applicationId' => '1234',
                        'applicationSecret' => '1234',
                    ]
                ]
            ]);

        $request = new ServerRequest([
            'params' => [
                'plugin' => null,
                'controller' => 'Users',
                'action' => 'callback',
                '_ext' => null,
                'pass' => [
                    'github'
                ]
            ],
            'environment' => [
                'REQUEST_URI' => '/users/callback/github/'
            ]
        ]);
        $response = new Response();

        $oauth = new OauthAuthenticator($this->identifiers, [
            'loginUrl' => [
                'controller' => 'Users',
                'action' => 'login'
            ],
            'redirectUrl' => [
                'controller' => 'Users',
                'action' => 'callback'
            ],
            'authService' => $authService
        ]);

        $result = $oauth->authenticate($request, $response);

        $this->assertInstanceOf('\Authentication\Result', $result);
        $this->assertEquals(Result::FAILURE_CREDENTIALS_NOT_FOUND, $result->getCode());
        $this->assertEquals([0 => 'Login credentials not found.'], $result->getErrors());
    }

    /**
     * testIdentityNotFound
     *
     * @return void
     */
    public function testIdentityNotFound()
    {
        $entity = new \stdClass;
        $entity->username = 'foo';

        $accessToken = $this->createMock(Accesstoken::class);
        $accessToken->method('getToken')
            ->willReturn('1234');

        $provider = $this->createMock(Github::class);
        $provider->method('getIdentity')
            ->with($accessToken)
            ->willReturn($entity);
        $provider->method('makeAuthUrl')
            ->willReturn('example.com');
        $provider->method('getAccessTokenByRequestParameters')
            ->willReturn($accessToken);

        $authService = $this->createMock(Service::class);
        $authService->method('getprovider')
            ->willReturn($provider);
        $authService->method('getConfig')
            ->willReturn([
                'redirectUri' => 'http://app.dev/users/callback',
                'provider' => [
                    'github' => [
                        'applicationId' => '1234',
                        'applicationSecret' => '1234',
                    ]
                ]
            ]);

        $request = new ServerRequest([
            'params' => [
                'plugin' => null,
                'controller' => 'Users',
                'action' => 'callback',
                '_ext' => null,
                'pass' => [
                    'github'
                ]
            ],
            'environment' => [
                'REQUEST_URI' => '/users/callback/github/'
            ]
        ]);
        $response = new Response();

        $oauth = new OauthAuthenticator($this->identifiers, [
            'loginUrl' => [
                'controller' => 'Users',
                'action' => 'login'
            ],
            'redirectUrl' => [
                'controller' => 'Users',
                'action' => 'callback'
            ],
            'authService' => $authService
        ]);

        $result = $oauth->authenticate($request, $response);

        $this->assertInstanceOf('\Authentication\Result', $result);
        $this->assertEquals(Result::FAILURE_IDENTITY_NOT_FOUND, $result->getCode());
        $this->assertEquals(['Orm' => []], $result->getErrors());
    }

    /**
     * testMissingRedirectUrl
     *
     * @return void
     * @expectedException \RuntimeException
     * @expectedExceptionMessage You must pass the `redirectUrl` option.
     */
    public function testMissingRedirectUrl()
    {
        $oauth = new OauthAuthenticator($this->identifiers, [
            'loginUrl' => [
                'controller' => 'Users',
                'action' => 'login'
            ],
            'authService' => new Service(
                new Curl,
                $this->sessionMock,
                []
            )
        ]);
    }

    /**
     * testMissingLoginUrl
     *
     * @return void
     * @expectedException \RuntimeException
     * @expectedExceptionMessage You must pass the `loginUrl` option.
     */
    public function testMissingLoginUrl()
    {
        $oauth = new OauthAuthenticator($this->identifiers, [
            'redirectUrl' => [
                'controller' => 'Users',
                'action' => 'callback'
            ],
            'authService' => new Service(
                new Curl,
                $this->sessionMock,
                []
            )
        ]);
    }

    /**
     * testMissingAuthService
     *
     * @return void
     * @expectedException \RuntimeException
     * @expectedExceptionMessage You must pass the `authService` option.
     */
    public function testMissingAuthService()
    {
        $oauth = new OauthAuthenticator($this->identifiers, [
            'loginUrl' => [
                'controller' => 'Users',
                'action' => 'login'
            ],
            'redirectUrl' => [
                'controller' => 'Users',
                'action' => 'callback'
            ]
        ]);
    }

    /**
     * testWrongAuthService
     *
     * @return void
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Option `authService` must be an instance of \SocialConnect\Auth\Service.
     */
    public function testWrongAuthService()
    {
        $oauth = new OauthAuthenticator($this->identifiers, [
            'loginUrl' => [
                'controller' => 'Users',
                'action' => 'login'
            ],
            'redirectUrl' => [
                'controller' => 'Users',
                'action' => 'callback'
            ],
            'authService' => 'foo'
        ]);
    }
}
