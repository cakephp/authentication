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
namespace Authentication\Test\TestCase\Controller\Component;

use ArrayObject;
use Authentication\AuthenticationService;
use Authentication\AuthenticationServiceInterface;
use Authentication\Authenticator\AuthenticatorInterface;
use Authentication\Authenticator\UnauthenticatedException;
use Authentication\Controller\Component\AuthenticationComponent;
use Authentication\Identity;
use Authentication\IdentityInterface;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Controller\ComponentRegistry;
use Cake\Controller\Controller;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Http\ServerRequestFactory;
use Cake\ORM\Entity;
use InvalidArgumentException;
use TestApp\Authentication\InvalidAuthenticationService;
use UnexpectedValueException;

/**
 * Authentication component test.
 */
class AuthenticationComponentTest extends TestCase
{
    /**
     * @var array|\ArrayAccess
     */
    protected $identityData;

    /**
     * @var \Authentication\Identity
     */
    protected $identity;

    /**
     * @var \Cake\Http\ServerRequest
     */
    protected $request;

    /**
     * @var \Cake\Http\Response
     */
    protected $response;

    /**
     * @var \Authentication\AuthenticationService
     */
    protected $service;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->identityData = new Entity([
            'username' => 'florian',
            'profession' => 'developer',
        ]);

        $this->identity = new Identity($this->identityData);

        $this->service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password',
            ],
            'authenticators' => [
                'Authentication.Session',
                'Authentication.Form',
            ],
        ]);

        $this->request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
    }

    /**
     * testGetAuthenticationService
     *
     * @return void
     */
    public function testGetAuthenticationService()
    {
        $service = new AuthenticationService();
        $request = $this->request->withAttribute('authentication', $service);
        $controller = new Controller($request);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);
        $result = $component->getAuthenticationService();
        $this->assertSame($service, $result);
    }

    /**
     * testGetAuthenticationServiceMissingServiceAttribute
     *
     * @return void
     */
    public function testGetAuthenticationServiceMissingServiceAttribute()
    {
        $this->expectException('Exception');
        $this->expectExceptionMessage('The request object does not contain the required `authentication` attribute');
        $controller = new Controller($this->request);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);
        $component->getAuthenticationService();
    }

    /**
     * testGetAuthenticationServiceInvalidServiceObject
     *
     * @return void
     */
    public function testGetAuthenticationServiceInvalidServiceObject()
    {
        $this->expectException('Exception');
        $this->expectExceptionMessage('Authentication service does not implement Authentication\AuthenticationServiceInterface');
        $request = $this->request->withAttribute('authentication', new InvalidAuthenticationService());
        $controller = new Controller($request);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);
        $component->getAuthenticationService();
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

        $controller = new Controller($request);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $result = $component->getIdentity();
        $this->assertInstanceOf(IdentityInterface::class, $result);
        $this->assertSame('florian', $result->username);
    }

    /**
     * testGetIdentity with custom attribute
     *
     * @eturn void
     */
    public function testGetIdentityWithCustomAttribute()
    {
        $this->request = $this->request->withAttribute('customIdentity', $this->identity);
        $this->request = $this->request->withAttribute('authentication', $this->service);

        $controller = new Controller($this->request);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry, [
            'identityAttribute' => 'customIdentity',
        ]);

        $result = $component->getIdentity();
        $this->assertInstanceOf(IdentityInterface::class, $result);
        $this->assertSame('florian', $result->username);
    }

    /**
     * testSetIdentity
     *
     * @eturn void
     */
    public function testSetIdentity()
    {
        $request = $this->request->withAttribute('authentication', $this->service);

        $controller = new Controller($request);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $component->setIdentity($this->identityData);
        $result = $component->getIdentity();
        $this->assertSame($this->identityData, $result->getOriginalData());
    }

    /**
     * Test that the setIdentity() called with an identity instance sets
     * this instance as a request attribute
     *
     * @eturn void
     */
    public function testSetIdentityInstance()
    {
        $request = $this->request->withAttribute('authentication', $this->service);

        $controller = new Controller($request);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $identity = new Identity($this->identityData);
        $component->setIdentity($identity);
        $result = $component->getIdentity();
        $this->assertSame($identity, $result);
    }

    /**
     * Ensure setIdentity() clears identity and persists identity data.
     *
     * @eturn void
     */
    public function testSetIdentityOverwrite()
    {
        $request = $this->request->withAttribute('authentication', $this->service);

        $controller = new Controller($request);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $component->setIdentity($this->identityData);
        $result = $component->getIdentity();
        $this->assertSame($this->identityData, $result->getOriginalData());
        $this->assertSame(
            $this->identityData->username,
            $request->getSession()->read('Auth.username'),
            'Session should be updated.'
        );

        // Replace the identity
        $newIdentity = new Entity(['username' => 'jessie']);
        $component->setIdentity($newIdentity);

        $result = $component->getIdentity();
        $this->assertSame($newIdentity, $result->getOriginalData());
        $this->assertSame(
            $newIdentity->username,
            $request->getSession()->read('Auth.username'),
            'Session should be updated.'
        );
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

        $controller = new Controller($request);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $result = $component->getIdentityData('profession');
        $this->assertSame('developer', $result);
    }

    /**
     * testGetMissingIdentityData
     *
     * @eturn void
     */
    public function testGetMissingIdentityData()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('The identity has not been found.');
        $request = $this->request->withAttribute('authentication', $this->service);

        $controller = new Controller($request);
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

        $controller = new Controller($request);
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

        $controller = new Controller($request);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $this->assertSame('florian', $controller->getRequest()->getAttribute('identity')->username);
        $component->logout();
        $this->assertNull($controller->getRequest()->getAttribute('identity'));
        $this->assertInstanceOf(Event::class, $result);
        $this->assertSame('Authentication.logout', $result->getName());
    }

    /**
     * test getLoginRedirect
     *
     * @eturn void
     */
    public function testGetLoginRedirect()
    {
        $this->service->setConfig('queryParam', 'redirect');
        $request = $this->request
            ->withAttribute('identity', $this->identity)
            ->withAttribute('authentication', $this->service)
            ->withQueryParams(['redirect' => 'ok/path?value=key']);

        $controller = new Controller($request);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $result = $component->getLoginRedirect();
        $this->assertSame('/ok/path?value=key', $result);
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

        $this->service->authenticate($this->request);

        $request = $this->request
            ->withAttribute('identity', $this->identity)
            ->withAttribute('authentication', $this->service);

        $controller = new Controller($request);
        $controller->loadComponent('Authentication.Authentication');
        $controller->startupProcess();

        $this->assertInstanceOf(Event::class, $result);
        $this->assertSame('Authentication.afterIdentify', $result->getName());
        $this->assertNotEmpty($result->getData());
        $this->assertInstanceOf(AuthenticatorInterface::class, $result->getData('provider'));
        $this->assertInstanceOf(IdentityInterface::class, $result->getData('identity'));
        $this->assertInstanceOf(AuthenticationServiceInterface::class, $result->getData('service'));
    }

    /**
     * test unauthenticated actions methods
     *
     * @return void
     */
    public function testUnauthenticatedActions()
    {
        $request = $this->request
            ->withParam('action', 'view')
            ->withAttribute('authentication', $this->service);

        $controller = new Controller($request);
        $controller->loadComponent('Authentication.Authentication');

        $controller->Authentication->allowUnauthenticated(['view']);
        $this->assertSame(['view'], $controller->Authentication->getUnauthenticatedActions());

        $controller->Authentication->allowUnauthenticated(['add', 'delete']);
        $this->assertSame(['add', 'delete'], $controller->Authentication->getUnauthenticatedActions());

        $controller->Authentication->addUnauthenticatedActions(['index']);
        $this->assertSame(['add', 'delete', 'index'], $controller->Authentication->getUnauthenticatedActions());

        $controller->Authentication->addUnauthenticatedActions(['index', 'view']);
        $this->assertSame(
            ['add', 'delete', 'index', 'view'],
            $controller->Authentication->getUnauthenticatedActions(),
            'Should contain unique set.'
        );
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

        $controller = new Controller($request);
        $controller->loadComponent('Authentication.Authentication');

        $controller->Authentication->allowUnauthenticated(['view']);
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

        $controller = new Controller($request);
        $controller->loadComponent('Authentication.Authentication');

        $this->expectException(UnauthenticatedException::class);
        $this->expectExceptionCode(401);
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

        $controller = new Controller($request);
        $controller->loadComponent('Authentication.Authentication');

        $this->expectException(UnauthenticatedException::class);
        $this->expectExceptionCode(401);
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

        $controller = new Controller($request);
        $controller->loadComponent('Authentication.Authentication', [
            'requireIdentity' => false,
        ]);

        // Mismatched actions would normally cause an error.
        $controller->Authentication->allowUnauthenticated(['index', 'add']);
        $controller->startupProcess();
        $this->assertTrue(true, 'No exception should be raised as require identity is off.');
    }

    /**
     * Test that the identity check can be run from callback for Controller.initialize
     *
     * @return void
     */
    public function testIdentityCheckInBeforeFilter()
    {
        $request = $this->request
            ->withAttribute('authentication', $this->service);

        $controller = new Controller($request);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $this->expectException(UnauthenticatedException::class);
        $this->expectExceptionMessage('Authentication is required to continue');
        $this->expectExceptionCode(401);

        $component->setConfig('identityCheckEvent', 'Controller.initialize');
        $component->allowUnauthenticated(['index', 'add']);
        $component->beforeFilter();
    }

    public function testCustomUnauthenticatedMessage()
    {
        $request = $this->request
            ->withAttribute('authentication', $this->service);

        $controller = new Controller($request);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $errorMessage = 'You shall not pass!';
        $this->expectException(UnauthenticatedException::class);
        $this->expectExceptionMessage($errorMessage);
        $this->expectExceptionCode(401);

        $component->setConfig('identityCheckEvent', 'Controller.initialize');
        $component->setConfig('unauthenticatedMessage', $errorMessage);
        $component->allowUnauthenticated(['index', 'add']);
        $component->beforeFilter();
    }

    /**
     * testImpersonate
     *
     * @return void
     */
    public function testImpersonate()
    {
        $impersonator = new ArrayObject(['username' => 'mariano']);
        $impersonated = new ArrayObject(['username' => 'larry']);
        $this->request->getSession()->write('Auth', $impersonator);
        $this->service->authenticate($this->request);
        $identity = new Identity($impersonator);
        $request = $this->request
            ->withAttribute('identity', $identity)
            ->withAttribute('authentication', $this->service);
        $controller = new Controller($request, $this->response);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $this->assertEquals($impersonator, $controller->getRequest()->getSession()->read('Auth'));
        $this->assertNull($controller->getRequest()->getSession()->read('AuthImpersonate'));

        $component->impersonate($impersonated);
        $this->assertEquals($impersonated, $controller->getRequest()->getSession()->read('Auth'));
        $this->assertEquals($impersonator, $controller->getRequest()->getSession()->read('AuthImpersonate'));

        $component->stopImpersonating();
        $this->assertNull($controller->getRequest()->getSession()->read('AuthImpersonate'));
    }

    /**
     * test that impersonate() can handle identities with array data within them.
     *
     * @return void
     */
    public function testImpersonateDecoratorIgnored()
    {
        $impersonator = ['username' => 'mariano'];
        $impersonated = new ArrayObject(['username' => 'larry']);

        $this->request->getSession()->write('Auth', $impersonator);
        $this->service->authenticate($this->request);
        $identity = new Identity($impersonator);
        $request = $this->request
            ->withAttribute('identity', $identity)
            ->withAttribute('authentication', $this->service);
        $controller = new Controller($request, $this->response);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $this->assertEquals($impersonator, $controller->getRequest()->getSession()->read('Auth'));
        $this->assertNull($controller->getRequest()->getSession()->read('AuthImpersonate'));

        $component->impersonate($impersonated);
        $this->assertEquals($impersonated, $controller->getRequest()->getSession()->read('Auth'));
        $this->assertEquals(new ArrayObject($impersonator), $controller->getRequest()->getSession()->read('AuthImpersonate'));

        $component->stopImpersonating();
        $this->assertNull($controller->getRequest()->getSession()->read('AuthImpersonate'));
    }

    /**
     * testImpersonateNoIdentity
     *
     * @return void
     */
    public function testImpersonateNoIdentity()
    {
        $impersonated = new ArrayObject(['username' => 'larry']);
        $request = $this->request
            ->withAttribute('authentication', $this->service);
        $controller = new Controller($request, $this->response);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);
        $this->expectException(UnauthenticatedException::class);
        $this->expectExceptionMessage('You must be logged in before impersonating a user.');
        $component->impersonate($impersonated);
    }

    /**
     * testImpersonateFailure
     *
     * @return void
     */
    public function testImpersonateFailure()
    {
        $impersonator = new ArrayObject(['username' => 'mariano']);
        $impersonated = new ArrayObject(['username' => 'larry']);
        $service = $this->getMockBuilder(AuthenticationService::class)
            ->onlyMethods(['isImpersonating', 'impersonate'])
            ->getMock();
        $service->expects($this->once())
            ->method('impersonate');
        $service->expects($this->once())
            ->method('isImpersonating')
            ->willReturn(false);
        $identity = new Identity($impersonator);
        $request = $this->request
            ->withAttribute('identity', $identity)
            ->withAttribute('authentication', $service);
        $controller = new Controller($request, $this->response);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('An error has occurred impersonating user.');
        $component->impersonate($impersonated);
    }

    /**
     * testStopImpersonating
     *
     * @return void
     */
    public function testStopImpersonating()
    {
        $impersonator = new ArrayObject(['username' => 'mariano']);
        $impersonated = new ArrayObject(['username' => 'larry']);
        $this->request->getSession()->write('Auth', $impersonated);
        $this->request->getSession()->write('AuthImpersonate', $impersonator);
        $this->service->authenticate($this->request);
        $request = $this->request->withAttribute('authentication', $this->service);
        $controller = new Controller($request, $this->response);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);
        $this->assertEquals($impersonator, $controller->getRequest()->getSession()->read('AuthImpersonate'));
        $this->assertEquals($impersonated, $controller->getRequest()->getSession()->read('Auth'));
        $component->stopImpersonating();
        $this->assertNull($controller->getRequest()->getSession()->read('AuthImpersonate'));
        $this->assertEquals($impersonator, $controller->getRequest()->getSession()->read('Auth'));
    }

    /**
     * testStopImpersonatingFailure
     *
     * @return void
     */
    public function testStopImpersonatingFailure()
    {
        $impersonator = new ArrayObject(['username' => 'mariano']);
        $service = $this->getMockBuilder(AuthenticationService::class)
            ->onlyMethods(['isImpersonating', 'stopImpersonating'])
            ->getMock();
        $service->expects($this->once())
            ->method('stopImpersonating');
        $service->expects($this->once())
            ->method('isImpersonating')
            ->willReturn(true);
        $identity = new Identity($impersonator);
        $request = $this->request
            ->withAttribute('identity', $identity)
            ->withAttribute('authentication', $service);
        $controller = new Controller($request, $this->response);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('An error has occurred stopping impersonation.');
        $component->stopImpersonating();
    }

    /**
     * testIsImpersonating
     *
     * @return void
     */
    public function testIsImpersonating()
    {
        $impersonator = new ArrayObject(['username' => 'mariano']);
        $impersonated = new ArrayObject(['username' => 'larry']);
        $this->request->getSession()->write('Auth', $impersonated);
        $this->request->getSession()->write('AuthImpersonate', $impersonator);
        $this->service->authenticate($this->request);
        $request = $this->request
            ->withAttribute('authentication', $this->service);
        $controller = new Controller($request, $this->response);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $result = $component->isImpersonating();
        $this->assertTrue($result);
    }

    /**
     * testGetImpersonationAuthenticationServiceFailure
     *
     * @return void
     */
    public function testGetImpersonationAuthenticationServiceFailure()
    {
        $service = $this->getMockBuilder(AuthenticationServiceInterface::class)->getMock();

        $component = $this->createPartialMock(AuthenticationComponent::class, ['getAuthenticationService']);
        $component->expects($this->once())
            ->method('getAuthenticationService')
            ->willReturn($service);

        $this->expectException(InvalidArgumentException::class);
        $classname = get_class($service);
        $this->expectExceptionMessage("The $classname must implement ImpersonationInterface in order to use impersonation.");
        $component->isImpersonating();
    }

    /**
     * testIsImpersonatingNotImpersonating
     *
     * @return void
     */
    public function testIsImpersonatingNotImpersonating()
    {
        $user = new ArrayObject(['username' => 'mariano']);
        $this->request->getSession()->write('Auth', $user);
        $this->service->authenticate($this->request);
        $request = $this->request->withAttribute('authentication', $this->service);
        $controller = new Controller($request, $this->response);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $result = $component->isImpersonating();
        $this->assertFalse($result);
    }
}
