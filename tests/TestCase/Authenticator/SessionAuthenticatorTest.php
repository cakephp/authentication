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
namespace Authentication\Test\TestCase\Authenticator;

use ArrayObject;
use Authentication\Authenticator\Result;
use Authentication\Authenticator\SessionAuthenticator;
use Authentication\Identifier\IdentifierCollection;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Http\Exception\UnauthorizedException;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;
use Cake\ORM\TableRegistry;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class SessionAuthenticatorTest extends TestCase
{
    /**
     * Fixtures
     *
     * @var array
     */
    protected array $fixtures = [
        'core.AuthUsers',
        'core.Users',
    ];

    /**
     * @var \Authentication\IdentifierCollection
     */
    protected $identifiers;

    protected $sessionMock;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->identifiers = new IdentifierCollection([
           'Authentication.Password',
        ]);

        $class = 'Cake\Http\Session';
        if (!class_exists($class)) {
            $class = '\Cake\Network\Session';
        }
        $this->sessionMock = $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->onlyMethods(['read', 'write', 'delete', 'renew', 'check'])
            ->getMock();
    }

    /**
     * Test authentication
     *
     * @return void
     */
    public function testAuthenticateSuccess()
    {
        $request = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/']);

        $this->sessionMock->expects($this->once())
            ->method('read')
            ->with('Auth')
            ->willReturn([
                'username' => 'mariano',
                'password' => 'password',
            ]);

        $request = $request->withAttribute('session', $this->sessionMock);

        $authenticator = new SessionAuthenticator($this->identifiers);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());
    }

    /**
     * Test authentication
     *
     * @return void
     */
    public function testAuthenticateFailure()
    {
        $request = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/']);

        $this->sessionMock->expects($this->once())
            ->method('read')
            ->with('Auth')
            ->willReturn(null);

        $request = $request->withAttribute('session', $this->sessionMock);

        $authenticator = new SessionAuthenticator($this->identifiers);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_IDENTITY_NOT_FOUND, $result->getStatus());
    }

    /**
     * Test successful session data verification by database lookup
     *
     * @return void
     */
    public function testVerifyByDatabaseSuccess()
    {
        $request = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/']);

        $this->sessionMock->expects($this->once())
            ->method('read')
            ->with('Auth')
            ->willReturn([
                'username' => 'mariano',
                'password' => 'h45h',
            ]);

        $request = $request->withAttribute('session', $this->sessionMock);

        $authenticator = new SessionAuthenticator($this->identifiers, [
            'identify' => true,
        ]);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());
    }

    /**
     * Test session data verification by database lookup failure
     *
     * @return void
     */
    public function testVerifyByDatabaseFailure()
    {
        $request = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/']);

        $this->sessionMock->expects($this->once())
            ->method('read')
            ->with('Auth')
            ->willReturn([
                'username' => 'does-not',
                'password' => 'exist',
            ]);

        $request = $request->withAttribute('session', $this->sessionMock);

        $authenticator = new SessionAuthenticator($this->identifiers, [
            'identify' => true,
        ]);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_CREDENTIALS_INVALID, $result->getStatus());
    }

    /**
     * testPersistIdentity
     *
     * @return void
     */
    public function testPersistIdentity()
    {
        $request = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/']);
        $request = $request->withAttribute('session', $this->sessionMock);
        $response = new Response();
        $authenticator = new SessionAuthenticator($this->identifiers);

        $data = new ArrayObject(['username' => 'florian']);

        $this->sessionMock
            ->expects($this->exactly(2))
            ->method('check')
            ->with(
                ...self::withConsecutive(['Auth'], ['Auth'])
            )
            ->willReturnOnConsecutiveCalls(false, true);

        $this->sessionMock
            ->expects($this->once())
            ->method('renew');

        $this->sessionMock
            ->expects($this->once())
            ->method('write')
            ->with('Auth', $data);

        $result = $authenticator->persistIdentity($request, $response, $data);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('request', $result);
        $this->assertArrayHasKey('response', $result);
        $this->assertInstanceOf(RequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);

        // Persist again to make sure identity isn't replaced if it exists.
        $authenticator->persistIdentity($request, $response, new ArrayObject(['username' => 'jane']));
    }

    /**
     * testClearIdentity
     *
     * @return void
     */
    public function testClearIdentity()
    {
        $request = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/']);
        $request = $request->withAttribute('session', $this->sessionMock);
        $response = new Response();

        $authenticator = new SessionAuthenticator($this->identifiers);

        $this->sessionMock->expects($this->once())
            ->method('delete')
            ->with('Auth');

        $this->sessionMock
            ->expects($this->once())
            ->method('renew');

        $result = $authenticator->clearIdentity($request, $response);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('request', $result);
        $this->assertArrayHasKey('response', $result);
        $this->assertInstanceOf(RequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);
    }

    /**
     * testImpersonate
     *
     * @return void
     */
    public function testImpersonate()
    {
        $request = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/']);
        $request = $request->withAttribute('session', $this->sessionMock);
        $response = new Response();

        $authenticator = new SessionAuthenticator($this->identifiers);
        $AuthUsers = TableRegistry::getTableLocator()->get('AuthUsers');
        $impersonator = $AuthUsers->newEntity([
            'username' => 'mariano',
            'password' => 'password',
        ]);
        $impersonated = $AuthUsers->newEntity(['username' => 'larry']);

        $this->sessionMock->expects($this->once())
            ->method('check')
            ->with('AuthImpersonate');

        $this->sessionMock
            ->expects($this->exactly(2))
            ->method('write')
            ->with(
                ...self::withConsecutive(['AuthImpersonate', $impersonator], ['Auth', $impersonated])
            );

        $result = $authenticator->impersonate($request, $response, $impersonator, $impersonated);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('request', $result);
        $this->assertArrayHasKey('response', $result);
        $this->assertInstanceOf(RequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);
    }

    /**
     * testImpersonateAlreadyImpersonating
     *
     * @return void
     */
    public function testImpersonateAlreadyImpersonating()
    {
        $request = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/']);
        $request = $request->withAttribute('session', $this->sessionMock);
        $response = new Response();

        $authenticator = new SessionAuthenticator($this->identifiers);
        $impersonator = new ArrayObject([
            'username' => 'mariano',
            'password' => 'password',
        ]);
        $impersonated = new ArrayObject(['username' => 'larry']);

        $this->sessionMock->expects($this->once())
            ->method('check')
            ->with('AuthImpersonate')
            ->willReturn(true);

        $this->sessionMock
            ->expects($this->never())
            ->method('write');

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage(
            'You are impersonating a user already. Stop the current impersonation before impersonating another user.'
        );
        $authenticator->impersonate($request, $response, $impersonator, $impersonated);
    }

    /**
     * testStopImpersonating
     *
     * @return void
     */
    public function testStopImpersonating()
    {
        $request = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/']);
        $request = $request->withAttribute('session', $this->sessionMock);
        $response = new Response();

        $authenticator = new SessionAuthenticator($this->identifiers);

        $impersonator = new ArrayObject([
            'username' => 'mariano',
            'password' => 'password',
        ]);

        $this->sessionMock->expects($this->once())
            ->method('check')
            ->with('AuthImpersonate')
            ->willReturn(true);

        $this->sessionMock
            ->expects($this->once())
            ->method('read')
            ->with('AuthImpersonate')
            ->willReturn($impersonator);

        $this->sessionMock
            ->expects($this->once())
            ->method('delete')
            ->with('AuthImpersonate');

        $this->sessionMock
            ->expects($this->once())
            ->method('write')
            ->with('Auth', $impersonator);

        $result = $authenticator->stopImpersonating($request, $response);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('request', $result);
        $this->assertArrayHasKey('response', $result);
        $this->assertInstanceOf(RequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);
    }

    /**
     * testStopImpersonatingNotImpersonating
     *
     * @return void
     */
    public function testStopImpersonatingNotImpersonating()
    {
        $request = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/']);
        $request = $request->withAttribute('session', $this->sessionMock);
        $response = new Response();

        $authenticator = new SessionAuthenticator($this->identifiers);

        $this->sessionMock->expects($this->once())
            ->method('check')
            ->with('AuthImpersonate')
            ->willReturn(false);

        $this->sessionMock
            ->expects($this->never())
            ->method('read');

        $this->sessionMock
            ->expects($this->never())
            ->method('delete');

        $this->sessionMock
            ->expects($this->never())
            ->method('write');

        $result = $authenticator->stopImpersonating($request, $response);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('request', $result);
        $this->assertArrayHasKey('response', $result);
        $this->assertInstanceOf(RequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);
    }

    /**
     * testIsImpersonating
     *
     * @return void
     */
    public function testIsImpersonating()
    {
        $request = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/']);
        $request = $request->withAttribute('session', $this->sessionMock);

        $authenticator = new SessionAuthenticator($this->identifiers);

        $this->sessionMock->expects($this->once())
            ->method('check')
            ->with('AuthImpersonate');

        $result = $authenticator->isImpersonating($request);
        $this->assertFalse($result);
    }
}
