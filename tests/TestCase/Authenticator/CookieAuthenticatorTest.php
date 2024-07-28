<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link https://cakephp.org CakePHP(tm) Project
 * @since 4.0.0
 * @license https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Test\TestCase\Authenticator;

use ArrayObject;
use Authentication\Authenticator\CookieAuthenticator;
use Authentication\Authenticator\Result;
use Authentication\Identifier\IdentifierCollection;
use Cake\Core\Configure;
use Cake\Http\Cookie\Cookie;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class CookieAuthenticatorTest extends TestCase
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
     * @inheritDoc
     */
    public function setUp(): void
    {
        $this->skipIf(!class_exists(Cookie::class));

        // Note: security salt is written in tests/bootstrap.php

        parent::setUp();
    }

    /**
     * testAuthenticateInvalidTokenMissingUsername
     *
     * @return void
     */
    public function testAuthenticateInvalidTokenMissingUsername()
    {
        $identifiers = new IdentifierCollection([
            'Authentication.Password',
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'CookieAuth' => '["$2y$10$O5VgLDfIqszzr0Q47Ygkc.LkoLIwlIjc/OzoGp6yJasQlxcHU4.ES"]',
            ]
        );

        $authenticator = new CookieAuthenticator($identifiers);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_CREDENTIALS_INVALID, $result->getStatus());
    }

    /**
     * testAuthenticateSuccess
     *
     * @return void
     */
    public function testAuthenticateSuccess()
    {
        $identifiers = new IdentifierCollection([
            'Authentication.Password',
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                // hash(username . password . hmac(username . password, salt))
                'CookieAuth' => '["mariano","$2y$10$RlCAFt3e/9l42f8SIaIbqejOg9/b/HklPo.fjXY.tFGuluafugssa"]',
            ]
        );

        $authenticator = new CookieAuthenticator($identifiers);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());
    }

    /**
     * testAuthenticateSuccess
     *
     * @return void
     */
    public function testAuthenticateExpandedCookie()
    {
        $identifiers = new IdentifierCollection([
            'Authentication.Password',
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'CookieAuth' => ['mariano', '$2y$10$RlCAFt3e/9l42f8SIaIbqejOg9/b/HklPo.fjXY.tFGuluafugssa'],
            ]
        );

        $authenticator = new CookieAuthenticator($identifiers);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());
    }

    /**
     * testAuthenticateSuccessNoSalt
     *
     * @return void
     */
    public function testAuthenticateNoSalt()
    {
        Configure::delete('Security.salt');

        $identifiers = new IdentifierCollection([
            'Authentication.Password',
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                // hash(username . password)
                'CookieAuth' => '["mariano","$2y$10$yq91zLgrlF0TUzPjFj49DOL44svGrOYxaBfB6QYWEvxVKzNkvcVom"]',
            ]
        );

        $authenticator = new CookieAuthenticator($identifiers, ['salt' => false]);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());
    }

    /**
     * testAuthenticateSuccessNoSalt
     *
     * @return void
     */
    public function testAuthenticateInvalidSalt()
    {
        $identifiers = new IdentifierCollection([
            'Authentication.Password',
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'CookieAuth' => '["mariano","some_hash"]',
            ]
        );

        $authenticator = new CookieAuthenticator($identifiers, ['salt' => '']);

        $this->expectException(InvalidArgumentException::class);
        $authenticator->authenticate($request);
    }

    /**
     * testAuthenticateUnknownUser
     *
     * @return void
     */
    public function testAuthenticateUnknownUser()
    {
        $identifiers = new IdentifierCollection([
            'Authentication.Password',
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'CookieAuth' => '["robert","$2y$10$1bE1SgasKoz9WmEvUfuZLeYa6pQgxUIJ5LAoS/KGmC1hNuWkUG7ES"]',
            ]
        );

        $authenticator = new CookieAuthenticator($identifiers);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_IDENTITY_NOT_FOUND, $result->getStatus());
    }

    /**
     * testCredentialsNotPresent
     *
     * @return void
     */
    public function testCredentialsNotPresent()
    {
        $identifiers = new IdentifierCollection([
            'Authentication.Password',
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath']
        );

        $authenticator = new CookieAuthenticator($identifiers);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_CREDENTIALS_MISSING, $result->getStatus());
    }

    /**
     * testAuthenticateInvalidToken
     *
     * @return void
     */
    public function testAuthenticateInvalidToken()
    {
        $identifiers = new IdentifierCollection([
            'Authentication.Password',
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'CookieAuth' => '["mariano","$2y$10$1bE1SgasKoz9WmEvUfuZLeYa6pQgxUIJ5LAoS/asdasdsadasd"]',
            ]
        );

        $authenticator = new CookieAuthenticator($identifiers);
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
        $identifiers = new IdentifierCollection([
            'Authentication.Password',
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath']
        );
        $request = $request->withParsedBody([
            'remember_me' => 1,
        ]);
        $response = new Response();

        Cookie::setDefaults(['samesite' => 'None']);
        $authenticator = new CookieAuthenticator($identifiers, [
            'cookie' => ['expires' => '2030-01-01 00:00:00'],
        ]);

        $identity = new ArrayObject([
            'username' => 'mariano',
            'password' => '$2a$10$u05j8FjsvLBNdfhBhc21LOuVMpzpabVXQ9OpC2wO3pSO0q6t7HHMO',
        ]);
        $result = $authenticator->persistIdentity($request, $response, $identity);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('request', $result);
        $this->assertArrayHasKey('response', $result);
        $this->assertInstanceOf(RequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);
        $hashCost = '10';
        if (PHP_VERSION_ID >= 80400) {
            $hashCost = '12';
        }
        $this->assertStringContainsString(
            'CookieAuth=%5B%22mariano%22%2C%22%242y%24' . $hashCost . '%24', // `CookieAuth=["mariano","$2y$10$`
            $result['response']->getHeaderLine('Set-Cookie')
        );
        $this->assertStringContainsString(
            'expires=Tue, 01-Jan-2030 00:00:00 GMT;',
            $result['response']->getHeaderLine('Set-Cookie')
        );
        $this->assertStringContainsString(
            'samesite=None',
            $result['response']->getHeaderLine('Set-Cookie')
        );

        Cookie::setDefaults(['samesite' => null]);

        // Testing that the field is not present
        $request = $request->withParsedBody([]);
        $result = $authenticator->persistIdentity($request, $response, $identity);
        $this->assertStringNotContainsString(
            'CookieAuth',
            $result['response']->getHeaderLine('Set-Cookie')
        );

        // Testing a different field name
        $request = $request->withParsedBody([
            'other_field' => 1,
        ]);
        $authenticator = new CookieAuthenticator($identifiers, [
            'rememberMeField' => 'other_field',
        ]);
        $result = $authenticator->persistIdentity($request, $response, $identity);
        $this->assertStringContainsString(
            'CookieAuth=%5B%22mariano%22%2C%22%242y%24' . $hashCost . '%24',
            $result['response']->getHeaderLine('Set-Cookie')
        );
    }

    /**
     * testPersistIdentityLoginUrlMismatch
     *
     * @return void
     */
    public function testPersistIdentityLoginUrlMismatch()
    {
        $identifiers = new IdentifierCollection([
            'Authentication.Password',
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath']
        );
        $request = $request->withParsedBody([
            'remember_me' => 1,
        ]);
        $response = new Response();

        $authenticator = new CookieAuthenticator($identifiers, [
            'loginUrl' => '/users/login',
        ]);

        $identity = new ArrayObject([
            'username' => 'mariano',
            'password' => '$2a$10$u05j8FjsvLBNdfhBhc21LOuVMpzpabVXQ9OpC2wO3pSO0q6t7HHMO',
        ]);
        $result = $authenticator->persistIdentity($request, $response, $identity);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('request', $result);
        $this->assertArrayHasKey('response', $result);
        $this->assertInstanceOf(RequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);
        $this->assertStringNotContainsString(
            'CookieAuth=%5B%22mariano%22%2C%22%242y%2410%24',
            $result['response']->getHeaderLine('Set-Cookie')
        );
    }

    /**
     * testClearIdentity
     *
     * @return void
     */
    public function testClearIdentity()
    {
        $identifiers = new IdentifierCollection([
            'Authentication.Password',
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath']
        );
        $response = new Response();

        $authenticator = new CookieAuthenticator($identifiers);

        $result = $authenticator->clearIdentity($request, $response);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('request', $result);
        $this->assertArrayHasKey('response', $result);
        $this->assertInstanceOf(RequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);

        $cookieHeader = $result['response']->getHeaderLine('Set-Cookie');
        $this->assertStringContainsString('CookieAuth=; expires=Thu, 01-Jan-1970 00:00:01', $cookieHeader);
        $this->assertStringContainsString('; path=/', $cookieHeader);
    }
}
