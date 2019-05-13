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
namespace Authentication\Test\TestCase;

use ArrayObject;
use Authentication\Identity;
use BadMethodCallException;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;

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
            'username' => 'florian',
        ];

        $identity = new Identity($data);

        $result = $identity->getIdentifier();
        $this->assertEquals(1, $result);

        $this->assertEquals('florian', $identity->username);
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
            'mail' => 'info@cakephp.org',
        ];

        $identity = new Identity($data, [
            'fieldMap' => [
                'username' => 'first_name',
                'email' => 'mail',
            ],
        ]);

        $this->assertTrue(isset($identity['username']), 'Renamed field responds to isset');
        $this->assertTrue(isset($identity['first_name']), 'old alias responds to isset.');
        $this->assertFalse(isset($identity['missing']));

        $this->assertTrue(isset($identity->username), 'Renamed field responds to isset');
        $this->assertTrue(isset($identity->first_name), 'old alias responds to isset.');
        $this->assertFalse(isset($identity->missing));

        $this->assertSame('florian', $identity['username'], 'renamed field responsds to offsetget');
        $this->assertSame('florian', $identity->username, 'renamed field responds to__get');
        $this->assertNull($identity->missing);
    }

    /**
     * Identities disallow data being unset.
     *
     * @return void
     */
    public function testOffsetUnsetError()
    {
        $this->expectException(BadMethodCallException::class);
        $data = [
            'id' => 1,
        ];
        $identity = new Identity($data);
        unset($identity['id']);

        $identity['username'] = 'mark';
    }

    /**
     * Identities disallow data being set.
     *
     * @return void
     */
    public function testOffsetSetError()
    {
        $this->expectException(BadMethodCallException::class);
        $data = [
            'id' => 1,
        ];
        $identity = new Identity($data);
        $identity['username'] = 'mark';
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

    public function testBuildInvalidArgument()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('data must be an `array` or implement `ArrayAccess` interface, `stdClass` given.');
        new Identity(new \stdClass());
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
