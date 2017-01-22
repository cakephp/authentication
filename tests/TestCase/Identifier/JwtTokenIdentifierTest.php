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
namespace Authentication\Test\TestCase\Identifier;

use ADmad\JwtAuth\Auth\JwtTokenIdentifier;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Datasource\EntityInterface;
use Firebase\JWT\JWT;

class JwtTokenIdentifierTest extends TestCase
{

    /**
     * testIdentify
     *
     * @return void
     */
    public function testIdentify()
    {
        $data = [
            'id' => 3,
            'username' => 'larry',
            'firstname' => 'larry'
        ];

        $token = 'Bearer ' . JWT::encode($data, 'test');

        $identifier = new JwtTokenIdentifier([
            'salt' => 'test',
            'debug' => true
        ]);

        $result = $identifier->identify(['token' => $token]);

        $this->assertInstanceOf(EntityInterface::class, $result);
        $this->assertEquals(3, $result->id);
    }
}
