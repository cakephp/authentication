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
namespace Authentication\Authenticator;

use ArrayAccess;
use InvalidArgumentException;

/**
 * Authentication result object
 */
class Result implements ResultInterface
{
    /**
     * Authentication result status
     */
    protected string $status;

    /**
     * The identity data used in the authentication attempt
     */
    protected ArrayAccess|array|null $data = null;

    /**
     * Sets the result status, identity, and failure messages
     *
     * @param \ArrayAccess|array|null $data The identity data
     * @param string $status Status constant equivalent.
     * @param array $errors Messages.
     * @throws \InvalidArgumentException When invalid identity data is passed.
     */
    public function __construct(ArrayAccess|array|null $data, string $status, protected array $errors = [])
    {
        if ($status === self::SUCCESS && empty($data)) {
            throw new InvalidArgumentException('Identity data can not be empty with status success.');
        }

        $this->status = $status;
        $this->data = $data;
    }

    /**
     * Returns whether the result represents a successful authentication attempt.
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->status === ResultInterface::SUCCESS;
    }

    /**
     * Get the result status for this authentication attempt.
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Returns the identity data used in the authentication attempt.
     *
     * @return \ArrayAccess|array|null
     */
    public function getData(): ArrayAccess|array|null
    {
        return $this->data;
    }

    /**
     * Returns an array of string reasons why the authentication attempt was unsuccessful.
     *
     * If authentication was successful, this method returns an empty array.
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
