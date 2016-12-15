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
namespace Authentication\Test\TestCase\Identifier;

use Authentication\AuthenticationService;
use Authentication\Controller\Component\AuthenticationComponent;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Controller\ComponentRegistry;
use Cake\Controller\Controller;
use Cake\Datasource\EntityInterface;
use Cake\Http\ServerRequestFactory;
use Cake\Network\Response;
use Cake\ORM\Entity;

class AuthenticationComponentTest extends TestCase
{

    /**
     * {@inheritDoc}
     */
    public function setUp()
    {
        parent::setUp();

        $this->identity = new Entity([
            'username' => 'florian',
            'profession' => 'developer'
        ]);

        $this->service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Orm'
            ],
            'authenticators' => [
                'Authentication.Session',
                'Authentication.Form'
            ]
        ]);

        $this->request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
            [],
            []
        );

        $this->response = new Response();
    }

    /**
     * testGetUser
     *
     * @eturn void
     */
    public function testGetUser()
    {
        $this->request = $this->request->withAttribute('identity', $this->identity);
        $this->request = $this->request->withAttribute('authentication', $this->service);

        $controller = new Controller($this->request, $this->response);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $result = $component->getIdentity();
        $this->assertInstanceOf(EntityInterface::class, $result);
        $this->assertEquals('florian', $result->get('username', 'florian'));

        $result = $component->getIdentity('profession');
        $this->assertEquals('developer', $result);
    }

    /**
     * testLogout
     *
     * @return void
     */
    public function testLogout()
    {
        $this->markTestIncomplete();
    }
}
