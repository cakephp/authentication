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

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Authentication\PasswordHasher\PasswordHasherTrait;
use Cake\Core\Exception\Exception;
use Cake\Network\Exception\InternalErrorException;
use Cake\ORM\TableRegistry;
use ErrorException;

/**
 * LDAP Identifier
 *
 * @link https://github.com/QueenCityCodeFactory/LDAP
 */
class LdapIdentifier extends AbstractIdentifier
{

    use LdapOopTrait;
    use PasswordHasherTrait;

    /**
     * Default configuration
     *
     * @var array
     */
    protected $_defaultConfig = [
        'logErrors' => false,
        'port' => null,
        'dataField' => 'email',
        'model' => 'Users',
        'finder' => 'all',
        'passwordHasher' => DefaultPasswordHasher::class
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

        if (isset($config['host']) && is_object($config['host']) && ($config['host'] instanceof \Closure)) {
            $config['host'] = $config['host']();
        }

        if (empty($config['host'])) {
            throw new InternalErrorException('LDAP Server not specified');
        }

        parent::__construct($config);

        $this->_connectLdap();
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
     * {@inheritDoc}
     */
    public function identify($data)
    {
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
                return $this->_orm($username);
            }
        } catch (ErrorException $e) {
            if ($this->_config['logErrors'] === true) {
                $this->log($e->getMessage());
            }
            $this->_handleLdapError();
        }

        return false;
    }

    /**
     * Gets an user from the LDAP connection
     *
     * @param string $username
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
     * Lookup the username in the ORM
     *
     * @param string $username The username string.
     * @return \Cake\Datasource\EntityInterface|null
     */
    protected function _orm($username)
    {
        $config = $this->_config;
        $table = TableRegistry::get($config['model']);

        $options = [
            'conditions' => [$table->aliasField($config['dataField']) => $username]
        ];

        $finder = $config['finder'];
        if (is_array($finder)) {
            $options += current($finder);
            $finder = key($finder);
        }

        $result = $table->find($finder, $options)->first();
        if (empty($result)) {
            return null;
        }

        return $result;
    }

    /**
     * Transform the result of the user search
     *
     * @param string $attr
     * @return string|null
     */
    protected function _transformResult($attr)
    {
        foreach($this->_config['filters'] as $key => $filter) {
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
        $this->_errors[] = $extendedError;
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        set_error_handler(
            function ($errorNumber, $errorText, $errorFile, $errorLine) {
                throw new ErrorException($errorText, 0, $errorNumber, $errorFile, $errorLine);
            },
            E_ALL
        );

        try {
            $this->ldapClose();
        } catch (ErrorException $e) {
            // Do Nothing
        }

        restore_error_handler();
    }
}
