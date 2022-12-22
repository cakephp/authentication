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
use Cake\Http\Exception\UnauthorizedException;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\Http\ServerRequestFactory;
use Cake\I18n\DateTime;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use stdClass;

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
     * Test that no exception if thrown when challenge is disabled for authenticator
     *
     * @return void
     */
    public function testAuthenticateWithChallengeDisabled()
    {
        $request = ServerRequestFactory::fromGlobals([
            'SERVER_NAME' => 'example.com',
            'REQUEST_URI' => '/testpath',
            'PHP_AUTH_USER' => 'admad',
            'PHP_AUTH_PW' => 'WRONG',
        ]);

        $service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password',
            ],
            'authenticators' => [
                'Authentication.HttpBasic' => [
                    'skipChallenge' => true,
                ],
            ],
        ]);

        $result = $service->authenticate($request);
        $this->assertFalse($result->isValid());
    }

    /**
     * Integration test for session auth + identify always getting a fresh user record.
     *
     * @return void
     */
    public function testAuthenticationWithSessionIdentify()
    {
        $users = $this->fetchTable('Users');
        $user = $users->get(1);

        $request = ServerRequestFactory::fromGlobals([
            'SERVER_NAME' => 'example.com',
            'REQUEST_URI' => '/testpath',
        ]);
        $request->getSession()->write('Auth', [
            'username' => $user->username,
            'password' => $user->password,
        ]);

        $factory = function () {
            return new AuthenticationService([
                'identifiers' => [
                    'Authentication.Password',
                ],
                'authenticators' => [
                    'Authentication.Session' => [
                        'identify' => true,
                    ],
                ],
            ]);
        };
        $service = $factory();
        $result = $service->authenticate($request);
        $this->assertTrue($result->isValid());

        $dateValue = new DateTime('2022-01-01 10:11:12');
        $identity = $result->getData();
        $this->assertEquals($identity->username, $user->username);
        $this->assertNotEquals($identity->created, $dateValue);

        // Update the user so that we can ensure session is reading from the db.
        $user->created = $dateValue;
        $users->saveOrFail($user);

        $service = $factory();
        $result = $service->authenticate($request);
        $this->assertTrue($result->isValid());
        $identity = $result->getData();
        $this->assertEquals($identity->username, $user->username);
        $this->assertEquals($identity->created, $dateValue);
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
     * testClearIdentity
     *
     * @return void
     */
    public function testClearIdentityWithImpersonation()
    {
        $service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password',
            ],
            'authenticators' => [
                'Authentication.Session',
            ],
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/']
        );
        $response = new Response();

        $impersonator = new ArrayObject(['username' => 'mariano']);
        $impersonated = new ArrayObject(['username' => 'larry']);
        $request = $request->withAttribute('identity', $impersonated);
        $request->getSession()->write('Auth', $impersonated);
        $request->getSession()->write('AuthImpersonate', $impersonator);
        $this->assertNotEmpty($request->getAttribute('identity'));
        $result = $service->clearIdentity($request, $response);
        $this->assertIsArray($result);
        $this->assertInstanceOf(ServerRequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);
        $this->assertNull($result['request']->getAttribute('identity'));
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

        $this->assertSame(
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

        $this->assertSame(
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

        $this->assertSame(
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
        $identity = new ArrayObject();

        $service = new AuthenticationService();

        $result = $service->persistIdentity($request, $response, $identity);

        $this->assertInstanceOf(IdentityInterface::class, $result['request']->getAttribute('identity'));
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
     * Test that the persistIdentity() called with an identity instance sets
     * this instance as a request attribute.
     *
     * For example the identity data passed to this method (eg. User entity)
     * may already implement the IdentityInterface itself.
     *
     * @return void
     */
    public function testPersistIdentityInstance()
    {
        $request = new ServerRequest();
        $response = new Response();
        $identity = new Identity([]);

        $service = new AuthenticationService();

        $result = $service->persistIdentity($request, $response, $identity);

        $this->assertSame($identity, $result['request']->getAttribute('identity'));
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
     * Tests that passing the identity instance buildIdentity() gets the same result
     *
     * @return void
     */
    public function testBuildIdentityWithInstance()
    {
        $service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password',
            ],
        ]);

        $identity = new Identity([]);
        $result = $service->buildIdentity($identity);

        $this->assertSame($result, $identity);
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
            'identityClass' => stdClass::class,
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
        $this->assertSame('by-callable', $service->getIdentity()->getIdentifier());
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

    /**
     * testGetIdentityNull
     *
     * @return void
     */
    public function testGetIdentityNull()
    {
        $request = new ServerRequest();

        $result = new Result(null, Result::FAILURE_OTHER);

        $authenticator = $this->createMock(AuthenticatorInterface::class);
        $authenticator->method('authenticate')
            ->willReturn($result);

        $service = new AuthenticationService();
        $service->authenticators()->set('Test', $authenticator);

        $service->authenticate($request);

        $this->assertNull($service->getIdentity());
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
        $request = $request->withAttribute('base', '/base');

        $service = new AuthenticationService([
            'unauthenticatedRedirect' => '/users/login',
            'queryParam' => 'redirect',
        ]);
        $this->assertSame(
            '/users/login?redirect=%2Fsecrets',
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

    /**
     * testImpersonate
     *
     * @return void
     */
    public function testImpersonate()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
            []
        );

        $response = new Response();
        $impersonator = new ArrayObject(['username' => 'mariano']);
        $impersonated = new ArrayObject(['username' => 'larry']);
        $request->getSession()->write('Auth', $impersonator);

        $service = new AuthenticationService([
            'authenticators' => [
                'Authentication.Session',
            ],
        ]);
        $service->authenticate($request);
        $result = $service->impersonate($request, $response, $impersonator, $impersonated);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('request', $result);
        $this->assertArrayHasKey('response', $result);
        $this->assertInstanceOf(RequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);
        $this->assertEquals($impersonator, $result['request']->getSession()->read('AuthImpersonate'));
        $this->assertEquals($impersonated, $result['request']->getSession()->read('Auth'));
    }

    /**
     * testImpersonateAlreadyImpersonating
     *
     * @return void
     */
    public function testImpersonateAlreadyImpersonating()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
            []
        );

        $response = new Response();
        $impersonator = new ArrayObject(['username' => 'mariano']);
        $impersonated = new ArrayObject(['username' => 'larry']);
        $request->getSession()->write('Auth', $impersonated);
        $request->getSession()->write('AuthImpersonate', $impersonator);

        $service = new AuthenticationService([
            'authenticators' => [
                'Authentication.Session',
            ],
        ]);
        $service->authenticate($request);
        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage('You are impersonating a user already. Stop the current impersonation before impersonating another user.');
        $service->impersonate($request, $response, $impersonator, $impersonated);
    }

    /**
     * testImpersonateWrongProvider
     *
     * @return void
     */
    public function testImpersonateWrongProvider()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $response = new Response();
        $impersonator = new ArrayObject(['username' => 'mariano']);
        $impersonated = new ArrayObject(['username' => 'larry']);
        $service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password',
            ],
            'authenticators' => [
                'Authentication.Form',
            ],
        ]);

        $service->authenticate($request);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The Authentication\Authenticator\FormAuthenticator Provider must implement ImpersonationInterface in order to use impersonation.');
        $service->impersonate($request, $response, $impersonator, $impersonated);
    }

    /**
     * testStopImpersonating
     *
     * @return void
     */
    public function testStopImpersonating()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
            []
        );

        $response = new Response();
        $impersonator = new ArrayObject(['username' => 'mariano']);
        $impersonated = new ArrayObject(['username' => 'larry']);
        $request->getSession()->write('Auth', $impersonated);
        $request->getSession()->write('AuthImpersonate', $impersonator);

        $service = new AuthenticationService([
            'authenticators' => [
                'Authentication.Session',
            ],
        ]);
        $service->authenticate($request);
        $result = $service->stopImpersonating($request, $response);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('request', $result);
        $this->assertArrayHasKey('response', $result);
        $this->assertInstanceOf(RequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);
        $this->assertNull($result['request']->getSession()->read('AuthImpersonate'));
        $this->assertEquals($impersonator, $result['request']->getSession()->read('Auth'));
    }

    /**
     * testStopImpersonatingWrongProvider
     *
     * @return void
     */
    public function testStopImpersonatingWrongProvider()
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

        $service->authenticate($request);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The Authentication\Authenticator\FormAuthenticator Provider must implement ImpersonationInterface in order to use impersonation.');
        $service->stopImpersonating($request, $response);
    }

    /**
     * testIsImpersonatingImpersonating
     *
     * @return void
     */
    public function testIsImpersonatingImpersonating()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
            []
        );

        $impersonator = new ArrayObject(['username' => 'mariano']);
        $impersonated = new ArrayObject(['username' => 'larry']);
        $request->getSession()->write('Auth', $impersonated);
        $request->getSession()->write('AuthImpersonate', $impersonator);
        $service = new AuthenticationService([
            'authenticators' => [
                'Authentication.Session',

            ],
        ]);
        $service->authenticate($request);

        $result = $service->isImpersonating($request);
        $this->assertTrue($result);
    }

    /**
     * testIsImpersonatingNotImpersonating
     *
     * @return void
     */
    public function testIsImpersonatingNotImpersonating()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
            []
        );

        $user = new ArrayObject(['username' => 'mariano']);
        $request->getSession()->write('Auth', $user);

        $service = new AuthenticationService([
            'authenticators' => [
                'Authentication.Session',

            ],
        ]);
        $service->authenticate($request);
        $result = $service->isImpersonating($request);
        $this->assertFalse($result);
    }

    /**
     * testIsImpersonatingWrongProvider
     *
     * @return void
     */
    public function testIsImpersonatingWrongProvider()
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

        $service->authenticate($request);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The Authentication\Authenticator\FormAuthenticator Provider must implement ImpersonationInterface in order to use impersonation.');
        $service->isImpersonating($request);
    }
}
