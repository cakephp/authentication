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
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use SocialConnect\Auth\Service;
use SocialConnect\Common\Http\Client\Curl;
use SocialConnect\Provider\Session\Session;

/**
 * Oauth Authenticator
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
     * The current provider
     *
     * @var string
     */
    protected $_provider;

    /**
     * {@inheritDoc}
     */
    protected $_defaultConfig = [
        'oauth' => null,
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

        $this->_checkOauthConfig();

        $this->_httpClient = new Curl();
        $this->_authService = new Service(
            $this->_httpClient,
            new Session(),
            $this->getConfig('oauth')
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
        if ($this->_isLoginUrl($request) === true) {
            $this->_redirect($response, $this->_provider);
        }

        if ($this->_isOauthRedirectUrl($request) === true) {
            try {
                $identity = $this->_getIdentity($request->getQueryParams());
            } catch (Exception $e) {
                return new Result(null, Result::FAILURE_OTHER, [
                    $e->getMessage()
                ]);
            }

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
     * Checks the Oauth config
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @return void
     */
    protected function _checkOauthConfig()
    {
        if (empty($this->_config['oauth'])) {
            throw new RuntimeException('You must pass the `oauth` option.');
        }
        if (empty($this->_config['redirectUrl'])) {
            throw new RuntimeException('You must pass the `redirectUrl` option.');
        }
        if (empty($this->_config['loginUrl'])) {
            throw new RuntimeException('You must pass the `loginUrl` option.');
        }
    }

    /**
     * Checks the requests if it is the configured redirect action
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The current request.
     * @return bool
     */
    protected function _isOauthRedirectUrl(ServerRequestInterface $request)
    {
        $redirectUrl = $this->getConfig('redirectUrl');
        $this->_provider = implode('', $request->getParam('pass'));

        if (empty($this->_provider) || !array_key_exists($this->_provider, $this->getConfig('oauth.provider'))) {
            return false;
        }

        if (!empty($redirectUrl)) {
            if (is_array($redirectUrl)) {
                $redirectUrl = Router::url($redirectUrl);
            }
            $redirectUrl = $redirectUrl . '/' . $this->_provider . '/';

            return strcasecmp($request->getUri()->getPath(), $redirectUrl) === 0;
        }

        return false;
    }

    /**
     * Checks the requests if it is the configured login action
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The current request.
     * @return bool
     */
    protected function _isLoginUrl(ServerRequestInterface $request)
    {
        $loginUrl = $this->getConfig('loginUrl');
        $this->_provider = $request->getQuery('provider');

        if (empty($this->_provider) || !array_key_exists($this->_provider, $this->getConfig('oauth.provider'))) {
            return false;
        }

        if (!empty($loginUrl)) {
            if (is_array($loginUrl)) {
                $loginUrl = Router::url($loginUrl);
            }

            return strcasecmp($request->getUri()->getPath(), $loginUrl) === 0;
        }

        return false;
    }

    /**
     * Check the fields to ensure they are supplied
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
     * Fetch the identity from the provider
     *
     * @param string $queryParameters Query Parameter
     * @return \SocialConnect\Common\Entity\User User entity
     */
    protected function _getIdentity($queryParameters)
    {
        $provider = $this->_authService->getProvider($this->_provider);
        $accessToken = $provider->getAccessTokenByRequestParameters($queryParameters);

        return $provider->getIdentity($accessToken);
    }

    /**
     * Redirect the user to the provider
     *
     * @param \Psr\Http\Message\ResponseInterface $response Response object.
     * @param string $providerIdentifier Name of the provider
     * @return void
     */
    protected function _redirect(ResponseInterface $response, $providerIdentifier)
    {
        $provider = $this->_authService->getProvider($providerIdentifier);

        $response = $response
            ->withStatus(302)
            ->withLocation($provider->makeAuthUrl());

        $emitter = new ResponseEmitter();
        $emitter->emit($response);

        exit;
    }
}
