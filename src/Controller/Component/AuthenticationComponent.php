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

use ArrayAccess;
use Authentication\AuthenticationServiceInterface;
use Authentication\Authenticator\PersistenceInterface;
use Authentication\Authenticator\StatelessInterface;
use Cake\Controller\Component;
use Cake\Event\EventDispatcherInterface;
use Cake\Event\EventDispatcherTrait;
use Cake\Routing\Router;
use Cake\Utility\Hash;
use Exception;
use RuntimeException;

class AuthenticationComponent extends Component implements EventDispatcherInterface
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
        $controller = $this->getController();
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

        if (empty($provider) ||
            $provider instanceof PersistenceInterface ||
            $provider instanceof StatelessInterface
        ) {
            return;
        }

        $this->dispatchEvent('Authentication.afterIdentify', [
            'provider' => $provider,
            'identity' => $this->getIdentity(),
            'service' => $this->_authentication
        ], $this->getController());
    }

    /**
     * Gets the result of the last authenticate() call.
     *
     * @return \Authentication\Authenticator\ResultInterface|null Authentication result interface
     */
    public function getResult()
    {
        return $this->_authentication->getResult();
    }

    /**
     * Returns the identity used in the authentication attempt.
     *
     * @return \ArrayAccess|null
     */
    public function getIdentity()
    {
        $controller = $this->getController();
        $identity = $controller->request->getAttribute('identity');

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

        $result = $this->_authentication->setIdentity(
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
     * @return void|string|array
     */
    public function logout()
    {
        $controller = $this->getController();
        $result = $this->_authentication->clearIdentity(
            $controller->request,
            $controller->response
        );

        $controller->request = $result['request'];
        $controller->response = $result['response'];

        $this->dispatchEvent('Authentication.logout', [], $controller);

        $logoutRedirect = $this->getConfig('logoutRedirect');
        if ($logoutRedirect !== false) {
            return Router::normalize($logoutRedirect);
        }
    }
}
