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
     * - `sessionKey` Session key.
     * - `impersonateSessionKey` Session key for impersonation.
     * - `identityAttribute` Request attribute for the identity.
     *
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'fields' => [],
        'sessionKey' => 'Auth',
        'impersonateSessionKey' => 'AuthImpersonate',
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

        if (!$user) {
            return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND);
        }

        if (!($user instanceof ArrayAccess)) {
            $user = new ArrayObject($user);
        }

        return new Result($user, Result::SUCCESS);
    }

    /**
     * @inheritDoc
     */
    public function persistIdentity(
        ServerRequestInterface $request,
        ResponseInterface $response,
        ArrayAccess|array $identity,
    ): array {
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
     * @inheritDoc
     */
    public function impersonate(
        ServerRequestInterface $request,
        ResponseInterface $response,
        ArrayAccess $impersonator,
        ArrayAccess $impersonated,
    ): array {
        $sessionKey = $this->getConfig('sessionKey');
        $impersonateSessionKey = $this->getConfig('impersonateSessionKey');
        /** @var \Cake\Http\Session $session */
        $session = $request->getAttribute('session');
        if ($session->check($impersonateSessionKey)) {
            throw new UnauthorizedException(
                'You are impersonating a user already. ' .
                'Stop the current impersonation before impersonating another user.',
            );
        }
        $session->write($impersonateSessionKey, $impersonator);
        $session->write($sessionKey, $impersonated);

        return [
            'request' => $request,
            'response' => $response,
        ];
    }

    /**
     * @inheritDoc
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
