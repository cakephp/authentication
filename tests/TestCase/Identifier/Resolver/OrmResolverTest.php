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
namespace Authentication\Test\TestCase\Identifier\Resolver;

use Authentication\Identifier\Resolver\OrmResolver;
use Authentication\Test\TestCase\AuthenticationTestCase;
use Cake\Datasource\EntityInterface;

class OrmResolverTest extends AuthenticationTestCase
{
    public function testFindDefault()
    {
        $resolver = new OrmResolver();

        $user = $resolver->find([
            'username' => 'mariano',
        ]);

        $this->assertInstanceOf(EntityInterface::class, $user);
        $this->assertEquals('mariano', $user['username']);
    }

    public function testFindConfig()
    {
        $resolver = new OrmResolver([
            'userModel' => 'AuthUsers',
            'finder' => [
                'all',
                'auth' => ['return_created' => true],
            ],
        ]);

        $user = $resolver->find([
            'username' => 'mariano',
        ]);

        $this->assertNotEmpty($user->created);
    }

    public function testFindAnd()
    {
        $resolver = new OrmResolver();

        $user = $resolver->find([
            'id' => 1,
            'username' => 'mariano',
        ]);

        $this->assertEquals(1, $user['id']);
    }

    public function testFindOr()
    {
        $resolver = new OrmResolver();

        $user = $resolver->find([
            'id' => 1,
            'username' => 'luigiano',
        ], 'OR');

        $this->assertEquals(1, $user['id']);
    }

    public function testFindMissing()
    {
        $resolver = new OrmResolver();

        $user = $resolver->find([
            'id' => 1,
            'username' => 'luigiano',
        ]);

        $this->assertNull($user);
    }

    public function testFindMultipleValues()
    {
        $resolver = new OrmResolver();

        $user = $resolver->find([
            'username' => [
                'luigiano',
                'mariano',
            ],
        ]);

        $this->assertEquals(1, $user['id']);
    }
}
