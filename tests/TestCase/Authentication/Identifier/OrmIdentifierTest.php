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
 * @since         4.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Auth\Test\TestCase\Middleware\Authentication;

use Authentication\Authentication\Identifier\OrmIdentifier;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;

class OrmIdentifierTest extends TestCase
{

    /**
     * testIdentify
     *
     * @return void
     */
    public function testIdentify()
    {
        $identifier = new OrmIdentifier();

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

        $this->assertFalse($result);

        $result = $identifier->identify([
            'password' => 'password'
        ]);

        $this->assertFalse($result);

        // Valid user but invalid password
        $result = $identifier->identify([
            'username' => 'mariano',
            'password' => 'invalid-password'
        ]);

        $this->assertFalse($result);
    }

    /**
     * testFinderArrayConfig
     *
     * @return void
     */
    public function testFinderArrayConfig()
    {
        $identifier = new OrmIdentifier([
            'userModel' => 'AuthUsers',
            'finder' => [
                'auth' => ['return_created' => true]
            ]
        ]);

        $result = $identifier->identify([
            'username' => 'mariano',
            'password' => 'password'
        ]);

        $this->assertNotEmpty($result->created);
    }
}
