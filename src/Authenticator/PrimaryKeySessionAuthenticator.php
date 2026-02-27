<?php
declare(strict_types=1);

namespace Authentication\Authenticator;

use ArrayAccess;
use Authentication\Identifier\IdentifierFactory;
use Authentication\Identifier\IdentifierInterface;
use Cake\Http\Exception\UnauthorizedException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Session Authenticator with only ID
 *
 * This authenticator stores only the user's primary key in the session,
 * and looks up the full user record from the database on each request.
 *
 * By default, it uses a TokenIdentifier configured to look up users by
 * their `id` field. This works out of the box for most applications:
 *
 * ```php
 * $service->loadAuthenticator('Authentication.PrimaryKeySession');
 * ```
 *
 * You can customize the identifier configuration if needed:
 *
 * ```php
 * $service->loadAuthenticator('Authentication.PrimaryKeySession', [
 *     'identifier' => [
 *         'className' => 'Authentication.Token',
 *         'tokenField' => 'uuid',
 *         'dataField' => 'key',
 *         'resolver' => [
 *             'className' => 'Authentication.Orm',
 *             'userModel' => 'Members',
 *         ],
 *     ],
 * ]);
 * ```
 */
class PrimaryKeySessionAuthenticator extends SessionAuthenticator
{
    /**
     * Default config for this object.
     *
     * - `identifierKey` The key used when passing the ID to the identifier.
     * - `idField` The field on the user entity that contains the primary key.
     *
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'fields' => [],
        'sessionKey' => 'Auth',
        'impersonateSessionKey' => 'AuthImpersonate',
        'identityAttribute' => 'identity',
        'identifierKey' => 'key',
        'idField' => 'id',
    ];

    /**
     * Gets the identifier.
     *
     * If no identifier was explicitly configured, creates a default TokenIdentifier
     * configured to look up users by their primary key (`id` field).
     *
     * @return \Authentication\Identifier\IdentifierInterface
     */
    public function getIdentifier(): IdentifierInterface
    {
        if ($this->_identifier === null) {
            $this->_identifier = IdentifierFactory::create([
                'className' => 'Authentication.Token',
                'tokenField' => $this->getConfig('idField'),
                'dataField' => $this->getConfig('identifierKey'),
            ]);
        }

        return $this->_identifier;
    }

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

        $userId = $session->read($sessionKey);
        if (!$userId) {
            return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND);
        }

        $user = $this->getIdentifier()->identify([$this->getConfig('identifierKey') => $userId]);
        if (!$user) {
            return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND);
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
            $session->write($sessionKey, $identity[$this->getConfig('idField')]);
        }

        return [
            'request' => $request,
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
        $session->write($impersonateSessionKey, $impersonator[$this->getConfig('idField')]);
        $session->write($sessionKey, $impersonated[$this->getConfig('idField')]);

        return [
            'request' => $request,
            'response' => $response,
        ];
    }
}
