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

use Authentication\Identifier\IdentifierInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * HttpBasic Authenticator
 *
 * Provides Basic HTTP authentication support.
 */
class HttpBasicAuthenticator extends AbstractAuthenticator implements StatelessInterface
{
    /**
     * Default config for this object.
     * - `fields` The fields to use to identify a user by.
     * - `skipChallenge` If set to `true` then challenge exception will not be
     *   generated in case of authentication failure. Defaults to `false`.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'fields' => [
            IdentifierInterface::CREDENTIAL_USERNAME => 'username',
            IdentifierInterface::CREDENTIAL_PASSWORD => 'password',
        ],
        'skipChallenge' => false,
    ];

    /**
     * Authenticate a user using HTTP auth. Will use the configured User model and attempt a
     * login using HTTP auth.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request to authenticate with.
     * @return \Authentication\Authenticator\ResultInterface
     */
    public function authenticate(ServerRequestInterface $request): ResultInterface
    {
        $server = $request->getServerParams();
        $username = $server['PHP_AUTH_USER'] ?? '';
        $password = $server['PHP_AUTH_PW'] ?? '';

        if ($username === '' || $password === '') {
            return new Result(null, Result::FAILURE_CREDENTIALS_MISSING);
        }

        $user = $this->_identifier->identify([
            IdentifierInterface::CREDENTIAL_USERNAME => $username,
            IdentifierInterface::CREDENTIAL_PASSWORD => $password,
        ]);

        if ($user === null) {
            return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND);
        }

        return new Result($user, Result::SUCCESS);
    }

    /**
     * Create a challenge exception for basic auth challenge.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request A request object.
     * @return void
     * @throws \Authentication\Authenticator\AuthenticationRequiredException
     */
    public function unauthorizedChallenge(ServerRequestInterface $request): void
    {
        if ($this->getConfig('skipChallenge')) {
            return;
        }

        throw new AuthenticationRequiredException($this->loginHeaders($request), '');
    }

    /**
     * Generate the login headers
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request object.
     * @return array Headers for logging in.
     */
    protected function loginHeaders(ServerRequestInterface $request): array
    {
        $server = $request->getServerParams();
        $realm = $this->getConfig('realm') ?: $server['SERVER_NAME'];

        return ['WWW-Authenticate' => sprintf('Basic realm="%s"', $realm)];
    }
}
