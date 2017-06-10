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
namespace Authentication\Authenticator;

use ArrayAccess;
use InvalidArgumentException;

/**
 * Authentication result object
 */
class Result implements ResultInterface
{
    /**
     * Authentication result code
     *
     * @var int
     */
    protected $_code;

    /**
     * The identity used in the authentication attempt
     *
     * @var null|\ArrayAccess
     */
    protected $_identity;

    /**
     * An array of string reasons why the authentication attempt was unsuccessful
     *
     * If authentication was successful, this should be an empty array.
     *
     * @var array
     */
    protected $_errors = [];

    /**
     * Sets the result code, identity, and failure messages
     *
     * @param null|\ArrayAccess $identity The identity data
     * @param int $code Error code.
     * @param array $messages Messages.
     */
    public function __construct($identity, $code, array $messages = [])
    {
        if (empty($identity) && $code === self::SUCCESS) {
            throw new InvalidArgumentException('Identity can not be empty with status success.');
        }
        if ($identity !== null && !$identity instanceof ArrayAccess) {
            throw new InvalidArgumentException('Identity must be `null` or an object implementing \ArrayAccess');
        }

        $this->_code = $code;
        $this->_identity = $identity;
        $this->_errors = $messages;
    }

    /**
     * Returns whether the result represents a successful authentication attempt.
     *
     * @return bool
     */
    public function isValid()
    {
        return $this->_code > 0;
    }

    /**
     * Get the result code for this authentication attempt.
     *
     * @return int
     */
    public function getCode()
    {
        return $this->_code;
    }

    /**
     * Returns the identity used in the authentication attempt.
     *
     * @return null|\ArrayAccess
     */
    public function getIdentity()
    {
        return $this->_identity;
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
}
