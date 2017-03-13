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

/**
 * Provides a very thin OOP wrapper around the ldap_* functions.
 *
 * We don't need and want a huge LDAP lib for our purpose.
 *
 * But this makes it easier to unit test code that is using ldap because we can
 * mock it very easy. It also provides some convenience.
 */
trait LdapOopTrait {

    /**
     * LDAP Object
     *
     * @var object
     */
    protected $ldapConnection;

    /**
     * Binds the connection
     *
     * @param string $bind
     * @param string $password
     * @return
     */
    public function ldapBind($bind, $password)
    {
        return ldap_bind($this->getLdapConnection(), $bind, $password);
    }

    public function ldapFirstEntry($searchResults)
    {
        return ldap_first_entry($this->getLdapConnection(), $searchResults);
    }

    public function ldapSearch($filter, $attribute)
    {
        return ldap_search($this->getLdapConnection(), $this->_config['baseDN'], $filter, $attribute);
    }

    public function getLdapConnection()
    {
        if (empty($this->ldapConnection)) {
            throw new \RuntimeException('You are not connected to a LDAP server.');
        }

        return $this->ldapConnection;
    }

    /**
     * Connect
     *
     * @param string $host
     * @param int|null $port
     * @return void
     */
    public function ldapConnect($host, $port = null)
    {
        $this->ldapConnection = ldap_connect($host, $port);
    }

    public function ldapGetAttributes($entry)
    {
        return ldap_get_attributes($this->getLdapConnection(), $entry);
    }

    /**
     * Set an LDAP option
     */
    public function ldapSetOption($option, $value)
    {
        ldap_set_option($this->getLdapConnection(), $option, $value);
    }

    /**
     * Get an LDAP option
     */
    public function ldapGetOption($option)
    {
        ldap_get_option($this->getLdapConnection(), $option, $extendedError);

        return $extendedError;
    }

    /**
     * Closes the LDAP connection
     *
     * @return void
     */
    public function ldapClose()
    {
        ldap_close($this->ldapConnection);
        $this->ldapConnection = null;
    }
}
