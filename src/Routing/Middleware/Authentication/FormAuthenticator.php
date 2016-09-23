<?php
/**
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         2.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace MiddlewareAuth\Routing\Middleware\Authentication;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * An authentication adapter for AuthComponent. Provides the ability to authenticate using POST
 * data. Can be used by configuring AuthComponent to use it via the AuthComponent::$authenticate config.
 *
 * ```
 *  $this->Auth->authenticate = [
 *      'Form' => [
 *          'finder' => ['auth' => ['some_finder_option' => 'some_value']]
 *      ]
 *  ]
 * ```
 *
 * When configuring FormAuthenticate you can pass in config to which fields, model and additional conditions
 * are used. See FormAuthenticate::$_config for more information.
 *
 * @see \Cake\Controller\Component\AuthComponent::$authenticate
 */
class FormAuthenticator extends AbstractAuthenticator
{

    /**
     * Checks the fields to ensure they are supplied.
     *
     * @param \Cake\Network\Request $request The request that contains login information.
     * @param array $fields The fields to be checked.
     * @return bool False if the fields have not been supplied. True if they exist.
     */
    protected function _checkFields(ServerRequestInterface $request, array $fields)
    {
        $body = $request->getParsedBody();

        foreach ([$fields['username'], $fields['password']] as $field) {
            $value = $body[$field];
            if (empty($value) || !is_string($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Authenticates the identity contained in a request. Will use the `config.userModel`, and `config.fields`
     * to find POST data that is used to find a matching record in the `config.userModel`. Will return false if
     * there is no post data, either username or password is missing, or if the scope conditions have not been met.
     *
     * @param \Cake\Network\Request $request The request that contains login information.
     * @param \Cake\Network\Response $response Unused response object.
     * @return mixed False on login failure.  An array of User data on success.
     */
    public function authenticate(ServerRequestInterface $request, ResponseInterface $response)
    {
        $fields = $this->_config['fields'];
        if (!$this->_checkFields($request, $fields)) {
            return false;
        }

        $body = $request->getParsedBody();

        return $this->_findUser(
            $body[$fields['username']],
            $body[$fields['password']]
        );
    }
}