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
namespace Authentication\Identifier\Ldap;

use Authentication\Identifier\Ldap\AdapterInterface;
use RuntimeException;

/**
 * Provides a very thin OOP wrapper around the ldap_* functions.
 *
 * We don't need and want a huge LDAP lib for our purpose.
 *
 * But this makes it easier to unit test code that is using ldap because we can
 * mock it very easy. It also provides some convenience.
 */
class ExtensionAdapter implements AdapterInterface
{

    /**
     * Constructor
     *
     * @throws \RuntimeException
     */
    public function __construct()
    {
        if (!extension_loaded('ldap')) {
            throw new RuntimeException('You must enable the ldap extension to use the LDAP identifier.');
        }

        if (!defined('LDAP_OPT_DIAGNOSTIC_MESSAGE')) {
            define('LDAP_OPT_DIAGNOSTIC_MESSAGE', 0x0032);
        }
    }

    /**
     * LDAP Object
     *
     * @var object
     */
    protected $_connection;

    /**
     * Bind to LDAP directory
     *
     * @param string $bind Bind rdn
     * @param string $password Bind password
     * @return bool
     */
    public function bind($bind, $password)
    {
        return ldap_bind($this->getConnection(), $bind, $password);
    }

    /**
     * Get the LDAP connection
     *
     * @return mixed
     * @throws \RuntimeException If the connection is empty
     */
    public function getConnection()
    {
        if (empty($this->_connection)) {
            throw new RuntimeException('You are not connected to a LDAP server.');
        }

        return $this->_connection;
    }

    /**
     * Connect to an LDAP server
     *
     * @param string $host Hostname
     * @param int $port Port
     * @param array $options Additonal LDAP options
     * @return void
     */
    public function connect($host, $port, $options)
    {
        $this->_connection = ldap_connect($host, $port);

        if (is_array($options)) {
            foreach ($options as $option => $value) {
                $this->setOption($option, $value);
            }
        }
    }

    /**
     *  Set the value of the given option
     *
     * @param int $option Option to set
     * @param mixed $value The new value for the specified option
     * @return void
     */
    public function setOption($option, $value)
    {
        ldap_set_option($this->getConnection(), $option, $value);
    }

    /**
     * Get the current value for given option
     *
     * @param int $option Option to get
     * @return mixed This will be set to the option value.
     */
    public function getOption($option)
    {
        ldap_get_option($this->getConnection(), $option, $returnValue);

        return $returnValue;
    }

    /**
     * Get the diagnostic message
     *
     * @return string|null
     */
    public function getDiagnosticMessage()
    {
        return $this->getOption(LDAP_OPT_DIAGNOSTIC_MESSAGE);
    }

    /**
     * Unbind from LDAP directory
     *
     * @return void
     */
    public function unbind()
    {
        ldap_unbind($this->_connection);
        $this->_connection = null;
    }
}
