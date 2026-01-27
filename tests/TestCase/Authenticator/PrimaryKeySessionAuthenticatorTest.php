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
use Authentication\Authenticator\PrimaryKeySessionAuthenticator;
use Authentication\Authenticator\Result;
use Authentication\Identifier\IdentifierFactory;
use Authentication\Identifier\TokenIdentifier;
use Cake\Http\Exception\UnauthorizedException;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;
use Cake\Http\Session;
use Cake\TestSuite\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class PrimaryKeySessionAuthenticatorTest extends TestCase
{
    /**
     * @var array<string>
     */
    protected array $fixtures = [
        'core.AuthUsers',
        'core.Users',
    ];

    /**
     * @var \Authentication\Identifier\IdentifierInterface
     */
    protected $identifier;

    /**
     * @var \Cake\Http\Session&\PHPUnit\Framework\MockObject\MockObject
     */
    protected $sessionMock;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->identifier = IdentifierFactory::create('Authentication.Token', [
            'tokenField' => 'id',
            'dataField' => 'key',
        ]);

        $this->sessionMock = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['read', 'write', 'delete', 'renew', 'check'])
            ->getMock();
    }

    /**
     * Test authentication with explicit identifier
     *
     * @return void
     */
    public function testAuthenticateSuccess()
    {
        $request = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/']);

        $this->sessionMock->expects($this->once())
            ->method('read')
            ->with('Auth')
            ->willReturn(1);

        $request = $request->withAttribute('session', $this->sessionMock);

        $authenticator = new PrimaryKeySessionAuthenticator($this->identifier);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());
    }

    /**
     * Test authentication works with default identifier (no explicit configuration)
     *
     * @return void
     */
    public function testAuthenticateSuccessWithDefaultIdentifier()
    {
        $request = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/']);

        $this->sessionMock->expects($this->once())
            ->method('read')
            ->with('Auth')
            ->willReturn(1);

        $request = $request->withAttribute('session', $this->sessionMock);

        // No identifier passed - should use the default TokenIdentifier
        $authenticator = new PrimaryKeySessionAuthenticator(null);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());
    }

    /**
     * Test getIdentifier returns default TokenIdentifier when none configured
     *
     * @return void
     */
    public function testGetIdentifierReturnsDefaultWhenNotConfigured()
    {
        $authenticator = new PrimaryKeySessionAuthenticator(null);
        $identifier = $authenticator->getIdentifier();

        $this->assertInstanceOf(TokenIdentifier::class, $identifier);
        $this->assertSame('id', $identifier->getConfig('tokenField'));
        $this->assertSame('key', $identifier->getConfig('dataField'));
    }

    /**
     * Test custom idField/identifierKey config propagates to default identifier
     *
     * @return void
     */
    public function testGetIdentifierUsesCustomConfig()
    {
        $authenticator = new PrimaryKeySessionAuthenticator(null, [
            'idField' => 'uuid',
            'identifierKey' => 'token',
        ]);
        $identifier = $authenticator->getIdentifier();

        $this->assertInstanceOf(TokenIdentifier::class, $identifier);
        $this->assertSame('uuid', $identifier->getConfig('tokenField'));
        $this->assertSame('token', $identifier->getConfig('dataField'));
    }

    /**
     * Test authentication
     *
     * @return void
     */
    public function testAuthenticateSuccessCustomFinder()
    {
        $request = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/']);

        $usersTable = $this->fetchTable('AuthUsers');
        $user = $usersTable->find()->firstOrFail();

        $this->sessionMock->expects($this->once())
            ->method('read')
            ->with('Auth')
            ->willReturn($user->id);

        $request = $request->withAttribute('session', $this->sessionMock);

        $this->identifier = IdentifierFactory::create('Authentication.Token', [
            'tokenField' => 'id',
            'dataField' => 'key',
            'resolver' => [
                'className' => 'Authentication.Orm',
                'userModel' => 'AuthUsers',
                'finder' => 'auth',
            ],
        ]);

        $authenticator = new PrimaryKeySessionAuthenticator($this->identifier);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());

        $entity = $result->getData();
        $this->assertNotEmpty($entity->username);
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

        $authenticator = new PrimaryKeySessionAuthenticator($this->identifier);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_IDENTITY_NOT_FOUND, $result->getStatus());
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
            ->willReturn(999);

        $request = $request->withAttribute('session', $this->sessionMock);

        $authenticator = new PrimaryKeySessionAuthenticator($this->identifier, [
        ]);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_IDENTITY_NOT_FOUND, $result->getStatus());
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
        $authenticator = new PrimaryKeySessionAuthenticator($this->identifier);

        $data = new ArrayObject(['id' => 1]);

        $this->sessionMock
            ->expects($this->exactly(2))
            ->method('check')
            ->with(
                ...static::withConsecutive(['Auth'], ['Auth']),
            )
            ->willReturnOnConsecutiveCalls(false, true);

        $this->sessionMock
            ->expects($this->once())
            ->method('renew');

        $this->sessionMock
            ->expects($this->once())
            ->method('write')
            ->with('Auth', 1);

        $result = $authenticator->persistIdentity($request, $response, $data);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('request', $result);
        $this->assertArrayHasKey('response', $result);
        $this->assertInstanceOf(RequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);

        // Persist again to make sure identity isn't replaced if it exists.
        $authenticator->persistIdentity($request, $response, 2);
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

        $authenticator = new PrimaryKeySessionAuthenticator($this->identifier);

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

        $authenticator = new PrimaryKeySessionAuthenticator($this->identifier);
        $usersTable = $this->fetchTable('Users');
        $impersonator = $usersTable->newEntity([
            'username' => 'mariano',
            'password' => 'password',
        ]);
        $impersonator->id = 123;
        $impersonated = $usersTable->newEntity(['username' => 'larry']);
        $impersonated->id = 456;

        $this->sessionMock->expects($this->once())
            ->method('check')
            ->with('AuthImpersonate');

        $this->sessionMock
            ->expects($this->exactly(2))
            ->method('write')
            ->with(
                ...static::withConsecutive(['AuthImpersonate', $impersonator->id], ['Auth', $impersonated->id]),
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

        $authenticator = new PrimaryKeySessionAuthenticator($this->identifier);
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
            'You are impersonating a user already. Stop the current impersonation before impersonating another user.',
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

        $authenticator = new PrimaryKeySessionAuthenticator($this->identifier);

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

        $authenticator = new PrimaryKeySessionAuthenticator($this->identifier);

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

        $authenticator = new PrimaryKeySessionAuthenticator($this->identifier);

        $this->sessionMock->expects($this->once())
            ->method('check')
            ->with('AuthImpersonate');

        $result = $authenticator->isImpersonating($request);
        $this->assertFalse($result);
    }
}
