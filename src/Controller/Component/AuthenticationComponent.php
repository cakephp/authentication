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
namespace Authentication\Controller\Component;

use Authentication\AuthenticationServiceInterface;
use Authentication\Authenticator\PersistenceInterface;
use Cake\Controller\Component;
use Cake\Event\Event;
use Cake\Event\EventDispatcherTrait;
use Cake\Routing\Router;
use Cake\Utility\Hash;
use Exception;

class AuthenticationComponent extends Component
{

    use EventDispatcherTrait;

    /**
     * {@inheritDoc}
     */
    protected $_defaultConfig = [
        'logoutRedirect' => false
    ];

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
        $controller = $this->_registry->getController();
        $this->_authentication = $controller->request->getAttribute('authentication');

        if ($this->_authentication === null) {
            throw new Exception('The request object does not contain the required `authentication` attribute');
        }

        if (!$this->_authentication instanceof AuthenticationServiceInterface) {
            throw new Exception('Authentication service does not implement ' . AuthenticationServiceInterface::class);
        }

        $this->eventManager($controller->eventManager());

        $this->_afterIdentify();
    }

    /**
     * Triggers the Authentication.afterIdentify event for non stateless adapters that are not persistent either
     *
     * @return void
     */
    protected function _afterIdentify()
    {
        $provider = $this->_authentication->getAuthenticationProvider();

        if (empty($provider) || $provider instanceof PersistenceInterface || $provider->isStateless()) {
            return;
        }

        $event = new Event('Authentication.afterIdentify', $this->_registry->getController(), [
            'provider' => $provider,
            'identity' => $this->getIdentity(),
            'service' => $this->_authentication
        ]);

        $this->eventManager()->dispatch($event);
    }

    /**
     * Gets the result of the last authenticate() call.
     *
     * @return \Authentication\Result Authentication result interface
     */
    public function getResult()
    {
        return $this->_authentication->getResult();
    }

    /**
     * Returns the identity used in the authentication attempt.
     *
     * @param string|null $path Path to return from the data.
     * @return mixed
     */
    public function getIdentity($path = null)
    {
        $controller = $this->_registry->getController();
        $identity = $controller->request->getAttribute('identity');

        if (is_string($path)) {
            return Hash::get($identity, $path);
        }

        return $identity;
    }

    /**
     * Set identity data to all authenticators that are loaded and support persistence.
     *
     * @param mixed $identity Identity data to persist.
     * @return void
     */
    public function setIdentity($identity)
    {
        $controller = $this->_registry->getController();
        $this->controller->request = $this->_authentication->setIdentity(
            $controller->request,
            $identity
        );
    }

    /**
     * Log a user out.
     *
     * Triggers the `Authentication.logout` event.
     *
     * @return void|string|array
     */
    public function logout()
    {
        $controller = $this->_registry->getController();
        $result = $this->_authentication->clearIdentity(
            $controller->request,
            $controller->response
        );

        $controller->request = $result['request'];
        $controller->response = $result['response'];

        $logoutRedirect = $this->config('logoutRedirect');
        if ($logoutRedirect !== false) {
            return Router::normalize($logoutRedirect);
        }
    }
}
