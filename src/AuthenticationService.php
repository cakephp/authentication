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
namespace Authentication;

use Authentication\Authenticator\AuthenticatorCollection;
use Authentication\Authenticator\PersistenceInterface;
use Authentication\Authenticator\StatelessInterface;
use Authentication\Identifier\IdentifierCollection;
use Cake\Core\InstanceConfigTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * Authentication Service
 */
class AuthenticationService implements AuthenticationServiceInterface
{
    use InstanceConfigTrait;

    /**
     * Authenticator collection
     *
     * @var \Authentication\Authenticator\AuthenticatorCollection|null
     */
    protected $_authenticators;

    /**
     * Identifier collection
     *
     * @var \Authentication\Identifier\IdentifierCollection|null
     */
    protected $_identifiers;

    /**
     * Authenticator that successfully authenticated the identity.
     *
     * @var \Authentication\Authenticator\AuthenticatorInterface|null
     */
    protected $_successfulAuthenticator;

    /**
     * Result of the last authenticate() call.
     *
     * @var \Authentication\Authenticator\ResultInterface|null
     */
    protected $_result;

    /**
     * Default configuration
     *
     * - `authenticators` - An array of authentication objects to use for authenticating users.
     *   You can configure multiple adapters and they will be checked sequentially
     *   when users are identified.
     * - `identifiers` - An array of identifiers. The identifiers are constructed by the service
     *   and then passed to the authenticators that will pass the credentials to them and get the
     *   user data.
     * - `identityClass` - The class name of identity or a callable identity builder.
     *
     *   ```
     *   $service = new AuthenticationService([
     *      'authenticators' => [
     *          'Authentication.Form
     *      ],
     *      'identifiers' => [
     *          'Authentication.Password'
     *      ]
     *   ]);
     *   ```
     * - `identityAttribute` - The request attribute to store the identity in.
     * - `unauthenticatedRedirect` - The URL to redirect unauthenticated errors to. See
     *    AuthenticationComponent::allowUnauthenticated()
     * - `queryParam` - Set to a string to have unauthenticated redirects contain a `redirect` query string
     *   parameter with the previously blocked URL.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'authenticators' => [],
        'identifiers' => [],
        'identityClass' => Identity::class,
        'identityAttribute' => 'identity',
        'queryParam' => null,
        'unauthorizedRedirect' => null,
    ];

    /**
     * Constructor
     *
     * @param array $config Configuration options.
     */
    public function __construct(array $config = [])
    {
        $this->setConfig($config);
    }

    /**
     * Access the identifier collection
     *
     * @return \Authentication\Identifier\IdentifierCollection
     */
    public function identifiers()
    {
        if (!$this->_identifiers) {
            $this->_identifiers = new IdentifierCollection($this->getConfig('identifiers'));
        }

        return $this->_identifiers;
    }

    /**
     * Access the authenticator collection
     *
     * @return \Authentication\Authenticator\AuthenticatorCollection
     */
    public function authenticators()
    {
        if (!$this->_authenticators) {
            $identifiers = $this->identifiers();
            $authenticators = $this->getConfig('authenticators');
            $this->_authenticators = new AuthenticatorCollection($identifiers, $authenticators);
        }

        return $this->_authenticators;
    }

    /**
     * Loads an authenticator.
     *
     * @param string $name Name or class name.
     * @param array $config Authenticator configuration.
     * @return \Authentication\Authenticator\AuthenticatorInterface
     */
    public function loadAuthenticator($name, array $config = [])
    {
        return $this->authenticators()->load($name, $config);
    }

    /**
     * Loads an identifier.
     *
     * @param string $name Name or class name.
     * @param array $config Identifier configuration.
     * @return \Authentication\Identifier\IdentifierInterface Identifier instance
     */
    public function loadIdentifier($name, array $config = [])
    {
        return $this->identifiers()->load($name, $config);
    }

    /**
     * {@inheritDoc}
     *
     * @throws \RuntimeException Throws a runtime exception when no authenticators are loaded.
     */
    public function authenticate(ServerRequestInterface $request, ResponseInterface $response)
    {
        if ($this->authenticators()->isEmpty()) {
            throw new RuntimeException(
                'No authenticators loaded. You need to load at least one authenticator.'
            );
        }

        $result = null;
        foreach ($this->authenticators() as $authenticator) {
            $result = $authenticator->authenticate($request, $response);
            if ($result->isValid()) {
                if (!($authenticator instanceof StatelessInterface)) {
                    $requestResponse = $this->persistIdentity($request, $response, $result->getData());
                    $request = $requestResponse['request'];
                    $response = $requestResponse['response'];
                }

                $this->_successfulAuthenticator = $authenticator;
                $this->_result = $result;

                return [
                    'result' => $result,
                    'request' => $request,
                    'response' => $response
                ];
            }

            if ($authenticator instanceof StatelessInterface) {
                $authenticator->unauthorizedChallenge($request);
            }
        }

        $this->_successfulAuthenticator = null;
        $this->_result = $result;

        return [
            'result' => $result,
            'request' => $request,
            'response' => $response
        ];
    }

    /**
     * Clears the identity from authenticators that store them and the request
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request.
     * @param \Psr\Http\Message\ResponseInterface $response The response.
     * @return array Return an array containing the request and response objects.
     */
    public function clearIdentity(ServerRequestInterface $request, ResponseInterface $response)
    {
        foreach ($this->authenticators() as $authenticator) {
            if ($authenticator instanceof PersistenceInterface) {
                $result = $authenticator->clearIdentity($request, $response);
                $request = $result['request'];
                $response = $result['response'];
            }
        }

        return [
            'request' => $request->withoutAttribute($this->getConfig('identityAttribute')),
            'response' => $response
        ];
    }

    /**
     * Sets identity data and persists it in the authenticators that support it.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request.
     * @param \Psr\Http\Message\ResponseInterface $response The response.
     * @param \ArrayAccess|array $identity Identity data.
     * @return array
     */
    public function persistIdentity(ServerRequestInterface $request, ResponseInterface $response, $identity)
    {
        foreach ($this->authenticators() as $authenticator) {
            if ($authenticator instanceof PersistenceInterface) {
                $result = $authenticator->persistIdentity($request, $response, $identity);
                $request = $result['request'];
                $response = $result['response'];
            }
        }

        if (!($identity instanceof IdentityInterface)) {
            $identity = $this->buildIdentity($identity);
        }

        return [
            'request' => $request->withAttribute($this->getConfig('identityAttribute'), $identity),
            'response' => $response
        ];
    }

    /**
     * Gets the successful authenticator instance if one was successful after calling authenticate
     *
     * @return \Authentication\Authenticator\AuthenticatorInterface|null
     */
    public function getAuthenticationProvider()
    {
        return $this->_successfulAuthenticator;
    }

    /**
     * Gets the result of the last authenticate() call.
     *
     * @return \Authentication\Authenticator\ResultInterface|null Authentication result interface
     */
    public function getResult()
    {
        return $this->_result;
    }

    /**
     * Gets an identity object
     *
     * @return null|\Authentication\IdentityInterface
     */
    public function getIdentity()
    {
        if ($this->_result === null || !$this->_result->isValid()) {
            return null;
        }

        $identity = $this->_result->getData();
        if (!($identity instanceof IdentityInterface)) {
            $identity = $this->buildIdentity($identity);
        }

        return $identity;
    }

    /**
     * Return the name of the identity attribute.
     *
     * @return string
     */
    public function getIdentityAttribute()
    {
        return $this->getConfig('identityAttribute');
    }

    /**
     * Builds the identity object
     *
     * @param \ArrayAccess|array $identityData Identity data
     * @return \Authentication\IdentityInterface
     */
    public function buildIdentity($identityData)
    {
        $class = $this->getConfig('identityClass');

        if (is_callable($class)) {
            $identity = $class($identityData);
        } else {
            $identity = new $class($identityData);
        }

        if (!($identity instanceof IdentityInterface)) {
            throw new RuntimeException(sprintf(
                'Object `%s` does not implement `%s`',
                get_class($identity),
                IdentityInterface::class
            ));
        }

        return $identity;
    }

    /**
     * Return the URL to redirect unauthenticated users to.
     *
     * If the `unauthenticaedRedirect` option is not set,
     * this method will return null.
     *
     * If the `queryParam` option is set a query parameter
     * will be appended with the denied URL path.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request
     * @return string|null
     */
    public function getUnauthenticatedRedirectUrl(ServerRequestInterface $request)
    {
        $param = $this->getConfig('queryParam');
        $target = $this->getConfig('unauthenticatedRedirect');
        if ($target === null) {
            return null;
        }
        if ($param === null) {
            return $target;
        }

        $uri = $request->getUri();
        if (property_exists($uri, 'base')) {
            $uri = $uri->withPath($uri->base . $uri->getPath());
        }
        $redirect = $uri->getPath();
        if ($uri->getQuery()) {
            $redirect .= '?' . $uri->getQuery();
        }
        $query = urlencode($param) . '=' . urlencode($redirect);

        $url = parse_url($target);
        if (isset($url['query']) && strlen($url['query'])) {
            $url['query'] .= '&' . $query;
        } else {
            $url['query'] = $query;
        }
        $fragment = isset($url['fragment']) ? '#' . $url['fragment'] : '';

        return $url['path'] . '?' . $url['query'] . $fragment;
    }
}
