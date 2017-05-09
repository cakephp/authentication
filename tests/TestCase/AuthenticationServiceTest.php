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

use ArrayAccess;
use ArrayObject;
use Authentication\AuthenticationService;
use Authentication\Authenticator\FormAuthenticator;
use Authentication\Authenticator\UnauthorizedException;
use Authentication\Identifier\IdentifierCollection;
use Authentication\Identifier\PasswordIdentifier;
use Authentication\Result;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TestApp\Authentication\Authenticator\InvalidAuthenticator;

class AuthenticationServiceTest extends TestCase
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
        $response = new Response();

        $service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password'
            ],
            'authenticators' => [
                'Authentication.Session',
                'Authentication.Form'
            ]
        ]);

        $result = $service->authenticate($request, $response);
        $this->assertTrue($result->isValid());

        $result = $service->getAuthenticationProvider();
        $this->assertInstanceOf(FormAuthenticator::class, $result);

        $this->assertEquals(
            'mariano',
            $request->getAttribute('session')->read('Auth.username')
        );
    }

    /**
     * test authenticate() with a challenger authenticator
     *
     * @return void
     */
    public function testAuthenticateWithChallenge()
    {
        $request = ServerRequestFactory::fromGlobals([
            'SERVER_NAME' => 'example.com',
            'REQUEST_URI' => '/testpath',
            'PHP_AUTH_USER' => 'mariano',
            'PHP_AUTH_PW' => 'WRONG'
        ]);
        $response = new Response();

        $service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password'
            ],
            'authenticators' => [
                'Authentication.HttpBasic'
            ]
        ]);

        try {
            $service->authenticate($request, $response);
            $this->fail('Challenge exception should have been raised');
        } catch (UnauthorizedException $e) {
            $expected = [
                'WWW-Authenticate' => 'Basic realm="example.com"'
            ];
            $this->assertEquals($expected, $e->getHeaders());
        }
    }

    /**
     * testLoadAuthenticatorException
     *
     * @expectedException \RuntimeException
     */
    public function testLoadAuthenticatorException()
    {
        $service = new AuthenticationService();
        $service->loadAuthenticator('does-not-exist');
    }

    /**
     * testLoadInvalidAuthenticatorObject
     *
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Authenticator class `TestApp\Authentication\Authenticator\InvalidAuthenticator` must implement \Auth\Authentication\AuthenticatorInterface
     */
    public function testLoadInvalidAuthenticatorObject()
    {
        $service = new AuthenticationService();
        $service->loadAuthenticator(InvalidAuthenticator::class);
    }

    /**
     * testLoadIdentifier
     *
     * @return void
     */
    public function testLoadIdentifier()
    {
        $service = new AuthenticationService();
        $result = $service->loadIdentifier('Authentication.Password');
        $this->assertInstanceOf(PasswordIdentifier::class, $result);
    }

    /**
     * testIdentifiers
     *
     * @return void
     */
    public function testIdentifiers()
    {
        $service = new AuthenticationService();
        $result = $service->identifiers();
        $this->assertInstanceOf(IdentifierCollection::class, $result);
    }

    /**
     * testClearIdentity
     *
     * @return void
     */
    public function testClearIdentity()
    {
        $service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password'
            ],
            'authenticators' => [
                'Authentication.Form'
            ]
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/']
        );
        $response = new Response();

        $request = $request->withAttribute('identity', ['username' => 'florian']);
        $this->assertNotEmpty($request->getAttribute('identity'));
        $result = $service->clearIdentity($request, $response);
        $this->assertInternalType('array', $result);
        $this->assertInstanceOf(ServerRequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);
        $this->assertNull($result['request']->getAttribute('identity'));
    }

    /**
     * testSetIdentity
     *
     * @return void
     */
    public function testSetIdentity()
    {
        $service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password'
            ],
            'authenticators' => [
                'Authentication.Session',
                'Authentication.Form'
            ]
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/']
        );

        $response = new Response();

        $this->assertEmpty($request->getAttribute('identity'));

        $data = new ArrayObject(['username' => 'florian']);
        $result = $service->setIdentity($request, $response, $data);

        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('request', $result);
        $this->assertArrayHasKey('response', $result);
        $this->assertInstanceOf(RequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);

        $this->assertEquals(
            'florian',
            $result['request']->getAttribute('session')->read('Auth.username')
        );

        $identity = $result['request']->getAttribute('identity');
        $this->assertInstanceOf(ArrayAccess::class, $identity);
        $this->assertEquals($data, $identity);
    }

    /**
     * testGetResult
     *
     * @return void
     */
    public function testGetResult()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $response = new Response();

        $service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password'
            ],
            'authenticators' => [
                'Authentication.Session',
                'Authentication.Form'
            ]
        ]);

        $result = $service->getResult();
        $this->assertNull($result);

        $service->authenticate($request, $response);
        $result = $service->getResult();
        $this->assertInstanceOf(Result::class, $result);
    }

    /**
     * testNoAuthenticatorsLoadedException
     *
     * @expectedException \RuntimeException
     * @expectedExceptionMessage No authenticators loaded. You need to load at least one authenticator.
     * @return void
     */
    public function testNoAuthenticatorsLoadedException()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $response = new Response();

        $service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password'
            ]
        ]);

        $service->authenticate($request, $response);
    }
}
