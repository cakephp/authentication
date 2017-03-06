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
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Test\TestCase\Authenticator;

use Authentication\Authenticator\SessionAuthenticator;
use Authentication\Identifier\IdentifierCollection;
use Authentication\Result;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;
use Cake\Network\Session;
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
        'core.auth_users',
        'core.users'
    ];

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        parent::setUp();

        $this->identifiers = new IdentifierCollection([
           'Authentication.Orm'
        ]);

        $this->sessionMock = $this->getMockBuilder('\Cake\Network\Session')
            ->disableOriginalConstructor()
            ->setMethods(['read', 'write', 'delete'])
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
                'password' => 'password'
            ]));

        $request = $request->withAttribute('session', $this->sessionMock);

        $authenticator = new SessionAuthenticator($this->identifiers);
        $result = $authenticator->authenticate($request, $response);

        $this->assertInstanceOf('\Authentication\Result', $result);
        $this->assertEquals(Result::SUCCESS, $result->getCode());

        $this->sessionMock->expects($this->at(0))
            ->method('read')
            ->with('Auth')
            ->will($this->returnValue(null));

        $request = $request->withAttribute('session', $this->sessionMock);

        $authenticator = new SessionAuthenticator($this->identifiers);
        $result = $authenticator->authenticate($request, $response);

        $this->assertInstanceOf('\Authentication\Result', $result);
        $this->assertEquals(Result::FAILURE_IDENTITY_NOT_FOUND, $result->getCode());
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
                'password' => 'password'
            ]));

        $request = $request->withAttribute('session', $this->sessionMock);

        $authenticator = new SessionAuthenticator($this->identifiers, [
            'identify' => true
        ]);
        $result = $authenticator->authenticate($request, $response);

        $this->assertInstanceOf('\Authentication\Result', $result);
        $this->assertEquals(Result::SUCCESS, $result->getCode());

        $this->sessionMock->expects($this->at(0))
            ->method('read')
            ->with('Auth')
            ->will($this->returnValue([
                'username' => 'does-not',
                'password' => 'exist'
            ]));

        $request = $request->withAttribute('session', $this->sessionMock);

        $authenticator = new SessionAuthenticator($this->identifiers, [
            'identify' => true
        ]);
        $result = $authenticator->authenticate($request, $response);

        $this->assertInstanceOf('\Authentication\Result', $result);
        $this->assertEquals(Result::FAILURE_CREDENTIAL_INVALID, $result->getCode());
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

        $this->sessionMock->expects($this->at(0))
            ->method('write')
            ->with('Auth', ['username' => 'florian']);

        $result = $authenticator->persistIdentity($request, $response, ['username' => 'florian']);
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('request', $result);
        $this->assertArrayHasKey('response', $result);
        $this->assertInstanceOf(RequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);
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

        $result = $authenticator->clearIdentity($request, $response);
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('request', $result);
        $this->assertArrayHasKey('response', $result);
        $this->assertInstanceOf(RequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);
    }
}
