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
use Authentication\Identifier\Resolver\ResolverAwareTrait;
use Authentication\Identifier\Resolver\ResolverInterface;
use Cake\Core\App;
use Cake\ORM\TableRegistry;
use Cake\Utility\Security;
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
 *       'lookupBindDN' => 'cn=read-only-admin,dc=example,dc=com',
 *       'lookupBindPassword' => 'password',
 *       'baseDN' => 'dc=example,dc=com',
 *       'filter' => function($uid) {
 *           return str_replace("%uid", $uid,
 *               "(&(&(|(objectclass=person)))(|(uid=%uid)(samaccountname=%uid)(|(mailPrimaryAddress=%uid)(mail=%uid))))");
 *           },
 *       'updateLocalIdentity' => function($identity, $ldap_attributes) {
 *
 *          return $identity;
 *       },
 *       'createLocalIdentityIfMissing' => function($ldap_attributes) {
 *          $Users = TableRegistry::getTableLocator()->get('Users');
 *          $identity = $Users->newEntity();
 *          $identity->username = $ldap_attributes['uid'][0];
 *          $identity->password = Security::randomString();
 *          $Users->save($identity);
 *
 *          return $identity;
 *       }
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

    use ResolverAwareTrait;

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
        'resolver' => 'Authentication.Orm',
        'matchingField' => self::CREDENTIAL_LDAP_ATTRIBUTE
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
        if (!isset($this->_config['host'])) {
            throw new RuntimeException('Config `host` is not set.');
        }

        if (!isset($this->_config['bindDN']) && (!isset($this->_config['filter']))) {
            throw new RuntimeException('Config `bindDN` is not set.');
        }

        if (isset($this->_config['bindDN'])) {
            if (!is_callable($this->_config['bindDN'])) {
                throw new InvalidArgumentException(sprintf(
                    'The `bindDN` config is not a callable. Got `%s` instead.',
                    gettype($this->_config['bindDN'])
                ));
            }
        } else {
            if (!isset($this->_config['filter'])) {
                throw new RuntimeException('Config `filter` is not set.');
            }

            if (!is_callable($this->_config['filter'])) {
                throw new InvalidArgumentException(sprintf(
                    'The `filter` config is not a callable. Got `%s` instead.',
                    gettype($this->_config['filter'])
                ));
            }

            if (!isset($this->_config['lookupBindPassword'])) {
                throw new RuntimeException('Config `lookupBindPassword` is not set.');
            }

            if (!isset($this->_config['lookupBindPassword'])) {
                throw new RuntimeException('Config `lookupBindPassword` is not set.');
            }

            if (!isset($this->_config['baseDN'])) {
                throw new RuntimeException('Config `baseDN` is not set.');
            }

            if (isset($this->_config['updateLocalIdentity'])) {
                if (!($this->_config['updateLocalIdentity'] instanceof Closure)) {
                    throw new InvalidArgumentException(sprintf(
                        'The `updateLocalIdentity` config is not a callable. Got `%s` instead.',
                        gettype($this->_config['updateLocalIdentity'])
                    ));
                }
            }

            if (isset($this->_config['createLocalIdentityIfMissing'])) {
                if (!($this->_config['createLocalIdentityIfMissing'] instanceof Closure)) {
                    throw new InvalidArgumentException(sprintf(
                        'The `createLocalIdentityIfMissing` config is not a callable. Got `%s` instead.',
                        gettype($this->_config['createLocalIdentityIfMissing'])
                    ));
                }
            }
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
        $bindDN = $this->getConfig('bindDN');

        if (isset($data[$fields[self::CREDENTIAL_USERNAME]]) && isset($data[$fields[self::CREDENTIAL_PASSWORD]]) && isset($bindDN)) {
            return $this->_bindUser($data[$fields[self::CREDENTIAL_USERNAME]], $data[$fields[self::CREDENTIAL_PASSWORD]]);
        } elseif (isset($data[$fields[self::CREDENTIAL_USERNAME]]) && isset($data[$fields[self::CREDENTIAL_PASSWORD]])) {
            $bindResult = $this->_bindUserUsingLookup($data[$fields[self::CREDENTIAL_USERNAME]], $data[$fields[self::CREDENTIAL_PASSWORD]]);

            if ($bindResult) {
                $matchingField = $this->getConfig('matchingField');
                if (!isset($bindResult['ldapAttributes'][$matchingField]) || empty($bindResult['ldapAttributes'][$matchingField])) {
                    return null;
                }

                $identity = null;
                if (is_string($bindResult['ldapAttributes'][$matchingField])) {
                    $identity = $this->_findIdentity($bindResult['ldapAttributes'][$matchingField]);
                } elseif (is_array($bindResult['ldapAttributes'][$matchingField])) {
                    foreach ($bindResult['ldapAttributes'][$matchingField] as $attribute_value) {
                        $identity = $this->_findIdentity($attribute_value);
                        if ($identity !== null) {
                            break;
                        }
                    }
                }

                $updateLocalIdentity = $this->getConfig('updateLocalIdentity');
                $createLocalIdentityIfMissing = $this->getConfig('createLocalIdentityIfMissing');

                if ($identity !== null) {
                    if (is_callable($updateLocalIdentity)) {
                        $identity = $updateLocalIdentity($identity, $bindResult['ldapAttributes']);
                    }

                    return $identity;
                } else {
                    if (is_callable($createLocalIdentityIfMissing)) {
                        $identity = $createLocalIdentityIfMissing($bindResult['ldapAttributes']);

                        return $identity;
                    }
                }
            }
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
            $ldapBind = $this->_ldap->bind($config['bindDN']($username), $password);
            if ($ldapBind === true) {
                $this->_ldap->unbind();

                return new ArrayObject([
                    $config['fields'][self::CREDENTIAL_USERNAME] => $username
                ]);
            }
        } catch (ErrorException $e) {
            $this->_handleLdapError($e->getMessage());
        }
        $this->_ldap->unbind();

        return null;
    }

    /**
     * Try to bind the given user to the LDAP server using a lookup account
     *
     * @param string $username The username
     * @param string $password The password
     * @return \ArrayAccess|null
     */
    protected function _bindUserUsingLookup($username, $password)
    {
        $config = $this->getConfig();

        try {
            $ldapBind = $this->_ldap->bind($config['lookupBindDN'], $config['lookupBindPassword']);

            if ($ldapBind === true) {
                $entries = $this->_ldap->search($config['baseDN'], $config['filter']($username));

                for ($i = 0; $i < $entries['count']; $i++) {
                    $userLdapBind = $this->_ldap->bind($entries[$i]['dn'], $password);

                    if ($userLdapBind === true) {
                        $this->_ldap->unbind();

                        return new ArrayObject([
                            $config['fields'][self::CREDENTIAL_USERNAME] => $username,
                            'ldapAttributes' => $this->_formatAttributes($entries[$i])
                        ]);
                    }
                }
            }
        } catch (ErrorException $e) {
            $this->_handleLdapError($e->getMessage());
        }
        $this->_ldap->unbind();

        return null;
    }

    /**
     * Format LDAP attribute data into associative array
     *
     * @param array $attributes LDAP entry attributes
     * @return array
     */
    protected function _formatAttributes($attributes)
    {
        $formatted = [];
        foreach ($attributes as $name => $values) {
            if (is_array($values)) {
                foreach ($values as $k => $value) {
                    if ($k !== 'count') {
                        $formatted[$name][] = $value;
                    }
                }
            } elseif (is_string($name) && $name !== 'count') {
                $formatted[$name] = $values;
            }
        }

        return $formatted;
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
        if ($extendedError !== null) {
            $this->_errors[] = $extendedError;
        }
        $this->_errors[] = $message;
    }

    /**
     * Find a user record using the LDAP data provided.
     *
     * @param string $identifier The username/identifier.
     * @return \ArrayAccess|array|null
     */
    protected function _findIdentity($identifier)
    {
        $fields = $this->getConfig('fields.' . self::CREDENTIAL_USERNAME);
        $conditions = [];
        foreach ((array)$fields as $field) {
            $conditions[$field] = $identifier;
        }

        return $this->getResolver()->find($conditions, ResolverInterface::TYPE_OR);
    }
}
