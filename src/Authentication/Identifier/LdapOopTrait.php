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

/**
 * Provides a very thin OOP wrapper around the ldap_* functions.
 *
 * This makes it easier to unit test code that is using ldap because we can
 * mock it very easy. It also provides come convenience.
 */
trait LdapOopTrait {

    /**
     * LDAP Object
     *
     * @var object
     */
    protected $ldapConnection;

    public function ldapBind($bind, $password)
    {
        return ldap_bind($this->ldapConnection(), $bind, $password);
    }

    public function ldapFirstEntry($searchResults)
    {
        return ldap_first_entry($this->ldapConnection($searchResults));
    }

    public function ldapSearch($baseDn, $filter)
    {
        return ldap_search($this->ldapConnection(), $baseDn, $filter);
    }

    public function ldapConnection()
    {
        if (empty($this->ldapConnection)) {
            throw new \RuntimeException('You are not connected to a LDAP server.');
        }

        return $this->ldapConnection;
    }

    public function ldapConnect($host, $port = null)
    {
        $this->ldapConnection = ldap_connect($host, $port);
    }

    public function ldapGetAttributes($entity)
    {
        return ldap_get_attributes($this->ldapConnection(), $entity);
    }

    public function ldapSetOption($option, $value)
    {
        ldap_set_option($this->ldapConnection(), $option, $value);
    }

    public function ldapGetOption($option) {
        ldap_get_option($this->ldapConnection(), $option, $extendedError);
        return $extendedError;
    }

    public function ldapClose()
    {
        ldap_close($this->ldapConnection());
        $this->ldapConnection = null;
    }
}
