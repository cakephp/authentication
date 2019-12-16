<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Authenticator;

use Authentication\Identifier\IdentifierInterface;
use Cake\Utility\Security;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * HttpDigest Authenticator
 *
 * Provides Digest HTTP authentication support.
 *
 * ### Generating passwords compatible with Digest authentication.
 *
 * DigestAuthenticate requires a special password hash that conforms to RFC2617.
 * You can generate this password using `HttpDigestAuthenticate::password()`
 *
 * ```
 * $digestPass = HttpDigestAuthenticator::password($username, $password, env('SERVER_NAME'));
 * ```
 *
 * If you wish to use digest authentication alongside other authentication methods,
 * it's recommended that you store the digest authentication separately. For
 * example `User.digest_pass` could be used for a digest password, while
 * `User.password` would store the password hash for use with other methods like
 * BasicHttp or Form.
 */
class HttpDigestAuthenticator extends HttpBasicAuthenticator
{
    /**
     * Constructor
     *
     * Besides the keys specified in AbstractAuthenticator::$_defaultConfig,
     * HttpDigestAuthenticate uses the following extra keys:
     *
     * - `realm` The realm authentication is for, Defaults to the servername.
     * - `nonceLifetime` The number of seconds that nonces are valid for. Defaults to 300.
     * - `qop` Defaults to 'auth', no other values are supported at this time.
     * - `opaque` A string that must be returned unchanged by clients.
     *    Defaults to `md5($config['realm'])`
     *
     * @param \Authentication\Identifier\IdentifierInterface $identifier Identifier instance.
     * @param array $config Configuration settings.
     */
    public function __construct(IdentifierInterface $identifier, array $config = [])
    {
        $secret = '';
        if (class_exists(Security::class)) {
            $secret = Security::getSalt();
        }
        $this->setConfig([
            'realm' => null,
            'qop' => 'auth',
            'nonceLifetime' => 300,
            'opaque' => null,
            'secret' => $secret,
        ]);

        $this->setConfig($config);
        parent::__construct($identifier, $config);

        $secret = $this->getConfig('secret');
        if (!is_string($secret) || strlen($secret) === 0) {
            throw new InvalidArgumentException('Secret key must be a non-empty string.');
        }
    }

    /**
     * Get a user based on information in the request. Used by cookie-less auth for stateless clients.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request that contains login information.
     * @return \Authentication\Authenticator\ResultInterface
     */
    public function authenticate(ServerRequestInterface $request): ResultInterface
    {
        $digest = $this->_getDigest($request);
        if ($digest === null) {
            return new Result(null, Result::FAILURE_CREDENTIALS_MISSING);
        }

        $user = $this->_identifier->identify([
            IdentifierInterface::CREDENTIAL_USERNAME => $digest['username'],
        ]);

        if (empty($user)) {
            return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND);
        }

        if (!$this->validNonce($digest['nonce'])) {
            return new Result(null, Result::FAILURE_CREDENTIALS_INVALID);
        }

        $field = $this->_config['fields'][IdentifierInterface::CREDENTIAL_PASSWORD];
        $password = $user[$field];

        $server = $request->getServerParams();
        if (!isset($server['ORIGINAL_REQUEST_METHOD'])) {
            $server['ORIGINAL_REQUEST_METHOD'] = $server['REQUEST_METHOD'];
        }

        $hash = $this->generateResponseHash($digest, $password, $server['ORIGINAL_REQUEST_METHOD']);
        if (hash_equals($hash, $digest['response'])) {
            return new Result($user, Result::SUCCESS);
        }

        return new Result(null, Result::FAILURE_CREDENTIALS_INVALID);
    }

    /**
     * Gets the digest headers from the request/environment.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request that contains login information.
     * @return string[]|null Array of digest information.
     */
    protected function _getDigest(ServerRequestInterface $request): ?array
    {
        $server = $request->getServerParams();
        $digest = empty($server['PHP_AUTH_DIGEST']) ? null : $server['PHP_AUTH_DIGEST'];
        if (empty($digest) && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (!empty($headers['Authorization']) && substr($headers['Authorization'], 0, 7) === 'Digest ') {
                $digest = substr($headers['Authorization'], 7);
            }
        }
        if (empty($digest)) {
            return null;
        }

        return $this->parseAuthData($digest);
    }

    /**
     * Parse the digest authentication headers and split them up.
     *
     * @param string $digest The raw digest authentication headers.
     * @return string[]|null An array of digest authentication headers
     */
    public function parseAuthData(string $digest): ?array
    {
        if (substr($digest, 0, 7) === 'Digest ') {
            $digest = substr($digest, 7);
        }
        $keys = $match = [];
        $req = ['nonce' => 1, 'nc' => 1, 'cnonce' => 1, 'qop' => 1, 'username' => 1, 'uri' => 1, 'response' => 1];
        preg_match_all('/(\w+)=([\'"]?)([a-zA-Z0-9\:\#\%\?\&@=\.\/_-]+)\2/', $digest, $match, PREG_SET_ORDER);

        foreach ($match as $i) {
            $keys[$i[1]] = $i[3];
            unset($req[$i[1]]);
        }

        if (empty($req)) {
            return $keys;
        }

        return null;
    }

    /**
     * Generate the response hash for a given digest array.
     *
     * @param string[] $digest Digest information containing data from HttpDigestAuthenticate::parseAuthData().
     * @param string $password The digest hash password generated with HttpDigestAuthenticate::password()
     * @param string $method Request method
     * @return string Response hash
     */
    public function generateResponseHash(array $digest, string $password, string $method): string
    {
        return md5(
            $password .
            ':' . $digest['nonce'] . ':' . $digest['nc'] . ':' . $digest['cnonce'] . ':' . $digest['qop'] . ':' .
            md5($method . ':' . $digest['uri'])
        );
    }

    /**
     * Creates an auth digest password hash to store
     *
     * @param string $username The username to use in the digest hash.
     * @param string $password The unhashed password to make a digest hash for.
     * @param string $realm The realm the password is for.
     * @return string the hashed password that can later be used with Digest authentication.
     */
    public static function password(string $username, string $password, string $realm): string
    {
        return md5($username . ':' . $realm . ':' . $password);
    }

    /**
     * Generate the login headers
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request that contains login information.
     * @return array Headers for logging in.
     */
    protected function loginHeaders(ServerRequestInterface $request): array
    {
        $server = $request->getServerParams();
        $realm = $this->_config['realm'] ?: $server['SERVER_NAME'];

        $options = [
            'realm' => $realm,
            'qop' => $this->_config['qop'],
            'nonce' => $this->generateNonce(),
            'opaque' => $this->_config['opaque'] ?: md5($realm),
        ];

        $digest = $this->_getDigest($request);
        if ($digest !== null && isset($digest['nonce']) && !$this->validNonce($digest['nonce'])) {
            $options['stale'] = true;
        }

        $opts = [];
        foreach ($options as $k => $v) {
            if (is_bool($v)) {
                $v = $v ? 'true' : 'false';
                $opts[] = sprintf('%s=%s', $k, $v);
            } else {
                $opts[] = sprintf('%s="%s"', $k, $v);
            }
        }

        return ['WWW-Authenticate' => 'Digest ' . implode(',', $opts)];
    }

    /**
     * Generate a nonce value that is validated in future requests.
     *
     * @return string
     */
    protected function generateNonce(): string
    {
        $expiryTime = microtime(true) + $this->getConfig('nonceLifetime');
        $secret = $this->getConfig('secret');
        $signatureValue = hash_hmac('sha1', $expiryTime . ':' . $secret, $secret);
        $nonceValue = $expiryTime . ':' . $signatureValue;

        return base64_encode($nonceValue);
    }

    /**
     * Check the nonce to ensure it is valid and not expired.
     *
     * @param string $nonce The nonce value to check.
     * @return bool
     */
    protected function validNonce(string $nonce): bool
    {
        $value = base64_decode($nonce);
        if ($value === false) {
            return false;
        }
        $parts = explode(':', $value);
        if (count($parts) !== 2) {
            return false;
        }
        [$expires, $checksum] = $parts;
        if ($expires < microtime(true)) {
            return false;
        }
        $secret = $this->getConfig('secret');
        $check = hash_hmac('sha1', $expires . ':' . $secret, $secret);

        return hash_equals($check, $checksum);
    }
}
