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
use Cake\Http\Cookie\Cookie;
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
            'expire' => null,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httpOnly' => false,
            'value' => ''
        ];

        parent::__construct($identifiers, $config);
    }

    /**
     * Checks the CakePHP Version by looking for the cookie implementation
     *
     * @return void
     */
    protected function _checkCakeVersion()
    {
        if (!class_exists('Cake\Http\Cookie\Cookie')) {
            throw new RuntimeException('You must use the CakePHP `3.next` branch or wait until CakePHP version 3.5 is released to use the CookieAuthenticator');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function authenticate(ServerRequestInterface $request, ResponseInterface $response)
    {
        $cookies = $request->getCookieParams();
        $cookieName = $this->getConfig('cookie.name');

        if (!isset($cookies[$cookieName])) {
            return new Result(null, Result::FAILURE_CREDENTIALS_NOT_FOUND, [
                'Login credentials not found'
            ]);
        }
        $userData = $cookies[$cookieName];

        $user = $this->identifiers()->identify($userData);

        if (empty($user)) {
            return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND, $this->identifiers()->getErrors());
        }

        return new Result($user, Result::SUCCESS);
    }

    /**
     * {@inheritDoc}
     */
    public function persistIdentity(ServerRequestInterface $request, ResponseInterface $response, $identity)
    {
        $data = $this->getConfig('cookie');
        $data['value'] = $identity;

        $cookie = new Cookie(
            $data['name'],
            $data['value'],
            $data['expire'],
            $data['path'],
            $data['domain'],
            $data['secure'],
            $data['httpOnly']
        );

        return [
            'request' => $request,
            'response' => $response->withAddedHeader('Set-Cookie', $cookie->toHeaderValue())
        ];
    }

    /**
     * Clears the identity data
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request object.
     * @param \Cake\Http\Response $response The response object.
     * @return array Returns an array containing the request and response object
     */
    public function clearIdentity(ServerRequestInterface $request, ResponseInterface $response)
    {
        $cookie = $this->getConfig('cookie');
        $cookie['expire'] = strtotime('-1 year');

        return [
            'request' => $request,
            'response' => $response->withCookie(
                $cookie['name'],
                $cookie
            )
        ];
    }
}
