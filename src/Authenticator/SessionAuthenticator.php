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
 * @license       https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Authenticator;

use ArrayAccess;
use ArrayObject;
use Authentication\Identifier\IdentifierInterface;
use Cake\Http\Exception\UnauthorizedException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Session Authenticator
 */
class SessionAuthenticator extends AbstractAuthenticator implements PersistenceInterface, ImpersonationInterface
{
    /**
     * Default config for this object.
     * - `fields` The fields to use to verify a user by.
     * - `sessionKey` Session key.
     * - `identify` Whether or not to identify user data stored in a session. This is
     *   useful if you want to remotely end sessions that have a different password stored,
     *   or if your identification logic needs additional conditions before a user can login.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'fields' => [
            IdentifierInterface::CREDENTIAL_USERNAME => 'username',
        ],
        'sessionKey' => 'Auth',
        'impersonateSessionKey' => 'AuthImpersonate',
        'identify' => false,
        'identityAttribute' => 'identity',
    ];

    /**
     * Authenticate a user using session data.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request to authenticate with.
     * @return \Authentication\Authenticator\ResultInterface
     */
    public function authenticate(ServerRequestInterface $request): ResultInterface
    {
        $sessionKey = $this->getConfig('sessionKey');
        /** @var \Cake\Http\Session $session */
        $session = $request->getAttribute('session');
        $user = $session->read($sessionKey);

        if (empty($user)) {
            return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND);
        }

        if ($this->getConfig('identify') === true) {
            $credentials = [];
            foreach ($this->getConfig('fields') as $key => $field) {
                $credentials[$key] = $user[$field];
            }
            $user = $this->_identifier->identify($credentials);

            if (empty($user)) {
                return new Result(null, Result::FAILURE_CREDENTIALS_INVALID);
            }
        }

        if (!($user instanceof ArrayAccess)) {
            $user = new ArrayObject($user);
        }

        return new Result($user, Result::SUCCESS);
    }

    /**
     * @inheritDoc
     */
    public function persistIdentity(ServerRequestInterface $request, ResponseInterface $response, $identity): array
    {
        $sessionKey = $this->getConfig('sessionKey');
        /** @var \Cake\Http\Session $session */
        $session = $request->getAttribute('session');

        if (!$session->check($sessionKey)) {
            $session->renew();
            $session->write($sessionKey, $identity);
        }

        return [
            'request' => $request,
            'response' => $response,
        ];
    }

    /**
     * @inheritDoc
     */
    public function clearIdentity(ServerRequestInterface $request, ResponseInterface $response): array
    {
        $sessionKey = $this->getConfig('sessionKey');
        /** @var \Cake\Http\Session $session */
        $session = $request->getAttribute('session');
        $session->delete($sessionKey);
        $session->renew();

        return [
            'request' => $request->withoutAttribute($this->getConfig('identityAttribute')),
            'response' => $response,
        ];
    }

    /**
     * Impersonates a user
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request
     * @param \Psr\Http\Message\ResponseInterface $response The response
     * @param \ArrayAccess $impersonator User who impersonates
     * @param \ArrayAccess $impersonated User impersonated
     * @return array
     */
    public function impersonate(
        ServerRequestInterface $request,
        ResponseInterface $response,
        \ArrayAccess $impersonator,
        \ArrayAccess $impersonated
    ): array {
        $sessionKey = $this->getConfig('sessionKey');
        $impersonateSessionKey = $this->getConfig('impersonateSessionKey');
        /** @var \Cake\Http\Session $session */
        $session = $request->getAttribute('session');
        if ($session->check($impersonateSessionKey)) {
            throw new UnauthorizedException(
                'You are impersonating a user already. ' .
                'Stop the current impersonation before impersonating another user.'
            );
        }
        $session->write($impersonateSessionKey, $impersonator);
        $session->write($sessionKey, $impersonated);
        $this->setConfig('identify', true);

        return [
            'request' => $request,
            'response' => $response,
        ];
    }

    /**
     * Stops impersonation
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request
     * @param \Psr\Http\Message\ResponseInterface $response The response
     * @return array
     */
    public function stopImpersonating(ServerRequestInterface $request, ResponseInterface $response): array
    {
        $sessionKey = $this->getConfig('sessionKey');
        $impersonateSessionKey = $this->getConfig('impersonateSessionKey');
        /** @var \Cake\Http\Session $session */
        $session = $request->getAttribute('session');
        if ($session->check($impersonateSessionKey)) {
            $identity = $session->read($impersonateSessionKey);
            $session->delete($impersonateSessionKey);
            $session->write($sessionKey, $identity);
            $this->setConfig('identify', true);
        }

        return [
            'request' => $request,
            'response' => $response,
        ];
    }

    /**
     * Returns true if impersonation is being done
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request
     * @return bool
     */
    public function isImpersonating(ServerRequestInterface $request): bool
    {
        $impersonateSessionKey = $this->getConfig('impersonateSessionKey');
        /** @var \Cake\Http\Session $session */
        $session = $request->getAttribute('session');

        return $session->check($impersonateSessionKey);
    }
}
