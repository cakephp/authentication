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

use Authentication\Identifier\LdapIdentifier;
use Authentication\Identifier\LdapInterface;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Datasource\EntityInterface;

/**
 * Overwrite all Ldap methods to enable tests
 */
class Ldap implements LdapInterface
{
    protected $_connection = 'connected';

    public function bind($bind, $password)
    {
        return true;
    }

    public function getConnection()
    {
        return $this->_connection;
    }

    public function connect($host, $port)
    {
        return;
    }

    public function setOption($option, $value)
    {
        return;
    }

    public function getOption($option)
    {
        return;
    }

    public function unbind()
    {
        $this->_connection = null;
    }
}

class LdapIdentifierTest extends TestCase
{

    /**
     * testIdentify
     *
     * @return void
     */
    public function testIdentify()
    {
        $identifier = new LdapIdentifier([
            'host' => 'ldap.example.com',
            'bindDN' => function () {
                return 'dc=example,dc=com';
            },
            'ldapClass' => Ldap::class
        ]);

        $result = $identifier->identify([
            'username' => 'john',
            'password' => 'doe'
        ]);

        $this->assertInstanceOf('\Cake\Datasource\EntityInterface', $result);
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
        $identifier = new LdapIdentifier([
            'host' => 'ldap.example.com',
            'bindDN' => function () {
                return 'dc=example,dc=com';
            },
            'ldapClass' => 1
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
