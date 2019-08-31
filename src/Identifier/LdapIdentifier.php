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
namespace Authentication\Identifier;

use ArrayObject;
use Authentication\Identifier\Ldap\AdapterInterface;
use Authentication\Identifier\Ldap\ExtensionAdapter;
use Cake\Core\App;
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
 *       'port' => '389',
 *       'bindDN' => 'uid=read-only-admin,dc=example,dc=com',
 *       'bindPassword' => 'password',
 *       'filter' => function($uid) {
 *           return str_replace("%uid", $uid,
 *               "(&(&(|(objectclass=person)))(|(samaccountname=%uid)(|(mailPrimaryAddress=%uid)(mail=%uid))))");
 *           },
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
        'ldap' => ExtensionAdapter::class,
        'fields' => [
            self::CREDENTIAL_USERNAME => 'username',
            self::CREDENTIAL_PASSWORD => 'password'
        ],
        'port' => 389,
        'filter' => function($uid) {
            return str_replace("%uid", $uid,
                "(&(&(|(objectclass=person)))(|(samaccountname=%uid)(|(mailPrimaryAddress=%uid)(mail=%uid))))");
         },
        'options' => [LDAP_OPT_PROTOCOL_VERSION => 3]
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
     * @var \Authentication\Identifier\Ldap\AdapterInterface
     */
    protected $_ldap = null;

    /**
     * {@inheritDoc}
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        $this->_checkLdapConfig();
        $this->_buildLdapObject();
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
        if (!isset($this->_config['filter'])) {
            throw new RuntimeException('Config `bindDN` is not set.');
        }
        if (!is_callable($this->_config['filter'])) {
            throw new InvalidArgumentException(sprintf(
                'The `filter` config is not a callable. Got `%s` instead.',
                gettype($this->_config['filter'])
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
        $ldap = $this->_config['ldap'];

        if (is_string($ldap)) {
            $class = App::className($ldap, 'Identifier/Ldap');
            $ldap = new $class();
        }

        if (!($ldap instanceof AdapterInterface)) {
            $message = sprintf('Option `ldap` must implement `%s`.', AdapterInterface::class);
            throw new RuntimeException($message);
        }

        $this->_ldap = $ldap;
    }

    /**
     * {@inheritDoc}
     */
    public function identify(array $data)
    {
        $this->_connectLdap();
        $fields = $this->getConfig('fields');

        if (isset($data[$fields[self::CREDENTIAL_USERNAME]]) && isset($data[$fields[self::CREDENTIAL_PASSWORD]])) {
            return $this->_bindUser($data[$fields[self::CREDENTIAL_USERNAME]], $data[$fields[self::CREDENTIAL_PASSWORD]]);
        }

        return null;
    }

    /**
     * Returns configured LDAP adapter.
     *
     * @return \Authentication\Identifier\Ldap\AdapterInterface
     */
    public function getAdapter()
    {
        return $this->_ldap;
    }

    /**
     * Initializes the LDAP connection
     *
     * @return void
     */
    protected function _connectLdap()
    {
        $config = $this->getConfig();

        $this->_ldap->connect(
            $config['host'],
            $config['port'],
            $this->getConfig('options')
        );
    }

    /**
     * Try to bind the given user to the LDAP server
     *
     * @param string $username The username
     * @param string $password The password
     * @return \ArrayAccess|null
     */
    protected function _bindUser($username, $password)
    {
        $config = $this->getConfig();
        try {
            $ldapBind = $this->_ldap->bind($config['bindDN'], $config['bindPassword']);
            if ($ldapBind === true) {
                
                $entries = $this->_ldap->search($config['baseDN'], $config['filter']($username));
                
                if (isset($entries[0]['dn']) && $this->_ldap->bind($entries[0]['dn'], $password)) {
                        
                    $this->_ldap->unbind();

                    return new ArrayObject([
                        $config['fields'][self::CREDENTIAL_USERNAME] => $username
                    ]);   
                }
            }
        } catch (ErrorException $e) {
            $this->_handleLdapError($e->getMessage());
        }
        $this->_ldap->unbind();

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
        $extendedError = $this->_ldap->getDiagnosticMessage();
        if (!is_null($extendedError)) {
            $this->_errors[] = $extendedError;
        }
        $this->_errors[] = $message;
    }
}
