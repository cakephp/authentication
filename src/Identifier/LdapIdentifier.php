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
use InvalidArgumentException;
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

    /**
     * Default configuration
     *
     * @var array
     */
    protected $_defaultConfig = [
        'ldapClass' => null,
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
     * LDAP connection object
     *
     * @var \Authentication\Identifier\LdapInterface
     */
    protected $_ldap = null;

    /**
     * {@inheritDoc}
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        $this->_checkLdapExtension();
        $this->_checkLdapConfig();
        $this->_buildLdapObject();
    }

    /**
     * Checks if the php LDAP extension is loaded
     *
     * @throws \RuntimeException
     * @return void
     */
    protected function _checkLdapExtension()
    {
        if (!extension_loaded('ldap')) {
            throw new RuntimeException('You must enable the ldap extension to use the LDAP identifier.');
        }
    }

    /**
     * Checks the LDAP config
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @return void
     */
    protected function _checkLdapConfig()
    {
        if (!defined('LDAP_OPT_DIAGNOSTIC_MESSAGE')) {
            define('LDAP_OPT_DIAGNOSTIC_MESSAGE', 0x0032);
        }
        if (!isset($this->_config['bindDN'])) {
            throw new RuntimeException('Config `bindDN` is not set.');
        }
        if (!is_callable($this->_config['bindDN'])) {
            throw new InvalidArgumentException(sprintf(
                'The `bindDN` config is not a callable. Got `%s` instead.',
                gettype($this->_config['bindDN'])
            ));
        }
        if (!isset($this->_config['host'])) {
            throw new RuntimeException('Config `host` is not set.');
        }
    }

    /**
     * Constructs the LDAP object and sets it to the property
     *
     * @throws \RuntimeException
     * @return void
     */
    protected function _buildLdapObject()
    {
        if (empty($this->_config['ldapClass'])) {
            $this->setConfig('ldapClass', Ldap::class);
        }

        if (is_string($this->_config['ldapClass'])) {
            $this->_ldap = new $this->_config['ldapClass'];

            return;
        }

        if ($this->_config['ldapClass'] instanceof LdapInterface) {
            $this->_ldap = $this->_config['ldapClass'];

            return;
        }

        throw new RuntimeException('Could not build the LDAP connection object.');
    }

    /**
     * Identify
     *
     * @param array $data Authentication credentials
     * @return \Cake\Datasource\EntityInterface|null
     */
    public function identify($data)
    {
        $this->_connectLdap();
        $fields = $this->getConfig('fields');

        if (isset($data[$fields['username']]) && isset($data[$fields['password']])) {
            return $this->_bindUser($data[$fields['username']], $data[$fields['password']]);
        }

        return null;
    }

    /**
     * Initializes the LDAP connection
     *
     * @return void
     * @throws \Cake\Network\Exception\InternalErrorException Raised in case of an unsucessful connection.
     */
    protected function _connectLdap()
    {
        $config = $this->getConfig();

        try {
            $this->_ldap->connect(
                $config['host'],
                $config['port']
            );
            if (isset($config['options']) && is_array($config['options'])) {
                foreach ($config['options'] as $option => $value) {
                    $this->_ldap->setOption($option, $value);
                }
            } else {
                $this->_ldap->setOption(LDAP_OPT_NETWORK_TIMEOUT, 5);
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
     * @return \Cake\Datasource\EntityInterface|null
     */
    protected function _bindUser($username, $password)
    {
        // Turn all LDAP errors into exceptions
        set_error_handler(
            function ($errorNumber, $errorText) {
                 throw new ErrorException($errorText);
            },
            E_ALL
        );

        $config = $this->getConfig();
        try {
            $ldapBind = $this->_ldap->bind($config['bindDN']($username), $password);
            if ($ldapBind === true) {
                $this->_ldap->unbind();

                return new Entity([
                    $config['fields']['username'] => $username
                ]);
            }
        } catch (ErrorException $e) {
            $this->_handleLdapError($e->getMessage());
        }
        $this->_ldap->unbind();
        restore_error_handler();

        return null;
    }

    /**
     * Handles an LDAP error
     *
     * @param string $message Exception message
     * @return void
     */
    protected function _handleLdapError($message)
    {
        $extendedError = $this->_ldap->getOption(LDAP_OPT_DIAGNOSTIC_MESSAGE);
        if (!is_null($extendedError)) {
            $this->_errors[] = $extendedError;
        } else {
            $this->_errors[] = $message;
        }
    }
}
