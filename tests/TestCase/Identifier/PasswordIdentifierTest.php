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
use Authentication\Identifier\PasswordIdentifier;
use Authentication\Identifier\Resolver\ResolverInterface;
use Authentication\PasswordHasher\DefaultPasswordHasher;
use Authentication\PasswordHasher\LegacyPasswordHasher;
use Authentication\PasswordHasher\PasswordHasherInterface;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;

class PasswordIdentifierTest extends TestCase
{
    /**
     * testIdentifyValid
     *
     * @return void
     */
    public function testIdentifyValid()
    {
        $resolver = $this->createMock(ResolverInterface::class);
        $hasher = $this->createMock(PasswordHasherInterface::class);

        $user = new ArrayObject([
            'username' => 'mariano',
            'password' => 'h45hedpa55w0rd',
        ]);

        $resolver->expects($this->once())
            ->method('find')
            ->with(['username' => 'mariano'])
            ->willReturn($user);

        $hasher->expects($this->once())
            ->method('check')
            ->with('password', 'h45hedpa55w0rd')
            ->willReturn(true);

        $identifier = new PasswordIdentifier();
        $identifier->setResolver($resolver)->setPasswordHasher($hasher);

        $result = $identifier->identify([
            'username' => 'mariano',
            'password' => 'password',
        ]);

        $this->assertInstanceOf('\ArrayAccess', $result);
        $this->assertSame($user, $result);
    }

    /**
     * testIdentifyNeedsRehash
     *
     * @return void
     */
    public function testIdentifyNeedsRehash()
    {
        $resolver = $this->createMock(ResolverInterface::class);
        $hasher = $this->createMock(PasswordHasherInterface::class);

        $user = new ArrayObject([
            'username' => 'mariano',
            'password' => 'h45hedpa55w0rd',
        ]);

        $resolver->method('find')
            ->willReturn($user);

        $hasher->method('check')
            ->willReturn(true);

        $hasher->expects($this->once())
            ->method('needsRehash')
            ->with('h45hedpa55w0rd')
            ->willReturn(true);

        $identifier = new PasswordIdentifier();
        $identifier->setResolver($resolver)->setPasswordHasher($hasher);

        $result = $identifier->identify([
            'username' => 'mariano',
            'password' => 'password',
        ]);

        $this->assertInstanceOf('\ArrayAccess', $result);
        $this->assertTrue($identifier->needsPasswordRehash());
    }

    /**
     * testIdentifyInvalidUser
     *
     * @return void
     */
    public function testIdentifyInvalidUser()
    {
        $resolver = $this->createMock(ResolverInterface::class);
        $hasher = $this->createMock(PasswordHasherInterface::class);

        $resolver->expects($this->once())
            ->method('find')
            ->with(['username' => 'does-not'])
            ->willReturn(null);

        $hasher->expects($this->once())
            ->method('check')
            ->with('exist', '')
            ->willReturn(false);

        $identifier = new PasswordIdentifier();
        $identifier->setResolver($resolver)->setPasswordHasher($hasher);

        $result = $identifier->identify([
            'username' => 'does-not',
            'password' => 'exist',
        ]);

        $this->assertNull($result);
    }

    /**
     * testIdentifyInvalidPassword
     *
     * @return void
     */
    public function testIdentifyInvalidPassword()
    {
        $resolver = $this->createMock(ResolverInterface::class);
        $hasher = $this->createMock(PasswordHasherInterface::class);

        $user = new ArrayObject([
            'username' => 'mariano',
            'password' => 'h45hedpa55w0rd',
        ]);

        $resolver->expects($this->once())
            ->method('find')
            ->with(['username' => 'mariano'])
            ->willReturn($user);

        $hasher->expects($this->once())
            ->method('check')
            ->with('wrongpassword', 'h45hedpa55w0rd')
            ->willReturn(false);

        $identifier = new PasswordIdentifier();
        $identifier->setResolver($resolver)->setPasswordHasher($hasher);

        $result = $identifier->identify([
            'username' => 'mariano',
            'password' => 'wrongpassword',
        ]);

        $this->assertNull($result);
    }

    /**
     * testIdentifyEmptyPassword
     *
     * @return void
     */
    public function testIdentifyEmptyPassword()
    {
        $resolver = $this->createMock(ResolverInterface::class);
        $hasher = $this->createMock(PasswordHasherInterface::class);

        $user = new ArrayObject([
            'username' => 'mariano',
            'password' => 'h45hedpa55w0rd',
        ]);

        $resolver->expects($this->once())
            ->method('find')
            ->with(['username' => 'mariano'])
            ->willReturn($user);

        $hasher->expects($this->once())
            ->method('check')
            ->with('', 'h45hedpa55w0rd')
            ->willReturn(false);

        $identifier = new PasswordIdentifier();
        $identifier->setResolver($resolver)->setPasswordHasher($hasher);

        $result = $identifier->identify([
            'username' => 'mariano',
            'password' => '',
        ]);

        $this->assertNull($result);
    }

    /**
     * testIdentifyNoPassword
     *
     * @return void
     */
    public function testIdentifyNoPassword()
    {
        $resolver = $this->createMock(ResolverInterface::class);
        $hasher = $this->createMock(PasswordHasherInterface::class);

        $user = new ArrayObject([
            'username' => 'mariano',
            'password' => 'h45hedpa55w0rd',
        ]);

        $resolver->expects($this->once())
            ->method('find')
            ->with(['username' => 'mariano'])
            ->willReturn($user);

        $hasher->expects($this->never())
            ->method('check');

        $identifier = new PasswordIdentifier();
        $identifier->setResolver($resolver)->setPasswordHasher($hasher);

        $result = $identifier->identify([
            'username' => 'mariano',
        ]);

        $this->assertInstanceOf('\ArrayAccess', $result);
    }

    /**
     * testIdentifyMissingCredentials
     *
     * @return void
     */
    public function testIdentifyMissingCredentials()
    {
        $resolver = $this->createMock(ResolverInterface::class);
        $hasher = $this->createMock(PasswordHasherInterface::class);

        $resolver->expects($this->never())
            ->method('find');

        $hasher->expects($this->never())
            ->method('check');

        $identifier = new PasswordIdentifier();
        $identifier->setResolver($resolver)->setPasswordHasher($hasher);

        $result = $identifier->identify([]);

        $this->assertNull($result);
    }

    /**
     * testIdentifyMultiField
     *
     * @return void
     */
    public function testIdentifyMultiField()
    {
        $resolver = $this->createMock(ResolverInterface::class);
        $hasher = $this->createMock(PasswordHasherInterface::class);

        $user = new ArrayObject([
            'username' => 'mariano',
            'email' => 'mariano@example.com',
            'password' => 'h45hedpa55w0rd',
        ]);

        $resolver->expects($this->once())
            ->method('find')
            ->with([
                'username' => 'mariano@example.com',
                'email' => 'mariano@example.com',
            ], 'OR')
            ->willReturn($user);

        $hasher->expects($this->once())
            ->method('check')
            ->with('password', 'h45hedpa55w0rd')
            ->willReturn(true);

        $hasher->expects($this->once())
            ->method('needsRehash')
            ->with('h45hedpa55w0rd');

        $identifier = new PasswordIdentifier([
            'fields' => ['username' => ['email', 'username']],
        ]);
        $identifier->setResolver($resolver)->setPasswordHasher($hasher);

        $result = $identifier->identify([
            'username' => 'mariano@example.com',
            'password' => 'password',
        ]);

        $this->assertInstanceOf('\ArrayAccess', $result);
        $this->assertSame($user, $result);
    }

    /**
     * testDefaultPasswordHasher
     *
     * @return void
     */
    public function testDefaultPasswordHasher()
    {
        $identifier = new PasswordIdentifier();
        $hasher = $identifier->getPasswordHasher();
        $this->assertInstanceOf(DefaultPasswordHasher::class, $hasher);
    }

    /**
     * testCustomPasswordHasher
     *
     * @return void
     */
    public function testCustomPasswordHasher()
    {
        $identifier = new PasswordIdentifier([
            'passwordHasher' => 'Authentication.Legacy',
        ]);
        $hasher = $identifier->getPasswordHasher();
        $this->assertInstanceOf(LegacyPasswordHasher::class, $hasher);
    }
}
