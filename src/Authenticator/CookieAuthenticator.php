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

use Authentication\Identifier\IdentifierCollection;
use Authentication\Identifier\IdentifierInterface;
use Authentication\PasswordHasher\PasswordHasherTrait;
use Authentication\UrlChecker\UrlCheckerTrait;
use Cake\Http\Cookie\Cookie;
use Cake\Http\Cookie\CookieInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * Cookie Authenticator
 *
 * Authenticates an identity based on a cookies data.
 */
class CookieAuthenticator extends AbstractAuthenticator implements PersistenceInterface
{
    use PasswordHasherTrait;
    use UrlCheckerTrait;

    /**
     * @inheritdoc
     */
    protected $_defaultConfig = [
        'loginUrl' => null,
        'urlChecker' => 'Authentication.Default',
        'rememberMeField' => 'remember_me',
        'fields' => [
            IdentifierInterface::CREDENTIAL_USERNAME => 'username',
            IdentifierInterface::CREDENTIAL_PASSWORD => 'password',
        ],
        'cookie' => [
            'name' => 'CookieAuth',
            'expire' => null,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httpOnly' => false,
        ],
        'passwordHasher' => 'Authentication.Default',
    ];

    /**
     * @inheritdoc
     */
    public function __construct(IdentifierCollection $identifiers, array $config = [])
    {
        $this->_checkCakeVersion();

        parent::__construct($identifiers, $config);
    }

    /**
     * Checks the CakePHP Version by looking for the cookie implementation
     *
     * @return void
     */
    protected function _checkCakeVersion(): void
    {
        if (!class_exists(Cookie::class)) {
            throw new RuntimeException('Install CakePHP version >=3.5.0 to use the `CookieAuthenticator`.');
        }
    }

    /**
     * @inheritdoc
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

        $identity = $this->_identifier->identify(compact('username'));

        if (empty($identity)) {
            return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND, $this->_identifier->getErrors());
        }

        if (!$this->_checkToken($identity, $tokenHash)) {
            return new Result(null, Result::FAILURE_CREDENTIALS_INVALID, [
                'Cookie token does not match',
            ]);
        }

        return new Result($identity, Result::SUCCESS);
    }

    /**
     * @inheritdoc
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
     * Returns concatenated username and password hash.
     *
     * @param array|\ArrayAccess $identity Identity data.
     * @return string
     */
    protected function _createPlainToken($identity): string
    {
        $usernameField = $this->getConfig('fields.username');
        $passwordField = $this->getConfig('fields.password');

        return $identity[$usernameField] . $identity[$passwordField];
    }

    /**
     * Creates a full cookie token serialized as a JSON sting.
     *
     * Cookie token consists of a username and hashed username + password hash.
     *
     * @param array|\ArrayAccess $identity Identity data.
     * @return string
     */
    protected function _createToken($identity): string
    {
        $plain = $this->_createPlainToken($identity);
        $hash = $this->getPasswordHasher()->hash($plain);

        $usernameField = $this->getConfig('fields.username');

        return json_encode([$identity[$usernameField], $hash]);
    }

    /**
     * Checks whether a token hash matches the identity data.
     *
     * @param array|\ArrayAccess $identity Identity data.
     * @param string $tokenHash Hashed part of a cookie token.
     * @return bool
     */
    protected function _checkToken($identity, $tokenHash): bool
    {
        $plain = $this->_createPlainToken($identity);

        return $this->getPasswordHasher()->check($plain, $tokenHash);
    }

    /**
     * @inheritdoc
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
    protected function _createCookie($value): CookieInterface
    {
        $data = $this->getConfig('cookie');

        $cookie = new Cookie(
            $data['name'],
            $value,
            $data['expire'],
            $data['path'],
            $data['domain'],
            $data['secure'],
            $data['httpOnly']
        );

        return $cookie;
    }
}
