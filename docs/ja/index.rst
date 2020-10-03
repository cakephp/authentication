unauthenticated users are able to access it::

    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);

        $this->Authentication->allowUnauthenticated(['login']);
    }

Then add a simple logout action::

    public function logout()
    {
        $this->Authentication->logout();
        return $this->redirect(['controller' => 'Users', 'action' => 'login']);
    }

In order to login your users will need to have hashed passwords. You can
automatically hash passwords when users update their password using an entity
setter method::

    // in src/Model/Entity/User.php
    use Authentication\PasswordHasher\DefaultPasswordHasher;

    class User extends Entity
    {
        // ... other methods

        // Automatically hash passwords when they are changed.
        protected function _setPassword(string $password)
        {
            $hasher = new DefaultPasswordHasher();
            return $hasher->hash($password);
        }
    }


Further Reading
===============

.. toctree::
    :maxdepth: 1

    /authenticators
    /identifiers
    /password-hashers
    /identity-object
    /authentication-component
    /migration-from-the-authcomponent
    /url-checkers
    /testing
    /view-helper

