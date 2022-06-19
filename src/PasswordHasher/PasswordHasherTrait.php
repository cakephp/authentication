<?php
declare(strict_types=1);

namespace Authentication\PasswordHasher;

trait PasswordHasherTrait
{
    /**
     * Password hasher instance.
     *
     * @var \Authentication\PasswordHasher\PasswordHasherInterface|null
     */
    protected ?PasswordHasherInterface $_passwordHasher = null;

    /**
     * Whether or not the user authenticated by this class
     * requires their password to be rehashed with another algorithm.
     *
     * @var bool
     */
    protected bool $_needsPasswordRehash = false;

    /**
     * Return password hasher object.
     * If a password hasher has not been set, DefaultPasswordHasher instance is returned.
     *
     * @return \Authentication\PasswordHasher\PasswordHasherInterface Password hasher instance.
     */
    public function getPasswordHasher(): PasswordHasherInterface
    {
        if ($this->_passwordHasher === null) {
            $this->_passwordHasher = new DefaultPasswordHasher();
        }

        return $this->_passwordHasher;
    }

    /**
     * Sets password hasher object.
     *
     * @param \Authentication\PasswordHasher\PasswordHasherInterface $passwordHasher Password hasher instance.
     * @return $this
     */
    public function setPasswordHasher(PasswordHasherInterface $passwordHasher)
    {
        $this->_passwordHasher = $passwordHasher;

        return $this;
    }

    /**
     * Returns whether or not the password stored in the repository for the logged in user
     * requires to be rehashed with another algorithm
     *
     * @return bool
     */
    public function needsPasswordRehash(): bool
    {
        return $this->_needsPasswordRehash;
    }
}
