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
use Authentication\Identifier\AbstractIdentifier;
use Authentication\Identifier\IdentifierCollection;
use Authentication\Identifier\IdentifierInterface;
use Authentication\PasswordHasher\PasswordHasherTrait;
use Authentication\UrlChecker\UrlCheckerTrait;
use Cake\Http\Cookie\Cookie;
use Cake\Http\Cookie\CookieInterface;
use Cake\Utility\Security;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Cookie Authenticator
 *
 * Authenticates an identity based on a cookie data.
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
            AbstractIdentifier::CREDENTIAL_USERNAME => 'username',
            AbstractIdentifier::CREDENTIAL_PASSWORD => 'password',
        ],
        'cookie' => [
            'name' => 'CookieAuth',
        ],
        'passwordHasher' => 'Authentication.Default',
        'salt' => true,
    ];

    /**
     * Gets the identifier, loading a default Password identifier if none configured.
     *
     * This is done lazily to allow loadIdentifier() to be called after loadAuthenticator().
     *
     * @return \Authentication\Identifier\IdentifierInterface
     */
    public function getIdentifier(): IdentifierInterface
    {
        if ($this->_identifier instanceof IdentifierCollection && $this->_identifier->isEmpty()) {
            $identifierConfig = [];
            if ($this->getConfig('fields')) {
                $identifierConfig['fields'] = $this->getConfig('fields');
            }
            $this->_identifier->load('Authentication.Password', $identifierConfig);
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

        if (is_array($cookies[$cookieName])) {
            $token = $cookies[$cookieName];
        } else {
            $token = json_decode($cookies[$cookieName], true);
        }

        if ($token === null || count($token) !== 2) {
            return new Result(null, Result::FAILURE_CREDENTIALS_INVALID, [
                'Cookie token is invalid.',
            ]);
        }

        [$username, $tokenHash] = $token;

        $identifier = $this->getIdentifier();
        $identity = $identifier->identify(compact('username'));

        if (!$identity) {
            return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND, $identifier->getErrors());
        }

        if (!$this->_checkToken($identity, $tokenHash)) {
            return new Result(null, Result::FAILURE_CREDENTIALS_INVALID, [
                'Cookie token does not match',
            ]);
        }

        return new Result($identity, Result::SUCCESS);
    }

    /**
     * @inheritDoc
     */
    public function persistIdentity(ServerRequestInterface $request, ResponseInterface $response, $identity): array
    {
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
     * Creates a plain part of a cookie token.
     *
     * Returns concatenated username, password hash, and HMAC signature.
     *
     * @param \ArrayAccess|array $identity Identity data.
     * @return string
     */
    protected function _createPlainToken(ArrayAccess|array $identity): string
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
        // Instead of appending the plain salt, we create a hash. This limits the chance of the salt being leaked.

        return $value . $hmac;
    }

    /**
     * Creates a full cookie token serialized as a JSON sting.
     *
     * Cookie token consists of a username and hashed username + password hash.
     *
     * @param \ArrayAccess|array $identity Identity data.
     * @return string
     * @throws \JsonException
     */
    protected function _createToken(ArrayAccess|array $identity): string
    {
        $plain = $this->_createPlainToken($identity);
        $hash = $this->getPasswordHasher()->hash($plain);

        $usernameField = $this->getConfig('fields.username');

        return json_encode([$identity[$usernameField], $hash], JSON_THROW_ON_ERROR);
    }

    /**
     * Checks whether a token hash matches the identity data.
     *
     * @param \ArrayAccess|array $identity Identity data.
     * @param string $tokenHash Hashed part of a cookie token.
     * @return bool
     */
    protected function _checkToken(ArrayAccess|array $identity, string $tokenHash): bool
    {
        $plain = $this->_createPlainToken($identity);

        return $this->getPasswordHasher()->check($plain, $tokenHash);
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
