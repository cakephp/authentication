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
            'ldapClass' => $ldap
        ]);

        $result = $identifier->identify([
            'username' => 'john',
            'password' => 'doe'
        ]);

        $this->assertInstanceOf(EntityInterface::class, $result);
    }

    /**
     * testWrongLdapObject
     *
     * @return void
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Could not build the LDAP connection object.
     */
    public function testWrongLdapObject()
    {
        $notLdap = $this->createMock(\stdClass::class);

        $identifier = new LdapIdentifier([
            'host' => 'ldap.example.com',
            'bindDN' => function () {
                return 'dc=example,dc=com';
            },
            'ldapClass' => $notLdap
        ]);
    }

    /**
     * testUncallableDN
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
}
