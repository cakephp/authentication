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
namespace Authentication\Test\TestCase\UrlChecker;

use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Authentication\UrlChecker\DefaultUrlChecker;
use Cake\Http\ServerRequestFactory;

/**
 * DefaultUrlCheckerTest
 */
class DefaultUrlCheckerTest extends TestCase
{
    /**
     * testCheckFailure
     *
     * @return void
     */
    public function testCheckFailure()
    {
        $checker = new DefaultUrlChecker();

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/does-not-match']
        );

        $result = $checker->check($request, '/users/login');
        $this->assertFalse($result);
    }

    /**
     * testCheckSimple
     *
     * @return void
     */
    public function testCheckSimple()
    {
        $checker = new DefaultUrlChecker();
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/login']
        );
        $result = $checker->check($request, '/users/login');
        $this->assertTrue($result);

        $result = $checker->check($request, [
            '/users/login',
            '/admin/login',
        ]);
        $this->assertTrue($result);
    }

    /**
     * testCheckArray
     *
     * @return void
     */
    public function testCheckArray()
    {
        $checker = new DefaultUrlChecker();
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/login']
        );

        $result = $checker->check($request, [
            '/users/login',
            '/admin/login',
        ]);
        $this->assertTrue($result);
    }

    /**
     * testCheckRegexp
     *
     * @return void
     */
    public function testCheckRegexp()
    {
        $checker = new DefaultUrlChecker();
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/en/users/login']
        );

        $result = $checker->check($request, '%^/[a-z]{2}/users/login/?$%', [
            'useRegex' => true,
        ]);
        $this->assertTrue($result);
    }

    /**
     * testCheckFull
     *
     * @return void
     */
    public function testCheckFull()
    {
        $checker = new DefaultUrlChecker();
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/login']
        );

        $result = $checker->check($request, 'http://localhost/users/login', [
            'checkFullUrl' => true,
        ]);
        $this->assertTrue($result);
    }

    /**
     * testCheckBase
     *
     * @return void
     */
    public function testCheckBase()
    {
        $checker = new DefaultUrlChecker();
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/login']
        );
        $uri = $request->getUri();
        $uri->base = '/base';
        $request = $request->withUri($uri);

        $result = $checker->check($request, 'http://localhost/base/users/login', [
            'checkFullUrl' => true,
        ]);
        $this->assertTrue($result);
    }
}
