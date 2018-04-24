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
namespace Authentication\Test\TestCase\Authenticator;

use Authentication\Authenticator\HttpBasicAuthenticator;
use Authentication\Authenticator\UnauthorizedException;
use Authentication\Identifier\IdentifierCollection;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;
use Cake\I18n\Time;
use Cake\ORM\TableRegistry;

class HttpBasicAuthenticatorTest extends TestCase
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
     * @inheritdoc
     */
    public function setUp()
    {
        parent::setUp();

        $this->identifiers = new IdentifierCollection([
           'Authentication.Password'
        ]);

        $this->auth = new HttpBasicAuthenticator($this->identifiers);
        $this->response = new Response();
    }

    /**
     * test applying settings in the constructor
     *
     * @return void
     */
    public function testConstructor()
    {
        $object = new HttpBasicAuthenticator($this->identifiers, [
            'userModel' => 'AuthUser',
            'fields' => [
                'username' => 'user',
                'password' => 'password'
            ]
        ]);

        $this->assertEquals('AuthUser', $object->getConfig('userModel'));
        $this->assertEquals(['username' => 'user', 'password' => 'password'], $object->getConfig('fields'));
    }

    /**
     * test the authenticate method
     *
     * @return void
     */
    public function testAuthenticateNoData()
    {
        $request = ServerRequestFactory::fromGlobals(
            [
                'REQUEST_URI' => '/posts/index',
            ]
        );

        $result = $this->auth->authenticate($request, $this->response);
        $this->assertFalse($result->isValid());
        $this->assertSame($result, $this->auth->getLastResult());
    }

    /**
     * test the authenticate method
     *
     * @return void
     */
    public function testAuthenticateNoUsername()
    {
        $request = ServerRequestFactory::fromGlobals(
            [
                'REQUEST_URI' => '/posts/index',
                'PHP_AUTH_PW' => 'foobar',
            ]
        );

        $result = $this->auth->authenticate($request, $this->response);
        $this->assertFalse($result->isValid());
        $this->assertSame($result, $this->auth->getLastResult());
    }

    /**
     * test the authenticate method
     *
     * @return void
     */
    public function testAuthenticateNoPassword()
    {
        $request = ServerRequestFactory::fromGlobals(
            [
                'REQUEST_URI' => '/posts/index',
                'PHP_AUTH_USER' => 'mariano',
            ]
        );

        $result = $this->auth->authenticate($request, $this->response);
        $this->assertFalse($result->isValid());
        $this->assertSame($result, $this->auth->getLastResult());
    }

    /**
     * test the authenticate method
     *
     * @return void
     */
    public function testAuthenticateInjection()
    {
        $request = ServerRequestFactory::fromGlobals(
            [
                'REQUEST_URI' => '/posts/index',
                'PHP_AUTH_USER' => '> 1',
                'PHP_AUTH_PW' => "' OR 1 = 1"
            ]
        );

        $result = $this->auth->authenticate($request, $this->response);
        $this->assertFalse($result->isValid());
        $this->assertSame($result, $this->auth->getLastResult());
    }

    /**
     * Test that username of 0 works.
     *
     * @return void
     */
    public function testAuthenticateUsernameZero()
    {
        $User = TableRegistry::get('Users');
        $User->updateAll(['username' => '0'], ['username' => 'mariano']);

        $_SERVER['PHP_AUTH_USER'] = '0';
        $_SERVER['PHP_AUTH_PW'] = 'password';

        $request = ServerRequestFactory::fromGlobals(
            [
                'REQUEST_URI' => '/posts/index',
                'SERVER_NAME' => 'localhost',
                'PHP_AUTH_USER' => '0',
                'PHP_AUTH_PW' => 'password'
            ],
            [
                'user' => '0',
                'password' => 'password'
            ]
        );

        $expected = [
            'id' => 1,
            'username' => '0',
            'created' => new Time('2007-03-17 01:16:23'),
            'updated' => new Time('2007-03-17 01:18:31'),
        ];
        $result = $this->auth->authenticate($request, $this->response);
        $this->assertTrue($result->isValid());
        $this->assertArraySubset($expected, $result->getData()->toArray());
        $this->assertSame($result, $this->auth->getLastResult());
    }

    /**
     * test that challenge headers are sent when no credentials are found.
     *
     * @return void
     */
    public function testAuthenticateChallenge()
    {
        $request = ServerRequestFactory::fromGlobals(
            [
                'REQUEST_URI' => '/posts/index',
                'SERVER_NAME' => 'localhost',
            ]
        );

        try {
            $this->auth->unauthorizedChallenge($request);
            $this->fail('Should challenge');
        } catch (UnauthorizedException $e) {
            $expected = ['WWW-Authenticate' => 'Basic realm="localhost"'];
            $this->assertEquals($expected, $e->getHeaders());
            $this->assertEquals(401, $e->getCode());
        }
    }

    /**
     * test authenticate success
     *
     * @return void
     */
    public function testAuthenticateSuccess()
    {
        $request = ServerRequestFactory::fromGlobals(
            [
                'REQUEST_URI' => '/posts/index',
                'PHP_AUTH_USER' => 'mariano',
                'PHP_AUTH_PW' => 'password'
            ]
        );

        $result = $this->auth->authenticate($request, $this->response);
        $expected = [
            'id' => 1,
            'username' => 'mariano',
            'created' => new Time('2007-03-17 01:16:23'),
            'updated' => new Time('2007-03-17 01:18:31')
        ];

        $this->assertTrue($result->isValid());
        $this->assertArraySubset($expected, $result->getData()->toArray());
        $this->assertSame($result, $this->auth->getLastResult());
    }
}
