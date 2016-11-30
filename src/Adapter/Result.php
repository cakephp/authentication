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
 * @since         4.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Adapter;

use Cake\Datasource\EntityInterface;
use InvalidArgumentException;

/**
 * Authentication result object
 */
class Result implements ResultInterface
{
    /**
     * General Failure
     */
    const FAILURE = 0;

    /**
     * Failure due to identity not being found.
     */
    const FAILURE_IDENTITY_NOT_FOUND = -1;

    /**
     * Failure due to invalid credential being supplied.
     */
    const FAILURE_CREDENTIAL_INVALID = -2;

    /**
     * Failure due to other circumstances.
     */
    const FAILURE_OTHER = -3;

    /**
     * Authentication success.
     */
    const SUCCESS = 1;

    /**
     * Authentication result code
     *
     * @var int
     */
    protected $code;

    /**
     * The identity used in the authentication attempt
     *
     * @var null|\Cake\Datasource\EntityInterface
     */
    protected $identity;

    /**
     * An array of string reasons why the authentication attempt was unsuccessful
     *
     * If authentication was successful, this should be an empty array.
     *
     * @var array
     */
    protected $errors = [];

    /**
     * Sets the result code, identity, and failure messages
     *
     * @param null|\Cake\Datasource\EntityInterface $identity The identity data
     * @param int $code Error code.
     * @param array $messages Messages.
     */
    public function __construct($identity, $code, array $messages = [])
    {
        if (empty($identity) && $code === self::SUCCESS) {
            throw new InvalidArgumentException('Identity can no be empty with status success.');
        }
        if ($identity !== null && !$identity instanceof EntityInterface) {
            throw new InvalidArgumentException('Identity must be NULL or an object implementing \Cake\Datasource\EntityInterface');
        }

        $this->code = (int)$code;
        $this->identity = $identity;
        $this->errors = $messages;
    }

    /**
     * Returns whether the result represents a successful authentication attempt.
     *
     * @return bool
     */
    public function isValid()
    {
        return $this->code > 0;
    }

    /**
     * Get the result code for this authentication attempt.
     *
     * @return int
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Returns the identity used in the authentication attempt.
     *
     * @return null|\Cake\Datasource\EntityInterface
     */
    public function getIdentity()
    {
        return $this->identity;
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
        return $this->errors;
    }
}
