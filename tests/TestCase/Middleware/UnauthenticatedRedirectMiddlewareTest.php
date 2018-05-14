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
namespace Authentication\Test\TestCase\Middleware;

use Authentication\AuthenticationService;
use Authentication\Authenticator\UnauthorizedException;
use Authentication\Middleware\UnauthenticatedRedirectMiddleware;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;
use Cake\TestSuite\TestCase;
use RuntimeException;

class UnauthenticatedRedirectMiddlewareTest extends TestCase
{
    public function testInvokeSuccess()
    {
        $next = function ($req, $res) {
            return $res->withHeader('X-pass', 'ok');
        };

        $request = ServerRequestFactory::fromGlobals();
        $response = new Response();

        $middleware = new UnauthenticatedRedirectMiddleware('/users/login');
        $res = $middleware($request, $response, $next);
        $this->assertTrue($res->hasHeader('X-pass'));
    }

    public function testInvokeRedirect()
    {
        $next = function ($req, $res) {
            throw new UnauthorizedException([]);
        };

        $request = ServerRequestFactory::fromGlobals();
        $response = new Response();

        $middleware = new UnauthenticatedRedirectMiddleware('/users/login');
        $res = $middleware($request, $response, $next);
        $this->assertTrue($res->hasHeader('Location'));
        $this->assertEquals('/users/login', $res->getHeaderLine('Location'));
    }

    public function testInvokeNoTrap()
    {
        $next = function ($req, $res) {
            throw new RuntimeException('oh no');
        };

        $request = ServerRequestFactory::fromGlobals();
        $response = new Response();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('oh no');
        $middleware = new UnauthenticatedRedirectMiddleware('/users/login');
        $middleware($request, $response, $next);
    }
}
