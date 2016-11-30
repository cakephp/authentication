<?php
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
 * @since         2.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Authentication;

use Authentication\Identifier\IdentifierCollection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Digest Authentication adapter for AuthComponent.
 *
 * Provides Digest HTTP authentication support for AuthComponent.
 *
 * ### Using Digest auth
 *
 * ```
 *  $authenticator = new DigestAuthenticator();
 *  $result = $authenticator->authenticate($request);
 *  if ($result->isValid()) {
 *      // Do something with the result
 *  }
 * ```
 *
 * You should also set `AuthComponent::$sessionKey = false;` in your AppController's
 * beforeFilter() to prevent CakePHP from sending a session cookie to the client.
 *
 * Since HTTP Digest Authentication is stateless you don't need a login() action
 * in your controller. The user credentials will be checked on each request. If
 * valid credentials are not provided, required authentication headers will be sent
 * by this authentication provider which triggers the login dialog in the browser/client.
 *
 * You may also want to use `$this->Auth->unauthorizedRedirect = false;`.
 * This causes AuthComponent to throw a ForbiddenException exception instead of
 * redirecting to another page.
 *
 * ### Generating passwords compatible with Digest authentication.
 *
 * DigestAuthenticate requires a special password hash that conforms to RFC2617.
 * You can generate this password using `DigestAuthenticate::password()`
 *
 * ```
 * $digestPass = DigestAuthenticator::password($username, $password, env('SERVER_NAME'));
 * ```
 *
 * If you wish to use digest authentication alongside other authentication methods,
 * it's recommended that you store the digest authentication separately. For
 * example `User.digest_pass` could be used for a digest password, while
 * `User.password` would store the password hash for use with other methods like
 * Basic or Form.
 */
class DigestAuthenticator extends BasicAuthenticator
{

    /**
     * Constructor
     *
     * Besides the keys specified in AbstractAuthenticator::$_defaultConfig,
     * DigestAuthenticate uses the following extra keys:
     *
     * - `realm` The realm authentication is for, Defaults to the servername.
     * - `nonce` A nonce used for authentication. Defaults to `uniqid()`.
     * - `qop` Defaults to 'auth', no other values are supported at this time.
     * - `opaque` A string that must be returned unchanged by clients.
     *    Defaults to `md5($config['realm'])`
     *
     *
     * @param \Auth\Authentication\Identifier\IdentifierCollection $identifiers Array of config to use.
     * @param array $config Configuration settings.
     */
    public function __construct(IdentifierCollection $identifiers, array $config = [])
    {
        $this->config([
            'realm' => null,
            'qop' => 'auth',
            'nonce' => uniqid(''),
            'opaque' => null,
        ]);

        $this->config($config);
        parent::__construct($identifiers, $config);
    }

    /**
     * Get a user based on information in the request. Used by cookie-less auth for stateless clients.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request that contains login information.
     * @param \Psr\Http\Message\ResponseInterface $response Unused response object.
     * @return mixed False on login failure.  An array of User data on success.
     */
    public function authenticate(ServerRequestInterface $request, ResponseInterface $response)
    {
        $digest = $this->_getDigest($request);
        if (empty($digest)) {
            return new Result(null, Result::FAILURE_OTHER);
        }

        $user = $this->identifiers()->identify([
            'username' => $digest['username'],
        ]);

        if (empty($user)) {
            return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND);
        }

        $field = $this->_config['fields']['password'];
        $password = $user[$field];
        unset($user[$field]);

        $server = $request->getServerParams();
        if (!isset($server['ORIGINAL_REQUEST_METHOD'])) {
            $server['ORIGINAL_REQUEST_METHOD'] = $server['REQUEST_METHOD'];
        }

        $hash = $this->generateResponseHash($digest, $password, $server['ORIGINAL_REQUEST_METHOD']);
        if ($digest['response'] === $hash) {
            return new Result($user, Result::SUCCESS);
        }

        return new Result(null, Result::FAILURE_CREDENTIAL_INVALID);
    }

    /**
     * Gets the digest headers from the request/environment.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request that contains login information.
     * @return array Array of digest information.
     */
    protected function _getDigest(ServerRequestInterface $request)
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
            return false;
        }

        return $this->parseAuthData($digest);
    }

    /**
     * Parse the digest authentication headers and split them up.
     *
     * @param string $digest The raw digest authentication headers.
     * @return array|null An array of digest authentication headers
     */
    public function parseAuthData($digest)
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
     * @param array $digest Digest information containing data from DigestAuthenticate::parseAuthData().
     * @param string $password The digest hash password generated with DigestAuthenticate::password()
     * @param string $method Request method
     * @return string Response hash
     */
    public function generateResponseHash($digest, $password, $method)
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
    public static function password($username, $password, $realm)
    {
        return md5($username . ':' . $realm . ':' . $password);
    }

    /**
     * Generate the login headers
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request that contains login information.
     * @return string Headers for logging in.
     */
    public function loginHeaders(ServerRequestInterface $request)
    {
        $server = $request->getServerParams();
        $realm = $this->_config['realm'] ?: $server['SERVER_NAME'];

        $options = [
            'realm' => $realm,
            'qop' => $this->_config['qop'],
            'nonce' => $this->_config['nonce'],
            'opaque' => $this->_config['opaque'] ?: md5($realm)
        ];

        $opts = [];
        foreach ($options as $k => $v) {
            $opts[] = sprintf('%s="%s"', $k, $v);
        }

        return 'WWW-Authenticate: Digest ' . implode(',', $opts);
    }
}
