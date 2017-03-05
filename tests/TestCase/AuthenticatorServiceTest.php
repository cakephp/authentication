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
namespace Authentication\Test\TestCase\Authenticator;

use Authentication\AuthenticationService;
use Authentication\Authenticator\FormAuthenticator;
use Authentication\Authenticator\UnauthorizedException;
use Authentication\Identifier\IdentifierCollection;
use Authentication\Identifier\OrmIdentifier;
use Authentication\Result;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TestApp\Authentication\Authenticator\InvalidAuthenticator;

class AuthenticatorServiceTest extends TestCase
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
                'Authentication.Orm'
            ],
            'authenticators' => [
                'Authentication.Form'
            ]
        ]);

        $result = $service->authenticate($request, $response);
        $this->assertTrue($result->isValid());

        $result = $service->getAuthenticationProvider();
        $this->assertInstanceOf(FormAuthenticator::class, $result);
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
                'Authentication.Orm'
            ],
            'authenticators' => [
                'Authentication.HttpBasic'
            ]
        ]);

        try {
            $result = $service->authenticate($request, $response);
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
     * @expectedException \Cake\Core\Exception\Exception
     */
    public function testLoadAuthenticatorException()
    {
        $service = new AuthenticationService();
        $service->loadAuthenticator('does-not-exist');
    }

    /**
     * testLoadInvalidAuthenticatorObject
     *
     * @expectedException \Exception
     * @expectedExceptionMessage Authenticator must implement AuthenticateInterface.
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
        $result = $service->loadIdentifier('Authentication.Orm');
        $this->assertInstanceOf(OrmIdentifier::class, $result);
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
                'Authentication.Orm'
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
                'Authentication.Orm'
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

        $result = $service->setIdentity($request, $response, ['username' => 'florian']);

        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('request', $result);
        $this->assertArrayHasKey('response', $result);
        $this->assertInstanceOf(RequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);

        $identity = $result['request']->getAttribute('identity');
        $this->assertInternalType('array', $identity);
        $this->assertEquals(['username' => 'florian'], $identity);
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
                'Authentication.Orm'
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
     * @expectedExceptionMessage No authenticator loaded. You need to load at least one authenticator.
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
                'Authentication.Orm'
            ]
        ]);

        $service->authenticate($request, $response);
    }
}
