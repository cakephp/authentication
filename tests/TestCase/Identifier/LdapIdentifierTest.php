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
 * @since         1.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

namespace Authentication\Test\TestCase\Identifier;

use ArrayAccess;
use Authentication\Identifier\LdapIdentifier;
use Authentication\Identifier\Ldap\AdapterInterface;
use Authentication\Identifier\Ldap\ExtensionAdapter;
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
        $port = 389;
        $bindDN = 'uid=einstein,dc=example,dc=com';
        $bindPassword = 'doe';
        $baseDN = 'dc=example,dc=com';
        $filter = function ($uid) {
            return str_replace(
                "%uid",
                $uid,
                "(&(&(|(objectclass=person)))(|(uid=%uid)(samaccountname=%uid)(|(mailPrimaryAddress=%uid)(mail=%uid))))");
        };
        $options = [
            'foo' => 3
        ];

        $ldap = $this->createMock(AdapterInterface::class);
        $ldap->expects($this->once())
            ->method('connect')
            ->with($host, $port, $options);
        $ldap->expects($this->any())
            ->method('bind')
            ->with('uid=einstein,dc=example,dc=com', 'doe')
            ->willReturn(true);
        $ldap->expects($this->once())
            ->method('search')
            ->with($baseDN, $filter('john'))
            ->willReturn([
                    'count' => 1,
                    0 => [
                        'objectclass' => [
                            'count' => 4,
                            0 => 'inetOrgPerson',
                            1 => 'organizationalPerson',
                            2 => 'person',
                            3 => 'top'
                        ],
                        0 => 'objectclass',
                        'cn' => [
                            'count' => 1,
                            0 => 'Albert Einstein'
                        ],
                        1 => 'cn',
                        'sn' => [
                            'count' => 1,
                            0 => 'Einstein'
                        ],
                        2 => 'sn',
                        'uid' => [
                            'count' => 1,
                            0 => 'einstein'
                        ],
                        3 => 'uid',
                        'mail' => [
                            'count' => 1,
                            0 => 'einstein@ldap.forumsys.com'
                        ],
                        4 => 'mail',
                        'telephonenumber' => [
                            'count' => 1,
                            0 => '314-159-2653'
                        ],
                        5 => 'telephonenumber',
                        'count' => 6,
                        'dn' => 'uid=einstein,dc=example,dc=com'
                    ]
                ]
            );


        $identifier = new LdapIdentifier([
            'host' => $host,
            'port' => $port,
            'bindDN' => $bindDN,
            'bindPassword' => $bindPassword,
            'baseDN' => $baseDN,
            'filter' => $filter,
            'ldap' => $ldap,
            'options' => $options
        ]);

        $result = $identifier->identify([
            'username' => 'john',
            'password' => 'doe'
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
            'bindDN' => 'cn=read-only-admin,dc=example,dc=com',
            'bindPassword' => 'password',
            'baseDN' => 'dc=example,dc=com',
            'filter' => function ($uid) {
                return str_replace(
                    "%uid",
                    $uid,
                    "(&(&(|(objectclass=person)))(|(uid=%uid)(samaccountname=%uid)(|(mailPrimaryAddress=%uid)(mail=%uid))))");
            },
            'ldap' => $ldap
        ]);

        $result = $identifier->identify([
            'username' => 'john',
            'password' => 'doe'
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

        $bindDN = 'cn=read-only-admin,dc=example,dc=com';
        $bindPassword = 'password';
        $baseDN = 'dc=example,dc=com';
        $filter = function ($uid) {
            return str_replace(
                "%uid",
                $uid,
                "(&(&(|(objectclass=person)))(|(uid=%uid)(samaccountname=%uid)(|(mailPrimaryAddress=%uid)(mail=%uid))))");
        };

        $identifier = new LdapIdentifier([
            'host' => 'ldap.example.com',
            'bindDN' => $bindDN,
            'bindPassword' => $bindPassword,
            'baseDN' => $baseDN,
            'filter' => $filter,
        ]);

        $this->assertInstanceOf(ExtensionAdapter::class, $identifier->getAdapter());
    }

    /**
     * testWrongLdapObject
     *
     * @return void
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Option `ldap` must implement `Authentication\Identifier\Ldap\AdapterInterface`.
     */
    public function testWrongLdapObject()
    {
        $notLdap = new stdClass;

        $identifier = new LdapIdentifier([
            'host' => 'ldap.example.com',
            'bindDN' => 'cn=read-only-admin,dc=example,dc=com',
            'bindPassword' => 'password',
            'baseDN' => 'dc=example,dc=com',
            'filter' => function ($uid) {
                return str_replace(
                    "%uid",
                    $uid,
                    "(&(&(|(objectclass=person)))(|(uid=%uid)(samaccountname=%uid)(|(mailPrimaryAddress=%uid)(mail=%uid))))");
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
            'host' => 'ldap.example.com',
            'filter' => function ($uid) {
                return str_replace(
                    "%uid",
                    $uid,
                    "(&(&(|(objectclass=person)))(|(uid=%uid)(samaccountname=%uid)(|(mailPrimaryAddress=%uid)(mail=%uid))))");
            },
        ]);
    }

    /**
     * testUncallableFilter
     *
     * @return void
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The `filter` config is not a callable. Got `string` instead.
     */
    public function testUncallableFilter()
    {
        $identifier = new LdapIdentifier([
            'host' => 'ldap.example.com',
            'filter' => 'Foo'
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
            'bindDN' => 'cn=read-only-admin,dc=example,dc=com',
            'filter' => function ($uid) {
                return str_replace(
                    "%uid",
                    $uid,
                    "(&(&(|(objectclass=person)))(|(uid=%uid)(samaccountname=%uid)(|(mailPrimaryAddress=%uid)(mail=%uid))))");
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
            'bindDN' => 'cn=read-only-admin,dc=example,dc=com',
            'bindPassword' => 'password',
            'baseDN' => 'dc=example,dc=com',
            'filter' => function ($uid) {
                return str_replace(
                    "%uid",
                    $uid,
                    "(&(&(|(objectclass=person)))(|(uid=%uid)(samaccountname=%uid)(|(mailPrimaryAddress=%uid)(mail=%uid))))");
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
