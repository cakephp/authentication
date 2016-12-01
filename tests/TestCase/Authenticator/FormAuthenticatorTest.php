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
 * @since         4.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Test\TestCase\Authenticator;

use Authentication\Authenticator\FormAuthenticator;
use Authentication\Identifier\IdentifierCollection;
use Authentication\Result;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Http\ServerRequestFactory;
use Cake\Routing\Router;
use Zend\Diactoros\Response;

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
           'Authentication.Orm'
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $response = new Response('php://memory', 200, ['X-testing' => 'Yes']);

        $form = new FormAuthenticator($identifiers);
        $result = $form->authenticate($request, $response);

        $this->assertInstanceOf('\Authentication\Result', $result);
        $this->assertEquals(Result::SUCCESS, $result->getCode());
    }

    /**
     * testAuthenticateLoginUrl
     *
     * @return void
     */
    public function testAuthenticateLoginUrl()
    {
        $identifiers = new IdentifierCollection([
           'Authentication.Orm'
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/does-not-match'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $response = new Response('php://memory', 200, ['X-testing' => 'Yes']);

        $form = new FormAuthenticator($identifiers, [
            'loginUrl' => '/users/login'
        ]);

        $result = $form->authenticate($request, $response);

        $this->assertInstanceOf('\Authentication\Result', $result);
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
           'Authentication.Orm'
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/Users/login'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $response = new Response('php://memory', 200, ['X-testing' => 'Yes']);

        $form = new FormAuthenticator($identifiers, [
            'loginUrl' => [
                'controller' => 'Users',
                'action' => 'login'
            ]
        ]);

        $result = $form->authenticate($request, $response);

        $this->assertInstanceOf('\Authentication\Result', $result);
        $this->assertEquals(Result::SUCCESS, $result->getCode());
        $this->assertEquals([], $result->getErrors());
    }
}
