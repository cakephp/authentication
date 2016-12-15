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

use Cake\Controller\Component;
use Cake\Event\EventDispatcherTrait;
use Cake\Utility\Hash;
use Exception;

class AuthenticationComponent extends Component
{

    use EventDispatcherTrait;

    /**
     * Authentication service instance.
     *
     * @var \Authentication\AuthenticationService
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
            throw new Exception('Authentication service instance not found in request.');
        }

        $this->eventManager($controller->eventManager());
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
     * @param string $path Path to return from the data.
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
     * Log a user out.
     *
     * Triggers the `Authentication.logout` event.
     *
     * @return void
     */
    public function logout()
    {
        $this->dispatchEvent('Authentication.logout', [$this->getIdentity()]);

        $controller = $this->_registry->getController();
        $result = $this->_authentication->clearIdentity(
            $controller->request,
            $controller->response
        );

        $controller->request = $result['request'];
        $controller->response = $result['response'];
    }
}
