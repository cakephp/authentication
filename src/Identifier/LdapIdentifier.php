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

/**
 * LDAP Identifier
 *
 * Identifies authentication credentials using LDAP.
 *
 * ```
 *  new LdapIdentifier([
 *      'fields' => [
 *          'username' => 'email',
 *          'password' => 'password'
 *       ],
 *       'port' => '389',
 *       'host' => 'ldap.example.com',
 *       'baseDN' => 'dc=example, dc=com',
 *       'bindDN' => 'ou=my-ou,dc=example, dc=com',
 *       'search' => 'mail',
 *       'filters' => ['mail'],
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
        ]
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
        if (!defined('LDAP_OPT_DIAGNOSTIC_MESSAGE')) {
            define('LDAP_OPT_DIAGNOSTIC_MESSAGE', 0x0032);
        }
        parent::__construct($config);
    }

    /**
     * {@inheritDoc}
     */
    public function identify($data)
    {
        $this->_connectLdap();
        $fields = $this->getConfig('fields');

        if (isset($data[$fields['username']]) && isset($data[$fields['password']])) {
            return $this->_findUser($data[$fields['username']], $data[$fields['password']]);
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
     * Initializes the Ldap connection
     *
     * @return void
     */
    protected function _connectLdap()
    {
        $config = $this->getConfig();

        try {
            $this->ldapConnect($config['host'], $config['port']);
            if (isset($config['options']) && is_array($config['options'])) {
                foreach ($config['options'] as $option => $value) {
                    $this->ldapSetOption($option, $value);
                }
            } else {
                $this->ldapSetOption(LDAP_OPT_NETWORK_TIMEOUT, 5);
            }
        } catch (Exception $e) {
            throw new InternalErrorException('Unable to connect to specified LDAP Server(s)');
        }
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
        try {
            $ldapBind = $this->ldapBind($this->_config['bindDN'], $password);
            if ($ldapBind === true) {
                $username = $this->_getUserFromLdap($username);
                $this->ldapUnbind();

                return new Entity([
                    $this->_config['fields']['username'] => $username
                ]);
            }
        } catch (ErrorException $e) {
            $this->_handleLdapError();
        }

        return false;
    }

    /**
     * Gets an user from the LDAP connection
     *
     * @param string $username The username to lookup
     * @return string|null
     * @author Michael Hoffmann
     */
    protected function _getUserFromLdap($username)
    {
        $searchResults = $this->ldapSearch(
            '(' . $this->_config['search'] . '=' . $username . ')',
            $this->_config['filters']
        );

        $entry = $this->ldapFirstEntry($searchResults);
        $attr = $this->ldapGetAttributes($entry);

        return $this->_transformResult($attr);
    }

    /**
     * Transform the result of the user search
     *
     * @param string $attr The atrributes to transform
     * @return string|null
     */
    protected function _transformResult($attr)
    {
        foreach ($this->_config['filters'] as $key => $filter) {
            if (array_key_exists($filter, $attr)) {
                return $attr[$filter][0];
            }
        }

        return null;
    }

    /**
     * Handles an Ldap error
     *
     * @return array Array of error messages
     */
    protected function _handleLdapError()
    {
        $extendedError = $this->ldapGetOption(LDAP_OPT_DIAGNOSTIC_MESSAGE);
        if (!is_null($extendedError)) {
            $this->_errors[] = $extendedError;
        }
    }
}
