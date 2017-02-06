<?php
namespace Authentication\Identifier;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Authentication\PasswordHasher\PasswordHasherTrait;
use Cake\Datasource\EntityInterface;
use Cake\ORM\TableRegistry;

/**
 * CakePHP ORM Identifier
 *
 * Identifies authentication credentials using the CakePHP ORM.
 *
 * ```
 *  new OrmIdentifier([
 *      'finder' => ['auth' => ['some_finder_option' => 'some_value']]
 *  ]);
 * ```
 *
 * When configuring OrmIdentifier you can pass in config to which fields,
 * model and additional conditions are used.
 */
class OrmIdentifier extends AbstractIdentifier
{

    use PasswordHasherTrait;

    /**
     * Default configuration
     *
     * @var array
     */
    protected $_defaultConfig = [
        'fields' => [
            'username' => 'username',
            'password' => 'password'
        ],
        'userModel' => 'Users',
        'finder' => 'all',
        'passwordHasher' => DefaultPasswordHasher::class
    ];

    /**
     * Identify
     *
     * @param array $data Authentication credentials
     * @return \Cake\Datasource\EntityInterface|null
     */
    public function identify($data)
    {
        $fields = $this->getConfig('fields');

        if (!isset($data[$fields['username']])) {
            return null;
        }

        $password = null;
        if (!empty($data[$fields['password']])) {
            $password = $data[$fields['password']];
        }

        return $this->_findUser($data[$fields['username']], $password);
    }

    /**
     * Find a user record using the username and password provided.
     * Input passwords will be hashed even when a user doesn't exist. This
     * helps mitigate timing attacks that are attempting to find valid usernames.
     *
     * @param string $username The username/identifier.
     * @param string|null $password The password, if not provided password checking is skipped
     *   and result of find is returned.
     * @return \Cake\Datasource\EntityInterface|null User data entity or null on failure.
     */
    protected function _findUser($username, $password = null)
    {
        $result = $this->_query($username)->first();
        if (empty($result)) {
            return null;
        }

        if ($password !== null) {
            $hasher = $this->passwordHasher();
            $hashedPassword = $result->get($this->_config['fields']['password']);
            if (!$hasher->check($password, $hashedPassword)) {
                return null;
            }

            $this->_needsPasswordRehash = $hasher->needsRehash($hashedPassword);
            $result->unsetProperty($this->_config['fields']['password']);
        }

        return $result;
    }

    /**
     * Get query object for fetching user from database.
     *
     * @param string $username The username/identifier.
     * @return \Cake\ORM\Query
     */
    protected function _query($username)
    {
        $config = $this->_config;
        $table = TableRegistry::get($config['userModel']);

        $options = [
            'conditions' => [$table->aliasField($config['fields']['username']) => $username]
        ];

        $finder = $config['finder'];
        if (is_array($finder)) {
            $options += current($finder);
            $finder = key($finder);
        }

        if (!isset($options['username'])) {
            $options['username'] = $username;
        }

        return $table->find($finder, $options);
    }
}
