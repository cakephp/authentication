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
 * @license       https://www.opensource.org/licenses/mit-license.php MIT License
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
