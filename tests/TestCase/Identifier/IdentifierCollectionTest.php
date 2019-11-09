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
 * @since 1.0.0
 * @license https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Test\TestCase\Identifier;

use Authentication\Identifier\IdentifierCollection;
use Authentication\Identifier\IdentifierInterface;
use Authentication\Identifier\PasswordIdentifier;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use TestApp\Authentication\Identifier\InvalidIdentifier;

class IdentifierCollectionTest extends TestCase
{
    public function testConstruct()
    {
        $collection = new IdentifierCollection([
            'Authentication.Password',
        ]);
        $result = $collection->get('Password');
        $this->assertInstanceOf('\Authentication\Identifier\PasswordIdentifier', $result);
    }

    /**
     * testLoad
     *
     * @return void
     */
    public function testLoad()
    {
        $collection = new IdentifierCollection();
        $result = $collection->load('Authentication.Password');
        $this->assertInstanceOf('\Authentication\Identifier\PasswordIdentifier', $result);
    }

    /**
     * testSet
     *
     * @return void
     */
    public function testSet()
    {
        $identifier = $this->createMock(IdentifierInterface::class);
        $collection = new IdentifierCollection();
        $collection->set('Password', $identifier);
        $this->assertSame($identifier, $collection->get('Password'));
    }

    public function testLoadException()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Identifier class `Does-not-exist` was not found.');
        $collection = new IdentifierCollection();
        $collection->load('Does-not-exist');
    }

    public function testLoadExceptionInterfaceNotImplemented()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Identifier class `TestApp\Authentication\Identifier\InvalidIdentifier`');
        $collection = new IdentifierCollection();
        $collection->load(InvalidIdentifier::class);
    }

    /**
     * testIsEmpty
     *
     * @return void
     */
    public function testIsEmpty()
    {
        $collection = new IdentifierCollection();
        $this->assertTrue($collection->isEmpty());

        $collection->load('Authentication.Password');
        $this->assertFalse($collection->isEmpty());
    }

    /**
     * testIterator
     *
     * @return void
     */
    public function testIterator()
    {
        $identifier = $this->createMock(IdentifierInterface::class);
        $collection = new IdentifierCollection();
        $collection->set('Password', $identifier);

        $this->assertContains($identifier, $collection);
    }

    /**
     * testIdentify
     *
     * @return void
     */
    public function testIdentify()
    {
        $collection = new IdentifierCollection([
            'Authentication.Password',
        ]);

        $result = $collection->identify([
            'username' => 'mariano',
            'password' => 'password',
        ]);

        $this->assertInstanceOf('\ArrayAccess', $result);
        $this->assertInstanceOf(PasswordIdentifier::class, $collection->getIdentificationProvider());

        $result = $collection->identify([
            'username' => 'mariano',
            'password' => 'invalid password',
        ]);
        $this->assertNull($collection->getIdentificationProvider());
    }
}
