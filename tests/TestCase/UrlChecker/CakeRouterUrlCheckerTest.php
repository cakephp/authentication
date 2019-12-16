<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link https://cakephp.org CakePHP(tm) Project
 * @since 1.0.0
 * @license https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Test\TestCase\UrlChecker;

use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Authentication\UrlChecker\CakeRouterUrlChecker;
use Cake\Http\ServerRequestFactory;
use Cake\Routing\Router;

/**
 * CakeRouterChecker
 */
class CakeRouterUrlCheckerTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
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

        Router::connect(
            '/login',
            ['controller' => 'Users', 'action' => 'login'],
            [
                '_host' => 'auth.localhost',
                '_name' => 'secureLogin',
            ]
        );
    }

    /**
     * testCheckSimple
     *
     * @return void
     */
    public function testCheckSimple()
    {
        $checker = new CakeRouterUrlChecker();
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/invalid']
        );
        $result = $checker->check($request, [
            'controller' => 'Users',
            'action' => 'login',
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
            'action' => 'login',
        ];

        $checker = new CakeRouterUrlChecker();
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/login']
        );
        $result = $checker->check($request, $url, [
            'checkFullUrl' => true,
        ]);
        $this->assertTrue($result);

        $checker = new CakeRouterUrlChecker();
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/invalid']
        );
        $result = $checker->check($request, $url, [
            'checkFullUrl' => true,
        ]);
        $this->assertFalse($result);

        $checker = new CakeRouterUrlChecker();
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/login']
        );
        $result = $checker->check($request, ['_name' => 'secureLogin'], [
            'checkFullUrl' => true,
        ]);
        $this->assertFalse($result);

        $checker = new CakeRouterUrlChecker();
        $request = ServerRequestFactory::fromGlobals(
            [
                'REQUEST_URI' => '/login',
                'SERVER_NAME' => 'auth.localhost',
            ]
        );
        $result = $checker->check($request, ['_name' => 'secureLogin'], [
            'checkFullUrl' => true,
        ]);
        $this->assertTrue($result);
    }

    /**
     * testEmptyUrl
     *
     * @return void
     */
    public function testEmptyUrl()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('The $loginUrls parameter is empty or not of type array.');
        $checker = new CakeRouterUrlChecker();
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/login']
        );
        $result = $checker->check($request, []);
        $this->assertFalse($result);
    }

    /**
     * testEmptyUrl
     *
     * @return void
     */
    public function testStringUrl()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('The $loginUrls parameter is empty or not of type array.');
        $checker = new CakeRouterUrlChecker();
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
        $checker = new CakeRouterUrlChecker();
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/login']
        );
        $result = $checker->check($request, ['_name' => 'login']);
        $this->assertTrue($result);
    }

    /**
     * testInvalidNamedRoute
     */
    public function testInvalidNamedRoute()
    {
        $this->expectException('Cake\Routing\Exception\MissingRouteException');
        $checker = new CakeRouterUrlChecker();
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
                'action' => 'login',
            ],
            [
                'controller' => 'admins',
                'action' => 'login',
            ],
        ];

        $checker = new CakeRouterUrlChecker();
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/login']
        );
        $result = $checker->check($request, $url, [
            'checkFullUrl' => true,
        ]);
        $this->assertTrue($result);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/admins/login']
        );
        $result = $checker->check($request, $url, [
            'checkFullUrl' => true,
        ]);
        $this->assertTrue($result);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/invalid']
        );
        $result = $checker->check($request, $url, [
            'checkFullUrl' => true,
        ]);
        $this->assertFalse($result);
    }
}
