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

use ArrayAccess;
use ArrayObject;
use Authentication\Authenticator\JwtAuthenticator;
use Authentication\Authenticator\Result;
use Authentication\Identifier\IdentifierCollection;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Http\ServerRequestFactory;
use Exception;
use Firebase\JWT\JWT;
use stdClass;

class JwtAuthenticatorTest extends TestCase
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
     * Test token encoded via HS256
     *
     * @var string
     */
    protected $tokenHS256;

    /**
     * Test token encoded via RS256
     *
     * @var string
     */
    protected $tokenRS256;

    /**
     * Identifier Collection
     *
     * @var \Authentication\Identifier\IdentifierCollection;
     */
    public $identifiers;

    /**
     * @var \Cake\Http\ServerRequest
     */
    protected $request;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        $data = [
            'subjectId' => 3,
            'id' => 3,
            'username' => 'larry',
            'firstname' => 'larry',
        ];

        $this->tokenHS256 = JWT::encode($data, 'secretKey', 'HS256');

        $privKey1 = file_get_contents(__DIR__ . '/../../data/rsa1-private.pem');
        $this->tokenRS256 = JWT::encode($data, $privKey1, 'RS256', 'jwk1');

        $this->identifiers = new IdentifierCollection([]);
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
        $this->request = $this->request->withAddedHeader('Authorization', 'Bearer ' . $this->tokenHS256);

        $authenticator = new JwtAuthenticator($this->identifiers, [
            'secretKey' => 'secretKey',
            'subjectKey' => 'subjectId',
        ]);

        $result = $authenticator->authenticate($this->request);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());
        $this->assertInstanceOf(ArrayAccess::class, $result->getData());
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
            ['token' => $this->tokenHS256]
        );

        $authenticator = new JwtAuthenticator($this->identifiers, [
            'secretKey' => 'secretKey',
            'subjectKey' => 'subjectId',
        ]);

        $result = $authenticator->authenticate($this->request);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());
        $this->assertInstanceOf(ArrayAccess::class, $result->getData());
    }

    /**
     * testAuthenticationViaIdentifierAndSubject
     *
     * @return void
     */
    public function testAuthenticationViaIdentifierAndSubject()
    {
        $this->request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
            ['token' => $this->tokenHS256]
        );

        $this->identifiers = $this->createMock(IdentifierCollection::class);
        $this->identifiers->expects($this->once())
            ->method('identify')
            ->with([
                'subjectId' => 3,
            ])
            ->willReturn(new ArrayObject([
                'subjectId' => 3,
                'id' => 3,
                'username' => 'larry',
                'firstname' => 'larry',
            ]));

        $authenticator = new JwtAuthenticator($this->identifiers, [
            'secretKey' => 'secretKey',
            'returnPayload' => false,
            'subjectKey' => 'subjectId',
        ]);

        $result = $authenticator->authenticate($this->request);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());
        $this->assertInstanceOf(ArrayAccess::class, $result->getData());
    }

    /**
     * Testing an invalid token
     *
     * The authenticator will turn the JWT libs exceptions into an error result.
     *
     * @return void
     */
    public function testAuthenticateInvalidPayloadNotAnObject()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
            ['token' => $this->tokenHS256]
        );

        $authenticator = $this->getMockBuilder(JwtAuthenticator::class)
            ->setConstructorArgs([
                $this->identifiers,
            ])
            ->onlyMethods([
                'getPayLoad',
            ])
            ->getMock();

        $authenticator->expects($this->once())
            ->method('getPayLoad')
            ->willThrowException(new Exception());

        $result = $authenticator->authenticate($request);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_CREDENTIALS_INVALID, $result->getStatus());
        $this->assertNull($result->getData());
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
            ['token' => $this->tokenHS256]
        );

        $authenticator = $this->getMockBuilder(JwtAuthenticator::class)
            ->setConstructorArgs([
                $this->identifiers,
            ])
            ->onlyMethods([
                'getPayLoad',
            ])
            ->getMock();

        $authenticator->expects($this->once())
            ->method('getPayLoad')
            ->willReturn(new stdClass());

        $result = $authenticator->authenticate($request);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_CREDENTIALS_MISSING, $result->getStatus());
        $this->assertNUll($result->getData());
    }

    public function testInvalidToken()
    {
        $this->request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
            ['token' => 'should cause an exception']
        );

        $authenticator = new JwtAuthenticator($this->identifiers, [
            'secretKey' => 'secretKey',
        ]);

        $result = $authenticator->authenticate($this->request);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_CREDENTIALS_INVALID, $result->getStatus());
        $this->assertNUll($result->getData());
        $errors = $result->getErrors();
        $this->assertArrayHasKey('message', $errors);
        $this->assertArrayHasKey('exception', $errors);
        $this->assertInstanceOf(Exception::class, $errors['exception']);
    }

    /**
     * testGetPayload with HS256 token
     *
     * @return void
     */
    public function testGetPayloadHS256()
    {
        $this->request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
            ['token' => $this->tokenHS256]
        );

        $authenticator = new JwtAuthenticator($this->identifiers, [
            'secretKey' => 'secretKey',
        ]);

        $result = $authenticator->getPayload();
        $this->assertNull($result);

        $authenticator->authenticate($this->request);

        $expected = [
            'subjectId' => 3,
            'id' => 3,
            'username' => 'larry',
            'firstname' => 'larry',
        ];

        $result = $authenticator->getPayload();
        $this->assertEquals($expected, (array)$result);
    }

    /**
     * testGetPayload with RS256 token
     *
     * @return void
     */
    public function testGetPayloadRS256()
    {
        $this->request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
            ['token' => $this->tokenRS256]
        );

        $authenticator = new JwtAuthenticator($this->identifiers, [
            'jwks' => json_decode(file_get_contents(__DIR__ . '/../../data/rsa-jwkset.json'), true),
        ]);

        $result = $authenticator->getPayload();
        $this->assertNull($result);

        $authenticator->authenticate($this->request);

        $expected = [
            'subjectId' => 3,
            'id' => 3,
            'username' => 'larry',
            'firstname' => 'larry',
        ];

        $result = $authenticator->getPayload();
        $this->assertEquals($expected, (array)$result);
    }
}
