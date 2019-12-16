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
use Authentication\PasswordHasher\PasswordHasherInterface;
use Authentication\PasswordHasher\PasswordHasherTrait;
use Cake\TestSuite\TestCase;

/**
 * Test case for PasswordHasherTrait
 */
class PasswordHasherTraitTest extends TestCase
{
    /**
     * testGetPasswordHasher
     *
     * @return void
     */
    public function testGetPasswordHasher()
    {
        $object = $this->getMockForTrait(PasswordHasherTrait::class);

        $defaultHasher = $object->getPasswordHasher();
        $this->assertInstanceOf(DefaultPasswordHasher::class, $defaultHasher);
    }

    /**
     * testSetPasswordHasher
     *
     * @return void
     */
    public function testSetPasswordHasher()
    {
        $hasher = $this->createMock(PasswordHasherInterface::class);
        $object = $this->getMockForTrait(PasswordHasherTrait::class);

        $object->setPasswordHasher($hasher);
        $passwordHasher = $object->getPasswordHasher();
        $this->assertSame($hasher, $passwordHasher);
    }
}
