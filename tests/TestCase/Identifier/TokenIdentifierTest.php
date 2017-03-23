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
use Cake\Datasource\EntityInterface;
use Cake\ORM\Entity;

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
            'userModel' => 'AuthUsers',
            'tokenField' => 'username'
        ]);

        $result = $identifier->identify(['token' => 'chartjes']);

        $this->assertInstanceOf('\Cake\Datasource\EntityInterface', $result);
    }

    /**
     * testCallableTokenVerification
     *
     * @return void
     */
    public function testCallableTokenVerification()
    {
        $identifier = new TokenIdentifier([
            'tokenVerification' => function ($data) {
                if ($data['token'] === 'larry') {
                    return new Entity(['username' => 'larry', 'id' => 3]);
                }

                return null;
            }
        ]);

        $result = $identifier->identify(['token' => 'not-larry']);
        $this->assertNull($result);

        $result = $identifier->identify(['token' => 'larry']);
        $this->assertInstanceOf('\Cake\Datasource\EntityInterface', $result);
    }

    /**
     * testTokenVerificationMethodDoesNotExist
     *
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Token verification method `Authentication\Identifier\TokenIdentifier::_missing()` does not exist
     */
    public function testTokenVerificationMethodDoesNotExist()
    {
        $identifier = new TokenIdentifier([
            'tokenVerification' => 'missing'
        ]);

        $identifier->identify(['token' => 'larry']);
    }

    /**
     * testTokenVerificationInvalidArgumentException
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The `tokenVerification` option is not a string or callable
     */
    public function testTokenVerificationInvalidArgumentException()
    {
        $identifier = new TokenIdentifier([
            'tokenVerification' => 12345
        ]);

        $identifier->identify(['token' => 'larry']);
    }
}
