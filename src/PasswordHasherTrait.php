<?php
namespace Auth;

trait PasswordHasherTrait
{

    /**
     * Password hasher instance.
     *
     * @var \Cake\Auth\AbstractPasswordHasher
     */
    protected $_passwordHasher;

    /**
     * Whether or not the user authenticated by this class
     * requires their password to be rehashed with another algorithm.
     *
     * @var bool
     */
    protected $_needsPasswordRehash = false;

    /**
     * Return password hasher object
     *
     * @return \Cake\Auth\AbstractPasswordHasher Password hasher instance
     * @throws \RuntimeException If password hasher class not found or
     *   it does not extend AbstractPasswordHasher
     */
    public function passwordHasher()
    {
        if ($this->_passwordHasher) {
            return $this->_passwordHasher;
        }

        $passwordHasher = $this->_config['passwordHasher'];

        return $this->_passwordHasher = PasswordHasherFactory::build($passwordHasher);
    }

    /**
     * Returns whether or not the password stored in the repository for the logged in user
     * requires to be rehashed with another algorithm
     *
     * @return bool
     */
    public function needsPasswordRehash()
    {
        return $this->_needsPasswordRehash;
    }
}
