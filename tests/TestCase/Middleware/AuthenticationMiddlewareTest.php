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
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use Auth\Middleware\AuthenticationMiddleware;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequestFactory;

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
     * setUp
     *
     * @return void
     */
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

    /**
     * testAuthentication
     *
     * @return void
     */
    public function testAuthentication()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $response = new Response('php://memory', 200, ['X-testing' => 'Yes']);

        $service = new AuthenticationService([
            'authenticators' => [
                'Auth.Form'
            ]
        ]);
        $middleware = new AuthenticationMiddleware($service);

        $callable = function () {
        };

        $result = $middleware($request, $response, $callable);
        //debug($result);
    }
}
