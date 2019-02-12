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
namespace Authentication\Identifier\Ldap;

use Authentication\Identifier\Ldap\AdapterInterface;
use ErrorException;
use RuntimeException;

/**
 * Provides a very thin OOP wrapper around the ldap_* functions.
 *
 * We don't need and want a huge LDAP lib for our purpose.
 *
 * But this makes it easier to unit test code that is using LDAP because we can
 * mock it very easy. It also provides some convenience.
 */
class ExtensionAdapter implements AdapterInterface
{

    /**
     * LDAP Object
     *
     * @var object|null
     */
    protected $_connection;

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
     * Bind to LDAP directory
     *
     * @param string $bind Bind rdn
     * @param string $password Bind password
     * @return bool
     */
    public function bind($bind, $password)
    {
        $this->_setErrorHandler();
        $result = ldap_bind($this->getConnection(), $bind, $password);
        $this->_unsetErrorHandler();

        return $result;
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
        $this->_setErrorHandler();
        $this->_connection = ldap_connect($host, $port);
        $this->_unsetErrorHandler();

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
        $this->_setErrorHandler();
        ldap_set_option($this->getConnection(), $option, $value);
        $this->_unsetErrorHandler();
    }

    /**
     * Get the current value for given option
     *
     * @param int $option Option to get
     * @return mixed This will be set to the option value.
     */
    public function getOption($option)
    {
        $this->_setErrorHandler();
        ldap_get_option($this->getConnection(), $option, $returnValue);
        $this->_unsetErrorHandler();

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
        $this->_setErrorHandler();
        ldap_unbind($this->_connection);
        $this->_unsetErrorHandler();

        $this->_connection = null;
    }

    /**
     * Set an error handler to turn LDAP errors into exceptions
     *
     * @return void
     * @throws \ErrorException
     */
    protected function _setErrorHandler()
    {
        set_error_handler(
            function ($errorNumber, $errorText) {
                 throw new ErrorException($errorText);
            },
            E_ALL
        );
    }

    /**
     * Restore the error handler
     *
     * @return void
     */
    protected function _unsetErrorHandler()
    {
        restore_error_handler();
    }
}
