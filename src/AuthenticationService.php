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
namespace Authentication;

use ArrayAccess;
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
     * @var \Authentication\Authenticator\AuthenticatorCollection
     */
    protected $_authenticators;

    /**
     * Identifier collection
     *
     * @var \Authentication\Identifier\IdentifierCollection
     */
    protected $_identifiers;

    /**
     * Authenticator that successfully authenticated the identity.
     *
     * @param \Authentication\Authenticator\AuthenticatorInterface
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
     *
     * @var array
     */
    protected $_defaultConfig = [
        'authenticators' => [],
        'identifiers' => [],
        'identityClass' => Identity::class
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
     * Authenticate the request against the configured authentication adapters.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request.
     * @param \Psr\Http\Message\ResponseInterface $response The response.
     * @return \Authentication\Authenticator\ResultInterface A result object. If none of
     * the adapters was a success the last failed result is returned.
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
                    $this->setIdentity($request, $response, $result->getData());
                }

                $this->_successfulAuthenticator = $authenticator;

                return $this->_result = $result;
            }

            if (!$result->isValid() && $authenticator instanceof StatelessInterface) {
                $authenticator->unauthorizedChallenge($request);
            }
        }

        $this->_successfulAuthenticator = null;

        return $this->_result = $result;
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
            'request' => $request->withoutAttribute('identity'),
            'response' => $response
        ];
    }

    /**
     * Sets identity data and persists it in the authenticators that support it.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request.
     * @param \Psr\Http\Message\ResponseInterface $response The response.
     * @param \ArrayAccess $identity The identity data.
     * @return array
     */
    public function setIdentity(ServerRequestInterface $request, ResponseInterface $response, ArrayAccess $identity)
    {
        foreach ($this->authenticators() as $authenticator) {
            if ($authenticator instanceof PersistenceInterface) {
                $result = $authenticator->persistIdentity($request, $response, $identity);
                $request = $result['request'];
                $response = $result['response'];
            }
        }

        if (!$identity instanceof IdentityInterface) {
            $identity = $this->buildIdentity($identity);
        }

        return [
            'request' => $request->withAttribute('identity', $identity),
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
        if (empty($this->_result) || !$this->_result->isValid()) {
            return null;
        }

        $identity = $this->_result->getData();
        if (!$identity instanceof IdentityInterface) {
            $identity = $this->buildIdentity($identity);
        }

        return $identity;
    }

    /**
     * Builds the identity object
     *
     * @param \ArrayAccess $identityData Identity data
     * @return \Authentication\IdentityInterface
     */
    public function buildIdentity(ArrayAccess $identityData)
    {
        $class = $this->getConfig('identityClass');

        if (is_callable($class)) {
            $identity = $class($identityData);
        } else {
            $identity = new $class($identityData);
        }

        if (!$identity instanceof IdentityInterface) {
            throw new RuntimeException(sprintf(
                'Object `%s` does not implement `%s`',
                get_class($identity),
                IdentityInterface::class
            ));
        }

        return $identity;
    }
}
