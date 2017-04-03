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

use Authentication\Identifier\TokenIdentifier;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;

class TokenIdentifierTest extends TestCase
{

    /**
     * testIdentify
     *
     * @return void
     */
    public function testIdentify()
    {
        $identifier = new TokenIdentifier([
            'tokenField' => 'username'
        ]);

        $result = $identifier->identify(['token' => 'larry']);

        $this->assertInstanceOf('\Cake\Datasource\EntityInterface', $result);
        $this->assertEquals(3, $result->id);

        $result = $identifier->identify(['token' => 'does not exist']);
        $this->assertNull($result);
    }

    /**
     * testCustomUserModel
     *
     * @return void
     */
    public function testCustomUserModel()
    {
        $identifier = new TokenIdentifier([
            'datasource' => [
                'className' => 'Authentication.Orm',
                'userModel' => 'AuthUsers'
            ],
            'tokenField' => 'username'
        ]);

        $result = $identifier->identify(['token' => 'chartjes']);

        $this->assertInstanceOf('\Cake\Datasource\EntityInterface', $result);
    }
}
