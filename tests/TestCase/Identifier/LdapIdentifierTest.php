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

use Authentication\Identifier\LdapIdentifier as BaseLdapIdentifier;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Datasource\EntityInterface;

/**
 * Overwrite all LdapOopTrait methods to enable tests
 */
class LdapIdentifier extends BaseLdapIdentifier
{
    protected $ldapConnection = 'connected';

    public function ldapBind($bind, $password)
    {
        return true;
    }

    public function getLdapConnection()
    {
        return $this->ldapConnection;
    }

    public function ldapConnect($host, $port)
    {
        return;
    }

    public function ldapSetOption($option, $value)
    {
        return;
    }

    public function ldapGetOption($option)
    {
        return;
    }

    public function ldapUnbind()
    {
        $this->ldapConnection = null;
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
            'bindDN' => function() {
                return 'dc=example,dc=com';
            },
        ]);

        $result = $identifier->identify([
            'username' => 'john',
            'password' => 'doe'
        ]);

        $this->assertInstanceOf('\Cake\Datasource\EntityInterface', $result);
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
            'bindDN' => function() {
                return 'dc=example,dc=com';
            }
        ]);
    }
}