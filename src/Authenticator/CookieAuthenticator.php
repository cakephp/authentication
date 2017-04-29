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
namespace Authentication\Authenticator;

use Authentication\Identifier\IdentifierCollection;
use Authentication\Result;
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

    /**
     * {@inheritDoc}
     */
    public function __construct(IdentifierCollection $identifiers, array $config = [])
    {
        $this->_checkCakeVersion();

        $this->_defaultConfig['cookie'] = [
            'name' => 'CookieAuth',
            'dataPath' => null,
            'expiresAt' => null,
            'path' => '',
            'domain' => '',
            'secure' => false,
            'httpOnly' => false
        ];

        parent::__construct($identifiers, $config);
    }

    /**
     * Checks the CakePHP Version by looking for the cookie implementation
     */
    protected function _checkCakeVersion()
    {
        if (!class_exists('Cake\Http\Cookie\Cookie')) {
            throw new RuntimeException('You must use CakePHP the `3.next` branch or wait version 3.5 to use the CookieAuthenticator');
        }
    }

    /**
     * Checks the cookie for authentication data
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request that contains login information.
     * @return bool False if the fields have not been supplied. True if they exist.
     */
    protected function _getDataFromCookie(ServerRequestInterface $request)
    {
        $cookieName = $this->getConfig('cookie.name');
        $cookies = RequestCookies::createFromRequest($request);

        if (!$cookies->has($cookieName)) {
            return false;
        }

        $cookie = $cookies->get($cookieName);

        return $cookie->read($this->getConfig('cookie.dataPath'));
    }

    /**
     * {@inheritDoc}
     */
    public function authenticate(ServerRequestInterface $request, ResponseInterface $response)
    {
        $user = $this->_getDataFromCookie($request);
        if (empty($data)) {
            return new Result(null, Result::FAILURE_CREDENTIALS_NOT_FOUND, [
                'Login credentials not found'
            ]);
        }

        $user = $this->identifiers()->identify($user);

        if (empty($user)) {
            return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND, $this->identifiers()->getErrors());
        }

        return new Result($user, Result::SUCCESS);
    }

    /**
     * Builds a cookie object
     *
     * @return \Cake\Http\Cookie
     */
    protected function _buildCookie()
    {
        $config = $this->getConfig('cookie');

        $cookie = new Cookie(
            $config['name']
        );

        if ($config['expires'] instanceof DateTime) {
            $cookie->expiresAt($config['expires']);
        } else {
            $cookie->willNeverExpire();
        }

        return $cookie;
    }

    /**
     * {@inheritDoc}
     */
    public function persistIdentity(ServerRequestInterface $request, ResponseInterface $response, $identity)
    {
        $cookie = $this->_buildCookie();
        $cookie->setValue($identity);

        $cookies = $response->getCookies();
        $cookies->add($cookie);

        return [
            'request' => $request,
            'response' => $response->withCookies($cookies)
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function clearIdentity(ServerRequestInterface $request, ResponseInterface $response)
    {
        $cookies = $response->getCookies();
        $cookie = $cookies->get($this->getConfig('cookies')['name']);
        $cookie->willBeDeleted();
        $cookies->add($cookie);

        return [
            'request' => $request,
            'response' => $response->withCookies($cookies)
        ];
    }
}
