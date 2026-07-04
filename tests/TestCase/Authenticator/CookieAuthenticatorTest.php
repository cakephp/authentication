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
use Authentication\Identifier\IdentifierFactory;
use Cake\Http\Cookie\Cookie;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;
use Cake\ORM\Entity;
use Cake\TestSuite\TestCase;
use Cake\Utility\Security;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use UnexpectedValueException;

class CookieAuthenticatorTest extends TestCase
{
    /**
     * Fixtures
     */
    protected array $fixtures = [
        'core.AuthUsers',
        'core.Users',
    ];

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->skipIf(!class_exists(Cookie::class));

        // Note: security salt is written in tests/bootstrap.php

        parent::setUp();
    }

    /**
     * Fetch a user entity from the fixture data.
     */
    protected function getUser(string $username): Entity
    {
        $users = $this->getTableLocator()->get('Users');

        /** @var \Cake\ORM\Entity */
        return $users->findByUsername($username)->firstOrFail();
    }

    /**
     * Build a valid v2 token from fixture data.
     *
     * @return array{0: string, 1: int, 2: string}
     */
    protected function createToken(string $username, ?int $expires = null, ?string $key = null): array
    {
        $user = $this->getUser($username);
        $expires = $expires ?? time() + 60 * 60 * 24;
        $key = $key ?? Security::getSalt();
        $hash = hash_hmac('sha256', $user->username . $user->password . $expires, $key);

        return [$user->username, $expires, $hash];
    }

    /**
     * Build a legacy (v1) token exactly as the pre-fix code did:
     * `[username, password_hash(username . password [. sha1 hmac])]`.
     *
     * @return array{0: string, 1: string}
     */
    protected function createLegacyToken(string $username, bool $withSalt = true): array
    {
        $user = $this->getUser($username);
        $value = $user->username . $user->password;
        $plain = $withSalt ? $value . hash_hmac('sha1', $value, Security::getSalt()) : $value;

        return [$user->username, password_hash($plain, PASSWORD_DEFAULT)];
    }

    /**
     * testAuthenticateInvalidTokenMissingUsername
     *
     * @return void
     */
    public function testAuthenticateInvalidTokenMissingUsername(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'CookieAuth' => '["$2y$10$O5VgLDfIqszzr0Q47Ygkc.LkoLIwlIjc/OzoGp6yJasQlxcHU4.ES"]',
            ],
        );

        $authenticator = new CookieAuthenticator($identifier);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_CREDENTIALS_INVALID, $result->getStatus());
    }

    /**
     * testAuthenticateSuccess
     *
     * @return void
     */
    public function testAuthenticateSuccess(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'CookieAuth' => json_encode($this->createToken('mariano')),
            ],
        );

        $authenticator = new CookieAuthenticator($identifier);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());
    }

    /**
     * An array-format (expanded) cookie is accepted.
     *
     * @return void
     */
    public function testAuthenticateExpandedCookie(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'CookieAuth' => $this->createToken('mariano'),
            ],
        );

        $authenticator = new CookieAuthenticator($identifier);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());
    }

    /**
     * `salt => false` cannot disable the HMAC key for v2 tokens; the
     * application salt is used instead.
     *
     * @return void
     */
    public function testAuthenticateNoSalt(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'CookieAuth' => json_encode($this->createToken('mariano')),
            ],
        );

        $authenticator = new CookieAuthenticator($identifier, ['salt' => false]);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());
    }

    /**
     * An empty-string salt config throws.
     *
     * @return void
     */
    public function testAuthenticateInvalidSalt(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'CookieAuth' => json_encode($this->createToken('mariano')),
            ],
        );

        $authenticator = new CookieAuthenticator($identifier, ['salt' => '']);

        $this->expectException(InvalidArgumentException::class);
        $authenticator->authenticate($request);
    }

    /**
     * testAuthenticateUnknownUser
     *
     * @return void
     */
    public function testAuthenticateUnknownUser(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'CookieAuth' => json_encode(['robert', time() + 60 * 60 * 24, str_repeat('a', 64)]),
            ],
        );

        $authenticator = new CookieAuthenticator($identifier);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_IDENTITY_NOT_FOUND, $result->getStatus());
    }

    /**
     * testCredentialsNotPresent
     *
     * @return void
     */
    public function testCredentialsNotPresent(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
        );

        $authenticator = new CookieAuthenticator($identifier);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_CREDENTIALS_MISSING, $result->getStatus());
    }

    /**
     * A well-formed token with a wrong HMAC is rejected.
     *
     * @return void
     */
    public function testAuthenticateInvalidToken(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $token = $this->createToken('mariano');
        $token[2] = str_repeat('0', 64);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'CookieAuth' => json_encode($token),
            ],
        );

        $authenticator = new CookieAuthenticator($identifier);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_CREDENTIALS_INVALID, $result->getStatus());
    }

    /**
     * testDESBypassRejected
     *
     * The forged DES-crypt hash must be rejected in BOTH modes: during the
     * grace period (killed by the password_get_info() algorithm check) and
     * after it (killed by the format rejection).
     *
     * @return void
     */
    public function testDESBypassRejected(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        // Attacker forges a DES-crypt hash. DES truncates plaintext to 8 bytes.
        // 'mariano' is 7 bytes; the first byte of any bcrypt/argon2 hash is '$',
        // so the first 8 bytes of the server plaintext are always 'mariano$'.
        $forgedDESHash = crypt('mariano$', 'xx');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            ['CookieAuth' => json_encode(['mariano', $forgedDESHash])],
        );

        // Grace period on (default)
        $authenticator = new CookieAuthenticator($identifier);
        $result = $authenticator->authenticate($request);
        $this->assertSame(Result::FAILURE_CREDENTIALS_INVALID, $result->getStatus());

        // Grace period ended
        $authenticator = new CookieAuthenticator($identifier, ['legacyTokens' => false]);
        $result = $authenticator->authenticate($request);
        $this->assertSame(Result::FAILURE_CREDENTIALS_INVALID, $result->getStatus());
    }

    /**
     * A legitimate legacy token authenticates during the grace period.
     *
     * @return void
     */
    public function testAuthenticateLegacyTokenSuccess(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'CookieAuth' => json_encode($this->createLegacyToken('mariano')),
            ],
        );

        $authenticator = new CookieAuthenticator($identifier);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());
    }

    /**
     * A legacy token created with `salt => false` authenticates during
     * the grace period.
     *
     * @return void
     */
    public function testAuthenticateLegacyTokenNoSaltSuccess(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'CookieAuth' => json_encode($this->createLegacyToken('mariano', false)),
            ],
        );

        $authenticator = new CookieAuthenticator($identifier, ['salt' => false]);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());
    }

    /**
     * Legacy tokens are rejected once the grace period is ended.
     *
     * @return void
     */
    public function testAuthenticateLegacyTokenRejectedWhenDisabled(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'CookieAuth' => json_encode($this->createLegacyToken('mariano')),
            ],
        );

        $authenticator = new CookieAuthenticator($identifier, ['legacyTokens' => false]);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_CREDENTIALS_INVALID, $result->getStatus());
    }

    /**
     * A legacy token for an unknown user reports identity-not-found.
     *
     * @return void
     */
    public function testAuthenticateLegacyTokenUnknownUser(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'CookieAuth' => '["robert","$2y$10$1bE1SgasKoz9WmEvUfuZLeYa6pQgxUIJ5LAoS/KGmC1hNuWkUG7ES"]',
            ],
        );

        $authenticator = new CookieAuthenticator($identifier);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_IDENTITY_NOT_FOUND, $result->getStatus());
    }

    /**
     * A legacy token whose bcrypt hash uses a work factor above the ceiling
     * is rejected before it can reach password_verify().
     *
     * @return void
     */
    public function testAuthenticateLegacyTokenExcessiveCostRejected(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');
        $user = $this->getUser('mariano');

        // Well-formed bcrypt string at cost 20 (above the default ceiling of
        // 15). Crafted directly so no expensive hash is ever computed.
        $abusiveHash = '$2y$20$' . str_repeat('a', 53);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'CookieAuth' => json_encode([$user->username, $abusiveHash]),
            ],
        );

        $authenticator = new CookieAuthenticator($identifier);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_CREDENTIALS_INVALID, $result->getStatus());
        // Must be rejected at the work-factor gate (this message), not by a
        // slow password_verify() mismatch ('Cookie token does not match').
        // Without this the test would pass even if the gate were removed.
        $this->assertContains('Cookie token is invalid.', $result->getErrors());
    }

    /**
     * The legacy hash work-factor ceiling is configurable: lowering it below
     * the cost used to issue a real token causes that token to be rejected.
     *
     * @return void
     */
    public function testAuthenticateLegacyHashLimitsConfigurable(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'CookieAuth' => json_encode($this->createLegacyToken('mariano')),
            ],
        );

        // A real legacy token uses PASSWORD_DEFAULT cost (>= 10). A ceiling of
        // 4 (bcrypt's minimum) is below that, so the token is now rejected.
        $authenticator = new CookieAuthenticator($identifier, [
            'legacyHashLimits' => ['cost' => 4, 'memory_cost' => 131072, 'time_cost' => 10],
        ]);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_CREDENTIALS_INVALID, $result->getStatus());
    }

    /**
     * A user authenticated via legacy token gets a v2 cookie at next login.
     *
     * @return void
     */
    public function testLegacyUpgradeAtLogin(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');
        $user = $this->getUser('mariano');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'CookieAuth' => json_encode($this->createLegacyToken('mariano')),
            ],
        );

        $authenticator = new CookieAuthenticator($identifier);
        $result = $authenticator->authenticate($request);
        $this->assertSame(Result::SUCCESS, $result->getStatus());

        $request = $request->withParsedBody([
            'remember_me' => 1,
        ]);
        $response = new Response();
        $identity = new ArrayObject([
            'username' => $user->username,
            'password' => $user->password,
        ]);
        $persisted = $authenticator->persistIdentity($request, $response, $identity);

        $cookie = Cookie::createFromHeaderString($persisted['response']->getHeaderLine('Set-Cookie'));
        $decoded = json_decode($cookie->getValue(), true);
        $this->assertCount(3, $decoded);
    }

    /**
     * An expired token is rejected before the identifier is queried.
     *
     * @return void
     */
    public function testAuthenticateExpiredToken(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'CookieAuth' => json_encode($this->createToken('mariano', time() - 1)),
            ],
        );

        $authenticator = new CookieAuthenticator($identifier);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_CREDENTIALS_INVALID, $result->getStatus());
    }

    /**
     * Extending the expiry timestamp invalidates the HMAC.
     *
     * @return void
     */
    public function testAuthenticateExpireModificationFailure(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $token = $this->createToken('mariano');
        $token[1] = $token[1] + 1;

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'CookieAuth' => json_encode($token),
            ],
        );

        $authenticator = new CookieAuthenticator($identifier);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_CREDENTIALS_INVALID, $result->getStatus());
    }

    /**
     * Appending to the hash invalidates the token.
     *
     * @return void
     */
    public function testAuthenticateHashModificationFailure(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $token = $this->createToken('mariano');
        $token[2] .= 'a';

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'CookieAuth' => json_encode($token),
            ],
        );

        $authenticator = new CookieAuthenticator($identifier);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_CREDENTIALS_INVALID, $result->getStatus());
    }

    public static function malformedCookieProvider(): array
    {
        return [
            'garbage json' => ['notjson'],
            'scalar string json' => ['"abc"'],
            'scalar int json' => ['123'],
            'assoc object' => ['{"a":"x","b":"y","c":"z"}'],
            'four parts' => ['["a","b","c","d"]'],
            'non-string hash' => ['["mariano",99999999999,123]'],
            'non-numeric expires' => ['["mariano","soon","abc"]'],
            'non-string username' => ['[1,99999999999,"abc"]'],
        ];
    }

    /**
     * Malformed cookies produce an invalid result, never a TypeError.
     *
     * @return void
     */
    #[DataProvider('malformedCookieProvider')]
    public function testAuthenticateMalformedCookie(string $cookieValue): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'CookieAuth' => $cookieValue,
            ],
        );

        $authenticator = new CookieAuthenticator($identifier);
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_CREDENTIALS_INVALID, $result->getStatus());
    }

    /**
     * A cookie issued with untouched default config must round-trip.
     *
     * This is the regression test for the upstream defect where the default
     * `cookie.expires` produced tokens that were already expired in 1970.
     *
     * @return void
     */
    public function testAuthenticateDefaultConfigRoundtrip(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');
        $user = $this->getUser('mariano');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
        );
        $request = $request->withParsedBody([
            'remember_me' => 1,
        ]);
        $response = new Response();

        $authenticator = new CookieAuthenticator($identifier);
        $identity = new ArrayObject([
            'username' => $user->username,
            'password' => $user->password,
        ]);
        $result = $authenticator->persistIdentity($request, $response, $identity);

        $cookie = Cookie::createFromHeaderString($result['response']->getHeaderLine('Set-Cookie'));

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            null,
            null,
            [
                'CookieAuth' => $cookie->getValue(),
            ],
        );
        $result = $authenticator->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());
    }

    public static function validExpiresProvider(): array
    {
        $string = '2030-01-01 00:00:00';
        $datetime = new DateTimeImmutable($string);

        return [
            'strtotime string' => [$string],
            'datetime instance' => [$datetime],
            'unix timestamp' => [$datetime->getTimestamp()],
        ];
    }

    /**
     * testPersistIdentity
     *
     * @return void
     */
    #[DataProvider('validExpiresProvider')]
    public function testPersistIdentity(DateTimeImmutable|string|int $expires): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
        );
        $request = $request->withParsedBody([
            'remember_me' => 1,
        ]);
        $response = new Response();

        Cookie::setDefaults(['samesite' => 'None']);
        $authenticator = new CookieAuthenticator($identifier, [
            'cookie' => ['expires' => $expires],
        ]);

        $password = '$2a$10$u05j8FjsvLBNdfhBhc21LOuVMpzpabVXQ9OpC2wO3pSO0q6t7HHMO';
        $identity = new ArrayObject([
            'username' => 'mariano',
            'password' => $password,
        ]);
        $result = $authenticator->persistIdentity($request, $response, $identity);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('request', $result);
        $this->assertArrayHasKey('response', $result);
        $this->assertInstanceOf(RequestInterface::class, $result['request']);
        $this->assertInstanceOf(ResponseInterface::class, $result['response']);

        $header = $result['response']->getHeaderLine('Set-Cookie');
        $cookie = Cookie::createFromHeaderString($header);
        $this->assertSame('CookieAuth', $cookie->getName());

        $expectedExpires = strtotime('2030-01-01 00:00:00');
        $decoded = json_decode($cookie->getValue(), true);
        $this->assertCount(3, $decoded);
        $this->assertSame('mariano', $decoded[0]);
        $this->assertSame($expectedExpires, $decoded[1]);
        $this->assertSame(
            hash_hmac('sha256', 'mariano' . $password . $expectedExpires, Security::getSalt()),
            $decoded[2],
        );
        $this->assertStringContainsString('expires=Tue, 01-Jan-2030 00:00:00 GMT;', $header);
        $this->assertStringContainsString('samesite=None', $header);

        Cookie::setDefaults(['samesite' => null]);
    }

    /**
     * The cookie is not written without the remember-me field.
     *
     * @return void
     */
    public function testPersistIdentityNoField(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
        );
        $request = $request->withParsedBody([]);
        $response = new Response();

        $authenticator = new CookieAuthenticator($identifier);
        $identity = new ArrayObject([
            'username' => 'mariano',
            'password' => '$2a$10$u05j8FjsvLBNdfhBhc21LOuVMpzpabVXQ9OpC2wO3pSO0q6t7HHMO',
        ]);
        $result = $authenticator->persistIdentity($request, $response, $identity);

        $this->assertStringNotContainsString(
            'CookieAuth',
            $result['response']->getHeaderLine('Set-Cookie'),
        );
    }

    /**
     * A custom remember-me field name is honored.
     *
     * @return void
     */
    public function testPersistIdentityOtherField(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
        );
        $request = $request->withParsedBody([
            'other_field' => 1,
        ]);
        $response = new Response();

        $authenticator = new CookieAuthenticator($identifier, [
            'rememberMeField' => 'other_field',
        ]);
        $identity = new ArrayObject([
            'username' => 'mariano',
            'password' => '$2a$10$u05j8FjsvLBNdfhBhc21LOuVMpzpabVXQ9OpC2wO3pSO0q6t7HHMO',
        ]);
        $result = $authenticator->persistIdentity($request, $response, $identity);

        $cookie = Cookie::createFromHeaderString($result['response']->getHeaderLine('Set-Cookie'));
        $this->assertSame('CookieAuth', $cookie->getName());
        $decoded = json_decode($cookie->getValue(), true);
        $this->assertCount(3, $decoded);
    }

    /**
     * An unparseable cookie.expires config value throws at persist time.
     *
     * @return void
     */
    public function testPersistIdentityInvalidExpiryTime(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
        );
        $request = $request->withParsedBody([
            'remember_me' => 1,
        ]);
        $response = new Response();

        $authenticator = new CookieAuthenticator($identifier, [
            'cookie' => ['expires' => 'nope'],
        ]);

        $identity = new ArrayObject([
            'username' => 'mariano',
            'password' => '$2a$10$u05j8FjsvLBNdfhBhc21LOuVMpzpabVXQ9OpC2wO3pSO0q6t7HHMO',
        ]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Invalid `cookie.expires` value');
        $authenticator->persistIdentity($request, $response, $identity);
    }

    /**
     * testPersistIdentityLoginUrlMismatch
     *
     * @return void
     */
    public function testPersistIdentityLoginUrlMismatch(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
        );
        $request = $request->withParsedBody([
            'remember_me' => 1,
        ]);
        $response = new Response();

        $authenticator = new CookieAuthenticator($identifier, [
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
        $this->assertSame('', $result['response']->getHeaderLine('Set-Cookie'));
    }

    /**
     * @return void
     */
    public function testPersistIdentityInvalidConfig(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/login'],
        );
        $request = $request->withParsedBody([
            'remember_me' => 1,
        ]);
        $response = new Response();

        $authenticator = new CookieAuthenticator($identifier, [
            'loginUrl' => '/users/login',
        ]);

        $identity = new ArrayObject([
            'username' => null,
            'password' => '$2a$10$u05j8FjsvLBNdfhBhc21LOuVMpzpabVXQ9OpC2wO3pSO0q6t7HHMO',
        ]);

        $this->expectException(InvalidArgumentException::class);

        $authenticator->persistIdentity($request, $response, $identity);
    }

    /**
     * testClearIdentity
     *
     * @return void
     */
    public function testClearIdentity(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
        );
        $response = new Response();

        $authenticator = new CookieAuthenticator($identifier);

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
