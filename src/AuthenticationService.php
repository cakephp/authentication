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

use Authentication\Authenticator\AuthenticateInterface;
use Authentication\Authenticator\PersistenceInterface;
use Authentication\Identifier\IdentifierCollection;
use Cake\Core\App;
use Cake\Core\Exception\Exception;
use Cake\Core\InstanceConfigTrait;
use Cake\Event\EventDispatcherTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Authentication Service
 */
class AuthenticationService
{
    use EventDispatcherTrait;
    use InstanceConfigTrait;

    /**
     * Authenticator objects
     *
     * @var array
     */
    protected $_authenticators = [];

    /**
     * Identifier collection
     *
     * @var array
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
     * @var \Authentication\Result|null
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
     *
     *   ```
     *   $service = new AuthenticationService([
     *      'authenticators' => [
     *          'Authentication.Form
     *      ],
     *      'identifiers' => [
     *          'Authentication.Orm' => [
     *              'userModel' => 'Users.Users'
     *          ]
     *      ]
     *   ]);
     *   ```
     *
     * @var array
     */
    protected $_defaultConfig = [
        'authenticators' => [],
        'identifiers' => []
    ];

    /**
     * Constructor
     *
     * @param array $config Configuration options.
     */
    public function __construct(array $config = [])
    {
        $this->config($config);
    }

    /**
     * Access the identifier collection
     *
     * @return \Authentication\Identifier\IdentifierCollection
     */
    public function identifiers()
    {
        if (!$this->_identifiers) {
            $this->_identifiers = new IdentifierCollection($this->config('identifiers'));
        }

        return $this->_identifiers;
    }

    /**
     * Loads all configured authenticators.
     *
     * @return void
     */
    public function loadAuthenticators()
    {
        if (empty($this->_config['authenticators'])
            || !empty($this->_authenticators)
        ) {
            return;
        }

        foreach ($this->_config['authenticators'] as $name => $config) {
            if (is_int($name) && is_string($config)) {
                $name = $config;
                $config = [];
            }
            $this->loadAuthenticator($name, $config);
        }
    }

    /**
     * Loads an authenticator.
     *
     * @param string $name Name or class name.
     * @param array $config Authenticator configuration.
     * @return \Authentication\Authenticator\AuthenticateInterface
     */
    public function loadAuthenticator($name, array $config = [])
    {
        $className = $this->_getAuthenticatorClass($name, $config);
        $authenticator = new $className($this->identifiers(), $config);

        if (!$authenticator instanceof AuthenticateInterface) {
            throw new Exception('Authenticator must implement AuthenticateInterface.');
        }

        if (isset($this->_authenticators)) {
            $this->_authenticators[$name] = $authenticator;
        }

        return $authenticator;
    }

    /**
     * Gets the authenticator class name.
     *
     * @param string $name Authenticator name.
     * @param array $config Configuration options for the authenticator.
     * @return string
     */
    protected function _getAuthenticatorClass($name, $config)
    {
        if (!empty($config['className'])) {
            $class = $config['className'];
            unset($config['className']);
        } else {
            $class = $name;
        }

        if (class_exists($class)) {
            return $class;
        }

        $className = App::className($class, 'Authenticator', 'Authenticator');
        if (!class_exists($className)) {
            throw new Exception(sprintf('Authenticator "%s" was not found.', $className));
        }

        return $className;
    }

    /**
     * Authenticate the request against the configured authentication adapters.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request.
     * @param \Psr\Http\Message\ResponseInterface $response The response.
     * @return \Authentication\ResultInterface A result object. If none of
     * the adapters was a success the last failed result is returned.
     */
    public function authenticate(ServerRequestInterface $request, ResponseInterface $response)
    {
        $this->loadAuthenticators();

        foreach ($this->_authenticators as $authenticator) {
            $result = $authenticator->authenticate($request, $response);
            if ($result->isValid()) {
                if ($authenticator instanceof PersistenceInterface) {
                    $authenticator->persistIdentity($request, $result->getIdentity());
                }

                $this->_successfulAuthenticator = $authenticator;

                return $this->_result = $result;
            }
        }

        $this->_successfulAuthenticator = null;
        $this->_result = $result;

        return $this->_result = new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND);
    }

    /**
     * Clears the identity from authenticators that store them and the request
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request.
     * @param \Psr\Http\Message\ResponseInterface $response The response.
     * @return \Psr\Http\Message\ServerRequestInterface
     */
    public function clearIdentity(ServerRequestInterface $request, ResponseInterface $response)
    {
        foreach ($this->_authenticators as $authenticator) {
            if ($authenticator instanceof PersistenceInterface) {
                $authenticator->clearIdentity($request);
            }
        }

        return $request->withoutAttribute('identity');
    }

    /**
     * Gets the successful authenticator instance if one was successful after calling authenticate
     *
     * @return \Authentication\Authenticator\AuthenticateInterface|null
     */
    public function getAuthenticationProvider()
    {
        return $this->_successfulAuthenticator;
    }

    /**
     * Gets the result of the last authenticate() call.
     *
     * @return \Authentication\Result Authentication result interface
     */
    public function getResult()
    {
        return $this->_result;
    }
}
