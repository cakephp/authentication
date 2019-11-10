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
namespace Authentication\Test\TestCase;

use ArrayObject;
use Authentication\AuthenticationService;
use Authentication\Authenticator\AuthenticationRequiredException;
use Authentication\Authenticator\AuthenticatorInterface;
use Authentication\Authenticator\FormAuthenticator;
use Authentication\Authenticator\Result;
use Authentication\Identifier\IdentifierCollection;
use Authentication\Identifier\PasswordIdentifier;
use Authentication\Identity;
use Authentication\IdentityInterface;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\Http\ServerRequestFactory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TestApp\Authentication\Authenticator\InvalidAuthenticator;

class AuthenticationServiceTest extends TestCase
{
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
                'Authentication.Password',
            ],
            'authenticators' => [
                'Authentication.Form',
            ],
        ]);

        $result = $service->authenticate($request);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertTrue($result->isValid());

        $result = $service->getAuthenticationProvider();
        $this->assertInstanceOf(FormAuthenticator::class, $result);

        $identifier = $service->getIdentificationProvider();
        $this->assertInstanceOf(PasswordIdentifier::class, $identifier);
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
            'PHP_AUTH_PW' => 'WRONG',
        ]);

        $service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password',
            ],
            'authenticators' => [
                'Authentication.HttpBasic',
            ],
        ]);

        try {
            $service->authenticate($request);
            $this->fail('Challenge exception should have been raised');
        } catch (AuthenticationRequiredException $e) {
            $expected = [
                'WWW-Authenticate' => 'Basic realm="example.com"',
            ];
            $this->assertEquals($expected, $e->getHeaders());
        }
    }

    /**
     * testLoadAuthenticatorException
     */
    public function testLoadAuthenticatorException()
    {
        $this->expectException('RuntimeException');
        $service = new AuthenticationService();
        $service->loadAuthenticator('does-not-exist');
    }

    /**
     * testLoadInvalidAuthenticatorObject
     */
    public function testLoadInvalidAuthenticatorObject()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Authenticator class `TestApp\Authentication\Authenticator\InvalidAuthenticator` must implement `Authentication\Authenticator\AuthenticatorInterface`');
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
                'Authentication.Password',
            ],
            'authenticators' => [
                'Authentication.Form',
            ],
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/']
        );
        $response = new Response();

        $request = $request->withAttribute('identity', ['username' => 'florian']);
        $this->assertNotEmpty($request->getAttribute('identity'));
        $result = $service->clearIdentity($request, $response);
        $this->assertIsArray($result);
        $this->assertInstanceOf(ServerRequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);
        $this->assertNull($result['request']->getAttribute('identity'));
    }

    /**
     * testClearIdentity, with custom identity attribute
     *
     * @return void
     */
    public function testClearIdentityWithCustomIdentityAttribute()
    {
        $service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password',
            ],
            'authenticators' => [
                'Authentication.Form',
            ],
            'identityAttribute' => 'customIdentity',
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/']
        );
        $response = new Response();

        $request = $request->withAttribute('customIdentity', ['username' => 'florian']);
        $this->assertNotEmpty($request->getAttribute('customIdentity'));
        $result = $service->clearIdentity($request, $response);
        $this->assertIsArray($result);
        $this->assertInstanceOf(ServerRequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);
        $this->assertNull($result['request']->getAttribute('customIdentity'));
    }

    /**
     * testClearIdentity, with custom identity attribute
     *
     * @return void
     */
    public function testClearIdentityWithCustomIdentityAttributeShouldPreserveDefault()
    {
        $service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password',
            ],
            'authenticators' => [
                'Authentication.Form',
            ],
            'identityAttribute' => 'customIdentity',
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/']
        );
        $response = new Response();

        $request = $request->withAttribute('identity', ['username' => 'johndoe']);
        $this->assertNotEmpty($request->getAttribute('identity'));
        $request = $request->withAttribute('customIdentity', ['username' => 'florian']);
        $this->assertNotEmpty($request->getAttribute('customIdentity'));
        $result = $service->clearIdentity($request, $response);
        $this->assertIsArray($result);
        $this->assertInstanceOf(ServerRequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);
        $this->assertNull($result['request']->getAttribute('customIdentity'));

        $data = ['username' => 'johndoe'];
        $this->assertEquals($data, $result['request']->getAttribute('identity'));
    }

    /**
     * testPersistIdentity
     *
     * @return void
     */
    public function testPersistIdentity()
    {
        $service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password',
            ],
            'authenticators' => [
                'Authentication.Session',
                'Authentication.Form',
            ],
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/']
        );

        $response = new Response();

        $this->assertEmpty($request->getAttribute('identity'));

        $data = new ArrayObject(['username' => 'florian']);
        $result = $service->persistIdentity($request, $response, $data);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('request', $result);
        $this->assertArrayHasKey('response', $result);
        $this->assertInstanceOf(RequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);

        $this->assertEquals(
            'florian',
            $result['request']->getAttribute('session')->read('Auth.username')
        );

        $identity = $result['request']->getAttribute('identity');
        $this->assertInstanceOf(IdentityInterface::class, $identity);
        $this->assertEquals($data, $identity->getOriginalData());
    }

    /**
     * testPersistIdentity, with custom identity attribute
     *
     * @return void
     */
    public function testPersistIdentityWithCustomIdentityAttribute()
    {
        $service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password',
            ],
            'authenticators' => [
                'Authentication.Session',
                'Authentication.Form',
            ],
            'identityAttribute' => 'customIdentity',
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/']
        );

        $response = new Response();

        $this->assertEmpty($request->getAttribute('identity'));
        $this->assertEmpty($request->getAttribute('customIdentity'));

        $data = new ArrayObject(['username' => 'florian']);
        $result = $service->persistIdentity($request, $response, $data);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('request', $result);
        $this->assertArrayHasKey('response', $result);
        $this->assertInstanceOf(RequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);

        $this->assertEquals(
            'florian',
            $result['request']->getAttribute('session')->read('Auth.username')
        );

        $identity = $result['request']->getAttribute('customIdentity');
        $this->assertInstanceOf(IdentityInterface::class, $identity);
        $this->assertEquals($data, $identity->getOriginalData());
        $this->assertEmpty($result['request']->getAttribute('identity'));
    }

    /**
     * testPersistIdentity, with custom identity attribute
     *
     * @return void
     */
    public function testPersistIdentityWithCustomIdentityAttributeShouldPreserveDefault()
    {
        $service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password',
            ],
            'authenticators' => [
                'Authentication.Session',
                'Authentication.Form',
            ],
            'identityAttribute' => 'customIdentity',
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/']
        );

        $response = new Response();
        $request = $request->withAttribute('identity', ['username' => 'johndoe']);
        $this->assertNotEmpty($request->getAttribute('identity'));

        $this->assertEmpty($request->getAttribute('customIdentity'));

        $data = new ArrayObject(['username' => 'florian']);
        $result = $service->persistIdentity($request, $response, $data);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('request', $result);
        $this->assertArrayHasKey('response', $result);
        $this->assertInstanceOf(RequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);

        $this->assertEquals(
            'florian',
            $result['request']->getAttribute('session')->read('Auth.username')
        );

        $identity = $result['request']->getAttribute('customIdentity');
        $this->assertInstanceOf(IdentityInterface::class, $identity);
        $this->assertEquals($data, $identity->getOriginalData());

        $data = ['username' => 'johndoe'];
        $this->assertEquals($data, $result['request']->getAttribute('identity'));
    }

    /**
     * testPersistIdentityInterface
     *
     * @return void
     */
    public function testPersistIdentityInterface()
    {
        $request = new ServerRequest();
        $response = new Response();
        $identity = $this->createMock(IdentityInterface::class);

        $service = new AuthenticationService();

        $result = $service->persistIdentity($request, $response, $identity);

        $this->assertSame($identity, $result['request']->getAttribute('identity'));
    }

    /**
     * testPersistIdentityInterface
     *
     * @return void
     */
    public function testPersistIdentityArray()
    {
        $request = new ServerRequest();
        $response = new Response();
        $data = [
            'username' => 'robert',
        ];

        $service = new AuthenticationService();

        $result = $service->persistIdentity($request, $response, $data);
        $this->assertInstanceOf(IdentityInterface::class, $result['request']->getAttribute('identity'));
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

        $service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password',
            ],
            'authenticators' => [
                'Authentication.Session',
                'Authentication.Form',
            ],
        ]);

        $result = $service->getResult();
        $this->assertNull($result);

        $service->authenticate($request);
        $result = $service->getResult();
        $this->assertInstanceOf(Result::class, $result);
    }

    /**
     * testNoAuthenticatorsLoadedException
     *
     * @return void
     */
    public function testNoAuthenticatorsLoadedException()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('No authenticators loaded. You need to load at least one authenticator.');
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );

        $service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password',
            ],
        ]);

        $service->authenticate($request);
    }

    /**
     * testBuildIdentity
     *
     * @return void
     */
    public function testBuildIdentity()
    {
        $service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password',
            ],
        ]);

        $this->assertInstanceOf(Identity::class, $service->buildIdentity(new ArrayObject([])));
    }

    /**
     * testBuildIdentityRuntimeException
     *
     * @return void
     */
    public function testBuildIdentityRuntimeException()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Object `stdClass` does not implement `Authentication\IdentityInterface`');
        $service = new AuthenticationService([
            'identityClass' => \stdClass::class,
            'identifiers' => [
                'Authentication.Password',
            ],
        ]);

        $service->buildIdentity(new ArrayObject([]));
    }

    /**
     * testCallableIdentityProvider
     *
     * @return void
     */
    public function testCallableIdentityProvider()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );

        $callable = function () {
            return new Identity(new ArrayObject([
                'id' => 'by-callable',
            ]));
        };

        $service = new AuthenticationService([
            'identityClass' => $callable,
            'identifiers' => [
                'Authentication.Password',
            ],
            'authenticators' => [
                'Authentication.Form',
            ],
        ]);

        // Authenticate an identity
        $service->authenticate($request);
        $this->assertInstanceOf(Identity::class, $service->getIdentity());
        $this->assertEquals('by-callable', $service->getIdentity()->getIdentifier());
    }

    /**
     * testGetIdentity
     *
     * @return void
     */
    public function testGetIdentity()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );

        $service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password',
            ],
            'authenticators' => [
                'Authentication.Form',
            ],
        ]);

        // No identity present before login
        $this->assertNull($service->getIdentity());

        // Authenticate an identity
        $service->authenticate($request);

        // Now we can get the identity
        $this->assertInstanceOf(Identity::class, $service->getIdentity());
    }

    /**
     * testGetIdentityInterface
     *
     * @return void
     */
    public function testGetIdentityInterface()
    {
        $request = new ServerRequest();

        $identity = $this->createMock(IdentityInterface::class);
        $result = new Result($identity, Result::SUCCESS);

        $authenticator = $this->createMock(AuthenticatorInterface::class);
        $authenticator->method('authenticate')
            ->willReturn($result);

        $service = new AuthenticationService();
        $service->authenticators()->set('Test', $authenticator);

        $service->authenticate($request);

        $this->assertSame($identity, $service->getIdentity());
    }

    public function testGetIdentityAttribute()
    {
        $service = new AuthenticationService(['identityAttribute' => 'user']);
        $this->assertSame('user', $service->getIdentityAttribute());
    }

    public function testGetUnauthenticatedRedirectUrlNoValues()
    {
        $service = new AuthenticationService();
        $request = new ServerRequest();

        $this->assertNull($service->getUnauthenticatedRedirectUrl($request));
    }

    public function testGetUnauthenticatedRedirectUrl()
    {
        $service = new AuthenticationService();
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/secrets']
        );
        $service->setConfig('unauthenticatedRedirect', '/users/login');
        $this->assertSame('/users/login', $service->getUnauthenticatedRedirectUrl($request));

        $service->setConfig('queryParam', 'redirect');
        $this->assertSame(
            '/users/login?redirect=%2Fsecrets',
            $service->getUnauthenticatedRedirectUrl($request)
        );

        $service->setConfig('unauthenticatedRedirect', '/users/login?foo=bar');
        $this->assertSame(
            '/users/login?foo=bar&redirect=%2Fsecrets',
            $service->getUnauthenticatedRedirectUrl($request)
        );

        $service->setConfig('unauthenticatedRedirect', '/users/login?foo=bar#fragment');
        $this->assertSame(
            '/users/login?foo=bar&redirect=%2Fsecrets#fragment',
            $service->getUnauthenticatedRedirectUrl($request)
        );
    }

    public function testGetUnauthenticatedRedirectUrlWithBasePath()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/secrets']
        );
        $uri = $request->getUri();
        $uri->base = '/base';
        $request = $request->withUri($uri);

        $service = new AuthenticationService([
            'unauthenticatedRedirect' => '/users/login',
            'queryParam' => 'redirect',
        ]);
        $this->assertSame(
            '/users/login?redirect=%2Fbase%2Fsecrets',
            $service->getUnauthenticatedRedirectUrl($request)
        );
    }

    public function testGetLoginRedirect()
    {
        $service = new AuthenticationService([
            'unauthenticatedRedirect' => '/users/login',
            'queryParam' => 'redirect',
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/secrets']
        );
        $this->assertNull($service->getLoginRedirect($request));

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/secrets'],
            ['redirect' => '']
        );
        $this->assertNull($service->getLoginRedirect($request));

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/secrets'],
            ['redirect' => 'http://evil.ca/evil/path']
        );
        $this->assertNull($service->getLoginRedirect($request));

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/secrets'],
            ['redirect' => 'ok.com/path']
        );
        $this->assertSame(
            '/ok.com/path',
            $service->getLoginRedirect($request)
        );

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/secrets'],
            ['redirect' => '/path/with?query=string']
        );
        $this->assertSame(
            '/path/with?query=string',
            $service->getLoginRedirect($request)
        );
    }
}
