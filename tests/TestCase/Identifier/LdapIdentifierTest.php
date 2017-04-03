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

use Authentication\Identifier\Backend\Ldap;
use Authentication\Identifier\Backend\LdapInterface;
use Authentication\Identifier\LdapIdentifier;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Datasource\EntityInterface;
use ErrorException;

class LdapIdentifierTest extends TestCase
{

    /**
     * {@inheritDoc}
     */
    public function setUp()
    {
        parent::setUp();

        $this->skipIf(!extension_loaded('ldap'), 'LDAP extension is not installed');
    }

    /**
     * testIdentify
     *
     * @return void
     */
    public function testIdentify()
    {
        $ldap = $this->createMock(Ldap::class);
        $ldap->method('bind')
            ->willReturn(true);

        $identifier = new LdapIdentifier([
            'host' => 'ldap.example.com',
            'bindDN' => function () {
                return 'dc=example,dc=com';
            },
            'ldap' => $ldap,
            'options' => [
                LDAP_OPT_PROTOCOL_VERSION => 3
            ]
        ]);

        $result = $identifier->identify([
            'username' => 'john',
            'password' => 'doe'
        ]);

        $this->assertInstanceOf(EntityInterface::class, $result);
    }

    /**
     * testIdentifyNull
     *
     * @return void
     */
    public function testIdentifyNull()
    {
        $ldap = $this->createMock(Ldap::class);
        $ldap->method('bind')
            ->willReturn(false);

        $identifier = new LdapIdentifier([
            'host' => 'ldap.example.com',
            'bindDN' => function () {
                return 'dc=example,dc=com';
            },
            'ldap' => $ldap
        ]);

        $result = $identifier->identify([
            'username' => 'john',
            'password' => 'doe'
        ]);
        $this->assertSame(null, $result);

        $resultTwo = $identifier->identify(null);
        $this->assertSame(null, $result);
    }

    /**
     * testWrongLdapObject
     *
     * @return void
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Option `ldap` must implement Authentication\Identifier\Backend\LdapInterface.
     */
    public function testWrongLdapObject()
    {
        $notLdap = new \stdClass;

        $identifier = new LdapIdentifier([
            'host' => 'ldap.example.com',
            'bindDN' => function () {
                return 'dc=example,dc=com';
            },
            'ldap' => $notLdap
        ]);
    }

    /**
     * testMissingBindDN
     *
     * @return void
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Config `bindDN` is not set.
     */
    public function testMissingBindDN()
    {
        $identifier = new LdapIdentifier([
            'host' => 'ldap.example.com'
        ]);
    }

    /**
     * testUncallableDN
     *
     * @return void
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The `bindDN` config is not a callable. Got `string` instead.
     */
    public function testUncallableDN()
    {
        $identifier = new LdapIdentifier([
            'host' => 'ldap.example.com',
            'bindDN' => 'Foo'
        ]);
    }

    /**
     * testMissingHost
     *
     * @return void
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Config `host` is not set.
     */
    public function testMissingHost()
    {
        $identifier = new LdapIdentifier([
            'bindDN' => function () {
                return 'dc=example,dc=com';
            }
        ]);
    }

    /**
     * testHandleError
     *
     * @return void
     */
    public function testHandleError()
    {
        $ldap = $this->createMock(Ldap::class);
        $ldap->method('bind')
            ->will($this->throwException(new ErrorException('This is an error.')));
        $ldap->method('getOption')
            ->willReturn('This is another error.');

        $identifier = new LdapIdentifier([
            'host' => 'ldap.example.com',
            'bindDN' => function () {
                return 'dc=example,dc=com';
            },
            'ldap' => $ldap
        ]);

        $result = $identifier->identify([
            'username' => 'john',
            'password' => 'doe'
        ]);

        $this->assertSame($identifier->getErrors(), [
            'This is another error.',
            'This is an error.'
        ]);
    }
}
