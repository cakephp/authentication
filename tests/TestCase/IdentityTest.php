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
            'username' => 'florian',
        ];

        $identity = new Identity($data);

        $result = $identity->getIdentifier();
        $this->assertSame(1, $result);

        $this->assertSame('florian', $identity->username);
    }

    public function testGet(): void
    {
        $data = new Entity([
            'id' => 1,
            'username' => 'florian',
            'account' => new Entity(['id' => 2, 'role' => 'admin']),
        ]);

        $identity = new Identity($data);

        $this->assertSame(1, $identity->get('id'));
        $this->assertSame('florian', $identity->get('username'));
        $this->assertSame('admin', $identity->get('account.role'));
        $this->assertNull($identity->get('missing'));
        $this->assertSame($data, $identity->get());
    }

    /**
     * Test field mapping with dot notation
     *
     * @return void
     */
    public function testFieldMappingWithDotNotation(): void
    {
        $data = [
            'id' => 1,
            'user_account' => new Entity(['id' => 2, 'role' => 'admin']),
            'profile_data' => ['email' => 'test@example.com', 'preferences' => ['theme' => 'dark']],
        ];

        $identity = new Identity($data, [
            'fieldMap' => [
                'account' => 'user_account',
                'profile' => 'profile_data',
            ],
        ]);

        // Test that fieldMap works with dot notation for nested access
        $this->assertSame('admin', $identity->get('account.role'));
        $this->assertSame('test@example.com', $identity->get('profile.email'));
        $this->assertSame('dark', $identity->get('profile.preferences.theme'));

        // Test that original field names still work with dot notation
        $this->assertSame('admin', $identity->get('user_account.role'));
        $this->assertSame('test@example.com', $identity->get('profile_data.email'));

        // Test that non-mapped fields with dot notation still work
        $this->assertNull($identity->get('missing.field'));
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

        $this->assertSame('florian', $identity['username'], 'renamed field responds to offsetget');
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
        $this->assertSame($data['username'], $identity['username']);
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
