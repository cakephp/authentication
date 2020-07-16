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
namespace Authentication\Test\TestCase\View\Helper;

use Authentication\Identity;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Authentication\View\Helper\IdentityHelper;
use Cake\Http\ServerRequest;
use Cake\View\View;
use RuntimeException;

/**
 * IdentityHelperTest
 */
class IdentityHelperTest extends TestCase
{
    /**
     * test bad identity
     *
     * @return void
     */
    public function testBadIdentityError()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Identity found in request does not');

        $identity = [
            'id' => 1,
        ];
        $request = (new ServerRequest())->withAttribute('identity', $identity);
        $view = new View($request);

        $helper = new IdentityHelper($view);
    }

    /**
     * testWithIdentity
     *
     * @return void
     */
    public function testWithIdentity()
    {
        $identity = new Identity([
            'id' => 1,
            'username' => 'cakephp',
            'profile' => [
                'first_name' => 'cake',
            ],
        ]);
        $request = (new ServerRequest())->withAttribute('identity', $identity);
        $view = new View($request);

        $helper = new IdentityHelper($view);
        $this->assertEquals(1, $helper->get('id'));
        $this->assertEquals('cake', $helper->get('profile.first_name'));
        $this->assertEquals($identity->getOriginalData(), $helper->get());

        $this->assertTrue($helper->isLoggedIn());
        $this->assertEquals(1, $helper->getId());

        $this->assertTrue($helper->is(1));
        $this->assertFalse($helper->is(2));
    }

    /**
     * testWithOutIdentity
     *
     * @return void
     */
    public function testWithOutIdentity()
    {
        $request = new ServerRequest();
        $view = new View($request);

        $helper = new IdentityHelper($view);
        $this->assertEquals(null, $helper->get('id'));
        $this->assertEquals(null, $helper->get('profile.first_name'));

        $this->assertFalse($helper->isLoggedIn());
        $this->assertNull($helper->getId());

        $this->assertFalse($helper->is(1));
    }
}
