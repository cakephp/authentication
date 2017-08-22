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

use Authentication\Identifier\Resolver\ResolverAwareTrait;
use Authentication\Identifier\Resolver\ResolverInterface;
use Authentication\PasswordHasher\PasswordHasherFactory;
use Authentication\PasswordHasher\PasswordHasherTrait;

/**
 * Password Identifier
 *
 * Identifies authentication credentials with password
 *
 * ```
 *  new PasswordIdentifier([
 *      'fields' => [
 *          'username' => ['username', 'email'],
 *          'password' => 'password'
 *      ]
 *  ]);
 * ```
 *
 * When configuring PasswordIdentifier you can pass in config to which fields,
 * model and additional conditions are used.
 */
class PasswordIdentifier extends AbstractIdentifier
{

    use PasswordHasherTrait {
        getPasswordHasher as protected _getPasswordHasher;
    }
    use ResolverAwareTrait;

    /**
     * Default configuration.
     * - `fields` The fields to use to identify a user by:
     *   - `username`: one or many username fields.
     *   - `password`: password field.
     * - `resolver` The resolver implementation to use.
     * - `passwordHasher` Password hasher class. Can be a string specifying class name
     *    or an array containing `className` key, any other keys will be passed as
     *    config to the class. Defaults to 'Default'.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'fields' => [
            'username' => 'username',
            'password' => 'password'
        ],
        'resolver' => 'Authentication.Orm',
        'passwordHasher' => null
    ];

    /**
     * Return password hasher object.
     *
     * @return \Authentication\PasswordHasher\PasswordHasherInterface Password hasher instance.
     */
    public function getPasswordHasher()
    {
        if ($this->_passwordHasher === null) {
            $passwordHasher = $this->getConfig('passwordHasher');
            if ($passwordHasher !== null) {
                $passwordHasher = PasswordHasherFactory::build($passwordHasher);
            } else {
                $passwordHasher = $this->_getPasswordHasher();
            }
            $this->_passwordHasher = $passwordHasher;
        }

        return $this->_passwordHasher;
    }

    /**
     * {@inheritDoc}
     */
    public function identify(array $data)
    {
        $fields = $this->getConfig('fields');

        $usernameFields = (array)$fields['username'];
        $found = array_intersect_key(array_flip($usernameFields), $data);
        if (empty($found)) {
            return null;
        }

        $password = null;
        if (!empty($data[$fields['password']])) {
            $password = $data[$fields['password']];
        }

        return $this->_findIdentity($data[key($found)], $password);
    }

    /**
     * Find a user record using the username and password provided.
     * Input passwords will be hashed even when a user doesn't exist. This
     * helps mitigate timing attacks that are attempting to find valid usernames.
     *
     * @param string $identifier The username/identifier.
     * @param string|null $password The password, if not provided password checking is skipped
     *   and result of find is returned.
     * @return \ArrayAccess|null User data entity or null on failure.
     */
    protected function _findIdentity($identifier, $password = null)
    {
        $result = $this->_findUser($identifier);
        if (empty($result)) {
            return null;
        }

        if ($password !== null) {
            $passwordField = $this->getConfig('fields.password');
            $hasher = $this->getPasswordHasher();
            $hashedPassword = $result[$passwordField];
            if (!$hasher->check($password, $hashedPassword)) {
                return null;
            }

            $this->_needsPasswordRehash = $hasher->needsRehash($hashedPassword);
            unset($result[$passwordField]);
        }

        return $result;
    }

    /**
     * Get query object for fetching user from database.
     *
     * @param string $identifier The username/identifier.
     * @return \ArrayAccess|null
     */
    protected function _findUser($identifier)
    {
        $fields = $this->getConfig('fields.username');
        $conditions = [];
        foreach ((array)$fields as $field) {
            $conditions[$field] = $identifier;
        }

        return $this->getResolver()->find($conditions, ResolverInterface::TYPE_OR);
    }
}
