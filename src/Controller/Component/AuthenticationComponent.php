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
namespace Authentication\Controller\Component;

use ArrayAccess;
use Authentication\AuthenticationServiceInterface;
use Authentication\Authenticator\PersistenceInterface;
use Authentication\Authenticator\ResultInterface;
use Authentication\Authenticator\StatelessInterface;
use Authentication\Authenticator\UnauthenticatedException;
use Authentication\IdentityInterface;
use Cake\Controller\Component;
use Cake\Event\EventDispatcherInterface;
use Cake\Event\EventDispatcherTrait;
use Cake\Routing\Router;
use Cake\Utility\Hash;
use Exception;
use RuntimeException;

/**
 * Controller Component for interacting with Authentication.
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
     * - `unauthenticatedMessage` - Error message to use when `UnauthenticatedException` is thrown.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'logoutRedirect' => false,
        'requireIdentity' => true,
        'identityAttribute' => 'identity',
        'identityCheckEvent' => 'Controller.startup',
        'unauthenticatedMessage' => null,
    ];

    /**
     * List of actions that don't require authentication.
     *
     * @var string[]
     */
    protected $unauthenticatedActions = [];

    /**
     * Authentication service instance.
     *
     * @var \Authentication\AuthenticationServiceInterface|null
     */
    protected $_authentication;

    /**
     * Initialize component.
     *
     * @param array $config The config data.
     * @return void
     */
    public function initialize(array $config): void
    {
        $controller = $this->getController();
        $this->setEventManager($controller->getEventManager());
    }

    /**
     * Triggers the Authentication.afterIdentify event for non stateless adapters that are not persistent either
     *
     * @return void
     */
    public function beforeFilter(): void
    {
        $authentication = $this->getAuthenticationService();
        $provider = $authentication->getAuthenticationProvider();

        if (
            $provider !== null &&
            !$provider instanceof PersistenceInterface &&
            !$provider instanceof StatelessInterface
        ) {
            $this->dispatchEvent('Authentication.afterIdentify', [
                'provider' => $provider,
                'identity' => $this->getIdentity(),
                'service' => $authentication,
            ], $this->getController());
        }

        if ($this->getConfig('identityCheckEvent') === 'Controller.initialize') {
            $this->doIdentityCheck();
        }
    }

    /**
     * Start up event handler
     *
     * @return void
     * @throws \Exception when request is missing or has an invalid AuthenticationService
     * @throws \Authentication\Authenticator\UnauthenticatedException when requireIdentity is true and request is missing an identity
     */
    public function startup(): void
    {
        if ($this->getConfig('identityCheckEvent') === 'Controller.startup') {
            $this->doIdentityCheck();
        }
    }

    /**
     * Returns authentication service.
     *
     * @return \Authentication\AuthenticationServiceInterface
     * @throws \Exception
     */
    public function getAuthenticationService(): AuthenticationServiceInterface
    {
        if ($this->_authentication !== null) {
            return $this->_authentication;
        }

        $controller = $this->getController();
        $service = $controller->getRequest()->getAttribute('authentication');
        if ($service === null) {
            throw new Exception('The request object does not contain the required `authentication` attribute');
        }

        if (!($service instanceof AuthenticationServiceInterface)) {
            throw new Exception('Authentication service does not implement ' . AuthenticationServiceInterface::class);
        }

        $this->_authentication = $service;

        return $service;
    }

    /**
     * Check if the identity presence is required.
     *
     * Also checks if the current action is accessible without authentication.
     *
     * @return void
     * @throws \Exception when request is missing or has an invalid AuthenticationService
     * @throws \Authentication\Authenticator\UnauthenticatedException when requireIdentity is true and request is missing an identity
     */
    protected function doIdentityCheck(): void
    {
        if (!$this->getConfig('requireIdentity')) {
            return;
        }

        $request = $this->getController()->getRequest();
        $action = $request->getParam('action');
        if (in_array($action, $this->unauthenticatedActions, true)) {
            return;
        }

        $identity = $request->getAttribute($this->getConfig('identityAttribute'));
        if (!$identity) {
            throw new UnauthenticatedException($this->getConfig('unauthenticatedMessage', ''));
        }
    }

    /**
     * Set the list of actions that don't require an authentication identity to be present.
     *
     * Actions not in this list will require an identity to be present. Any
     * valid identity will pass this constraint.
     *
     * @param string[] $actions The action list.
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
     * @param string[] $actions The action or actions to append.
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
     * @return string[]
     */
    public function getUnauthenticatedActions(): array
    {
        return $this->unauthenticatedActions;
    }

    /**
     * Gets the result of the last authenticate() call.
     *
     * @return \Authentication\Authenticator\ResultInterface|null Authentication result interface
     */
    public function getResult(): ?ResultInterface
    {
        return $this->getAuthenticationService()->getResult();
    }

    /**
     * Returns the identity used in the authentication attempt.
     *
     * @return \Authentication\IdentityInterface|null
     */
    public function getIdentity(): ?IdentityInterface
    {
        $controller = $this->getController();
        $identity = $controller->getRequest()->getAttribute($this->getConfig('identityAttribute'));

        return $identity;
    }

    /**
     * Returns the identity used in the authentication attempt.
     *
     * @param string $path Path to return from the data.
     * @return mixed
     * @throws \RuntimeException If the identity has not been found.
     */
    public function getIdentityData(string $path)
    {
        $identity = $this->getIdentity();

        if ($identity === null) {
            throw new RuntimeException('The identity has not been found.');
        }

        return Hash::get($identity, $path);
    }

    /**
     * Replace the current identity
     *
     * Clear and replace identity data in all authenticators
     * that are loaded and support persistence. The identity
     * is cleared and then set to ensure that privilege escalation
     * and de-escalation include side effects like session rotation.
     *
     * @param \ArrayAccess $identity Identity data to persist.
     * @return $this
     */
    public function setIdentity(ArrayAccess $identity)
    {
        $controller = $this->getController();
        $service = $this->getAuthenticationService();

        $service->clearIdentity($controller->getRequest(), $controller->getResponse());

        /** @psalm-var array{request: \Cake\Http\ServerRequest, response: \Cake\Http\Response} $result */
        $result = $service->persistIdentity(
            $controller->getRequest(),
            $controller->getResponse(),
            $identity
        );

        $controller->setRequest($result['request']);
        $controller->setResponse($result['response']);

        return $this;
    }

    /**
     * Log a user out.
     *
     * Triggers the `Authentication.logout` event.
     *
     * @return string|null Returns null or `logoutRedirect`.
     */
    public function logout(): ?string
    {
        $controller = $this->getController();
        /** @psalm-var array{request: \Cake\Http\ServerRequest, response: \Cake\Http\Response} $result */
        $result = $this->getAuthenticationService()->clearIdentity(
            $controller->getRequest(),
            $controller->getResponse()
        );

        $controller->setRequest($result['request']);
        $controller->setResponse($result['response']);

        $this->dispatchEvent('Authentication.logout', [], $controller);

        $logoutRedirect = $this->getConfig('logoutRedirect');
        if ($logoutRedirect === false) {
            return null;
        }

        return Router::normalize($logoutRedirect);
    }

    /**
     * Get the URL visited before an unauthenticated redirect.
     *
     * Reads from the current request's query string if available.
     *
     * Leverages the `unauthenticatedRedirect` and `queryParam` options in
     * the AuthenticationService.
     *
     * @return string|null
     */
    public function getLoginRedirect(): ?string
    {
        $controller = $this->getController();

        return $this->getAuthenticationService()->getLoginRedirect($controller->getRequest());
    }

    /**
     * Get the Controller callbacks this Component is interested in.
     *
     * @return array
     */
    public function implementedEvents(): array
    {
        return [
            'Controller.initialize' => 'beforeFilter',
            'Controller.startup' => 'startup',
        ];
    }
}
