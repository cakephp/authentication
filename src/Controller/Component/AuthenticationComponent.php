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
namespace Authentication\Controller\Component;

use ArrayAccess;
use Authentication\AuthenticationServiceInterface;
use Authentication\Authenticator\PersistenceInterface;
use Authentication\Authenticator\StatelessInterface;
use Authentication\Authenticator\UnauthenticatedException;
use Cake\Controller\Component;
use Cake\Event\EventDispatcherInterface;
use Cake\Event\EventDispatcherTrait;
use Cake\Routing\Router;
use Cake\Utility\Hash;
use Exception;
use RuntimeException;

/**
 * Controller Component for interacting with Authentication.
 *
 */
class AuthenticationComponent extends Component implements EventDispatcherInterface
{
    use EventDispatcherTrait;

    /**
     * Configuration options
     *
     * - `logoutRedirect` - The route/URL to direct users to after logout()
     * - `requireIdentity` - By default AuthenticationComponent will require an
     *   identity to be present whenever it is active. You can set the option to
     *   false to disable that behavior. See allowUnauthenticated() as well.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'logoutRedirect' => false,
        'requireIdentity' => true,
        'identityAttribute' => 'identity'
    ];

    /**
     * List of actions that don't require authentication.
     *
     * @var array
     */
    protected $unauthenticatedActions = [];

    /**
     * Authentication service instance.
     *
     * @var \Authentication\AuthenticationServiceInterface
     */
    protected $_authentication;

    /**
     * Initialize component.
     *
     * @param array $config The config data.
     * @return void
     */
    public function initialize(array $config)
    {
        $controller = $this->getController();
        $this->setEventManager($controller->getEventManager());
    }

    /**
     * Triggers the Authentication.afterIdentify event for non stateless adapters that are not persistent either
     *
     * @return void
     */
    public function beforeFilter()
    {
        $authentication = $this->getAuthenticationService();
        $provider = $authentication->getAuthenticationProvider();

        if ($provider === null ||
            $provider instanceof PersistenceInterface ||
            $provider instanceof StatelessInterface
        ) {
            return;
        }

        $this->dispatchEvent('Authentication.afterIdentify', [
            'provider' => $provider,
            'identity' => $this->getIdentity(),
            'service' => $authentication
        ], $this->getController());
    }

    /**
     * Returns authentication service.
     *
     * @return \Authentication\AuthenticationServiceInterface
     * @throws \Exception
     */
    public function getAuthenticationService()
    {
        $controller = $this->getController();
        $service = $controller->request->getAttribute('authentication');
        if ($service === null) {
            throw new Exception('The request object does not contain the required `authentication` attribute');
        }

        if (!($service instanceof AuthenticationServiceInterface)) {
            throw new Exception('Authentication service does not implement ' . AuthenticationServiceInterface::class);
        }

        return $service;
    }

    /**
     * Start up event handler
     *
     * @return void
     * @throws Exception when request is missing or has an invalid AuthenticationService
     * @throws UnauthenticatedException when requireIdentity is true and request is missing an identity
     */
    public function startup()
    {
        if (!$this->getConfig('requireIdentity')) {
            return;
        }

        $request = $this->getController()->request;
        $action = $request->getParam('action');
        if (in_array($action, $this->unauthenticatedActions)) {
            return;
        }

        $identity = $request->getAttribute($this->getConfig('identityAttribute'));
        if (!$identity) {
            throw new UnauthenticatedException();
        }
    }

    /**
     * Set the list of actions that don't require an authentication identity to be present.
     *
     * Actions not in this list will require an identity to be present. Any
     * valid identity will pass this constraint.
     *
     * @param array $actions The action list.
     * @return $this
     */
    public function allowUnauthenticated(array $actions)
    {
        $this->unauthenticatedActions = $actions;

        return $this;
    }

    /**
     * Add to the list of actions that don't require an authentication identity to be present.
     *
     * @param array $actions The action or actions to append.
     * @return $this
     */
    public function addUnauthenticatedActions(array $actions)
    {
        $this->unauthenticatedActions = array_merge($this->unauthenticatedActions, $actions);
        $this->unauthenticatedActions = array_values(array_unique($this->unauthenticatedActions));

        return $this;
    }

    /**
     * Get the current list of actions that don't require authentication.
     *
     * @return array
     */
    public function getUnauthenticatedActions()
    {
        return $this->unauthenticatedActions;
    }

    /**
     * Gets the result of the last authenticate() call.
     *
     * @return \Authentication\Authenticator\ResultInterface|null Authentication result interface
     */
    public function getResult()
    {
        return $this->getAuthenticationService()->getResult();
    }

    /**
     * Returns the identity used in the authentication attempt.
     *
     * @return \Authentication\IdentityInterface|null
     */
    public function getIdentity()
    {
        $controller = $this->getController();
        $identity = $controller->request->getAttribute($this->getConfig('identityAttribute'));

        return $identity;
    }

    /**
     * Returns the identity used in the authentication attempt.
     *
     * @param string $path Path to return from the data.
     * @return mixed
     * @throws \RuntimeException If the identity has not been found.
     */
    public function getIdentityData($path)
    {
        $identity = $this->getIdentity();

        if ($identity === null) {
            throw new RuntimeException('The identity has not been found.');
        }

        return Hash::get($identity, $path);
    }

    /**
     * Set identity data to all authenticators that are loaded and support persistence.
     *
     * @param \ArrayAccess $identity Identity data to persist.
     * @return $this
     */
    public function setIdentity(ArrayAccess $identity)
    {
        $controller = $this->getController();

        $result = $this->getAuthenticationService()->persistIdentity(
            $controller->request,
            $controller->response,
            $identity
        );

        $controller->setRequest($result['request']);
        $controller->response = $result['response'];

        return $this;
    }

    /**
     * Log a user out.
     *
     * Triggers the `Authentication.logout` event.
     *
     * @return string|null Returns null or `logoutRedirect`.
     */
    public function logout()
    {
        $controller = $this->getController();
        $result = $this->getAuthenticationService()->clearIdentity(
            $controller->request,
            $controller->response
        );

        $controller->request = $result['request'];
        $controller->response = $result['response'];

        $this->dispatchEvent('Authentication.logout', [], $controller);

        $logoutRedirect = $this->getConfig('logoutRedirect');
        if ($logoutRedirect === false) {
            return null;
        }

        return Router::normalize($logoutRedirect);
    }
}
