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

use Authentication\Identifier\Resolver\ResolverAwareTrait;
use Authentication\Identifier\Resolver\ResolverInterface;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use TestApp\Identifier\Resolver\TestResolver;

#[AllowMockObjectsWithoutExpectations]
class ResolverAwareTraitTest extends TestCase
{
    public function testBuildResolverFromClassName(): void
    {
        $object = new class {
            use ResolverAwareTrait;

            public function getConfig(): string
            {
                return 'Test';
            }
        };

        $resolver = $object->getResolver();
        $this->assertInstanceOf(TestResolver::class, $resolver);
    }

    public function testBuildResolverFromArray(): void
    {
        $object = new class {
            use ResolverAwareTrait;

            public function getConfig(): array
            {
                return [
                    'className' => 'Test',
                ];
            }
        };

        $resolver = $object->getResolver();
        $this->assertInstanceOf(TestResolver::class, $resolver);
    }

    public function testBuildResolverInvalid(): void
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Resolver must implement `Authentication\Identifier\Resolver\ResolverInterface`.');
        $object = new class {
            use ResolverAwareTrait;

            public function getConfig(): string
            {
                return 'Invalid';
            }
        };

        $object->getResolver();
    }

    public function testBuildResolverMissing(): void
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Resolver class `Missing` does not exist.');
        $object = new class {
            use ResolverAwareTrait;

            public function getConfig(): string
            {
                return 'Missing';
            }
        };

        $object->getResolver();
    }

    public function testBuildResolverMissingClassNameOption(): void
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Option `className` is not present.');
        $object = new class {
            use ResolverAwareTrait;

            public function getConfig(): array
            {
                return [];
            }
        };

        $object->getResolver();
    }

    public function testGetResolverNotSet(): void
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Resolver has not been set.');
        $object = new class {
            use ResolverAwareTrait;

            public function getConfig()
            {
                return null;
            }
        };

        $object->getResolver();
    }

    public function testSetResolver(): void
    {
        $object = new class {
            use ResolverAwareTrait;
        };

        $resolver = $this->createMock(ResolverInterface::class);

        $object->setResolver($resolver);
        $this->assertSame($object->getResolver(), $resolver);
    }
}
