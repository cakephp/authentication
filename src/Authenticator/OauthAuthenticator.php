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
use Cake\Http\ResponseEmitter;
use Cake\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SocialConnect\Auth\Service;
use SocialConnect\Common\Http\Client\Curl;
use SocialConnect\Provider\Session\Session;

/**
 * Oauth2 Authenticator
 *
 * Authenticates an identity based on a response from an Oauth provider.
 */
class OauthAuthenticator extends AbstractAuthenticator
{

    /**
     * Http Client
     *
     * @var \SocialConnect\Common\Http\Client\Curl
     */
    protected $_httpClient;

    /**
     * Service Class
     *
     * @var \SocialConnect\Auth\Service
     */
    protected $_authService;

    /**
     * {@inheritDoc}
     */
    protected $_defaultConfig = [
        'fields' => [
            'username' => 'username'
        ]
    ];

    /**
     * {@inheritDoc}
     */
    public function __construct(IdentifierCollection $identifiers, array $config = [])
    {
        parent::__construct($identifiers, $config);

        $this->_httpClient = new Curl();

        $this->_authService = new Service(
            $this->_httpClient,
            new Session(),
            $this->getConfig('Oauth')
        );
    }

    /**
     * Authenticates the identity contained in a request.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request that contains login information.
     * @param \Psr\Http\Message\ResponseInterface $response Response object.
     * @return \Authentication\ResultInterface
     */
    public function authenticate(ServerRequestInterface $request, ResponseInterface $response)
    {
        // TODO Improve Path comparison
        if (strcasecmp(Router::url($request->getUri()->getPath(), true), $this->getConfig('loginUrl')) === 0) {
            $this->_redirect($response);
        }

        // TODO Don't hardcode the provider
        if (strcasecmp(Router::url($request->getUri()->getPath(), true), $this->getConfig('Oauth.redirectUri') . '/github/') === 0) {
            $identity = $this->_getIdentity($request->getQueryParams());

            if ($this->_checkOauthIdentity((array)$identity) === false) {
                return new Result(null, Result::FAILURE_CREDENTIALS_NOT_FOUND, [
                    'Login credentials not found.'
                ]);
            }

            $user = $this->identifiers()->identify((array)$identity);

            if (empty($user)) {
                return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND, $this->identifiers()->getErrors());
            }

            return new Result($user, Result::SUCCESS);
        }

        return new Result(null, Result::FAILURE_OTHER, [
            'Login URL or Redirect URL does not macth.'
        ]);
    }

    /**
     * Checks the fields to ensure they are supplied.
     *
     * @param array $identity The identity returned by the provider.
     * @return bool False if the fields have not been supplied. True if they exist.
     */
    protected function _checkOauthIdentity($identity)
    {
        foreach ($this->getConfig('fields') as $field) {
            if (!isset($identity[$field])) {
                return false;
            }

            $value = $identity[$field];
            if (empty($value) || !is_string($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Redirect the user to the provider
     *
     * @param \Psr\Http\Message\ResponseInterface $response Response object.
     * @return void
     */
    protected function _redirect(ResponseInterface $response)
    {
        // TODO Don't hardcode the provider
        $providerName = 'github';
        $provider = $this->_authService->getProvider($providerName);
        $response = $response
            ->withStatus(302)
            ->withLocation($provider->makeAuthUrl());

        $emitter = new ResponseEmitter();
        $emitter->emit($response);

        exit;
    }

    /**
     * Fetch the identity from the provider
     *
     * @param string $queryParameters Query Parameter
     * @return \SocialConnect\Common\Entity\User User entity
     */
    protected function _getIdentity($queryParameters)
    {
        // TODO Don't hardcode the provider
        $providerName = 'github';
        $provider = $this->_authService->getProvider($providerName);
        $accessToken = $provider->getAccessTokenByRequestParameters($queryParameters);

        return $provider->getIdentity($accessToken);
    }
}
