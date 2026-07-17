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
 * @since         4.2.2
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Authenticator;

use RuntimeException;

/**
 * Thrown when an authenticator is asked for its identifier but none was
 * configured (and none could be created lazily).
 *
 * Extends `RuntimeException` for backwards compatibility with existing catch
 * blocks; it lets callers distinguish the "no identifier available" case from
 * other runtime failures such as an invalid identifier configuration.
 */
class MissingIdentifierException extends RuntimeException
{
}
