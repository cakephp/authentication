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
namespace Authentication\Identifier;

use ArrayAccess;

interface IdentifierInterface
{
    /**
     * Identifies a user or service by the passed credentials
     *
     * @param array<string, mixed> $credentials Authentication credentials
     * @return \ArrayAccess<string, mixed>|array<string, mixed>|null
     */
    public function identify(array $credentials): ArrayAccess|array|null;

    /**
     * Gets a list of errors happened in the identification process
     *
     * @return array<string>
     */
    public function getErrors(): array;
}
