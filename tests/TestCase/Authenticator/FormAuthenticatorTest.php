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

use Authentication\Authenticator\FormAuthenticator;
use Authentication\Authenticator\Result;
use Authentication\Identifier\IdentifierCollection;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;
use Cake\Routing\Router;

class FormAuthenticatorTest extends TestCase
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

    public function setUp()
    {
        parent::setUp();
        Router::reload();
        Router::scope('/', function ($routes) {
            $routes->connect('/', ['controller' => 'pages', 'action' => 'display', 'home']);
            $routes->connect('/some_alias', ['controller' => 'tests_apps', 'action' => 'some_method']);
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
        $identifiers = new IdentifierCollection([
           'Authentication.Password'
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $response = new Response();

        $form = new FormAuthenticator($identifiers);
        $result = $form->authenticate($request, $response);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::SUCCESS, $result->getCode());
    }

    /**
     * testCredentialsNotPresent
     *
     * @return void
     */
    public function testCredentialsNotPresent()
    {
        $identifiers = new IdentifierCollection([
           'Authentication.Password'
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/does-not-match'],
            [],
            []
        );
        $response = new Response();

        $form = new FormAuthenticator($identifiers);

        $result = $form->authenticate($request, $response);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::FAILURE_CREDENTIALS_NOT_FOUND, $result->getCode());
        $this->assertEquals([0 => 'Login credentials not found'], $result->getErrors());
    }

    /**
     * testAuthenticateLoginUrl
     *
     * @return void
     */
    public function testAuthenticateLoginUrl()
    {
        $identifiers = new IdentifierCollection([
           'Authentication.Password'
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/does-not-match'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $response = new Response();

        $form = new FormAuthenticator($identifiers, [
            'loginUrl' => '/users/login'
        ]);

        $result = $form->authenticate($request, $response);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::FAILURE_OTHER, $result->getCode());
        $this->assertEquals([0 => 'Login URL /users/does-not-match did not match /users/login'], $result->getErrors());
    }

    /**
     * testArrayLoginUrl
     *
     * @return void
     */
    public function testArrayLoginUrl()
    {
        $identifiers = new IdentifierCollection([
           'Authentication.Password'
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/Users/login'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $response = new Response();

        $form = new FormAuthenticator($identifiers, [
            'loginUrl' => [
                'controller' => 'Users',
                'action' => 'login'
            ]
        ]);

        $result = $form->authenticate($request, $response);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::SUCCESS, $result->getCode());
        $this->assertEquals([], $result->getErrors());
    }

    /**
     * testLoginUrlWithAppInSubFolder
     *
     * @return void
     */
    public function testLoginUrlWithAppInSubFolder()
    {
        $request = ServerRequestFactory::fromGlobals(
            [
                'REQUEST_URI' => '/subfolder/Users/login',
                'PHP_SELF' => '/subfolder/index.php',
            ],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );

        $this->assertEquals('/subfolder/', $request->getUri()->webroot);
        $this->assertEquals('/Users/login', $request->getUri()->getPath());

        $response = new Response();

        $identifiers = new IdentifierCollection([
           'Authentication.Password'
        ]);

        $form = new FormAuthenticator($identifiers, [
            'loginUrl' => [
                'controller' => 'Users',
                'action' => 'login'
            ]
        ]);

        $result = $form->authenticate($request, $response);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::SUCCESS, $result->getCode());
        $this->assertEquals([], $result->getErrors());
    }

    /**
     * testAuthenticateCustomFields
     *
     * @return void
     */
    public function testAuthenticateCustomFields()
    {
        $identifiers = $this->createMock(IdentifierCollection::class);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/login'],
            [],
            ['email' => 'mariano@cakephp.org', 'secret' => 'password']
        );
        $response = new Response();

        $form = new FormAuthenticator($identifiers, [
            'loginUrl' => '/users/login',
            'fields' => [
                'username' => 'email',
                'password' => 'secret'
            ]
        ]);

        $identifiers->expects($this->once())
            ->method('identify')
            ->with([
                'username' => 'mariano@cakephp.org',
                'password' => 'password'
            ])
            ->willReturn([
                'username' => 'mariano@cakephp.org',
                'password' => 'password'
            ]);

        $form->authenticate($request, $response);
    }

    /**
     * testAuthenticateValidData
     *
     * @return void
     */
    public function testAuthenticateValidData()
    {
        $identifiers = $this->createMock(IdentifierCollection::class);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/login'],
            [],
            ['id' => 1, 'username' => 'mariano', 'password' => 'password']
        );
        $response = new Response();

        $form = new FormAuthenticator($identifiers, [
            'loginUrl' => '/users/login'
        ]);

        $identifiers->expects($this->once())
            ->method('identify')
            ->with([
                'username' => 'mariano',
                'password' => 'password'
            ])
            ->willReturn([
                'username' => 'mariano',
                'password' => 'password'
            ]);

        $form->authenticate($request, $response);
    }
}
