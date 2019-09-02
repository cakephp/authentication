<?php
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

interface AdapterInterface
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
     * Connect to an LDAP server
     *
     * @param string $host Hostname
     * @param int $port Port
     * @param array $options Additional options
     * @return void
     */
    public function connect($host, $port, $options);

    /**
     * Search the LDAP directory
     *
     * @param string $baseDN Base DN for the directory
     * @param string $filter search filter
     * @return array
     */
    public function search($baseDN, $filter);

    /**
     * Unbind from LDAP directory
     *
     * @return void
     */
    public function unbind();

    /**
     * Get the diagnostic message
     *
     * @return string|null
     */
    public function getDiagnosticMessage();
}
