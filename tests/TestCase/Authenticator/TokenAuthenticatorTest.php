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

use Authentication\Authenticator\Result;
use Authentication\Authenticator\TokenAuthenticator;
use Authentication\Identifier\IdentifierCollection;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;

class TokenAuthenticatorTest extends TestCase
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
     * {@inheritdoc}
     */
    public function setUp()
    {
        parent::setUp();

        $this->identifiers = new IdentifierCollection([
           'Authentication.Token' => [
               'tokenField' => 'username'
           ]
        ]);

        $this->request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );

        $this->response = new Response();
    }

    /**
     * testAuthenticate
     *
     * @return void
     */
    public function testAuthenticateViaHeaderToken()
    {
        // Test without token
        $tokenAuth = new TokenAuthenticator($this->identifiers, [
            'queryParam' => 'token'
        ]);
        $result = $tokenAuth->authenticate($this->request, $this->response);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::FAILURE_OTHER, $result->getCode());

        // Test header token
        $requestWithHeaders = $this->request->withAddedHeader('Token', 'mariano');
        $tokenAuth = new TokenAuthenticator($this->identifiers, [
            'header' => 'Token'
        ]);
        $result = $tokenAuth->authenticate($requestWithHeaders, $this->response);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::SUCCESS, $result->getCode());
    }

    /**
     * testViaQueryParamToken
     *
     * @return void
     */
    public function testViaQueryParamToken()
    {
        // Test with query param token
        $requestWithParams = $this->request->withQueryParams(['token' => 'mariano']);
        $tokenAuth = new TokenAuthenticator($this->identifiers, [
            'queryParam' => 'token'
        ]);
        $result = $tokenAuth->authenticate($requestWithParams, $this->response);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::SUCCESS, $result->getCode());

        // Test with valid query param but invalid token
        $requestWithParams = $this->request->withQueryParams(['token' => 'does-not-exist']);
        $tokenAuth = new TokenAuthenticator($this->identifiers, [
            'queryParam' => 'token'
        ]);
        $result = $tokenAuth->authenticate($requestWithParams, $this->response);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::FAILURE_IDENTITY_NOT_FOUND, $result->getCode());
    }
}
