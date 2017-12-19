<?php
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

use Authentication\Identifier\IdentifierInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Token Authenticator
 *
 * Authenticates an identity based on a token in a query param or the header.
 */
class TokenAuthenticator extends AbstractAuthenticator implements StatelessInterface
{

    /**
     * {@inheritDoc}
     */
    protected $_defaultConfig = [
        'header' => null,
        'queryParam' => null,
        'tokenPrefix' => null
    ];

    /**
     * Checks if the token is in the headers or a request parameter
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request that contains login information.
     * @return string|null
     */
    protected function getToken(ServerRequestInterface $request)
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
    protected function stripTokenPrefix($token, $prefix)
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
    protected function getTokenFromHeader(ServerRequestInterface $request, $headerLine)
    {
        if ($headerLine !== null) {
            $header = $request->getHeaderLine($headerLine);
            if ($header !== '' && $header !== null) {
                return $header;
            }
        }

        return null;
    }

    /**
     * Gets the token from the request headers
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request that contains login information.
     * @param string $queryParam Request query parameter name
     * @return string|null
     */
    protected function getTokenFromQuery(ServerRequestInterface $request, $queryParam)
    {
        $queryParams = $request->getQueryParams();

        if (!array_key_exists($queryParam, $queryParams)) {
            return null;
        }

        return $queryParams[$queryParam];
    }

    /**
     * Authenticates the identity contained in a request. Will use the `config.userModel`, and `config.fields`
     * to find POST data that is used to find a matching record in the `config.userModel`. Will return false if
     * there is no post data, either username or password is missing, or if the scope conditions have not been met.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request that contains login information.
     * @param \Psr\Http\Message\ResponseInterface $response Unused response object.
     * @return \Authentication\Authenticator\ResultInterface
     */
    public function authenticate(ServerRequestInterface $request, ResponseInterface $response)
    {
        $token = $this->getToken($request);
        if ($token === null || $token === '') {
            return new Result(null, Result::FAILURE_CREDENTIALS_MISSING);
        }

        $identity = $this->_identifier->identify([IdentifierInterface::CREDENTIAL_TOKEN => $token]);

        if (empty($identity)) {
            return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND, $this->_identifier->getErrors());
        }

        return new Result($identity, Result::SUCCESS);
    }

    /**
     * No-op method.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request A request object.
     * @return void
     */
    public function unauthorizedChallenge(ServerRequestInterface $request)
    {
    }
}
