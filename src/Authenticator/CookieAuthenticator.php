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
use Cake\Utility\CookieCryptTrait;
use Cake\Utility\Security;
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

    use CookieCryptTrait;

    /**
     * {@inheritDoc}
     */
    protected $_defaultConfig = [
        'rememberMeField' => 'remember_me',
        'encryptionKey' => null,
        'encryptionType' => 'aes',
        'encrypt' => true,
        'cookie' => [
            'name' => 'CookieAuth',
            'expire' => null,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httpOnly' => false,
            'value' => ''
        ]
    ];

    /**
     * {@inheritDoc}
     */
    public function __construct(IdentifierCollection $identifiers, array $config = [])
    {
        $this->_checkCakeVersion();

        parent::__construct($identifiers, $config);
    }

    /**
     * Gets the cookie encryption key
     *
     * @return string
     */
    protected function _getCookieEncryptionKey()
    {
        $key = $this->getConfig('encryptionKey');
        if (!empty($key)) {
            return $key;
        }

        return Security::getSalt();
    }

    /**
     * Checks the CakePHP Version by looking for the cookie implementation
     *
     * @return void
     */
    protected function _checkCakeVersion()
    {
        if (!class_exists('Cake\Http\Cookie\Cookie')) {
            throw new RuntimeException('Install CakePHP version >=3.5.0 to use the `CookieAuthenticator`.');
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
        if ($this->getConfig('encrypt')) {
            $userData = $this->_decrypt($userData, $this->getConfig('encryptionType'));
        }

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
        $field = $this->getConfig('rememberMeField');
        $data = $request->getParsedBody();

        if (!is_array($data) || empty($data[$field])) {
            return [
                'request' => $request,
                'response' => $response
            ];
        }

        $data = $this->getConfig('cookie');
        $data['value'] = $this->getConfig('encrypt')
            ? $this->_encrypt((array)$identity, $this->getConfig('encryptionType'))
            : (array)$identity;

        $cookie = new \Cake\Http\Cookie\Cookie(
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
     * {@inheritDoc}
     */
    public function clearIdentity(ServerRequestInterface $request, ResponseInterface $response)
    {
        $cookie = (new \Cake\Http\Cookie\Cookie($this->getConfig('cookie.name')))->withExpired();

        return [
            'request' => $request,
            'response' => $response->withAddedHeader('Set-Cookie', $cookie->toHeaderValue())
        ];
    }
}
