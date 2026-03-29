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
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link https://cakephp.org CakePHP(tm) Project
 * @license https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Test\TestCase\PasswordHasher;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Authentication\PasswordHasher\PasswordHasherFactory;
use Cake\TestSuite\TestCase;
use TestPlugin\PasswordHasher\LegacyPasswordHasher;

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
    public function testBuild(): void
    {
        $hasher = PasswordHasherFactory::build('Authentication.Default');
        $this->assertInstanceof(DefaultPasswordHasher::class, $hasher);

        $hasher = PasswordHasherFactory::build([
            'className' => 'Authentication.Default',
            'hashOptions' => ['foo' => 'bar'],
        ]);
        $this->assertInstanceof(DefaultPasswordHasher::class, $hasher);
        $this->assertEquals(['foo' => 'bar'], $hasher->getConfig('hashOptions'));

        $this->loadPlugins(['TestPlugin']);
        $hasher = PasswordHasherFactory::build('TestPlugin.Legacy');
        $this->assertInstanceof(LegacyPasswordHasher::class, $hasher);
    }

    /**
     * test build() throws exception for non existent hasher
     *
     * @return void
     */
    public function testBuildMissingHasher(): void
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Password hasher class `FooBar` was not found.');
        PasswordHasherFactory::build('FooBar');
    }

    /**
     * test build() throws exception for non existent hasher
     *
     * @return void
     */
    public function testBuildInvalidHasher(): void
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Password hasher must implement PasswordHasherInterface.');
        PasswordHasherFactory::build('Invalid');
    }
}
