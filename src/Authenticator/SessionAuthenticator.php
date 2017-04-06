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
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Authenticator;

use ArrayAccess;
use ArrayObject;
use Authentication\Identifier\IdentifierCollection;
use Authentication\Result;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Session Authenticator
 */
class SessionAuthenticator extends AbstractAuthenticator implements PersistenceInterface
{

    /**
     * Constructor
     *
     * @param array $identifiers Array of config to use.
     * @param array $config Configuration settings.
     */
    public function __construct(IdentifierCollection $identifiers, array $config = [])
    {
        $this->_defaultConfig['sessionKey'] = 'Auth';
        $this->_defaultConfig['identify'] = false;
        parent::__construct($identifiers, $config);
    }

    /**
     * Authenticate a user using session data.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request to authenticate with.
     * @param \Psr\Http\Message\ResponseInterface $response The response to add headers to.
     * @return \Authentication\ResultInterface
     */
    public function authenticate(ServerRequestInterface $request, ResponseInterface $response)
    {
        $sessionKey = $this->getConfig('sessionKey');
        $session = $request->getAttribute('session');
        $user = $session->read($sessionKey);

        if (empty($user)) {
            return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND);
        }

        if ($this->getConfig('identify') === true) {
            $user = $this->identifiers()->identify([
                'username' => $user[$this->getConfig('fields')['username']],
                'password' => $user[$this->getConfig('fields')['password']]
            ]);

            if (empty($user)) {
                return new Result(null, Result::FAILURE_CREDENTIAL_INVALID);
            }
        }

        if (!$user instanceof ArrayAccess) {
            $user = new ArrayObject($user);
        }

        return new Result($user, Result::SUCCESS);
    }

    /**
     * {@inheritDoc}
     */
    public function persistIdentity(ServerRequestInterface $request, ResponseInterface $response, $identity)
    {
        $sessionKey = $this->getConfig('sessionKey');
        $request->getAttribute('session')->write($sessionKey, $identity);

        return [
            'request' => $request,
            'response' => $response,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function clearIdentity(ServerRequestInterface $request, ResponseInterface $response)
    {
        $sessionKey = $this->getConfig('sessionKey');
        $request->getAttribute('session')->delete($sessionKey);

        return [
            'request' => $request->withoutAttribute('identity'),
            'response' => $response
        ];
    }
}
