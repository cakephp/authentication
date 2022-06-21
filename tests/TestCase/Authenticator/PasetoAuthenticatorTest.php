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
 * @since 3.0.0
 * @license https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Test\TestCase\Authenticator;

use ArrayAccess;
use ArrayObject;
use Authentication\Authenticator\PasetoAuthenticator;
use Authentication\Authenticator\Result;
use Authentication\Identifier\IdentifierCollection;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Http\ServerRequestFactory;
use DateInterval;
use DateTime;
use Exception;
use ParagonIE\Paseto\Builder;
use ParagonIE\Paseto\Keys\AsymmetricSecretKey;
use ParagonIE\Paseto\Keys\SymmetricKey;
use ParagonIE\Paseto\Protocol\Version3;
use ParagonIE\Paseto\Protocol\Version4;
use ParagonIE\Paseto\ProtocolInterface;
use ParagonIE\Paseto\Purpose;

class PasetoAuthenticatorTest extends TestCase
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
     * @var string Symmetric Key for local purpose
     */
    private const LOCAL_SECRET_KEY = 'IXj4GlRtOOTg/7baipL+M2zfsW5PvzsA';

    /**
     * Identifier Collection
     *
     * @var \Authentication\Identifier\IdentifierCollection;
     */
    public $identifiers;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->identifiers = new IdentifierCollection([]);
    }

    /**
     * Test authentication with local purpose via header token.
     *
     * @dataProvider dataProviderForVersions
     * @param string $version The PASETO version
     * @param ProtocolInterface $protocol Instance of Version
     * @return void
     * @throws \ParagonIE\Paseto\Exception\InvalidKeyException
     * @throws \ParagonIE\Paseto\Exception\InvalidPurposeException
     * @throws \ParagonIE\Paseto\Exception\PasetoException
     */
    public function testLocalAuthenticationViaHeaderToken(string $version, ProtocolInterface $protocol): void
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/']
        );
        $token = $this->buildLocalToken($protocol);
        $request = $request->withAddedHeader('Authorization', 'Bearer ' . $token->toString());

        $authenticator = new PasetoAuthenticator($this->identifiers, [
            'secretKey' => self::LOCAL_SECRET_KEY,
            'purpose' => PasetoAuthenticator::LOCAL,
            'version' => $version,
        ]);

        $result = $authenticator->authenticate($request);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());
        $this->assertInstanceOf(ArrayAccess::class, $result->getData());
        $this->assertIsArray($result->getData()['footer']);
        $this->assertEquals('larry', $result->getData()['username']);
    }

    /**
     * Test authentication with local purpose via header token.
     *
     * @dataProvider dataProviderForVersions
     * @param string $version The PASETO version
     * @param ProtocolInterface $protocol Instance of Version
     * @return void
     * @throws \ParagonIE\Paseto\Exception\InvalidKeyException
     * @throws \ParagonIE\Paseto\Exception\InvalidPurposeException
     * @throws \ParagonIE\Paseto\Exception\PasetoException
     */
    public function testPublicAuthenticationViaHeaderToken(string $version, ProtocolInterface $protocol): void
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/']
        );
        /** @var Builder $token */
        /** @var AsymmetricSecretKey $privateKey */
        $publicData = $this->buildPublicToken($protocol);
        $token = $publicData['token'];
        $privateKey = $publicData['privateKey'];

        $request = $request->withAddedHeader('Authorization', 'Bearer ' . $token->toString());

        $authenticator = new PasetoAuthenticator($this->identifiers, [
            'secretKey' => $privateKey->encode(),
            'purpose' => PasetoAuthenticator::PUBLIC,
            'version' => $version,
        ]);

        $result = $authenticator->authenticate($request);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());
        $this->assertInstanceOf(ArrayAccess::class, $result->getData());
        $this->assertIsArray($result->getData()['footer']);
        $this->assertEquals('larry', $result->getData()['username']);
    }

    /**
     * Test authentication via query parameter.
     *
     * @dataProvider dataProviderForPurpose
     * @param string $purpose Either local or public
     * @return void
     * @throws \ParagonIE\Paseto\Exception\InvalidKeyException
     * @throws \ParagonIE\Paseto\Exception\InvalidPurposeException
     * @throws \ParagonIE\Paseto\Exception\PasetoException
     */
    public function testAuthenticationViaQueryParamToken(string $purpose): void
    {
        switch ($purpose) {
            case PasetoAuthenticator::LOCAL:
                $token = $this->buildLocalToken(new Version4());
                break;
            case PasetoAuthenticator::PUBLIC:
                /** @var Builder $token */
                /** @var AsymmetricSecretKey $privateKey */
                $publicData = $this->buildPublicToken(new Version4());
                $token = $publicData['token'];
                $privateKey = $publicData['privateKey'];
                $secretKey = $privateKey->encode();
                break;
            default:
                $token = null;
                $this->markAsRisky();
        }

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
            ['token' => $token->toString()]
        );

        $authenticator = new PasetoAuthenticator($this->identifiers, [
            'secretKey' => $secretKey ?? self::LOCAL_SECRET_KEY,
            'purpose' => $purpose,
            'version' => 'v4',
        ]);

        $result = $authenticator->authenticate($request);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());
        $this->assertInstanceOf(ArrayAccess::class, $result->getData());
    }

    /**
     * Test Authentication when `returnPayload` is false.
     *
     * @return void
     * @throws \ParagonIE\Paseto\Exception\InvalidKeyException
     * @throws \ParagonIE\Paseto\Exception\InvalidPurposeException
     * @throws \ParagonIE\Paseto\Exception\PasetoException
     */
    public function testAuthenticationViaIdentifierAndSub(): void
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/']
        );
        $token = $this->buildLocalToken(new Version4());
        $request = $request->withAddedHeader('Authorization', 'Bearer ' . $token->toString());

        $this->identifiers = $this->createMock(IdentifierCollection::class);
        $this->identifiers->expects($this->once())
            ->method('identify')
            ->with([
                'sub' => 3,
            ])
            ->willReturn(new ArrayObject([
                'username' => 'larry',
                'firstname' => 'larry',
            ]));

        $authenticator = new PasetoAuthenticator($this->identifiers, [
            'secretKey' => self::LOCAL_SECRET_KEY,
            'purpose' => PasetoAuthenticator::LOCAL,
            'version' => 'v4',
            'returnPayload' => false,
        ]);

        $result = $authenticator->authenticate($request);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());
        $this->assertInstanceOf(ArrayAccess::class, $result->getData());
    }

    /**
     * Test Authentication fails when token is invalid.
     *
     * @return void
     * @throws \ParagonIE\Paseto\Exception\InvalidKeyException
     * @throws \ParagonIE\Paseto\Exception\InvalidPurposeException
     * @throws \ParagonIE\Paseto\Exception\PasetoException
     */
    public function testAuthenticationFailsWithInvalidToken(): void
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/']
        );
        $token = $this->buildLocalToken(new Version4(), 'a-very-invalid-key');
        $request = $request->withAddedHeader('Authorization', 'Bearer ' . $token->toString());

        $authenticator = new PasetoAuthenticator($this->identifiers, [
            'secretKey' => self::LOCAL_SECRET_KEY,
            'purpose' => PasetoAuthenticator::LOCAL,
            'version' => 'v4',
            'returnPayload' => false,
        ]);

        $result = $authenticator->authenticate($request);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_CREDENTIALS_INVALID, $result->getStatus());
        $this->assertNUll($result->getData());
        $errors = $result->getErrors();
        $this->assertArrayHasKey('message', $errors);
        $this->assertArrayHasKey('exception', $errors);
        $this->assertInstanceOf(Exception::class, $errors['exception']);
    }

    /**
     * Test getPayLoad throws an Exception
     *
     * @return void
     * @throws \ParagonIE\Paseto\Exception\InvalidKeyException
     * @throws \ParagonIE\Paseto\Exception\InvalidPurposeException
     * @throws \ParagonIE\Paseto\Exception\PasetoException
     */
    public function testAuthenticationInvalidPayloadNotAnObject(): void
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/']
        );
        $request = $request->withAddedHeader('Authorization', 'Bearer 123');
        $authenticator = new PasetoAuthenticator($this->identifiers, [
            'secretKey' => self::LOCAL_SECRET_KEY,
            'purpose' => PasetoAuthenticator::LOCAL,
            'version' => 'v4',
        ]);

        $result = $authenticator->authenticate($request);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_CREDENTIALS_INVALID, $result->getStatus());
        $this->assertNull($result->getData());
    }

    /**
     * Test getPayLoad returns null
     *
     * @return void
     * @throws \ParagonIE\Paseto\Exception\InvalidKeyException
     * @throws \ParagonIE\Paseto\Exception\InvalidPurposeException
     * @throws \ParagonIE\Paseto\Exception\PasetoException
     */
    public function testAuthenticationPayloadIsNull(): void
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/']
        );
        $request = $request->withAddedHeader('Authorization', 'Bearer ');
        $authenticator = new PasetoAuthenticator($this->identifiers, [
            'secretKey' => self::LOCAL_SECRET_KEY,
            'purpose' => PasetoAuthenticator::LOCAL,
            'version' => 'v4',
        ]);

        $result = $authenticator->authenticate($request);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_CREDENTIALS_INVALID, $result->getStatus());
        $this->assertNull($result->getData());
    }

    /**
     * Test getPayLoad returns null
     *
     * @return void
     * @throws \ParagonIE\Paseto\Exception\InvalidKeyException
     * @throws \ParagonIE\Paseto\Exception\InvalidPurposeException
     * @throws \ParagonIE\Paseto\Exception\PasetoException
     */
    public function testAuthenticationReturnsNullPayload(): void
    {
        $authenticator = new PasetoAuthenticator($this->identifiers, [
            'secretKey' => self::LOCAL_SECRET_KEY,
            'purpose' => PasetoAuthenticator::LOCAL,
            'version' => 'v4',
        ]);

        $this->assertNull($authenticator->getPayload());
    }

    /**
     * Test getPayLoad returns a JsonObject with no subject.
     *
     * @return void
     * @throws \ParagonIE\Paseto\Exception\InvalidKeyException
     * @throws \ParagonIE\Paseto\Exception\InvalidPurposeException
     * @throws \ParagonIE\Paseto\Exception\PasetoException
     */
    public function testAuthenticationReturnsJsonObjectWithoutSub(): void
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/']
        );
        $token = $this->buildLocalToken(new Version4(), null, '');
        $request = $request->withAddedHeader('Authorization', 'Bearer ' . $token->toString());
        $authenticator = new PasetoAuthenticator($this->identifiers, [
            'secretKey' => self::LOCAL_SECRET_KEY,
            'purpose' => PasetoAuthenticator::LOCAL,
            'version' => 'v4',
        ]);

        $result = $authenticator->authenticate($request);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_CREDENTIALS_MISSING, $result->getStatus());
        $this->assertNull($result->getData());
    }

    /**
     * Test authentication when identity is not found.
     *
     * @return void
     * @throws \ParagonIE\Paseto\Exception\InvalidKeyException
     * @throws \ParagonIE\Paseto\Exception\InvalidPurposeException
     * @throws \ParagonIE\Paseto\Exception\PasetoException
     */
    public function testLocalAuthenticationIdentityNotFound(): void
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/']
        );
        $token = $this->buildLocalToken(new Version4(), null, '400123');
        $request = $request->withAddedHeader('Authorization', 'Bearer ' . $token->toString());

        $authenticator = new PasetoAuthenticator($this->identifiers, [
            'secretKey' => self::LOCAL_SECRET_KEY,
            'purpose' => PasetoAuthenticator::LOCAL,
            'version' => 'v4',
            'returnPayload' => false,
        ]);

        $result = $authenticator->authenticate($request);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_IDENTITY_NOT_FOUND, $result->getStatus());
        $this->assertNull($result->getData());
    }

    /**
     * Test constructor validations throw RunTimeException.
     *
     * @return void
     */
    public function testConstructorThrowsExceptionWhenVersionIsInvalid(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/PASETO `version` must be one of: v3 or v4/');
        new PasetoAuthenticator($this->identifiers, [
            'secretKey' => self::LOCAL_SECRET_KEY,
            'version' => 'invalid',
            'purpose' => PasetoAuthenticator::LOCAL,
        ]);
    }

    /**
     * Test constructor validations throw RunTimeException.
     *
     * @return void
     */
    public function testConstructorThrowsExceptionWhenPurposeIsInvalid(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/PASETO `purpose` config must one of: local or public/');
        new PasetoAuthenticator($this->identifiers, [
            'secretKey' => self::LOCAL_SECRET_KEY,
            'version' => 'v4',
            'purpose' => 'invalid',
        ]);
    }

    /**
     * Builds a local PASETO token.
     *
     * @param ProtocolInterface $version The PASETO version
     * @param null|string $keyMaterial [optional] If null self::LOCAL_SECRET_KEY is used
     * @param string $sub [optional] Defaults to "3"
     * @return Builder
     * @throws \ParagonIE\Paseto\Exception\InvalidKeyException
     * @throws \ParagonIE\Paseto\Exception\InvalidPurposeException
     * @throws \ParagonIE\Paseto\Exception\PasetoException
     */
    private function buildLocalToken(
        ProtocolInterface $version,
        ?string $keyMaterial = null,
        string $sub = '3'
    ): Builder {
        $key = new SymmetricKey($keyMaterial ?? self::LOCAL_SECRET_KEY, $version);

        return (new Builder())
            ->setKey($key)
            ->setSubject($sub)
            ->setVersion($version)
            ->setPurpose(Purpose::local())
            ->setIssuedAt()
            ->setNotBefore()
            ->setExpiration(
                (new DateTime())->add(new DateInterval('P01D'))
            )
            ->setClaims([
                'claim_data' => 'is encrypted',
                'username' => 'larry',
                'firstname' => 'larry',
            ])
            ->setFooterArray([
                'footer_data' => 'is unencrypted but tamper proof',
            ]);
    }

    /**
     * Builds a public PASETO token and turns a key-value array of `token` and `privateKey`.
     *
     * @param ProtocolInterface $version The PASETO version
     * @return array
     * @throws \ParagonIE\Paseto\Exception\InvalidKeyException
     * @throws \ParagonIE\Paseto\Exception\InvalidPurposeException
     * @throws \ParagonIE\Paseto\Exception\PasetoException
     */
    private function buildPublicToken(ProtocolInterface $version): array
    {
        $privateKey = AsymmetricSecretKey::generate($version);

        return [
            'token' => (new Builder())
                ->setKey($privateKey)
                ->setSubject('3')
                ->setVersion($version)
                ->setPurpose(Purpose::public())
                ->setIssuedAt()
                ->setNotBefore()
                ->setExpiration(
                    (new DateTime())->add(new DateInterval('P01D'))
                )
                ->setClaims([
                    'claim_data' => 'additional claims',
                    'username' => 'larry',
                    'firstname' => 'larry',
                ])
                ->setFooterArray([
                    'footer_data' => 'some footer data',
                ]),
            'privateKey' => $privateKey,
        ];
    }

    /**
     * Returns an array of version and purpose args.
     *
     * @return array
     */
    public function dataProviderForVersions(): array
    {
        return [
            ['v3', new Version3()],
            ['v4', new Version4()],
        ];
    }

    /**
     * Returns purpose arguments.
     *
     * @return array
     */
    public function dataProviderForPurpose(): array
    {
        return [
            [PasetoAuthenticator::PUBLIC],
            [PasetoAuthenticator::LOCAL],
        ];
    }
}
