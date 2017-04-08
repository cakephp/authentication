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
use Authentication\Authenticator\JwtAuthenticator;
use Authentication\Identifier\IdentifierCollection;
use Authentication\Result;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;
use Firebase\JWT\JWT;

class JwtAuthenticatorTest extends TestCase
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
     * Test token
     *
     * @var string
     */
    public $token;

    /**
     * Identifier Collection
     *
     * @var \Authentication\Identifier\IdentifierCollection;
     */
    public $identifiers;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        parent::setUp();

        $data = [
            'sub' => 3,
            'id' => 3,
            'username' => 'larry',
            'firstname' => 'larry'
        ];

        $this->token = JWT::encode($data, 'secretKey');
        $this->identifiers = new IdentifierCollection([]);
        $this->response = new Response();
    }

    /**
     * testAuthenticateViaHeaderToken
     *
     * @return void
     */
    public function testAuthenticateViaHeaderToken()
    {
        $this->request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/']
        );
        $this->request = $this->request->withAddedHeader('Authorization', 'Bearer ' . $this->token);

        $authenticator = new JwtAuthenticator($this->identifiers, [
            'secretKey' => 'secretKey'
        ]);

        $result = $authenticator->authenticate($this->request, $this->response);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::SUCCESS, $result->getCode());
        $this->assertInstanceOf(ArrayAccess::class, $result->getIdentity());
    }

    /**
     * testAuthenticateViaQueryParamToken
     *
     * @return void
     */
    public function testAuthenticateViaQueryParamToken()
    {
        $this->request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
            ['token' => $this->token]
        );

        $authenticator = new JwtAuthenticator($this->identifiers, [
            'secretKey' => 'secretKey'
        ]);

        $result = $authenticator->authenticate($this->request, $this->response);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::SUCCESS, $result->getCode());
        $this->assertInstanceOf(ArrayAccess::class, $result->getIdentity());
    }

    /**
     * testAuthenticationViaORMIdentifierAndSubject
     *
     * @return void
     */
    public function testAuthenticationViaORMIdentifierAndSubject()
    {
        $this->request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
            ['token' => $this->token]
        );

        $this->identifiers->load('Authentication.JwtSubject');

        $authenticator = new JwtAuthenticator($this->identifiers, [
            'secretKey' => 'secretKey'
        ]);

        $result = $authenticator->authenticate($this->request, $this->response);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::SUCCESS, $result->getCode());
        $this->assertInstanceOf(ArrayAccess::class, $result->getIdentity());
    }

    public function testAuthenticateInvalidPayloadNotAnObject()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
            ['token' => $this->token]
        );

        $response = new Response();

        $this->identifiers->load('Authentication.JwtSubject');

        $authenticator = $this->getMockBuilder(JwtAuthenticator::class)
            ->setConstructorArgs([
                $this->identifiers
            ])
            ->setMethods([
                'getPayLoad'
            ])
            ->getMock();

        $authenticator->expects($this->at(0))
            ->method('getPayLoad')
            ->will($this->returnValue('no an object'));

        $result = $authenticator->authenticate($request, $response);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::FAILURE_CREDENTIAL_INVALID, $result->getCode());
        $this->assertNUll($result->getIdentity());
    }

    /**
     * testAuthenticateInvalidPayloadEmpty
     *
     * @return void
     */
    public function testAuthenticateInvalidPayloadEmpty()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
            ['token' => $this->token]
        );

        $response = new Response();

        $this->identifiers->load('Authentication.JwtSubject');

        $authenticator = $this->getMockBuilder(JwtAuthenticator::class)
            ->setConstructorArgs([
                $this->identifiers
            ])
            ->setMethods([
                'getPayLoad'
            ])
            ->getMock();

        $authenticator->expects($this->at(0))
            ->method('getPayLoad')
            ->will($this->returnValue(new \stdClass()));

        $result = $authenticator->authenticate($request, $response);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::FAILURE_CREDENTIALS_NOT_FOUND, $result->getCode());
        $this->assertNUll($result->getIdentity());
    }

    /**
     * testGetPayload
     *
     * @return void
     */
    public function testGetPayload()
    {
        $this->request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
            ['token' => $this->token]
        );

        $this->identifiers->load('Authentication.JwtSubject');

        $authenticator = new JwtAuthenticator($this->identifiers, [
            'secretKey' => 'secretKey'
        ]);

        $result = $authenticator->getPayload();
        $this->assertNull($result);

        $authenticator->authenticate($this->request, $this->response);

        $expected = [
            'sub' => 3,
            'id' => 3,
            'username' => 'larry',
            'firstname' => 'larry'
        ];

        $result = $authenticator->getPayload();
        $this->assertEquals($expected, (array)$result);
    }
}
