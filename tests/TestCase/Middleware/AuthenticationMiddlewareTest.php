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
namespace Auth\Test\TestCase\Middleware;

use Auth\Authentication\AuthenticationService;
use Auth\Test\TestCase\AuthenticationTestCase as TestCase;
use Auth\Middleware\AuthenticationMiddleware;
use Cake\Http\ServerRequestFactory;
use Zend\Diactoros\Response;

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
        $this->service = $this->getMockBuilder(AuthenticationService::class)->getMock();
    }

    /**
     * testInvoke
     *
     * @return void
     */
    public function testInvoke()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $response = new Response('php://memory');

        $middleware = new AuthenticationMiddleware($this->service);

        $this->service->expects($this->once())
            ->method('getIdentity')
            ->will($this->returnValue(false));

        $this->service->expects($this->once())
            ->method('authenticate');

        $next = function($request, $response) {
            return $response;
        };

        $middleware($request, $response, $next);
    }
}
