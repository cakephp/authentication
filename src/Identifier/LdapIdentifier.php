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
namespace Authentication\Identifier;

use ArrayAccess;
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
 *       'bindDN' => function($username) {
 *           return $username; //transform into a rdn or dn
 *       },
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
    protected array $_defaultConfig = [
        'ldap' => ExtensionAdapter::class,
        'fields' => [
            self::CREDENTIAL_USERNAME => 'username',
            self::CREDENTIAL_PASSWORD => 'password',
        ],
        'port' => 389,
        'options' => [
            'tls' => false,
        ],
    ];

    /**
     * List of errors
     *
     * @var array
     */
    protected array $_errors = [];

    /**
     * LDAP connection object
     *
     * @var \Authentication\Identifier\Ldap\AdapterInterface
     */
    protected AdapterInterface $_ldap;

    /**
     * @inheritDoc
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
    protected function _checkLdapConfig(): void
    {
        if (!isset($this->_config['bindDN'])) {
            throw new RuntimeException('Config `bindDN` is not set.');
        }
        if (!is_callable($this->_config['bindDN'])) {
            throw new InvalidArgumentException(sprintf(
                'The `bindDN` config is not a callable. Got `%s` instead.',
                gettype($this->_config['bindDN'])
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
    protected function _buildLdapObject(): void
    {
        $ldap = $this->_config['ldap'];

        if (is_string($ldap)) {
            $class = App::className($ldap, 'Identifier/Ldap');
            if (!$class) {
                throw new RuntimeException(sprintf(
                    'Could not find LDAP identfier named `%s`',
                    $ldap
                ));
            }
            $ldap = new $class();
        }

        if (!($ldap instanceof AdapterInterface)) {
            $message = sprintf('Option `ldap` must implement `%s`.', AdapterInterface::class);
            throw new RuntimeException($message);
        }

        $this->_ldap = $ldap;
    }

    /**
     * @inheritDoc
     */
    public function identify(array $credentials): ArrayAccess|array|null
    {
        $this->_connectLdap();
        $fields = $this->getConfig('fields');

        $isUsernameSet = isset($credentials[$fields[self::CREDENTIAL_USERNAME]]);
        $isPasswordSet = isset($credentials[$fields[self::CREDENTIAL_PASSWORD]]);
        if ($isUsernameSet && $isPasswordSet) {
            return $this->_bindUser(
                $credentials[$fields[self::CREDENTIAL_USERNAME]],
                $credentials[$fields[self::CREDENTIAL_PASSWORD]]
            );
        }

        return null;
    }

    /**
     * Returns configured LDAP adapter.
     *
     * @return \Authentication\Identifier\Ldap\AdapterInterface
     */
    public function getAdapter(): AdapterInterface
    {
        return $this->_ldap;
    }

    /**
     * Initializes the LDAP connection
     *
     * @return void
     */
    protected function _connectLdap(): void
    {
        $config = $this->getConfig();

        $this->_ldap->connect(
            $config['host'],
            $config['port'],
            (array)$this->getConfig('options')
        );
    }

    /**
     * Try to bind the given user to the LDAP server
     *
     * @param string $username The username
     * @param string $password The password
     * @return \ArrayAccess|null
     */
    protected function _bindUser(string $username, string $password): ?ArrayAccess
    {
        $config = $this->getConfig();
        try {
            $ldapBind = $this->_ldap->bind($config['bindDN']($username), $password);
            if ($ldapBind === true) {
                $this->_ldap->unbind();

                return new ArrayObject([
                    $config['fields'][self::CREDENTIAL_USERNAME] => $username,
                ]);
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
    protected function _handleLdapError(string $message): void
    {
        $extendedError = $this->_ldap->getDiagnosticMessage();
        if (!is_null($extendedError)) {
            $this->_errors[] = $extendedError;
        }
        $this->_errors[] = $message;
    }
}
