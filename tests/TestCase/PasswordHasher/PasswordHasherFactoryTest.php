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

use Authentication\PasswordHasher\PasswordHasherFactory;
use Cake\TestSuite\TestCase;

/**
 * Test case for PasswordHasherFactory
 */
class PasswordHasherFactoryTest extends TestCase
{
    /**
     * test passwordhasher instance building
     *
     * @return void
     */
    public function testBuild()
    {
        $hasher = PasswordHasherFactory::build('Authentication.Default');
        $this->assertInstanceof('Authentication\PasswordHasher\DefaultPasswordHasher', $hasher);

        $hasher = PasswordHasherFactory::build([
            'className' => 'Authentication.Default',
            'hashOptions' => ['foo' => 'bar'],
        ]);
        $this->assertInstanceof('Authentication\PasswordHasher\DefaultPasswordHasher', $hasher);
        $this->assertEquals(['foo' => 'bar'], $hasher->getConfig('hashOptions'));

        $this->loadPlugins(['TestPlugin']);
        $hasher = PasswordHasherFactory::build('TestPlugin.Legacy');
        $this->assertInstanceof('TestPlugin\PasswordHasher\LegacyPasswordHasher', $hasher);
    }

    /**
     * test build() throws exception for non existent hasher
     *
     * @return void
     */
    public function testBuildMissingHasher()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Password hasher class `FooBar` was not found.');
        $hasher = PasswordHasherFactory::build('FooBar');
    }

    /**
     * test build() throws exception for non existent hasher
     *
     * @return void
     */
    public function testBuildInvalidHasher()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Password hasher must implement PasswordHasherInterface.');
        $hasher = PasswordHasherFactory::build('Invalid');
    }
}
