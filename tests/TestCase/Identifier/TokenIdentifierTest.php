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
namespace Authentication\Test\TestCase\Identifier;

use ArrayObject;
use Authentication\Identifier\Resolver\ResolverInterface;
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
        $resolver = $this->createMock(ResolverInterface::class);

        $identifier = new TokenIdentifier([
            'dataField' => 'user',
            'tokenField' => 'username',
        ]);
        $identifier->setResolver($resolver);

        $user = new ArrayObject([
            'username' => 'larry',
        ]);

        $resolver->expects($this->once())
            ->method('find')
            ->with([
                'username' => 'larry',
            ])
            ->willReturn($user);

        $result = $identifier->identify(['user' => 'larry']);
        $this->assertSame($user, $result);
    }

    /**
     * testIdentifyMissingData
     *
     * @return void
     */
    public function testIdentifyMissingData()
    {
        $resolver = $this->createMock(ResolverInterface::class);

        $identifier = new TokenIdentifier();
        $identifier->setResolver($resolver);

        $resolver->expects($this->never())
            ->method('find');

        $result = $identifier->identify(['user' => 'larry']);
        $this->assertNull($result);
    }
}
