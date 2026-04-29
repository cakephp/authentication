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
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Http\ServerRequestFactory;
use Cake\ORM\Entity;
use Cake\Routing\Router;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use TestApp\Authentication\InvalidAuthenticationService;
use UnexpectedValueException;

/**
 * Authentication component test.
 */
#[AllowMockObjectsWithoutExpectations]
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
     * @var \Authentication\AuthenticationService
     */
    protected $service;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->identityData = new Entity([
            'username' => 'florian',
            'profession' => 'developer',
        ]);

        $this->identity = new Identity($this->identityData);

        $this->service = new AuthenticationService([
            'authenticators' => [
                'Authentication.Session' => [
                    'identifier' => 'Authentication.Password',
                ],
                'Authentication.Form' => [
                    'identifier' => 'Authentication.Password',
                ],
            ],
        ]);

        $this->request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
            [],
            ['username' => 'mariano', 'password' => 'password'],
        );
    }

    /**
     * testGetAuthenticationService
     *
     * @return void
     */
    public function testGetAuthenticationService(): void
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
    public function testGetAuthenticationServiceMissingServiceAttribute(): void
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
    public function testGetAuthenticationServiceInvalidServiceObject(): void
    {
        $this->expectException('Exception');
        $this->expectExceptionMessage('Authentication service does not implement Authentication\AuthenticationServiceInterface');
        $request = $this->request->withAttribute('authentication', new InvalidAuthenticationService());
        $controller = new Controller($request);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);
        $component->getAuthenticationService();
    }

    public function testGetId(): void
    {
        $component = new AuthenticationComponent(new ComponentRegistry(new Controller($this->request)));
        $this->assertNull($component->getIdentifier());

        $request = $this->request
            ->withAttribute('identity', $this->identity)
            ->withAttribute('authentication', $this->service);

        $controller = new Controller($request);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $this->assertSame($component->getIdentifier(), $this->identity->getIdentifier());
    }

    /**
     * testGetIdentity
     *
     * @eturn void
     */
    public function testGetIdentity(): void
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
    public function testGetIdentityWithCustomAttribute(): void
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
    public function testSetIdentity(): void
    {
        $request = $this->request->withAttribute('authentication', $this->service);

        $controller = new Controller($request);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $component->setIdentity($this->identityData);

        $result = $component->getIdentity();
        $this->assertSame($this->identityData, $result->getOriginalData());

        $identityData = ['id' => 99];
        $component->setIdentity($identityData);
        $result = $component->getIdentity();
        $this->assertSame($identityData, $result->getOriginalData());
    }

    /**
     * Test that the setIdentity() called with an identity instance sets
     * this instance as a request attribute
     *
     * @eturn void
     */
    public function testSetIdentityInstance(): void
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
    public function testSetIdentityOverwrite(): void
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
            'Session should be updated.',
        );

        // Replace the identity
        $newIdentity = new Entity(['username' => 'jessie']);
        $component->setIdentity($newIdentity);

        $result = $component->getIdentity();
        $this->assertSame($newIdentity, $result->getOriginalData());
        $this->assertSame(
            $newIdentity->username,
            $request->getSession()->read('Auth.username'),
            'Session should be updated.',
        );
    }

    /**
     * Ensure replaceIdentity() swaps the request attribute without
     * touching the session.
     *
     * @return void
     */
    public function testReplaceIdentity(): void
    {
        $request = $this->request->withAttribute('authentication', $this->service);

        $controller = new Controller($request);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $component->replaceIdentity($this->identityData);

        $result = $component->getIdentity();
        $this->assertInstanceOf(IdentityInterface::class, $result);
        $this->assertSame($this->identityData, $result->getOriginalData());
        $this->assertNull(
            $controller->getRequest()->getSession()->read('Auth'),
            'Session must not be written by replaceIdentity().',
        );
    }

    /**
     * Test that replaceIdentity() called with an identity instance keeps the
     * exact instance as the request attribute.
     *
     * @return void
     */
    public function testReplaceIdentityInstance(): void
    {
        $request = $this->request->withAttribute('authentication', $this->service);

        $controller = new Controller($request);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $identity = new Identity($this->identityData);
        $component->replaceIdentity($identity);

        $this->assertSame($identity, $component->getIdentity());
    }

    /**
     * Ensure replaceIdentity() does not end an active impersonation,
     * unlike setIdentity() which clears identity first.
     *
     * @return void
     */
    public function testReplaceIdentityKeepsImpersonation(): void
    {
        $impersonator = new ArrayObject(['username' => 'mariano']);
        $impersonated = new ArrayObject(['username' => 'larry']);
        $this->request->getSession()->write('Auth', $impersonator);
        $this->service->authenticate($this->request);
        $identity = new Identity($impersonator);
        $request = $this->request
            ->withAttribute('identity', $identity)
            ->withAttribute('authentication', $this->service);
        $controller = new Controller($request);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $component->impersonate($impersonated);
        $this->assertEquals($impersonated, $controller->getRequest()->getSession()->read('Auth'));
        $this->assertEquals($impersonator, $controller->getRequest()->getSession()->read('AuthImpersonate'));

        $reloaded = new ArrayObject(['username' => 'larry', 'profile' => 'loaded']);
        $component->replaceIdentity($reloaded);

        $this->assertSame(
            $reloaded,
            $component->getIdentity()->getOriginalData(),
            'Request identity should reflect the reloaded user.',
        );
        $this->assertEquals(
            $impersonated,
            $controller->getRequest()->getSession()->read('Auth'),
            'Session Auth slot must be untouched by replaceIdentity().',
        );
        $this->assertEquals(
            $impersonator,
            $controller->getRequest()->getSession()->read('AuthImpersonate'),
            'Impersonation must survive replaceIdentity().',
        );
        $this->assertTrue($component->isImpersonating());
    }

    /**
     * Ensure setIdentity($identity, preserveImpersonation: true) persists the
     * new identity into the session but does not end an active impersonation,
     * unlike the default flow.
     *
     * @return void
     */
    public function testSetIdentityPreserveImpersonation(): void
    {
        $impersonator = new ArrayObject(['username' => 'mariano']);
        $impersonated = new ArrayObject(['username' => 'larry']);
        $this->request->getSession()->write('Auth', $impersonator);
        $this->service->authenticate($this->request);
        $identity = new Identity($impersonator);
        $request = $this->request
            ->withAttribute('identity', $identity)
            ->withAttribute('authentication', $this->service);
        $controller = new Controller($request);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $component->impersonate($impersonated);
        $this->assertEquals($impersonated, $controller->getRequest()->getSession()->read('Auth'));
        $this->assertEquals($impersonator, $controller->getRequest()->getSession()->read('AuthImpersonate'));

        $reloaded = new ArrayObject(['username' => 'larry', 'profile' => 'loaded']);
        $component->setIdentity($reloaded, preserveImpersonation: true);

        $this->assertSame(
            $reloaded,
            $component->getIdentity()->getOriginalData(),
            'Request identity should reflect the reloaded user.',
        );
        $this->assertEquals(
            $reloaded,
            $controller->getRequest()->getSession()->read('Auth'),
            'Session Auth slot must be persisted with the reloaded user.',
        );
        $this->assertEquals(
            $impersonator,
            $controller->getRequest()->getSession()->read('AuthImpersonate'),
            'Impersonation must survive setIdentity() when preserveImpersonation is set.',
        );
        $this->assertTrue($component->isImpersonating());
    }

    /**
     * Ensure that `setIdentity()` with the default behavior still ends an
     * active impersonation - we do not want to silently change BC.
     *
     * @return void
     */
    public function testSetIdentityDefaultEndsImpersonation(): void
    {
        $impersonator = new ArrayObject(['username' => 'mariano']);
        $impersonated = new ArrayObject(['username' => 'larry']);
        $this->request->getSession()->write('Auth', $impersonator);
        $this->service->authenticate($this->request);
        $identity = new Identity($impersonator);
        $request = $this->request
            ->withAttribute('identity', $identity)
            ->withAttribute('authentication', $this->service);
        $controller = new Controller($request);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $component->impersonate($impersonated);

        $reloaded = new ArrayObject(['username' => 'larry', 'profile' => 'loaded']);
        $component->setIdentity($reloaded);

        $this->assertNull(
            $controller->getRequest()->getSession()->read('AuthImpersonate'),
            'Default setIdentity() must end an active impersonation.',
        );
    }

    /**
     * testGetIdentity
     *
     * @eturn void
     */
    public function testGetIdentityData(): void
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
    public function testGetMissingIdentityData(): void
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
    public function testGetResult(): void
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
    public function testLogout(): void
    {
        $result = null;
        EventManager::instance()->on('Authentication.logout', function (Event $event) use (&$result): void {
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
    public function testGetLoginRedirect(): void
    {
        Configure::write('App.base', '/cakephp');
        $url = ['controller' => 'Users', 'action' => 'dashboard'];
        Router::createRouteBuilder('/')
            ->connect('/dashboard', $url);

        $this->service->setConfig('queryParam', 'redirect');
        $request = $this->request
            ->withAttribute('identity', $this->identity)
            ->withAttribute('authentication', $this->service);

        $controller = new Controller($request);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $result = $component->getLoginRedirect($url);
        $this->assertSame('/dashboard', $result);

        $request = $request->withQueryParams(['redirect' => 'ok/path?value=key']);

        $controller = new Controller($request);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $result = $component->getLoginRedirect($url);
        $this->assertSame('/ok/path?value=key', $result);

        Configure::delete('App.base');
    }

    /**
     * testRedirectAfterLogin
     *
     * @return void
     */
    public function testRedirectAfterLogin(): void
    {
        Configure::write('App.base', '/cakephp');
        $url = ['controller' => 'Users', 'action' => 'dashboard'];
        Router::createRouteBuilder('/')
            ->connect('/dashboard', $url);

        $this->service->setConfig('queryParam', 'redirect');
        $request = $this->request
            ->withAttribute('identity', $this->identity)
            ->withAttribute('authentication', $this->service)
            ->withQueryParams(['redirect' => 'ok/path?value=key']);

        $controller = new Controller($request);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $response = $component->redirectAfterLogin($url);
        $this->assertSame(Router::url('/ok/path?value=key'), $response?->getHeaderLine('Location'));

        Configure::delete('App.base');
    }

    /**
     * testRedirectAfterLoginFallsBackToDefaultForAbsoluteUrls
     *
     * @return void
     */
    public function testRedirectAfterLoginFallsBackToDefaultForAbsoluteUrls(): void
    {
        $url = ['controller' => 'Users', 'action' => 'dashboard'];
        Router::createRouteBuilder('/')
            ->connect('/dashboard', $url);

        $this->service->setConfig('queryParam', 'redirect');
        $request = $this->request
            ->withAttribute('identity', $this->identity)
            ->withAttribute('authentication', $this->service)
            ->withQueryParams(['redirect' => 'https://evil.example/phish']);

        $controller = new Controller($request);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $response = $component->redirectAfterLogin($url);
        $this->assertSame('/dashboard', $response?->getHeaderLine('Location'));
    }

    /**
     * testRedirectAfterLoginFallsBackToDefaultForProtocolRelativeUrls
     *
     * @return void
     */
    public function testRedirectAfterLoginFallsBackToDefaultForProtocolRelativeUrls(): void
    {
        $url = ['controller' => 'Users', 'action' => 'dashboard'];
        Router::createRouteBuilder('/')
            ->connect('/dashboard', $url);

        $this->service->setConfig('queryParam', 'redirect');
        $request = $this->request
            ->withAttribute('identity', $this->identity)
            ->withAttribute('authentication', $this->service)
            ->withQueryParams(['redirect' => '//evil.example/phish']);

        $controller = new Controller($request);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $response = $component->redirectAfterLogin($url);
        $this->assertSame('/dashboard', $response?->getHeaderLine('Location'));
    }

    /**
     * testAfterIdentifyEvent
     *
     * @return void
     */
    public function testAfterIdentifyEvent(): void
    {
        $result = null;
        EventManager::instance()->on('Authentication.afterIdentify', function (Event $event) use (&$result): void {
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
    public function testUnauthenticatedActions(): void
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
            'Should contain unique set.',
        );
    }

    /**
     * test unauthenticated actions ok
     *
     * @return void
     */
    public function testUnauthenticatedActionsOk(): void
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
    public function testUnauthenticatedActionsMismatchAction(): void
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
    public function testUnauthenticatedActionsNoActionsFails(): void
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
     * test disabling requireIdentity via settings
     *
     * @return void
     */
    public function testUnauthenticatedActionsDisabledOptions(): void
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
     * test disabling requireIdentity via convenience method
     *
     * @return void
     */
    public function testUnauthenticatedActionsDisabledOptionsCall(): void
    {
        $request = $this->request
            ->withParam('action', 'view')
            ->withAttribute('authentication', $this->service);

        $controller = new Controller($request);
        $controller->loadComponent('Authentication.Authentication');
        $controller->Authentication->disableIdentityCheck();

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
    public function testIdentityCheckInBeforeFilter(): void
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

    public function testCustomUnauthenticatedMessage(): void
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
    public function testImpersonate(): void
    {
        $impersonator = new ArrayObject(['username' => 'mariano']);
        $impersonated = new ArrayObject(['username' => 'larry']);
        $this->request->getSession()->write('Auth', $impersonator);
        $this->service->authenticate($this->request);
        $identity = new Identity($impersonator);
        $request = $this->request
            ->withAttribute('identity', $identity)
            ->withAttribute('authentication', $this->service);
        $controller = new Controller($request);
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
    public function testImpersonateDecoratorIgnored(): void
    {
        $impersonator = ['username' => 'mariano'];
        $impersonated = new ArrayObject(['username' => 'larry']);

        $this->request->getSession()->write('Auth', $impersonator);
        $this->service->authenticate($this->request);
        $identity = new Identity($impersonator);
        $request = $this->request
            ->withAttribute('identity', $identity)
            ->withAttribute('authentication', $this->service);
        $controller = new Controller($request);
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
    public function testImpersonateNoIdentity(): void
    {
        $impersonated = new ArrayObject(['username' => 'larry']);
        $request = $this->request
            ->withAttribute('authentication', $this->service);
        $controller = new Controller($request);
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
    public function testImpersonateFailure(): void
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
        $controller = new Controller($request);
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
    public function testStopImpersonating(): void
    {
        $impersonator = new ArrayObject(['username' => 'mariano']);
        $impersonated = new ArrayObject(['username' => 'larry']);
        $this->request->getSession()->write('Auth', $impersonated);
        $this->request->getSession()->write('AuthImpersonate', $impersonator);
        $this->service->authenticate($this->request);
        $request = $this->request->withAttribute('authentication', $this->service);
        $controller = new Controller($request);
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
    public function testStopImpersonatingFailure(): void
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
        $controller = new Controller($request);
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
    public function testIsImpersonating(): void
    {
        $impersonator = new ArrayObject(['username' => 'mariano']);
        $impersonated = new ArrayObject(['username' => 'larry']);
        $this->request->getSession()->write('Auth', $impersonated);
        $this->request->getSession()->write('AuthImpersonate', $impersonator);
        $this->service->authenticate($this->request);
        $request = $this->request
            ->withAttribute('authentication', $this->service)
            ->withAttribute('identity', new Identity($impersonated));
        $controller = new Controller($request);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $result = $component->isImpersonating();
        $this->assertTrue($result);

        $component->logout();
        $this->assertFalse($component->isImpersonating());
    }

    /**
     * testGetImpersonationAuthenticationServiceFailure
     *
     * @return void
     */
    public function testGetImpersonationAuthenticationServiceFailure(): void
    {
        $service = $this->getMockBuilder(AuthenticationServiceInterface::class)->getMock();

        $user = new ArrayObject(['username' => 'mariano']);
        $request = $this->request
            ->withAttribute('authentication', $service)
            ->withAttribute('identity', new Identity($user));
        $controller = new Controller($request);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $this->expectException(InvalidArgumentException::class);
        $classname = $service::class;
        $this->expectExceptionMessage(sprintf('The %s must implement ImpersonationInterface in order to use impersonation.', $classname));
        $component->isImpersonating();
    }

    /**
     * testIsImpersonatingNotImpersonating
     *
     * @return void
     */
    public function testIsImpersonatingNotImpersonating(): void
    {
        $user = new ArrayObject(['username' => 'mariano']);
        $this->request->getSession()->write('Auth', $user);
        $this->service->authenticate($this->request);
        $request = $this->request->withAttribute('authentication', $this->service);
        $controller = new Controller($request);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $result = $component->isImpersonating();
        $this->assertFalse($result);
    }
}
