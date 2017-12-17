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
namespace Authentication\Test\TestCase\UrlChecker;

use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Authentication\UrlChecker\CakeRouterChecker;
use Cake\Http\ServerRequestFactory;
use Cake\Routing\Router;

/**
 * CakeRouterChecker
 */
class CakeRouterCheckerTest extends TestCase
{

    /**
     * {@inheritDoc}
     */
    public function setUp()
    {
        parent::setUp();

        Router::reload();
        Router::fullBaseUrl('http://localhost');
        Router::connect(
            '/login',
            ['controller' => 'Users', 'action' => 'login'],
            ['_name' => 'login']
        );
        Router::connect('/:controller/:action');
    }

    /**
     * testCheckSimple
     *
     * @return void
     */
    public function testCheckSimple()
    {
        $checker = new CakeRouterChecker();
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/invalid']
        );
        $result = $checker->check($request, [
            'controller' => 'Users',
            'action' => 'login'
        ]);
        $this->assertFalse($result);
    }

    /**
     * checkFullUrls
     *
     * @return void
     */
    public function testCheckFullUrls()
    {
        $url = [
            'controller' => 'users',
            'action' => 'login'
        ];

        $checker = new CakeRouterChecker();
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/login']
        );
        $result = $checker->check($request, $url, [
            'checkFullUrl' => true
        ]);
        $this->assertTrue($result);

        $checker = new CakeRouterChecker();
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/invalid']
        );
        $result = $checker->check($request, $url, [
            'checkFullUrl' => true
        ]);
        $this->assertFalse($result);
    }

    /**
     * testEmptyUrl
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The $loginUrls parameter is empty or not of type array.
     * @return void
     */
    public function testEmptyUrl()
    {
        $checker = new CakeRouterChecker();
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/login']
        );
        $result = $checker->check($request, []);
        $this->assertFalse($result);
    }

    /**
     * testEmptyUrl
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The $loginUrls parameter is empty or not of type array.
     * @return void
     */
    public function testStringUrl()
    {
        $checker = new CakeRouterChecker();
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/login']
        );
        $result = $checker->check($request, '/users/login');
        $this->assertFalse($result);
    }

    /**
     * testNamedRoute
     *
     * @return void
     */
    public function testNamedRoute()
    {
        $checker = new CakeRouterChecker();
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/login']
        );
        $result = $checker->check($request, ['_name' => 'login']);
        $this->assertTrue($result);
    }

    /**
     * testInvalidNamedRoute
     *
     * @expectedException \Cake\Routing\Exception\MissingRouteException
     */
    public function testInvalidNamedRoute()
    {
        $checker = new CakeRouterChecker();
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/login']
        );
        $checker->check($request, ['_name' => 'login-does-not-exist']);
    }

    /**
     * testMultipleUrls
     *
     * @return void
     */
    public function testMultipleUrls()
    {
        $url = [
            [
                'controller' => 'users',
                'action' => 'login'
            ],
            [
                'controller' => 'admins',
                'action' => 'login'
            ]
        ];

        $checker = new CakeRouterChecker();
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/login']
        );
        $result = $checker->check($request, $url, [
            'checkFullUrl' => true
        ]);
        $this->assertTrue($result);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/admins/login']
        );
        $result = $checker->check($request, $url, [
            'checkFullUrl' => true
        ]);
        $this->assertTrue($result);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/invalid']
        );
        $result = $checker->check($request, $url, [
            'checkFullUrl' => true
        ]);
        $this->assertFalse($result);
    }
}
