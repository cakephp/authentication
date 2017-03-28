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
namespace Authentication\Identifier\Backend;

interface LdapInterface
{
    /**
     * Bind to LDAP directory
     *
     * @param string $bind Bind rdn
     * @param string $password Bind password
     * @return bool
     */
    public function bind($bind, $password);

    /**
     * Get the LDAP connection
     *
     * @return mixed
     * @throws \RuntimeException If the connection is empty
     */
    public function getConnection();

    /**
     * Connect to an LDAP server
     *
     * @param string $host Hostname
     * @param int $port Port
     * @return void
     */
    public function connect($host, $port);

    /**
     *  Set the value of the given option
     *
     * @param int $option Option to set
     * @param mixed $value The new value for the specified option
     * @return void
     */
    public function setOption($option, $value);

    /**
     * Get the current value for given option
     *
     * @param int $option Option to get
     * @return mixed This will be set to the option value.
     */
    public function getOption($option);

    /**
     * Unbind from LDAP directory
     *
     * @return void
     */
    public function unbind();
}
