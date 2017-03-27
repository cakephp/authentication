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

use RuntimeException;

/**
 * Provides a very thin OOP wrapper around the ldap_* functions.
 *
 * We don't need and want a huge LDAP lib for our purpose.
 *
 * But this makes it easier to unit test code that is using ldap because we can
 * mock it very easy. It also provides some convenience.
 */
class Ldap implements LdapInterface
{

    /**
     * LDAP Object
     *
     * @var object
     */
    protected $connection;

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
        if (empty($this->connection)) {
            throw new RuntimeException('You are not connected to a LDAP server.');
        }

        return $this->connection;
    }

    /**
     * Connect to an LDAP server
     *
     * @param string $host Hostname
     * @param int $port Port
     * @return void
     */
    public function connect($host, $port)
    {
        $this->connection = ldap_connect($host, $port);
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
        ldap_get_option($this->getLdapConnection(), $option, $returnValue);

        return $returnValue;
    }

    /**
     * Unbind from LDAP directory
     *
     * @return void
     */
    public function unbind()
    {
        ldap_unbind($this->connection);
        $this->connection = null;
    }
}
