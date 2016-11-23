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
 * @since         4.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Auth\Authentication\Identifier;

use Cake\Core\Exception\Exception;
use Cake\Network\Exception\InternalErrorException;
use ErrorException;

/**
 * @link https://github.com/QueenCityCodeFactory/LDAP
 */
class LdapIdentifier extends AbstractIdentifier
{

    /**
     * LDAP Object
     *
     * @var object
     */
    protected $ldapConnection;

    /**
     * Default configuration
     *
     * @var array
     */
    protected $_defaultConfig = [
        'logErrors' => false,
        'port' => null,
    ];

    /**
     * {@inheritDoc}
     */
    public function __construct(array $config = [])
    {
        if (!defined('LDAP_OPT_DIAGNOSTIC_MESSAGE')) {
            define('LDAP_OPT_DIAGNOSTIC_MESSAGE', 0x0032);
        }
        if (isset($config['host']) && is_object($config['host']) && ($config['host'] instanceof \Closure)) {
            $config['host'] = $config['host']();
        }
        if (empty($config['host'])) {
            throw new InternalErrorException('LDAP Server not specified!');
        }

        parent::__construct($config);
        $this->_connectLdap();
    }

    /**
     * Initializes the Ldap connection
     *
     * @return void
     */
    protected function _connectLdap()
    {
        $config = $this->config();

        try {
            $this->ldapConnection = ldap_connect($config['host'], $config['port']);
            if (isset($config['options']) && is_array($config['options'])) {
                foreach ($config['options'] as $option => $value) {
                    ldap_set_option($this->ldapConnection, $option, $value);
                }
            } else {
                ldap_set_option($this->ldapConnection, LDAP_OPT_NETWORK_TIMEOUT, 5);
            }
        } catch (Exception $e) {
            throw new InternalErrorException('Unable to connect to specified LDAP Server(s)');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function identify($data)
    {
        $fields = $this->config('fields');

        if (isset($data[$fields['username']]) && isset($data[$fields['password']])) {
            $this->_findUser($data[$fields['username']], $data[$fields['password']]);
        }

        return false;
    }

    /**
     * Find a user record using the username and password provided.
     *
     * @param string $username The username/identifier.
     * @param string|null $password The password
     * @return bool|array Either false on failure, or an array of user data.
     */
    protected function _findUser($username, $password = null)
    {
        if (!empty($this->_config['domain']) && !empty($username) && strpos($username, '@') === false) {
            $username .= '@' . $this->_config['domain'];
        }
        set_error_handler(
            function ($errorNumber, $errorText, $errorFile, $errorLine) {
                throw new ErrorException($errorText, 0, $errorNumber, $errorFile, $errorLine);
            },
            E_ALL
        );

        try {
            $ldapBind = ldap_bind($this->ldapConnection, isset($this->_config['bindDN']) ? $this->_config['bindDN']($username, $this->_config['domain']) : $username, $password);
            if ($ldapBind === true) {
                $searchResults = ldap_search($this->ldapConnection, $this->_config['baseDN']($username, $this->_config['domain']), '(' . $this->_config['search'] . '=' . $username . ')');
                $entry = ldap_first_entry($this->ldapConnection, $searchResults);

                return ldap_get_attributes($this->ldapConnection, $entry);
            }
        } catch (ErrorException $e) {
            if ($this->_config['logErrors'] === true) {
                $this->log($e->getMessage());
            }
            $this->_handleLdapError();
        }

        restore_error_handler();
        if (!empty($messages)) {
            // how to handle errors?
        }

        return false;
    }

    /**
     * Handles an Ldap error
     *
     * @return void
     */
    protected function _handleLdapError()
    {
        if (ldap_get_option($this->ldapConnection, LDAP_OPT_DIAGNOSTIC_MESSAGE, $extendedError)) {
            if (!empty($extendedError)) {
                foreach ($this->_config['errors'] as $error => $errorMessage) {
                    if (strpos($extendedError, $error) !== false) {
                        $messages[] = [
                            'message' => $errorMessage,
                            'key' => $this->_config['flash']['key'],
                            'element' => $this->_config['flash']['element'],
                            'params' => $this->_config['flash']['params'],
                        ];
                    }
                }
            }
        }
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        set_error_handler(
            function ($errorNumber, $errorText, $errorFile, $errorLine) {
                throw new ErrorException($errorText, 0, $errorNumber, $errorFile, $errorLine);
            },
            E_ALL
        );

        try {
            ldap_unbind($this->ldapConnection);
        } catch (ErrorException $e) {
            // Do Nothing
        }

        try {
            ldap_close($this->ldapConnection);
        } catch (ErrorException $e) {
            // Do Nothing
        }

        restore_error_handler();
    }
}
