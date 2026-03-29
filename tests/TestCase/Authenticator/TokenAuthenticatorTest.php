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

use Authentication\Authenticator\Result;
use Authentication\Authenticator\TokenAuthenticator;
use Authentication\Identifier\IdentifierFactory;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Http\ServerRequestFactory;

class TokenAuthenticatorTest extends TestCase
{
    /**
     * Fixtures
     */
    protected array $fixtures = [
        'core.AuthUsers',
        'core.Users',
    ];

    /**
     * @var \Authentication\Identifier\TokenIdentifier
     */
    protected $identifier;

    /**
     * @var \Cake\Http\ServerRequest
     */
    protected $request;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->identifier = IdentifierFactory::create('Authentication.Token', [
            'tokenField' => 'username',
        ]);

        $this->request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password'],
        );
    }

    /**
     * testAuthenticate
     *
     * @return void
     */
    public function testAuthenticateViaHeaderToken(): void
    {
        // Test without token
        $tokenAuth = new TokenAuthenticator($this->identifier, [
            'queryParam' => 'token',
        ]);
        $result = $tokenAuth->authenticate($this->request);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_CREDENTIALS_MISSING, $result->getStatus());

        // Test header token
        $requestWithHeaders = $this->request->withAddedHeader('Token', 'mariano');
        $tokenAuth = new TokenAuthenticator($this->identifier, [
            'header' => 'Token',
        ]);
        $result = $tokenAuth->authenticate($requestWithHeaders);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());
    }

    /**
     * testViaQueryParamToken
     *
     * @return void
     */
    public function testViaQueryParamToken(): void
    {
        // Test with query param token
        $requestWithParams = $this->request->withQueryParams(['token' => 'mariano']);
        $tokenAuth = new TokenAuthenticator($this->identifier, [
            'queryParam' => 'token',
        ]);
        $result = $tokenAuth->authenticate($requestWithParams);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());

        // Test with valid query param but invalid token
        $requestWithParams = $this->request->withQueryParams(['token' => 'does-not-exist']);
        $tokenAuth = new TokenAuthenticator($this->identifier, [
            'queryParam' => 'token',
        ]);
        $result = $tokenAuth->authenticate($requestWithParams);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_IDENTITY_NOT_FOUND, $result->getStatus());
    }

    /**
     * testTokenPrefix
     *
     * @return void
     */
    public function testTokenPrefix(): void
    {
        //valid prefix
        $requestWithHeaders = $this->request->withAddedHeader('Token', 'identity mariano');
        $tokenAuth = new TokenAuthenticator($this->identifier, [
            'header' => 'Token',
            'tokenPrefix' => 'identity',
        ]);
        $result = $tokenAuth->authenticate($requestWithHeaders);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());

        $requestWithHeaders = $this->request->withAddedHeader('X-Dipper-Auth', 'dipper_mariano');
        $tokenAuth = new TokenAuthenticator($this->identifier, [
            'header' => 'X-Dipper-Auth',
            'tokenPrefix' => 'dipper_',
        ]);
        $result = $tokenAuth->authenticate($requestWithHeaders);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());

        //invalid prefix
        $requestWithHeaders = $this->request->withAddedHeader('Token', 'bearer mariano');
        $tokenAuth = new TokenAuthenticator($this->identifier, [
            'header' => 'Token',
            'tokenPrefix' => 'identity',
        ]);
        $result = $tokenAuth->authenticate($requestWithHeaders);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_IDENTITY_NOT_FOUND, $result->getStatus());

        // should not remove prefix from token
        $requestWithHeaders = $this->request->withAddedHeader('X-Dipper-Auth', 'mari mariano');
        $tokenAuth = new TokenAuthenticator($this->identifier, [
            'header' => 'X-Dipper-Auth',
            'tokenPrefix' => 'mari',
        ]);
        $result = $tokenAuth->authenticate($requestWithHeaders);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());
    }

    /**
     * testWithoutQueryParamConfig
     *
     * @return void
     */
    public function testWithoutQueryParamConfig(): void
    {
        $tokenAuth = new TokenAuthenticator($this->identifier, [
            'header' => 'Token',
        ]);

        $result = $tokenAuth->authenticate(ServerRequestFactory::fromGlobals());
        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_CREDENTIALS_MISSING, $result->getStatus());
    }

    /**
     * testWithoutHeaderConfig
     *
     * @return void
     */
    public function testWithoutHeaderConfig(): void
    {
        $tokenAuth = new TokenAuthenticator($this->identifier, [
            'queryParam' => 'token',
        ]);

        $result = $tokenAuth->authenticate(ServerRequestFactory::fromGlobals());
        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_CREDENTIALS_MISSING, $result->getStatus());
    }

    /**
     * testWithoutAnyConfig
     *
     * @return void
     */
    public function testWithoutAnyConfig(): void
    {
        $tokenAuth = new TokenAuthenticator($this->identifier);

        $result = $tokenAuth->authenticate(ServerRequestFactory::fromGlobals());
        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_CREDENTIALS_MISSING, $result->getStatus());
    }
}
