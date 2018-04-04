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
namespace Authentication\Test\TestCase\Identifier;

use Authentication\AuthenticationService;
use Authentication\AuthenticationServiceInterface;
use Authentication\Authenticator\AuthenticatorInterface;
use Authentication\Authenticator\UnauthorizedException;
use Authentication\Controller\Component\AuthenticationComponent;
use Authentication\Identity;
use Authentication\IdentityInterface;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Controller\ComponentRegistry;
use Cake\Controller\Controller;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Http\ServerRequestFactory;
use Cake\Network\Response;
use Cake\ORM\Entity;
use TestApp\Authentication\InvalidAuthenticationService;

/**
 * Authentication component test.
 */
class AuthenticationComponentTest extends TestCase
{

    /**
     * {@inheritDoc}
     */
    public function setUp()
    {
        parent::setUp();

        $this->identityData = new Entity([
            'username' => 'florian',
            'profession' => 'developer'
        ]);

        $this->identity = new Identity($this->identityData);

        $this->service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password'
            ],
            'authenticators' => [
                'Authentication.Session',
                'Authentication.Form'
            ]
        ]);

        $this->request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
            [],
            ['username' => 'mariano', 'password' => 'password']
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
        $request = $this->request->withAttribute('authentication', new InvalidAuthenticationService());
        $controller = new Controller($request, $this->response);
        $registry = new ComponentRegistry($controller);
        new AuthenticationComponent($registry);
    }

    /**
     * testGetIdentity
     *
     * @eturn void
     */
    public function testGetIdentity()
    {
        $request = $this->request
            ->withAttribute('identity', $this->identity)
            ->withAttribute('authentication', $this->service);

        $controller = new Controller($request, $this->response);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $result = $component->getIdentity();
        $this->assertInstanceOf(IdentityInterface::class, $result);
        $this->assertEquals('florian', $result->username);
    }

    /**
     * testGetIdentity
     *
     * @eturn void
     */
    public function testSetIdentity()
    {
        $request = $this->request->withAttribute('authentication', $this->service);

        $controller = new Controller($request, $this->response);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $component->setIdentity($this->identityData);
        $result = $component->getIdentity();
        $this->assertSame($this->identityData, $result->getOriginalData());
    }

    /**
     * testGetIdentity
     *
     * @eturn void
     */
    public function testGetIdentityData()
    {
        $request = $this->request
            ->withAttribute('identity', $this->identity)
            ->withAttribute('authentication', $this->service);

        $controller = new Controller($request, $this->response);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $result = $component->getIdentityData('profession');
        $this->assertEquals('developer', $result);
    }

    /**
     * testGetMissingIdentityData
     *
     * @eturn void
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The identity has not been found.
     */
    public function testGetMissingIdentityData()
    {
        $request = $this->request->withAttribute('authentication', $this->service);

        $controller = new Controller($request, $this->response);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $component->getIdentityData('profession');
    }

    /**
     * testGetResult
     *
     * @return void
     */
    public function testGetResult()
    {
        $request = $this->request
            ->withAttribute('identity', $this->identity)
            ->withAttribute('authentication', $this->service);

        $controller = new Controller($request, $this->response);
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
        $result = null;
        EventManager::instance()->on('Authentication.logout', function (Event $event) use (&$result) {
            $result = $event;
        });

        $request = $this->request
            ->withAttribute('identity', $this->identity)
            ->withAttribute('authentication', $this->service);

        $controller = new Controller($request, $this->response);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $this->assertEquals('florian', $controller->request->getAttribute('identity')->username);
        $component->logout();
        $this->assertNull($controller->request->getAttribute('identity'));
        $this->assertInstanceOf(Event::class, $result);
        $this->assertEquals('Authentication.logout', $result->name());
    }

    /**
     * testAfterIdentifyEvent
     *
     * @return void
     */
    public function testAfterIdentifyEvent()
    {
        $result = null;
        EventManager::instance()->on('Authentication.afterIdentify', function (Event $event) use (&$result) {
            $result = $event;
        });

        $this->service->authenticate(
            $this->request,
            $this->response
        );

        $request = $this->request
            ->withAttribute('identity', $this->identity)
            ->withAttribute('authentication', $this->service);

        $controller = new Controller($request, $this->response);
        $controller->loadComponent('Authentication.Authentication');
        $controller->startupProcess();

        $this->assertInstanceOf(Event::class, $result);
        $this->assertEquals('Authentication.afterIdentify', $result->name());
        $this->assertNotEmpty($result->data);
        $this->assertInstanceOf(AuthenticatorInterface::class, $result->data['provider']);
        $this->assertInstanceOf(IdentityInterface::class, $result->data['identity']);
        $this->assertInstanceOf(AuthenticationServiceInterface::class, $result->data['service']);
    }

    /**
     * test unauthenticated actions ok
     *
     * @return void
     */
    public function testUnauthenticatedActionsOk()
    {
        $request = $this->request
            ->withParam('action', 'view')
            ->withAttribute('authentication', $this->service);

        $controller = new Controller($request, $this->response);
        $controller->loadComponent('Authentication.Authentication');

        $controller->Authentication->allowUnauthenticated(['view']);
        $this->assertSame(['view'], $controller->Authentication->getUnauthenticatedActions());
        $controller->startupProcess();
        $this->assertTrue(true, 'No exception should be raised');
    }

    /**
     * test unauthenticated actions mismatched action
     *
     * @return void
     */
    public function testUnauthenticatedActionsMismatchAction()
    {
        $request = $this->request
            ->withParam('action', 'view')
            ->withAttribute('authentication', $this->service);

        $controller = new Controller($request, $this->response);
        $controller->loadComponent('Authentication.Authentication');

        $this->expectException(UnauthorizedException::class);
        $controller->Authentication->allowUnauthenticated(['index', 'add']);
        $controller->startupProcess();
    }

    /**
     * test unauthenticated actions ok
     *
     * @return void
     */
    public function testUnauthenticatedActionsNoActionsFails()
    {
        $request = $this->request
            ->withParam('action', 'view')
            ->withAttribute('authentication', $this->service);

        $controller = new Controller($request, $this->response);
        $controller->loadComponent('Authentication.Authentication');

        $this->expectException(UnauthorizedException::class);
        $controller->startupProcess();
    }

    /**
     * test disabling requireidentity via settings
     *
     * @return void
     */
    public function testUnauthenticatedActionsDisabledOptions()
    {
        $request = $this->request
            ->withParam('action', 'view')
            ->withAttribute('authentication', $this->service);

        $controller = new Controller($request, $this->response);
        $controller->loadComponent('Authentication.Authentication', [
            'requireIdentity' => false
        ]);

        // Mismatched actions would normally cause an error.
        $controller->Authentication->allowUnauthenticated(['index', 'add']);
        $controller->startupProcess();
        $this->assertTrue(true, 'No exception should be raised as require identity is off.');
    }
}
