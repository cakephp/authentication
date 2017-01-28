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

use Authentication\Authenticator\JwtAuthenticator;
use Authentication\Identifier\IdentifierCollection;
use Authentication\Result;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Datasource\EntityInterface;
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
     * {@inheritdoc}
     */
    public function setUp()
    {
        parent::setUp();

        $data = [
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
        $this->assertInstanceOf(EntityInterface::class, $result->getIdentity());
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
        $this->assertInstanceOf(EntityInterface::class, $result->getIdentity());
    }
}
