<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link http://cakephp.org CakePHP(tm) Project
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Test\TestCase\PasswordHasher;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Authentication\PasswordHasher\FallbackPasswordHasher;
use Authentication\PasswordHasher\LegacyPasswordHasher;
use Cake\TestSuite\TestCase;

/**
 * Test case for FallbackPasswordHasher
 */
class FallbackPasswordHasherTest extends TestCase
{
    /**
     * Tests that only the first hasher is user for hashing a password
     *
     * @return void
     */
    public function testHash()
    {
        $hasher = new FallbackPasswordHasher(['hashers' => ['Authentication.Legacy', 'Authentication.Default']]);
        $legacy = new LegacyPasswordHasher();
        $this->assertSame($legacy->hash('foo'), $hasher->hash('foo'));

        $simple = new DefaultPasswordHasher();
        $hasher = new FallbackPasswordHasher(['hashers' => ['Authentication.Legacy', 'Authentication.Default']]);
        $this->assertSame($legacy->hash('foo'), $hasher->hash('foo'));
    }

    /**
     * Tests that the check method will check with configured hashers until a match
     * is found
     *
     * @return void
     */
    public function testCheck()
    {
        $hasher = new FallbackPasswordHasher(['hashers' => ['Authentication.Legacy', 'Authentication.Default']]);
        $legacy = new LegacyPasswordHasher();
        $simple = new DefaultPasswordHasher();

        $hash = $simple->hash('foo');
        $otherHash = $legacy->hash('foo');
        $this->assertTrue($hasher->check('foo', $hash));
        $this->assertTrue($hasher->check('foo', $otherHash));
    }

    /**
     * Tests that the check method will work with configured hashers including different
     * configs per hasher.
     *
     * @return void
     */
    public function testCheckWithConfigs()
    {
        $hasher = new FallbackPasswordHasher(['hashers' => ['Authentication.Default', 'Authentication.Legacy' => ['hashType' => 'md5']]]);
        $legacy = new LegacyPasswordHasher(['hashType' => 'md5']);
        $simple = new DefaultPasswordHasher();

        $hash = $simple->hash('foo');
        $legacyHash = $legacy->hash('foo');
        $this->assertTrue($hash !== $legacyHash);
        $this->assertTrue($hasher->check('foo', $hash));
        $this->assertTrue($hasher->check('foo', $legacyHash));
    }

    /**
     * Tests that the password only needs to be re-built according to the first hasher
     *
     * @return void
     */
    public function testNeedsRehash()
    {
        $hasher = new FallbackPasswordHasher(['hashers' => ['Authentication.Default', 'Authentication.Legacy']]);
        $legacy = new LegacyPasswordHasher();
        $otherHash = $legacy->hash('foo');
        $this->assertTrue($hasher->needsRehash($otherHash));

        $simple = new DefaultPasswordHasher();
        $hash = $simple->hash('foo');
        $this->assertFalse($hasher->needsRehash($hash));
    }
}
