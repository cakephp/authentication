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

use ArrayObject;
use Authentication\Authenticator\CookieAuthenticator;
use Authentication\Authenticator\Result;
use Authentication\Identifier\IdentifierCollection;
use Cake\Http\Cookie\Cookie;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;
use Cake\TestSuite\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class CookieAuthenticatorTest extends TestCase
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
     * {@inheritDoc}
     */
    public function setUp()
    {
        $this->skipIf(!class_exists(Cookie::class));

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
                'CookieAuth' => '["$2y$10$1bE1SgasKoz9WmEvUfuZLeYa6pQgxUIJ5LAoS/KGmC1hNuWkUG7ES"]',
            ]
        );
        $response = new Response();

        $authenticator = new CookieAuthenticator($identifiers);
        $result = $authenticator->authenticate($request, $response);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::FAILURE_CREDENTIALS_INVALID, $result->getStatus());
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
                'CookieAuth' => '["mariano","$2y$10$1bE1SgasKoz9WmEvUfuZLeYa6pQgxUIJ5LAoS/KGmC1hNuWkUG7ES"]',
            ]
        );
        $response = new Response();

        $authenticator = new CookieAuthenticator($identifiers);
        $result = $authenticator->authenticate($request, $response);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::SUCCESS, $result->getStatus());
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
                'CookieAuth' => ["mariano", "$2y$10$1bE1SgasKoz9WmEvUfuZLeYa6pQgxUIJ5LAoS/KGmC1hNuWkUG7ES"],
            ]
        );
        $response = new Response();

        $authenticator = new CookieAuthenticator($identifiers);
        $result = $authenticator->authenticate($request, $response);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::SUCCESS, $result->getStatus());
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
        $response = new Response();

        $authenticator = new CookieAuthenticator($identifiers);
        $result = $authenticator->authenticate($request, $response);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::FAILURE_IDENTITY_NOT_FOUND, $result->getStatus());
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
        $response = new Response();

        $authenticator = new CookieAuthenticator($identifiers);
        $result = $authenticator->authenticate($request, $response);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::FAILURE_CREDENTIALS_MISSING, $result->getStatus());
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
        $response = new Response();

        $authenticator = new CookieAuthenticator($identifiers);
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

        $authenticator = new CookieAuthenticator($identifiers);

        $identity = new ArrayObject([
            'username' => 'mariano',
            'password' => '$2a$10$u05j8FjsvLBNdfhBhc21LOuVMpzpabVXQ9OpC2wO3pSO0q6t7HHMO',
        ]);
        $result = $authenticator->persistIdentity($request, $response, $identity);

        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('request', $result);
        $this->assertArrayHasKey('response', $result);
        $this->assertInstanceOf(RequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);
        $this->assertContains('CookieAuth=%5B%22mariano%22%2C%22%242y%2410%24', $result['response']->getHeaderLine('Set-Cookie'));

        // Testing that the field is not present
        $request = $request->withParsedBody([]);
        $result = $authenticator->persistIdentity($request, $response, $identity);
        $this->assertNotContains('CookieAuth', $result['response']->getHeaderLine('Set-Cookie'));

        // Testing a different field name
        $request = $request->withParsedBody([
            'other_field' => 1,
        ]);
        $authenticator = new CookieAuthenticator($identifiers, [
            'rememberMeField' => 'other_field',
        ]);
        $result = $authenticator->persistIdentity($request, $response, $identity);
        $this->assertContains('CookieAuth=%5B%22mariano%22%2C%22%242y%2410%24', $result['response']->getHeaderLine('Set-Cookie'));
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

        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('request', $result);
        $this->assertArrayHasKey('response', $result);
        $this->assertInstanceOf(RequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);
        $this->assertNotContains('CookieAuth=%5B%22mariano%22%2C%22%242y%2410%24', $result['response']->getHeaderLine('Set-Cookie'));
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
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('request', $result);
        $this->assertArrayHasKey('response', $result);
        $this->assertInstanceOf(RequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);

        $this->assertEquals('CookieAuth=; expires=Thu, 01-Jan-1970 00:00:01 UTC; path=/', $result['response']->getHeaderLine('Set-Cookie'));
    }
}
