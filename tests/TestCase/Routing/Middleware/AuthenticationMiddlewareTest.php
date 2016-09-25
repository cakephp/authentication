<?php
namespace MiddlewareAuth\Test\TestCase\Routing\Middleware;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use MiddlewareAuth\Routing\Middleware\AuthenticationMiddleware;
use Zend\Diactoros\Request;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequestFactory;

class AuthenticationMiddlewareTest extends TestCase
{

    public $fixtures = [
        'core.auth_users',
        'core.users'
    ];

    public function setUp()
    {
        parent::setUp();

        $password = password_hash('password', PASSWORD_DEFAULT);
        TableRegistry::clear();

        $Users = TableRegistry::get('Users');
        $Users->updateAll(['password' => $password], []);

        $AuthUsers = TableRegistry::get('AuthUsers', [
            'className' => 'TestApp\Model\Table\AuthUsersTable'
        ]);
        $AuthUsers->updateAll(['password' => $password], []);
    }

    public function testAuthentication()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $response = new Response('php://memory', 200, ['X-testing' => 'Yes']);

        $middleware = new AuthenticationMiddleware([
            'authenticators' => [
                'MiddlewareAuth.Form'
            ]
        ]);

        $callable = function () {
        };

        $result = $middleware($request, $response, $callable);
        //debug($result);
    }
}
