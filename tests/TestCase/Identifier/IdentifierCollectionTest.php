<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Test\TestCase\Identifier;

use Authentication\Identifier\IdentifierCollection;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use TestApp\Authentication\Identifier\InvalidIdentifier;

class IdentifierCollectionTest extends TestCase
{

    public function testConstruct()
    {
        $collection = new IdentifierCollection([
            'Authentication.Orm'
        ]);
        $result = $collection->get('Orm');
        $this->assertInstanceOf('\Authentication\Identifier\OrmIdentifier', $result);
    }

    /**
     * testLoad
     *
     * @return void
     */
    public function testLoad()
    {
        $collection = new IdentifierCollection();
        $result = $collection->load('Authentication.Orm');
        $this->assertInstanceOf('\Authentication\Identifier\OrmIdentifier', $result);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Identifier class `Does-not-exist` was not found.
     */
    public function testLoadException()
    {
        $collection = new IdentifierCollection();
        $collection->load('Does-not-exist');
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Identifier class `TestApp\Authentication\Identifier\InvalidIdentifier`
     */
    public function testLoadExceptionInterfaceNotImplemented()
    {
        $collection = new IdentifierCollection();
        $collection->load(InvalidIdentifier::class);
    }

    /**
     * testGetAll
     *
     * @return void
     */
    public function testGetAll()
    {
        $collection = new IdentifierCollection();
        $collection->load('Authentication.Orm');
        $result = $collection->getAll();
        $this->assertInternalType('array', $result);
    }

    /**
     * testIdentify
     *
     * @return void
     */
    public function testIdentify()
    {
        $collection = new IdentifierCollection([
            'Authentication.Orm'
        ]);

        $result = $collection->identify([
            'username' => 'mariano',
            'password' => 'password'
        ]);

        $this->assertInstanceOf('\Cake\Datasource\EntityInterface', $result);
    }
}
