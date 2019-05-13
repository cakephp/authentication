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

use ArrayAccess;
use Authentication\Identifier\Ldap\AdapterInterface;
use Authentication\Identifier\Ldap\ExtensionAdapter;
use Authentication\Identifier\LdapIdentifier;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use ErrorException;
use stdClass;

class LdapIdentifierTest extends TestCase
{
    /**
     * testIdentify
     *
     * @return void
     */
    public function testIdentify()
    {
        $host = 'ldap.example.com';
        $bind = function ($username) {
            return 'cn=' . $username . ',dc=example,dc=com';
        };
        $options = [
            'foo' => 3,
        ];

        $ldap = $this->createMock(AdapterInterface::class);
        $ldap->expects($this->once())
            ->method('connect')
            ->with($host, 389, $options);
        $ldap->expects($this->once())
            ->method('bind')
            ->with('cn=john,dc=example,dc=com', 'doe')
            ->willReturn(true);

        $identifier = new LdapIdentifier([
            'host' => $host,
            'bindDN' => $bind,
            'ldap' => $ldap,
            'options' => $options,
        ]);

        $result = $identifier->identify([
            'username' => 'john',
            'password' => 'doe',
        ]);

        $this->assertInstanceOf(ArrayAccess::class, $result);
    }

    /**
     * testIdentifyMissingCredentials
     *
     * @return void
     */
    public function testIdentifyMissingCredentials()
    {
        $ldap = $this->createMock(AdapterInterface::class);
        $ldap->method('bind')
            ->willReturn(false);

        $identifier = new LdapIdentifier([
            'host' => 'ldap.example.com',
            'bindDN' => function () {
                return 'dc=example,dc=com';
            },
            'ldap' => $ldap,
        ]);

        $result = $identifier->identify([
            'username' => 'john',
            'password' => 'doe',
        ]);
        $this->assertNull($result);

        $resultTwo = $identifier->identify([]);
        $this->assertNull($resultTwo);
    }

    /**
     * testLdapExtensionAdapter
     *
     * @return void
     */
    public function testLdapExtensionAdapter()
    {
        $this->skipIf(!extension_loaded('ldap'), 'LDAP extension is not loaded.');

        $identifier = new LdapIdentifier([
            'host' => 'ldap.example.com',
            'bindDN' => function () {
                return 'dc=example,dc=com';
            },
        ]);

        $this->assertInstanceOf(ExtensionAdapter::class, $identifier->getAdapter());
    }

    /**
     * testWrongLdapObject
     *
     * @return void
     */
    public function testWrongLdapObject()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Option `ldap` must implement `Authentication\Identifier\Ldap\AdapterInterface`.');
        $notLdap = new stdClass();

        $identifier = new LdapIdentifier([
            'host' => 'ldap.example.com',
            'bindDN' => function () {
                return 'dc=example,dc=com';
            },
            'ldap' => $notLdap,
        ]);
    }

    /**
     * testMissingBindDN
     *
     * @return void
     */
    public function testMissingBindDN()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Config `bindDN` is not set.');
        $identifier = new LdapIdentifier([
            'host' => 'ldap.example.com',
        ]);
    }

    /**
     * testUncallableDN
     *
     * @return void
     */
    public function testUncallableDN()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('The `bindDN` config is not a callable. Got `string` instead.');
        $identifier = new LdapIdentifier([
            'host' => 'ldap.example.com',
            'bindDN' => 'Foo',
        ]);
    }

    /**
     * testMissingHost
     *
     * @return void
     */
    public function testMissingHost()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Config `host` is not set.');
        $identifier = new LdapIdentifier([
            'bindDN' => function () {
                return 'dc=example,dc=com';
            },
        ]);
    }

    /**
     * testHandleError
     *
     * @return void
     */
    public function testHandleError()
    {
        $ldap = $this->createMock(AdapterInterface::class);
        $ldap->method('bind')
            ->will($this->throwException(new ErrorException('This is an error.')));
        $ldap->method('getDiagnosticMessage')
            ->willReturn('This is another error.');

        $identifier = new LdapIdentifier([
            'host' => 'ldap.example.com',
            'bindDN' => function () {
                return 'dc=example,dc=com';
            },
            'ldap' => $ldap,
        ]);

        $result = $identifier->identify([
            'username' => 'john',
            'password' => 'doe',
        ]);

        $this->assertSame($identifier->getErrors(), [
            'This is another error.',
            'This is an error.',
        ]);
    }
}
