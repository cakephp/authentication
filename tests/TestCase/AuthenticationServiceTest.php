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
use Authentication\Authenticator\SessionAuthenticator;
use Authentication\Identifier\PasswordIdentifier;
use Authentication\Identity;
use Authentication\IdentityInterface;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Http\Exception\UnauthorizedException;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\Http\ServerRequestFactory;
use Cake\Routing\Router;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use stdClass;

#[AllowMockObjectsWithoutExpectations]
class AuthenticationServiceTest extends TestCase
{
    /**
     * testAuthenticate
     *
     * @return void
     */
    public function testAuthenticateDeprecated(): void
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password'],
        );

        $service = new AuthenticationService([
            'authenticators' => [
                'Authentication.Form' => [
                    'identifier' => 'Authentication.Password',
                ],
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
     * testAuthenticate
     *
     * @return void
     */
    public function testAuthenticate(): void
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password'],
        );

        $service = new AuthenticationService([
            'authenticators' => [
                'Authentication.Form' => [
                    'identifier' => 'Authentication.Password',
                ],
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
    public function testAuthenticateWithChallenge(): void
    {
        $request = ServerRequestFactory::fromGlobals([
            'SERVER_NAME' => 'example.com',
            'REQUEST_URI' => '/testpath',
            'PHP_AUTH_USER' => 'mariano',
            'PHP_AUTH_PW' => 'WRONG',
        ]);

        $service = new AuthenticationService([
            'authenticators' => [
                'Authentication.HttpBasic' => [
                    'identifier' => 'Authentication.Password',
                ],
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
    public function testAuthenticateWithChallengeDisabled(): void
    {
        $request = ServerRequestFactory::fromGlobals([
            'SERVER_NAME' => 'example.com',
            'REQUEST_URI' => '/testpath',
            'PHP_AUTH_USER' => 'admad',
            'PHP_AUTH_PW' => 'WRONG',
        ]);

        $service = new AuthenticationService([
            'authenticators' => [
                'Authentication.HttpBasic' => [
                    'skipChallenge' => true,
                    'identifier' => 'Authentication.Password',
                ],
            ],
        ]);

        $result = $service->authenticate($request);
        $this->assertFalse($result->isValid());
    }

    /**
     * testLoadAuthenticatorException
     */
    public function testLoadAuthenticatorException(): void
    {
        $this->expectException('RuntimeException');
        $service = new AuthenticationService();
        $service->loadAuthenticator('does-not-exist');
    }

    /**
     * testClearIdentity
     *
     * @return void
     */
    public function testClearIdentity(): void
    {
        $service = new AuthenticationService([
            'authenticators' => [
                'Authentication.Form' => [
                    'identifier' => 'Authentication.Password',
                ],
            ],
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
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
    public function testClearIdentityWithCustomIdentityAttribute(): void
    {
        $service = new AuthenticationService([
            'authenticators' => [
                'Authentication.Form' => [
                    'identifier' => 'Authentication.Password',
                ],
            ],
            'identityAttribute' => 'customIdentity',
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
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
    public function testClearIdentityWithCustomIdentityAttributeShouldPreserveDefault(): void
    {
        $service = new AuthenticationService([
            'authenticators' => [
                'Authentication.Form' => [
                    'identifier' => 'Authentication.Password',
                ],
            ],
            'identityAttribute' => 'customIdentity',
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
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
    public function testClearIdentityWithImpersonation(): void
    {
        $service = new AuthenticationService([
            'authenticators' => [
                'Authentication.Session' => [
                    'identifier' => 'Authentication.Password',
                ],
            ],
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
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
    public function testPersistIdentity(): void
    {
        $service = new AuthenticationService([
            'authenticators' => [
                'Authentication.Session' => [
                    'identifier' => 'Authentication.Password',
                ],
                'Authentication.Form' => [
                    'identifier' => 'Authentication.Password',
                ],
            ],
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
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
            $result['request']->getAttribute('session')->read('Auth.username'),
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
    public function testPersistIdentityWithCustomIdentityAttribute(): void
    {
        $service = new AuthenticationService([
            'authenticators' => [
                'Authentication.Session' => [
                    'identifier' => 'Authentication.Password',
                ],
                'Authentication.Form' => [
                    'identifier' => 'Authentication.Password',
                ],
            ],
            'identityAttribute' => 'customIdentity',
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
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
            $result['request']->getAttribute('session')->read('Auth.username'),
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
    public function testPersistIdentityWithCustomIdentityAttributeShouldPreserveDefault(): void
    {
        $service = new AuthenticationService([
            'authenticators' => [
                'Authentication.Session' => [
                    'identifier' => 'Authentication.Password',
                ],
                'Authentication.Form' => [
                    'identifier' => 'Authentication.Password',
                ],
            ],
            'identityAttribute' => 'customIdentity',
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
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
            $result['request']->getAttribute('session')->read('Auth.username'),
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
    public function testPersistIdentityInterface(): void
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
    public function testPersistIdentityArray(): void
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
    public function testPersistIdentityInstance(): void
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
    public function testGetResult(): void
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password'],
        );

        $service = new AuthenticationService([
            'authenticators' => [
                'Authentication.Session' => [
                    'identifier' => 'Authentication.Password',
                ],
                'Authentication.Form' => [
                    'identifier' => 'Authentication.Password',
                ],
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
    public function testNoAuthenticatorsLoadedException(): void
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('No authenticators loaded. You need to load at least one authenticator.');
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password'],
        );

        $service = new AuthenticationService();

        $service->authenticate($request);
    }

    /**
     * testBuildIdentity
     *
     * @return void
     */
    public function testBuildIdentity(): void
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
    public function testBuildIdentityWithInstance(): void
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
    public function testBuildIdentityRuntimeException(): void
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
    public function testCallableIdentityProvider(): void
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password'],
        );

        $callable = function (): Identity {
            return new Identity(new ArrayObject([
                'id' => 'by-callable',
            ]));
        };

        $service = new AuthenticationService([
            'identityClass' => $callable,
            'authenticators' => [
                'Authentication.Form' => [
                    'identifier' => 'Authentication.Password',
                ],
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
    public function testGetIdentity(): void
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password'],
        );

        $service = new AuthenticationService([
            'authenticators' => [
                'Authentication.Form' => [
                    'identifier' => 'Authentication.Password',
                ],
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
    public function testGetIdentityInterface(): void
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
    public function testGetIdentityNull(): void
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

    public function testGetIdentityAttribute(): void
    {
        $service = new AuthenticationService(['identityAttribute' => 'user']);
        $this->assertSame('user', $service->getIdentityAttribute());
    }

    public function testGetUnauthenticatedRedirectUrlNoValues(): void
    {
        $service = new AuthenticationService();
        $request = new ServerRequest();

        $this->assertNull($service->getUnauthenticatedRedirectUrl($request));
    }

    public function testGetUnauthenticatedRedirectUrl(): void
    {
        $service = new AuthenticationService();
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/secrets'],
        );
        $service->setConfig('unauthenticatedRedirect', '/users/login');
        $this->assertSame('/users/login', $service->getUnauthenticatedRedirectUrl($request));

        $service->setConfig('queryParam', 'redirect');
        $this->assertSame(
            '/users/login?redirect=%2Fsecrets',
            $service->getUnauthenticatedRedirectUrl($request),
        );

        $service->setConfig('unauthenticatedRedirect', '/users/login?foo=bar');
        $this->assertSame(
            '/users/login?foo=bar&redirect=%2Fsecrets',
            $service->getUnauthenticatedRedirectUrl($request),
        );

        $service->setConfig('unauthenticatedRedirect', '/users/login?foo=bar#fragment');
        $this->assertSame(
            '/users/login?foo=bar&redirect=%2Fsecrets#fragment',
            $service->getUnauthenticatedRedirectUrl($request),
        );
    }

    public function testGetUnauthenticatedRedirectUrlForPost(): void
    {
        $service = new AuthenticationService();
        $service->setConfig('unauthenticatedRedirect', '/users/login');
        $service->setConfig('queryParam', 'redirect');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/secrets', 'REQUEST_METHOD' => 'POST'],
        );
        $this->assertSame(
            '/users/login',
            $service->getUnauthenticatedRedirectUrl($request),
            'Redirect query param should be only set for GET requests',
        );
    }

    public function testGetUnauthenticatedRedirectUrlAsArray(): void
    {
        Router::fullBaseUrl('http://localhost');

        $builder = Router::createRouteBuilder('/');
        $builder->connect(
            '/login',
            ['controller' => 'Users', 'action' => 'login'],
            ['_name' => 'login'],
        );

        $service = new AuthenticationService();
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/secrets'],
        );
        $service->setConfig('unauthenticatedRedirect', [
            'prefix' => false,
            'plugin' => false,
            'controller' => 'Users',
            'action' => 'login',
        ]);
        $this->assertSame('/login', $service->getUnauthenticatedRedirectUrl($request));
    }

    public function testGetUnauthenticatedRedirectUrlWithBasePath(): void
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/secrets'],
        );
        $request = $request->withAttribute('base', '/base');

        $service = new AuthenticationService([
            'unauthenticatedRedirect' => '/users/login',
            'queryParam' => 'redirect',
        ]);
        $this->assertSame(
            '/users/login?redirect=%2Fsecrets',
            $service->getUnauthenticatedRedirectUrl($request),
        );
    }

    public function testGetLoginRedirectInvalid(): void
    {
        $service = new AuthenticationService([
            'unauthenticatedRedirect' => '/users/login',
            'queryParam' => 'redirect',
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/secrets'],
        );
        $this->assertNull($service->getLoginRedirect($request));

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/secrets'],
            ['redirect' => ''],
        );
        $this->assertNull($service->getLoginRedirect($request));

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/secrets'],
            ['redirect' => 'http://evil.ca/evil/path'],
        );
        $this->assertNull($service->getLoginRedirect($request));

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/secrets'],
            ['redirect' => 'ok.com/path'],
        );
        $this->assertSame(
            '/ok.com/path',
            $service->getLoginRedirect($request),
        );

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/secrets'],
            ['redirect' => '/path/with?query=string'],
        );
        $this->assertSame(
            '/path/with?query=string',
            $service->getLoginRedirect($request),
        );

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/login'],
            ['redirect' => '/\\evil.com'],
        );
        $this->assertNull(
            $service->getLoginRedirect($request),
        );

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/login'],
            ['redirect' => '\\/\\evil.com'],
        );
        $this->assertNull(
            $service->getLoginRedirect($request),
        );

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/login'],
            ['redirect' => '\\evil.com/path'],
        );
        $this->assertSame(
            '/evil.com/path',
            $service->getLoginRedirect($request),
        );

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/login'],
            ['redirect' => '\\\\evil.com/path'],
        );
        $this->assertNull(
            $service->getLoginRedirect($request),
        );
    }

    /**
     * testGetLoginRedirectValidationDisabled
     *
     * @return void
     */
    public function testGetLoginRedirectValidationDisabled(): void
    {
        $service = new AuthenticationService([
            'unauthenticatedRedirect' => '/users/login',
            'queryParam' => 'redirect',
            'redirectValidation' => [
                'enabled' => false,
            ],
        ]);

        // With validation disabled, even nested redirects should pass
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/secrets'],
            ['redirect' => '/login?redirect=/secrets'],
        );
        $this->assertSame(
            '/login?redirect=/secrets',
            $service->getLoginRedirect($request),
        );
    }

    /**
     * testGetLoginRedirectValidationNestedRedirects
     *
     * @return void
     */
    public function testGetLoginRedirectValidationNestedRedirects(): void
    {
        $service = new AuthenticationService([
            'unauthenticatedRedirect' => '/users/login',
            'queryParam' => 'redirect',
            'redirectValidation' => [
                'enabled' => true,
            ],
        ]);

        // Valid single-level redirect
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/secrets'],
            ['redirect' => '/articles/view/1'],
        );
        $this->assertSame(
            '/articles/view/1',
            $service->getLoginRedirect($request),
        );

        // Nested redirect (should be blocked)
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/secrets'],
            ['redirect' => '/login?redirect=/articles/view/1'],
        );
        $this->assertNull($service->getLoginRedirect($request));

        // Deeply nested redirect (should be blocked)
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/secrets'],
            ['redirect' => '/login?redirect=%2Flogin%3Fredirect%3D%252Farticles'],
        );
        $this->assertNull($service->getLoginRedirect($request));
    }

    /**
     * testGetLoginRedirectValidationEncodingLevels
     *
     * @return void
     */
    public function testGetLoginRedirectValidationEncodingLevels(): void
    {
        $service = new AuthenticationService([
            'unauthenticatedRedirect' => '/users/login',
            'queryParam' => 'redirect',
            'redirectValidation' => [
                'enabled' => true,
            ],
        ]);

        // Normal single-encoded URL (should pass)
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/secrets'],
            ['redirect' => '/articles%2Fview%2F1'],
        );
        $this->assertSame(
            '/articles%2Fview%2F1',
            $service->getLoginRedirect($request),
        );

        // Double-encoded URL (should be blocked)
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/secrets'],
            ['redirect' => '/articles%252Fview%252F1'],
        );
        $this->assertNull($service->getLoginRedirect($request));
    }

    /**
     * testGetLoginRedirectValidationMaxLength
     *
     * @return void
     */
    public function testGetLoginRedirectValidationMaxLength(): void
    {
        $service = new AuthenticationService([
            'unauthenticatedRedirect' => '/users/login',
            'queryParam' => 'redirect',
            'redirectValidation' => [
                'enabled' => true,
                'maxLength' => 100,
            ],
        ]);

        // Short URL (should pass)
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/secrets'],
            ['redirect' => '/articles/view/1'],
        );
        $this->assertSame(
            '/articles/view/1',
            $service->getLoginRedirect($request),
        );

        // Excessively long URL (should be blocked)
        $longUrl = '/articles/' . str_repeat('a', 150);
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/secrets'],
            ['redirect' => $longUrl],
        );
        $this->assertNull($service->getLoginRedirect($request));
    }

    /**
     * testGetLoginRedirectValidationWithQueryParameters
     *
     * @return void
     */
    public function testGetLoginRedirectValidationWithQueryParameters(): void
    {
        $service = new AuthenticationService([
            'unauthenticatedRedirect' => '/users/login',
            'queryParam' => 'redirect',
            'redirectValidation' => [
                'enabled' => true,
            ],
        ]);

        // Valid redirect with query parameters
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/secrets'],
            ['redirect' => '/articles/index?sort=created&direction=desc'],
        );
        $this->assertSame(
            '/articles/index?sort=created&direction=desc',
            $service->getLoginRedirect($request),
        );
    }

    /**
     * testImpersonate
     *
     * @return void
     */
    public function testImpersonate(): void
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
            [],
        );

        $response = new Response();
        $impersonator = new ArrayObject(['username' => 'mariano']);
        $impersonated = new ArrayObject(['username' => 'larry']);
        $request->getSession()->write('Auth', $impersonator);

        $service = new AuthenticationService([
            'authenticators' => [
                'Authentication.Session' => [
                    'identifier' => 'Authentication.Password',
                ],
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
    public function testImpersonateAlreadyImpersonating(): void
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
            [],
        );

        $response = new Response();
        $impersonator = new ArrayObject(['username' => 'mariano']);
        $impersonated = new ArrayObject(['username' => 'larry']);
        $request->getSession()->write('Auth', $impersonated);
        $request->getSession()->write('AuthImpersonate', $impersonator);

        $service = new AuthenticationService([
            'authenticators' => [
                'Authentication.Session' => [
                    'identifier' => 'Authentication.Password',
                ],
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
    public function testImpersonateWrongProvider(): void
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password'],
        );
        $response = new Response();
        $impersonator = new ArrayObject(['username' => 'mariano']);
        $impersonated = new ArrayObject(['username' => 'larry']);
        $service = new AuthenticationService([
            'authenticators' => [
                'Authentication.Form' => [
                    'identifier' => 'Authentication.Password',
                ],
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
    public function testStopImpersonating(): void
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
            [],
        );

        $response = new Response();
        $impersonator = new ArrayObject(['username' => 'mariano']);
        $impersonated = new ArrayObject(['username' => 'larry']);
        $request->getSession()->write('Auth', $impersonated);
        $request->getSession()->write('AuthImpersonate', $impersonator);

        $service = new AuthenticationService([
            'authenticators' => [
                'Authentication.Session' => [
                    'identifier' => 'Authentication.Password',
                ],
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
    public function testStopImpersonatingWrongProvider(): void
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password'],
        );
        $response = new Response();

        $service = new AuthenticationService([
            'authenticators' => [
                'Authentication.Form' => [
                    'identifier' => 'Authentication.Password',
                ],
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
    public function testIsImpersonatingImpersonating(): void
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
            [],
        );

        $impersonator = new ArrayObject(['username' => 'mariano']);
        $impersonated = new ArrayObject(['username' => 'larry']);
        $request->getSession()->write('Auth', $impersonated);
        $request->getSession()->write('AuthImpersonate', $impersonator);
        $service = new AuthenticationService([
            'authenticators' => [
                'Authentication.Session' => [
                    'identifier' => 'Authentication.Password',
                ],
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
    public function testIsImpersonatingNotImpersonating(): void
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
            [],
        );

        $user = new ArrayObject(['username' => 'mariano']);
        $request->getSession()->write('Auth', $user);

        $service = new AuthenticationService([
            'authenticators' => [
                'Authentication.Session' => [
                    'identifier' => 'Authentication.Password',
                ],
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
    public function testIsImpersonatingWrongProvider(): void
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password'],
        );

        $service = new AuthenticationService([
            'authenticators' => [
                'Authentication.Form' => [
                    'identifier' => 'Authentication.Password',
                ],
            ],
        ]);

        $service->authenticate($request);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The Authentication\Authenticator\FormAuthenticator Provider must implement ImpersonationInterface in order to use impersonation.');
        $service->isImpersonating($request);
    }

    /**
     * Test that FormAuthenticator works with default Password identifier
     *
     * @return void
     */
    public function testFormAuthenticatorDefaultIdentifier(): void
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password'],
        );

        // Test loading FormAuthenticator without specifying an identifier
        $service = new AuthenticationService();
        $service->loadAuthenticator('Authentication.Form');

        $result = $service->authenticate($request);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertTrue($result->isValid());

        $authenticator = $service->getAuthenticationProvider();
        $this->assertInstanceOf(FormAuthenticator::class, $authenticator);

        // The lazily created default identifier must still be reported.
        $this->assertInstanceOf(PasswordIdentifier::class, $service->getIdentificationProvider());
    }

    /**
     * Authenticators without an identifier (e.g. the session authenticator with
     * the default `identify` => false) must report no identification provider
     * instead of throwing when getIdentificationProvider() is called.
     *
     * @return void
     */
    public function testGetIdentificationProviderWithoutIdentifier(): void
    {
        $request = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/']);
        $request->getSession()->write('Auth', ['username' => 'mariano']);

        $service = new AuthenticationService();
        $service->loadAuthenticator('Authentication.Session');

        $result = $service->authenticate($request);
        $this->assertTrue($result->isValid());

        $this->assertInstanceOf(SessionAuthenticator::class, $service->getAuthenticationProvider());
        $this->assertNull($service->getIdentificationProvider());
    }
}
