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
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class SessionAuthenticatorTest extends TestCase
{
    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'core.AuthUsers',
        'core.Users',
    ];

    /**
     * @inheritdoc
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
            ->setMethods(['read', 'write', 'delete', 'renew', 'check'])
            ->getMock();
    }

    /**
     * Test authentication
     *
     * @return void
     */
    public function testAuthenticate()
    {
        $request = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/']);
        $response = new Response();

        $this->sessionMock->expects($this->at(0))
            ->method('read')
            ->with('Auth')
            ->will($this->returnValue([
                'username' => 'mariano',
                'password' => 'password',
            ]));

        $request = $request->withAttribute('session', $this->sessionMock);

        $authenticator = new SessionAuthenticator($this->identifiers);
        $result = $authenticator->authenticate($request, $response);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::SUCCESS, $result->getStatus());

        $this->sessionMock->expects($this->at(0))
            ->method('read')
            ->with('Auth')
            ->will($this->returnValue(null));

        $request = $request->withAttribute('session', $this->sessionMock);

        $authenticator = new SessionAuthenticator($this->identifiers);
        $result = $authenticator->authenticate($request, $response);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::FAILURE_IDENTITY_NOT_FOUND, $result->getStatus());
    }

    /**
     * Test session data verification by database lookup
     *
     * @return void
     */
    public function testVerifyByDatabase()
    {
        $request = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/']);
        $response = new Response();

        $this->sessionMock->expects($this->at(0))
            ->method('read')
            ->with('Auth')
            ->will($this->returnValue([
                'username' => 'mariano',
                'password' => 'h45h',
            ]));

        $request = $request->withAttribute('session', $this->sessionMock);

        $authenticator = new SessionAuthenticator($this->identifiers, [
            'identify' => true,
        ]);
        $result = $authenticator->authenticate($request, $response);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::SUCCESS, $result->getStatus());

        $this->sessionMock->expects($this->at(0))
            ->method('read')
            ->with('Auth')
            ->will($this->returnValue([
                'username' => 'does-not',
                'password' => 'exist',
            ]));

        $request = $request->withAttribute('session', $this->sessionMock);

        $authenticator = new SessionAuthenticator($this->identifiers, [
            'identify' => true,
        ]);
        $result = $authenticator->authenticate($request, $response);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::FAILURE_CREDENTIALS_INVALID, $result->getStatus());
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
            ->expects($this->at(0))
            ->method('check')
            ->with('Auth')
            ->willReturn(false);

        $this->sessionMock
            ->expects($this->once())
            ->method('renew');

        $this->sessionMock
            ->expects($this->once())
            ->method('write')
            ->with('Auth', $data);

        $this->sessionMock
            ->expects($this->at(3))
            ->method('check')
            ->with('Auth')
            ->willReturn(true);

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

        $this->sessionMock->expects($this->at(0))
            ->method('delete')
            ->with('Auth');

        $this->sessionMock
            ->expects($this->at(1))
            ->method('renew');

        $result = $authenticator->clearIdentity($request, $response);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('request', $result);
        $this->assertArrayHasKey('response', $result);
        $this->assertInstanceOf(RequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);
    }
}
