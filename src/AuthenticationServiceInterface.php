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
namespace Authentication;

use Authentication\Authenticator\AuthenticatorInterface;
use Authentication\Authenticator\PersistenceInterface;
use Authentication\Authenticator\ResultInterface;
use Authentication\Identifier\IdentifierInterface;
use Psr\Http\Message\ServerRequestInterface;

interface AuthenticationServiceInterface extends PersistenceInterface
{
    /**
     * Loads an authenticator.
     *
     * @param string $name Name or class name.
     * @param array $config Authenticator configuration.
     * @return \Authentication\Authenticator\AuthenticatorInterface
     */
    public function loadAuthenticator(string $name, array $config = []): AuthenticatorInterface;

    /**
     * Loads an identifier.
     *
     * @param string $name Name or class name.
     * @param array $config Identifier configuration.
     * @return \Authentication\Identifier\IdentifierInterface
     */
    public function loadIdentifier(string $name, array $config = []): IdentifierInterface;

    /**
     * Authenticate the request against the configured authentication adapters.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request.
     * @return \Authentication\Authenticator\ResultInterface The result object. If none of the adapters was a success
     *  the last failed result is returned.
     */
    public function authenticate(ServerRequestInterface $request): ResultInterface;

    /**
     * Gets an identity object or null if identity has not been resolved.
     *
     * @return \Authentication\IdentityInterface|null
     */
    public function getIdentity(): ?IdentityInterface;

    /**
     * Gets the successful authenticator instance if one was successful after calling authenticate
     *
     * @return \Authentication\Authenticator\AuthenticatorInterface|null
     */
    public function getAuthenticationProvider(): ?AuthenticatorInterface;

    /**
     * Gets the result of the last authenticate() call.
     *
     * @return \Authentication\Authenticator\ResultInterface|null Authentication result interface
     */
    public function getResult(): ?ResultInterface;

    /**
     * Return the name of the identity attribute.
     *
     * @return string
     */
    public function getIdentityAttribute(): string;

    /**
     * Return the URL to redirect unauthenticated users to.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request
     * @return string|null
     */
    public function getUnauthenticatedRedirectUrl(ServerRequestInterface $request): ?string;

    /**
     * Return the URL that an authenticated user came from or null.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request
     * @return string|null
     */
    public function getLoginRedirect(ServerRequestInterface $request): ?string;
}
