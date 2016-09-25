<?php
namespace MiddlewareAuth\Test\TestCase\Routing\Middleware\Authentication;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use MiddlewareAuth\Auth\Authentication\FormAuthenticator;
use MiddlewareAuth\Auth\Authentication\Result;
use Zend\Diactoros\Request;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequestFactory;

class FormAuthenticatorTest extends TestCase
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

    /**
     * testAuthenticate
     *
     * @return void
     */
    public function testAuthenticate()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $response = new Response('php://memory', 200, ['X-testing' => 'Yes']);

        $form = new FormAuthenticator();
        $result = $form->authenticate($request, $response);

        $this->assertInstanceOf('\MiddlewareAuth\Auth\Authentication\Result', $result);
        $this->assertEquals(Result::SUCCESS, $result->getCode());
    }
}
