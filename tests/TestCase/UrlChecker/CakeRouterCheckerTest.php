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
        Router::connect('/:controller/:action');
        Router::fullBaseUrl('http://localhost');
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
            ['REQUEST_URI' => '/users/login']
        );
        $result = $checker->check($request, '/users/login');
        $this->assertTrue($result);

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
}
