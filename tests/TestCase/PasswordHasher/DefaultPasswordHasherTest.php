<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link http://cakephp.org CakePHP(tm) Project
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Test\TestCase\PasswordHasher;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\TestCase;

/**
 * Test case for DefaultPasswordHasher
 */
class DefaultPasswordHasherTest extends TestCase
{
    /**
     * Tests that a password not produced by DefaultPasswordHasher needs
     * to be rehashed
     *
     * @return void
     */
    public function testNeedsRehash()
    {
        $hasher = new DefaultPasswordHasher();
        $this->assertTrue($hasher->needsRehash(md5('foo')));
        $password = $hasher->hash('foo');
        $this->assertFalse($hasher->needsRehash($password));
    }

    /**
     * Tests that when the hash options change, the password needs
     * to be rehashed
     *
     * @return void
     */
    public function testNeedsRehashWithDifferentOptions()
    {
        $defaultHasher = new DefaultPasswordHasher(['hashType' => PASSWORD_BCRYPT, 'hashOptions' => ['cost' => 10]]);
        $updatedHasher = new DefaultPasswordHasher(['hashType' => PASSWORD_BCRYPT, 'hashOptions' => ['cost' => 12]]);
        $password = $defaultHasher->hash('foo');

        $this->assertTrue($updatedHasher->needsRehash($password));
    }
}
