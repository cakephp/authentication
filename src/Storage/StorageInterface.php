<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Storage;

/**
 * Describes the methods that any class representing an Auth data storage should
 * comply with.
 */
interface StorageInterface
{
    /**
     * Read user record.
     *
     * @return mixed
     */
    public function read();

    /**
     * Write user record.
     *
     * @param mixed $user User record.
     * @return void
     */
    public function write($user);

    /**
     * Delete user record.
     *
     * @return void
     */
    public function delete();
}
