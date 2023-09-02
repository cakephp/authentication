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
namespace Authentication\Authenticator;

use Authentication\Identifier\TokenIdentifier;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Token Authenticator
 *
 * Authenticates an identity based on a token in a query param or the header.
 */
class TokenAuthenticator extends AbstractAuthenticator implements StatelessInterface
{
    /**
     * @inheritDoc
     */
    protected array $_defaultConfig = [
        'header' => null,
        'queryParam' => null,
        'tokenPrefix' => null,
    ];

    /**
     * Checks if the token is in the headers or a request parameter
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request that contains login information.
     * @return string|null
     */
    protected function getToken(ServerRequestInterface $request): ?string
    {
        $token = $this->getTokenFromHeader($request, $this->getConfig('header'));
        if ($token === null) {
            $token = $this->getTokenFromQuery($request, $this->getConfig('queryParam'));
        }

        $prefix = $this->getConfig('tokenPrefix');
        if ($prefix !== null && is_string($token)) {
            return $this->stripTokenPrefix($token, $prefix);
        }

        return $token;
    }

    /**
     * Strips a prefix from a token
     *
     * @param string $token Token string
     * @param string $prefix Prefix to strip
     * @return string
     */
    protected function stripTokenPrefix(string $token, string $prefix): string
    {
        return str_ireplace($prefix . ' ', '', $token);
    }

    /**
     * Gets the token from the request headers
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request that contains login information.
     * @param string|null $headerLine Header name
     * @return string|null
     */
    protected function getTokenFromHeader(ServerRequestInterface $request, ?string $headerLine): ?string
    {
        if (!empty($headerLine)) {
            $header = $request->getHeaderLine($headerLine);
            if (!empty($header)) {
                return $header;
            }
        }

        return null;
    }

    /**
     * Gets the token from the request query
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request that contains login information.
     * @param string $queryParam Request query parameter name
     * @return string|null
     */
    protected function getTokenFromQuery(ServerRequestInterface $request, ?string $queryParam): ?string
    {
        if (empty($queryParam)) {
            return null;
        }

        $queryParams = $request->getQueryParams();

        return $queryParams[$queryParam] ?? null;
    }

    /**
     * Authenticates the identity by token contained in a request.
     * Token could be passed as query using `config.queryParam` or as header param using `config.header`. Token
     * prefix will be stripped if `config.tokenPrefix` is set. Will return false if no token is provided or if the
     * scope conditions have not been met.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request that contains login information.
     * @return \Authentication\Authenticator\ResultInterface
     */
    public function authenticate(ServerRequestInterface $request): ResultInterface
    {
        $token = $this->getToken($request);
        if ($token === null) {
            return new Result(null, Result::FAILURE_CREDENTIALS_MISSING);
        }

        $user = $this->_identifier->identify([
            TokenIdentifier::CREDENTIAL_TOKEN => $token,
        ]);

        if (empty($user)) {
            return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND, $this->_identifier->getErrors());
        }

        return new Result($user, Result::SUCCESS);
    }

    /**
     * No-op method.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request A request object.
     * @return void
     */
    public function unauthorizedChallenge(ServerRequestInterface $request): void
    {
    }
}
