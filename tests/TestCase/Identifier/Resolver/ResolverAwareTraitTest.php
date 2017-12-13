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
namespace Authentication\Test\TestCase\Identifier\Resolver;

use Authentication\Identifier\Resolver\ResolverAwareTrait;
use Authentication\Identifier\Resolver\ResolverInterface;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;
use RuntimeException;
use TestApp\Identifier\Resolver\TestResolver;

class ResolverAwareTraitTest extends TestCase
{
    public function testBuildResolverFromClassName()
    {
        $object = $this->getMockBuilder(ResolverAwareTrait::class)
            ->setMethods(['getConfig'])
            ->getMockForTrait();

        $object->expects($this->once())
            ->method('getConfig')
            ->willReturn('Test');

        $resolver = $object->getResolver();
        $this->assertInstanceOf(TestResolver::class, $resolver);
    }

    public function testBuildResolverFromArray()
    {
        $object = $this->getMockBuilder(ResolverAwareTrait::class)
            ->setMethods(['getConfig'])
            ->getMockForTrait();

        $object->expects($this->once())
            ->method('getConfig')
            ->willReturn([
                'className' => 'Test'
            ]);

        $resolver = $object->getResolver();
        $this->assertInstanceOf(TestResolver::class, $resolver);
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage Resolver must implement `Authentication\Identifier\Resolver\ResolverInterface`.
     */
    public function testBuildResolverInvalid()
    {
        $object = $this->getMockBuilder(ResolverAwareTrait::class)
            ->setMethods(['getConfig'])
            ->getMockForTrait();

        $object->expects($this->once())
            ->method('getConfig')
            ->willReturn('Invalid');

        $object->getResolver();
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Resolver class `Missing` does not exist.
     */
    public function testBuildResolverMissing()
    {
        $object = $this->getMockBuilder(ResolverAwareTrait::class)
            ->setMethods(['getConfig'])
            ->getMockForTrait();

        $object->expects($this->once())
            ->method('getConfig')
            ->willReturn('Missing');

        $object->getResolver();
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Option `className` is not present.
     */
    public function testBuildResolverMissingClassNameOption()
    {
        $object = $this->getMockBuilder(ResolverAwareTrait::class)
            ->setMethods(['getConfig'])
            ->getMockForTrait();

        $object->expects($this->once())
            ->method('getConfig')
            ->willReturn([]);

        $object->getResolver();
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage Resolver has not been set.
     */
    public function testGetResolverNotSet()
    {
        $object = $this->getMockBuilder(ResolverAwareTrait::class)
            ->setMethods(['getConfig'])
            ->getMockForTrait();

        $object->expects($this->once())
            ->method('getConfig')
            ->willReturn(null);

        $object->getResolver();
    }

    public function testSetResolver()
    {
        $object = $this->getMockBuilder(ResolverAwareTrait::class)
            ->setMethods(['getConfig'])
            ->getMockForTrait();

        $resolver = $this->createMock(ResolverInterface::class);

        $object->setResolver($resolver);
        $this->assertSame($object->getResolver(), $resolver);
    }
}
