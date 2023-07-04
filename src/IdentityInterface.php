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
namespace Authentication;

use ArrayAccess;

/**
 * Identity interface
 */
interface IdentityInterface extends ArrayAccess
{
    /**
     * Get the primary key/id field for the identity.
     *
     * @return string|int|array|null
     */
    public function getIdentifier();

    /**
     * Gets the original data object.
     *
     * @return \ArrayAccess|array
     */
    public function getOriginalData();
}
