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

use ArrayObject;
use Authentication\Identifier\Resolver\ResolverInterface;
use Authentication\Identifier\VerificationIdentifier;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;

class VerificationIdentifierTest extends TestCase
{

    /**
     * testIdentify
     *
     * @return void
     */
    public function testIdentify()
    {
        $resolver = $this->createMock(ResolverInterface::class);

        $identifier = new VerificationIdentifier([
            'fields' => [
                'id' => 'user_id'
            ]
        ]);
        $identifier->setResolver($resolver);

        $user = new ArrayObject([
            'user_id' => 1
        ]);

        $resolver->expects($this->once())
            ->method('find')
            ->with([
                'user_id' => 1
            ])
            ->willReturn($user);

        $result = $identifier->identify(['id' => 1]);
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

        $identifier = new VerificationIdentifier();
        $identifier->setResolver($resolver);

        $resolver->expects($this->never())
            ->method('find');

        $result = $identifier->identify(['user' => 'larry']);
        $this->assertNull($result);
    }
}
