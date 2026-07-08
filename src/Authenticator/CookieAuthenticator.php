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
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         1.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Authenticator;

use ArrayAccess;
use Authentication\Identifier\IdentifierFactory;
use Authentication\Identifier\IdentifierInterface;
use Authentication\Identifier\PasswordIdentifier;
use Authentication\PasswordHasher\PasswordHasherFactory;
use Authentication\PasswordHasher\PasswordHasherInterface;
use Authentication\PasswordHasher\PasswordHasherTrait;
use Authentication\UrlChecker\UrlCheckerTrait;
use Cake\Http\Cookie\Cookie;
use Cake\Http\Cookie\CookieInterface;
use Cake\Utility\Security;
use DateTimeInterface;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * Cookie Authenticator
 *
 * Authenticates an identity based on a cookie data.
 *
 * You *must* enable encrypted cookies with `EncryptedCookieMiddleware` before using CookieAuthenticator.
 * Without encryption remember-me cookie values can be tampered with.
 */
class CookieAuthenticator extends AbstractAuthenticator implements PersistenceInterface
{
    use PasswordHasherTrait;
    use UrlCheckerTrait;

    /**
     * @inheritDoc
     */
    protected array $_defaultConfig = [
        'loginUrl' => null,
        'urlChecker' => 'Authentication.Default',
        'rememberMeField' => 'remember_me',
        'fields' => [
            PasswordIdentifier::CREDENTIAL_USERNAME => 'username',
            PasswordIdentifier::CREDENTIAL_PASSWORD => 'password',
        ],
        'cookie' => [
            'name' => 'CookieAuth',
            'expires' => '+7 days',
        ],
        // Used only to verify legacy (v1) cookie tokens during the
        // `legacyTokens` grace period. Deprecated: removed together with
        // `legacyTokens` support.
        'passwordHasher' => 'Authentication.Default',
        'salt' => true,
        // While true (default), legacy 2-part tokens issued before the
        // HMAC token format are accepted (hardened) and holders are
        // upgraded at their next login. Set to false to end the grace
        // period and enforce the new token format only.
        'legacyTokens' => true,
        // Upper bounds on the work factor of legacy token hashes. A forged
        // legacy token could otherwise embed a valid bcrypt/argon2 hash with
        // an arbitrarily large cost, turning password_verify() into a CPU or
        // memory exhaustion vector. Hashes above these limits are rejected
        // before verification. Defaults comfortably exceed the
        // PASSWORD_DEFAULT parameters used to issue real cookies; raise them
        // only if your application issued cookies with higher work factors.
        'legacyHashLimits' => [
            'cost' => 15,
            'memory_cost' => 131072,
            'time_cost' => 10,
        ],
    ];

    /**
     * Gets the identifier, loading a default Password identifier if none configured.
     *
     * This is done lazily to allow configuration to be fully set before creating the identifier.
     *
     * @return \Authentication\Identifier\IdentifierInterface
     */
    public function getIdentifier(): IdentifierInterface
    {
        if (!$this->_identifier instanceof IdentifierInterface) {
            $identifierConfig = [];
            if ($this->getConfig('fields')) {
                $identifierConfig['fields'] = $this->getConfig('fields');
            }
            $this->_identifier = IdentifierFactory::create('Authentication.Password', $identifierConfig);
        }

        return $this->_identifier;
    }

    /**
     * @inheritDoc
     */
    public function authenticate(ServerRequestInterface $request): ResultInterface
    {
        $cookies = $request->getCookieParams();
        $cookieName = $this->getConfig('cookie.name');
        if (!isset($cookies[$cookieName])) {
            return new Result(null, Result::FAILURE_CREDENTIALS_MISSING, [
                'Login credentials not found',
            ]);
        }

        $token = is_array($cookies[$cookieName])
            ? $cookies[$cookieName]
            : json_decode((string)$cookies[$cookieName], true);

        if (!is_array($token) || !array_is_list($token)) {
            return new Result(null, Result::FAILURE_CREDENTIALS_INVALID, [
                'Cookie token is invalid.',
            ]);
        }

        if (count($token) === 3) {
            return $this->_authenticateToken($token);
        }

        if (count($token) === 2 && $this->getConfig('legacyTokens')) {
            return $this->_authenticateLegacyToken($token);
        }

        return new Result(null, Result::FAILURE_CREDENTIALS_INVALID, [
            'Cookie token is invalid.',
        ]);
    }

    /**
     * Validates a three-part `[username, expires, hmac]` token.
     *
     * Checks part types, then expiry (before touching the identifier), then
     * locates the user and verifies the HMAC of
     * `username + password hash + expires` in constant time.
     *
     * @param array $token The decoded token parts.
     * @return \Authentication\Authenticator\ResultInterface
     */
    protected function _authenticateToken(array $token): ResultInterface
    {
        [$username, $expires, $tokenHash] = $token;
        if (!is_string($username) || !is_numeric($expires) || !is_string($tokenHash)) {
            return new Result(null, Result::FAILURE_CREDENTIALS_INVALID, [
                'Cookie token is invalid.',
            ]);
        }
        $expires = (int)$expires;

        if ($expires < time()) {
            return new Result(null, Result::FAILURE_CREDENTIALS_INVALID, [
                'Cookie token has expired.',
            ]);
        }

        $identifier = $this->getIdentifier();
        $identity = $identifier->identify(compact('username'));
        if (!$identity) {
            return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND, $identifier->getErrors());
        }

        $usernameField = $this->getConfig('fields.username');
        $passwordField = $this->getConfig('fields.password');
        $plain = $identity[$usernameField] . $identity[$passwordField] . $expires;

        if (!hash_equals(hash_hmac('sha256', $plain, $this->_hmacKey()), $tokenHash)) {
            return new Result(null, Result::FAILURE_CREDENTIALS_INVALID, [
                'Cookie token does not match',
            ]);
        }

        return new Result($identity, Result::SUCCESS);
    }

    /**
     * Validates a legacy two-part `[username, passwordHashedToken]` token.
     *
     * Legitimate legacy tokens were created with `password_hash()`, so any
     * hash that is not a known `password_hash()` algorithm is rejected
     * before it can reach `password_verify()`. This blocks forged
     * crypt()-format hashes (e.g. DES, which truncates its input to
     * 8 bytes) while all real legacy cookies keep working.
     *
     * @param array $token The decoded token parts.
     * @return \Authentication\Authenticator\ResultInterface
     */
    protected function _authenticateLegacyToken(array $token): ResultInterface
    {
        [$username, $tokenHash] = $token;
        if (!is_string($username) || !is_string($tokenHash)) {
            return new Result(null, Result::FAILURE_CREDENTIALS_INVALID, [
                'Cookie token is invalid.',
            ]);
        }

        $info = password_get_info($tokenHash);
        if ($info['algoName'] === 'unknown' || !$this->_legacyHashWithinLimits($info)) {
            return new Result(null, Result::FAILURE_CREDENTIALS_INVALID, [
                'Cookie token is invalid.',
            ]);
        }

        $identifier = $this->getIdentifier();
        $identity = $identifier->identify(compact('username'));
        if (!$identity) {
            return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND, $identifier->getErrors());
        }

        $plain = $this->_createLegacyPlainToken($identity);
        if (!$this->getPasswordHasher()->check($plain, $tokenHash)) {
            return new Result(null, Result::FAILURE_CREDENTIALS_INVALID, [
                'Cookie token does not match',
            ]);
        }

        return new Result($identity, Result::SUCCESS);
    }

    /**
     * Checks a legacy token hash uses a bounded work factor.
     *
     * A forged legacy token could embed a valid bcrypt/argon2 hash with an
     * arbitrarily large cost, turning password_verify() into a CPU or memory
     * exhaustion vector. Legitimately issued cookies use the PASSWORD_DEFAULT
     * parameters, so bounding the work factor rejects abusive hashes without
     * affecting real tokens. Bounds come from the `legacyHashLimits` config.
     *
     * @param array $info Result of password_get_info() for the token hash.
     * @return bool
     */
    protected function _legacyHashWithinLimits(array $info): bool
    {
        $limits = $this->getConfig('legacyHashLimits');
        $options = $info['options'] ?? [];

        if (isset($options['cost']) && $options['cost'] > $limits['cost']) {
            return false;
        }
        if (isset($options['memory_cost']) && $options['memory_cost'] > $limits['memory_cost']) {
            return false;
        }

        return !(isset($options['time_cost']) && $options['time_cost'] > $limits['time_cost']);
    }

    /**
     * Recreates the plain part of a legacy cookie token.
     *
     * This must match the pre-HMAC token construction byte for byte,
     * including the legacy `salt` config semantics, or existing cookies
     * would not survive the grace period.
     *
     * @param \ArrayAccess<string, mixed>|array<string, mixed> $identity Identity data.
     * @return string
     */
    protected function _createLegacyPlainToken(ArrayAccess|array $identity): string
    {
        $usernameField = $this->getConfig('fields.username');
        $passwordField = $this->getConfig('fields.password');

        if ($identity[$usernameField] === null || $identity[$passwordField] === null) {
            throw new InvalidArgumentException(
                sprintf('Fields %s cannot be found in entity', '`' . $usernameField . '`/`' . $passwordField . '`'),
            );
        }

        $value = $identity[$usernameField] . $identity[$passwordField];
        $salt = $this->getConfig('salt', '');

        if ($salt === false) {
            return $value;
        }
        if ($salt === true) {
            $salt = Security::getSalt();
        } elseif (!is_string($salt) || $salt === '') {
            throw new InvalidArgumentException('Salt must be a non-empty string.');
        }

        $hmac = hash_hmac('sha1', $value, $salt);

        return $value . $hmac;
    }

    /**
     * Return the password hasher built from the `passwordHasher` config.
     *
     * Overrides the trait accessor so the config option is honored.
     *
     * @return \Authentication\PasswordHasher\PasswordHasherInterface
     */
    public function getPasswordHasher(): PasswordHasherInterface
    {
        if (!$this->_passwordHasher instanceof PasswordHasherInterface) {
            $this->_passwordHasher = PasswordHasherFactory::build($this->getConfig('passwordHasher'));
        }

        return $this->_passwordHasher;
    }

    /**
     * @inheritDoc
     */
    public function persistIdentity(
        ServerRequestInterface $request,
        ResponseInterface $response,
        ArrayAccess|array $identity,
    ): array {
        $field = $this->getConfig('rememberMeField');
        $bodyData = $request->getParsedBody();

        if (!$this->_checkUrl($request) || !is_array($bodyData) || empty($bodyData[$field])) {
            return [
                'request' => $request,
                'response' => $response,
            ];
        }

        $value = $this->_createToken($identity);
        $cookie = $this->_createCookie($value);

        return [
            'request' => $request,
            'response' => $response->withAddedHeader('Set-Cookie', $cookie->toHeaderValue()),
        ];
    }

    /**
     * Creates a full cookie token serialized as a JSON string.
     *
     * Cookie token consists of the username, an expiry timestamp, and an
     * HMAC-SHA256 of `username + password hash + expires`.
     *
     * @param \ArrayAccess<string, mixed>|array<string, mixed> $identity Identity data.
     * @return string
     * @throws \JsonException
     */
    protected function _createToken(ArrayAccess|array $identity): string
    {
        $usernameField = $this->getConfig('fields.username');
        $passwordField = $this->getConfig('fields.password');

        if ($identity[$usernameField] === null || $identity[$passwordField] === null) {
            throw new InvalidArgumentException(
                sprintf('Fields %s cannot be found in entity', '`' . $usernameField . '`/`' . $passwordField . '`'),
            );
        }

        $expires = $this->_expiryTimestamp();
        $plain = $identity[$usernameField] . $identity[$passwordField] . $expires;
        $hash = hash_hmac('sha256', $plain, $this->_hmacKey());

        return json_encode([$identity[$usernameField], $expires, $hash], JSON_THROW_ON_ERROR);
    }

    /**
     * Returns the HMAC key for token creation and verification.
     *
     * The `salt` config is used as the key when it is a string. Any other
     * value falls back to the application salt — the HMAC key cannot be
     * disabled.
     *
     * @return string
     */
    protected function _hmacKey(): string
    {
        $salt = $this->getConfig('salt');
        if (is_string($salt)) {
            if ($salt === '') {
                throw new InvalidArgumentException('Salt must be a non-empty string.');
            }

            return $salt;
        }

        return Security::getSalt();
    }

    /**
     * Converts the `cookie.expires` config value to a timestamp.
     *
     * Supported values: `DateTimeInterface` instances, numeric UNIX
     * timestamps (consistent with `Cake\Http\Cookie\Cookie`), and
     * `strtotime()` compatible strings such as the `+7 days` default.
     *
     * @return int Timestamp the token will expire at.
     */
    protected function _expiryTimestamp(): int
    {
        $expires = $this->getConfig('cookie.expires');
        if ($expires instanceof DateTimeInterface) {
            return $expires->getTimestamp();
        }
        if (is_numeric($expires)) {
            return (int)$expires;
        }
        if (is_string($expires)) {
            $time = strtotime($expires);
            if ($time !== false) {
                return $time;
            }
        }

        throw new UnexpectedValueException('Invalid `cookie.expires` value');
    }

    /**
     * @inheritDoc
     */
    public function clearIdentity(ServerRequestInterface $request, ResponseInterface $response): array
    {
        $cookie = $this->_createCookie('')->withExpired();

        return [
            'request' => $request,
            'response' => $response->withAddedHeader('Set-Cookie', $cookie->toHeaderValue()),
        ];
    }

    /**
     * Creates a cookie instance with configured defaults.
     *
     * @param mixed $value Cookie value.
     * @return \Cake\Http\Cookie\CookieInterface
     */
    protected function _createCookie(mixed $value): CookieInterface
    {
        $options = $this->getConfig('cookie');
        $name = $options['name'];
        unset($options['name']);

        return Cookie::create(
            $name,
            $value,
            $options,
        );
    }
}
