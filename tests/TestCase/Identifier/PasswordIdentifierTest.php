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

use Authentication\Identifier\PasswordIdentifier;
use Authentication\PasswordHasher\DefaultPasswordHasher;
use Authentication\PasswordHasher\WeakPasswordHasher;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Datasource\EntityInterface;

class PasswordIdentifierTest extends TestCase
{

    /**
     * testIdentify
     *
     * @return void
     */
    public function testIdentify()
    {
        $identifier = new PasswordIdentifier();

        // Valid user
        $result = $identifier->identify([
            'username' => 'mariano',
            'password' => 'password'
        ]);

        $this->assertInstanceOf('\Cake\Datasource\EntityInterface', $result);

        // Invalid user and password
        $result = $identifier->identify([
            'username' => 'does-not',
            'password' => 'exist'
        ]);

        $this->assertNull($result);

        $result = $identifier->identify([
            'password' => 'password'
        ]);

        $this->assertNull($result);

        // Valid user but invalid password
        $result = $identifier->identify([
            'username' => 'mariano',
            'password' => 'invalid-password'
        ]);

        $this->assertNull($result);
    }

    /**
     * testIdentifyMultiField
     *
     * @return void
     */
    public function testIdentifyMultiField()
    {
        $identifier = new PasswordIdentifier([
            'fields' => ['username' => ['id', 'username']]
        ]);

        $result = $identifier->identify([
            'username' => 'larry',
            'password' => 'password'
        ]);
        $this->assertInstanceOf(EntityInterface::class, $result);

        $result = $identifier->identify([
            'username' => 3,
            'password' => 'password'
        ]);
        $this->assertInstanceOf(EntityInterface::class, $result);
        $this->assertEquals('larry', $result->username);
    }

    /**
     * testFinderArrayConfig
     *
     * @return void
     */
    public function testFinderArrayConfig()
    {
        $identifier = new PasswordIdentifier([
            'resolver' => [
                'className' => 'Authentication.Orm',
                'userModel' => 'AuthUsers',
                'finder' => [
                    'auth' => ['return_created' => true]
                ]
            ]
        ]);

        $result = $identifier->identify([
            'username' => 'mariano',
            'password' => 'password'
        ]);

        $this->assertNotEmpty($result->created);
    }

    /**
     * testDefaultPasswordHasher
     *
     * @return void
     */
    public function testDefaultPasswordHasher()
    {
        $identifier = new PasswordIdentifier();
        $hasher = $identifier->getPasswordHasher();
        $this->assertInstanceOf(DefaultPasswordHasher::class, $hasher);
    }

    /**
     * testCustomPasswordHasher
     *
     * @return void
     */
    public function testCustomPasswordHasher()
    {
        $identifier = new PasswordIdentifier([
            'passwordHasher' => WeakPasswordHasher::class
        ]);
        $hasher = $identifier->getPasswordHasher();
        $this->assertInstanceOf(WeakPasswordHasher::class, $hasher);
    }
}
