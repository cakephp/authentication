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
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Test\TestCase\Authenticator;

use ArrayObject;
use Authentication\Identity;
use Cake\ORM\Entity;
use Cake\TestSuite\TestCase;

class IdentityTest extends TestCase
{
    /**
     * Test getIdentifier()
     *
     * @return void
     */
    public function testGetIdentifier()
    {
        $data = [
            'id' => 1,
            'username' => 'florian'
        ];

        $identity = new Identity($data);

        $result = $identity->getIdentifier();
        $this->assertEquals(1, $result);

        $result = $identity->get('username');
        $this->assertEquals('florian', $result);
    }

    /**
     * Test mapping fields
     *
     * @return void
     */
    public function testFieldMapping()
    {
        $data = [
            'id' => 1,
            'first_name' => 'florian',
            'mail' => 'info@cakephp.org'
        ];

        $identity = new Identity($data, [
            'fieldMap' => [
                'username' => 'first_name',
                'email' => 'mail'
            ]
        ]);

        $result = $identity->get('username');
        $this->assertEquals('florian', $result);

        $result = $identity->get('email');
        $this->assertEquals('info@cakephp.org', $result);
    }

    /**
     * Test array data.
     */
    public function testBuildArray()
    {
        $data = ['username' => 'robert'];
        $identity = new Identity($data);
        $this->assertEquals($data['username'], $identity['username']);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedException Array data must be an `array` or implement `ArrayAccess` interface, `stdClass` given.
     */
    public function testBuildInvalidArgument()
    {
        new Identity(new \stdClass);
    }

    /**
     * Test getOriginalData() method
     *
     * @return void
     */
    public function testGetOriginalData()
    {
        $data = new ArrayObject(['email' => 'info@cakephp.org']);

        $identity = new Identity($data);
        $this->assertSame($data, $identity->getOriginalData());
    }
}
