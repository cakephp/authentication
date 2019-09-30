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
namespace Authentication\Authenticator;

use ArrayAccess;
use Authentication\Identifier\IdentifierInterface;
use InvalidArgumentException;

/**
 * Authentication result object
 */
class Result implements ResultInterface
{
    /**
     * Authentication result status
     *
     * @var string
     */
    protected $_status;

    /**
     * The identity data used in the authentication attempt
     *
     * @var null|array|\ArrayAccess
     */
    protected $_data;

    /**
     * An array of string reasons why the authentication attempt was unsuccessful
     *
     * If authentication was successful, this should be an empty array.
     *
     * @var array
     */
    protected $_errors = [];

    /**
     * Successful identifier or null.
     * @var \Authentication\Identifier\IdentifierInterface|null
     */
    protected $_identifier = null;

    /**
     * Successful authenticator or null.
     * @var \Authentication\Authenticator\AuthenticatorInterface|null
     */
    protected $_authenticator = null;

    /**
     * Sets the result status, identity, failure messages, successful identifier and authenticator.
     *
     * @param null|array|\ArrayAccess $data The identity data
     * @param string $status Status constant equivalent.
     * @param array $messages Messages.
     * @param \Authentication\Identifier\IdentifierInterface|null $identifier The matching identifier.
     * @param \Authentication\Authenticator\AuthenticatorInterface|null $authenticator The matching authenticator.
     * @throws \InvalidArgumentException When invalid identity data is passed.
     */
    public function __construct($data, $status, array $messages = [], IdentifierInterface $identifier = null, AuthenticatorInterface $authenticator = null)
    {
        if ($status === self::SUCCESS && empty($data)) {
            throw new InvalidArgumentException('Identity data can not be empty with status success.');
        }
        if ($data !== null && !is_array($data) && !($data instanceof ArrayAccess)) {
            $type = is_object($data) ? get_class($data) : gettype($data);
            $message = sprintf(
                'Identity data must be `null`, an `array` or implement `ArrayAccess` interface, `%s` given.',
                $type
            );
            throw new InvalidArgumentException($message);
        }

        $this->_status = $status;
        $this->_data = $data;
        $this->_errors = $messages;
        $this->_identifier = $identifier;
        $this->_authenticator = $authenticator;
    }

    /**
     * Returns whether the result represents a successful authentication attempt.
     *
     * @return bool
     */
    public function isValid()
    {
        return $this->_status === ResultInterface::SUCCESS;
    }

    /**
     * Get the result status for this authentication attempt.
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->_status;
    }

    /**
     * Returns the identity data used in the authentication attempt.
     *
     * @return \ArrayAccess|array|null
     */
    public function getData()
    {
        return $this->_data;
    }

    /**
     * Returns an array of string reasons why the authentication attempt was unsuccessful.
     *
     * If authentication was successful, this method returns an empty array.
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->_errors;
    }

    /**
     * Return null or the identifier who match the correct identity.
     * @return \Authentication\Identifier\IdentifierInterface|null
     */
    public function getIdentifier()
    {
        return $this->_identifier;
    }

    /**
     * Set the identifier who match the correct identity or null.
     * @param \Authentication\Identifier\IdentifierInterface|null $identifier The matching identifier.
     * @return void
     */
    public function setIdentifier(IdentifierInterface $identifier = null)
    {
        $this->_identifier = $identifier;
    }

    /**
     * Return null or the authenticator who match the correct identity.
     * @return \Authentication\Authenticator\AuthenticatorInterface|null
     */
    public function getAuthenticator()
    {
        return $this->_authenticator;
    }

    /**
     * Set the authenticator who match the correct identity or null.
     * @param \Authentication\Authenticator\AuthenticatorInterface|null $authenticator The matching authenticator.
     * @return void
     */
    public function setAuthenticator(AuthenticatorInterface $authenticator = null)
    {
        $this->_authenticator = $authenticator;
    }
}
