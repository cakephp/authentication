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
use TestApp\Authentication\InvalidAuthenticationService;

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
     * testInitializeMissingServiceAttribute
     *
     * @expectedException \Exception
     * @expectedExceptionMessage The request object does not contain the required `authentication` attribute
     * @return void
     */
    public function testInitializeMissingServiceAttribute()
    {
        $controller = new Controller($this->request, $this->response);
        $registry = new ComponentRegistry($controller);
        new AuthenticationComponent($registry);
    }

    /**
     * testInitializeInvalidServiceObject
     *
     * @expectedException \Exception
     * @expectedExceptionMessage Authentication service does not implement Authentication\AuthenticationServiceInterface
     * @return void
     */
    public function testInitializeInvalidServiceObject()
    {
        $this->request = $this->request->withAttribute('authentication', new InvalidAuthenticationService());
        $controller = new Controller($this->request, $this->response);
        $registry = new ComponentRegistry($controller);
        new AuthenticationComponent($registry);
    }

    /**
     * testGetUser
     *
     * @eturn void
     */
    public function testGetUserNullResult()
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
     * testGetResult
     *
     * @return void
     */
    public function testGetResult()
    {
        $this->request = $this->request->withAttribute('identity', $this->identity);
        $this->request = $this->request->withAttribute('authentication', $this->service);

        $controller = new Controller($this->request, $this->response);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);
        $this->assertNull($component->getResult());
    }

    /**
     * testLogout
     *
     * @return void
     */
    public function testLogout()
    {
        $this->request = $this->request->withAttribute('identity', $this->identity);
        $this->request = $this->request->withAttribute('authentication', $this->service);

        $controller = new Controller($this->request, $this->response);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $this->assertEquals('florian', $controller->request->getAttribute('identity')->get('username'));
        $component->logout();
        $this->assertNull($controller->request->getAttribute('identity'));
    }
}
