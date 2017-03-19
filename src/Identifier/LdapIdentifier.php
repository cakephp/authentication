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
namespace Authentication\Identifier;

use Cake\Core\Exception\Exception;
use Cake\Network\Exception\InternalErrorException;
use Cake\ORM\Entity;
use ErrorException;
use RuntimeException;

/**
 * LDAP Identifier
 *
 * Identifies authentication credentials using LDAP.
 *
 * ```
 *  new LdapIdentifier([
 *       'host' => 'ldap.example.com',
 *       'bindDN' => function($username) {
 *           return $username; //transform into a rdn or dn
 *       },
 *       'options' => [
 *           LDAP_OPT_PROTOCOL_VERSION => 3
 *       ]
 *  ]);
 * ```
 *
 * @link https://github.com/QueenCityCodeFactory/LDAP
 */
class LdapIdentifier extends AbstractIdentifier
{

    use LdapOopTrait;

    /**
     * Default configuration
     *
     * @var array
     */
    protected $_defaultConfig = [
        'fields' => [
            'username' => 'username',
            'password' => 'password'
        ],
        'port' => 389
    ];

    /**
     * List of errors
     *
     * @var array
     */
    protected $_errors = [];

    /**
     * {@inheritDoc}
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        if (!extension_loaded('ldap')) {
            throw new RuntimeException('You must enable the ldap extension to use the LDAP identifier.');
        }
        if (!defined('LDAP_OPT_DIAGNOSTIC_MESSAGE')) {
            define('LDAP_OPT_DIAGNOSTIC_MESSAGE', 0x0032);
        }
        if (!is_callable($this->_config['bindDN'])) {
            throw new RuntimeException('Config "bindDN" must be callable.');
        }
        if (!isset($this->_config['host'])) {
            throw new RuntimeException('Config "host" is mandatory.');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function identify($data)
    {
        $this->_connectLdap();
        $fields = $this->getConfig('fields');

        if (isset($data[$fields['username']]) && isset($data[$fields['password']])) {
            return $this->_bindUser($data[$fields['username']], $data[$fields['password']]);
        }

        return false;
    }

    /**
     * Gets the errors that happened
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->_errors;
    }

    /**
     * Initializes the LDAP connection
     *
     * @return void
     * @throws \Cake\Network\Exception\InternalErrorException Raised in case of an unsucessful connection.
     */
    protected function _connectLdap()
    {
        try {
            $this->ldapConnect(
                $this->_config['host'],
                $this->_config['port']
            );
            if (isset($this->_config['options']) && is_array($this->_config['options'])) {
                foreach ($this->_config['options'] as $option => $value) {
                    $this->ldapSetOption($option, $value);
                }
            } else {
                $this->ldapSetOption(LDAP_OPT_NETWORK_TIMEOUT, 5);
            }
        } catch (Exception $e) {
            throw new InternalErrorException('Unable to connect to specified LDAP Server');
        }
    }

    /**
     * Try to bind the given user to the LDAP server
     *
     * @param string $username The username
     * @param string $password The password
     * @return bool|array Either false on failure, or an array of user data.
     */
    protected function _bindUser($username, $password)
    {
        // Turn all LDAP errors into exceptions
        set_error_handler(
            function ($errorNumber, $errorText, $errorFile, $errorLine) {
                 throw new ErrorException($errorText, 0, $errorNumber, $errorFile, $errorLine);
            },
            E_ALL
        );

        try {
            $ldapBind = $this->ldapBind($this->_config['bindDN']($username), $password);
            if ($ldapBind === true) {
                $this->ldapUnbind();

                return new Entity([
                    $this->_config['fields']['username'] => $username
                ]);
            }
        } catch (ErrorException $e) {
            $this->_handleLdapError($e->getMessage());
        }
        $this->ldapUnbind();
        restore_error_handler();

        return false;
    }

    /**
     * Handles an LDAP error
     *
     * @param string $message Exception message
     * @return void
     */
    protected function _handleLdapError($message)
    {
        $extendedError = $this->ldapGetOption(LDAP_OPT_DIAGNOSTIC_MESSAGE);
        if (!is_null($extendedError)) {
            $this->_errors[] = $extendedError;
        } else {
            $this->_errors[] = $message;
        }
    }
}
