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

use Authentication\Authenticator\CookieAuthenticator;
use Authentication\Identifier\IdentifierCollection;
use Authentication\Result;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;
use Cake\Routing\Router;

class CookieAuthenticatorTest extends TestCase
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
     * {@inheritdoc}
     */
    public function setUp()
    {
        $this->skipIf(!class_exists('\Cake\Http\Cookie\Cookie'));

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
        $identifiers = new IdentifierCollection([]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath']
        );
        $response = new Response();

        $authenticator = new CookieAuthenticator($identifiers);
        $result = $authenticator->authenticate($request, $response);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::FAILURE_CREDENTIALS_NOT_FOUND, $result->getCode());
    }

    /**
     * testPersistIdentity
     *
     * @return void
     */
    public function testPersistIdentity()
    {

    }

    /**
     * clearIdentity
     *
     * @return void
     */
    public function clearIdentity()
    {

    }
}
