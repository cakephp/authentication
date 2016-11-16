<?php
namespace Auth\Authentication\Identifier;

use Auth\PasswordHasher\DefaultPasswordHasher;
use Auth\PasswordHasherTrait;
use Cake\ORM\TableRegistry;

class OrmIdentifier extends AbstractIdentifier {

    use PasswordHasherTrait;

    protected $_defaultConfig = [
        'fields' => [
            'username' => 'username',
            'password' => 'password'
        ],
        'userModel' => 'Users',
        'scope' => [],
        'finder' => 'all',
        'contain' => null,
        'passwordHasher' => DefaultPasswordHasher::class
    ];

    public function identify($data) {
        $fields = $this->config('fields');
        if (!isset($data[$fields['username']]) || !isset($data[$fields['password']])) {
            return false;
        }

        return $this->_findUser($data[$fields['username']], $data[$fields['password']]);
    }

    /**
     * Find a user record using the username and password provided.
     * Input passwords will be hashed even when a user doesn't exist. This
     * helps mitigate timing attacks that are attempting to find valid usernames.
     *
     * @param string $username The username/identifier.
     * @param string|null $password The password, if not provided password checking is skipped
     *   and result of find is returned.
     * @return bool|array Either false on failure, or an array of user data.
     */
    protected function _findUser($username, $password = null)
    {
        $result = $this->_query($username)->first();
        if (empty($result)) {
            return false;
        }

        if ($password !== null) {
            $hasher = $this->passwordHasher();
            $hashedPassword = $result->get($this->_config['fields']['password']);
            if (!$hasher->check($password, $hashedPassword)) {
                return false;
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
    protected function _query($username) {
        $config = $this->_config;
        $table = TableRegistry::get($config['userModel']);

        $options = [
            'conditions' => [$table->aliasField($config['fields']['username']) => $username]
        ];

        if (!empty($config['scope'])) {
            $options['conditions'] = array_merge($options['conditions'], $config['scope']);
        }
        if (!empty($config['contain'])) {
            $options['contain'] = $config['contain'];
        }

        $finder = $config['finder'];
        if (is_array($finder)) {
            $options += current($finder);
            $finder = key($finder);
        }

        if (!isset($options['username'])) {
            $options['username'] = $username;
        }

        $query = $table->find($finder, $options);

        return $query;
    }
}
