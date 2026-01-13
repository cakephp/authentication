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
namespace Authentication\Test\TestCase\Authenticator;

use Authentication\Authenticator\AuthenticatorCollection;
use Authentication\Authenticator\AuthenticatorInterface;
use Authentication\Authenticator\FormAuthenticator;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class AuthenticatorCollectionTest extends TestCase
{
    /**
     * Test constructor.
     *
     * @return void
     */
    public function testConstruct()
    {
        $collection = new AuthenticatorCollection([
            'Authentication.Form' => [
                'identifier' => 'Authentication.Password',
            ],
        ]);
        $result = $collection->get('Form');
        $this->assertInstanceOf(FormAuthenticator::class, $result);
    }

    /**
     * testLoad
     *
     * @return void
     */
    public function testLoad()
    {
        $collection = new AuthenticatorCollection();
        $result = $collection->load('Authentication.Form', [
            'identifier' => 'Authentication.Password',
        ]);
        $this->assertInstanceOf(FormAuthenticator::class, $result);
    }

    /**
     * testSet
     *
     * @return void
     */
    public function testSet()
    {
        $authenticator = $this->createMock(AuthenticatorInterface::class);

        $collection = new AuthenticatorCollection();
        $collection->set('Form', $authenticator);
        $this->assertSame($authenticator, $collection->get('Form'));
    }

    public function testLoadException()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Authenticator class `Does-not-exist` was not found.');
        $collection = new AuthenticatorCollection();
        $collection->load('Does-not-exist');
    }

    /**
     * testIsEmpty
     *
     * @return void
     */
    public function testIsEmpty()
    {
        $collection = new AuthenticatorCollection();
        $this->assertTrue($collection->isEmpty());

        $collection->load('Authentication.Form', [
            'identifier' => 'Authentication.Password',
        ]);
        $this->assertFalse($collection->isEmpty());
    }

    /**
     * testIterator
     *
     * @return void
     */
    public function testIterator()
    {
        $authenticator = $this->createMock(AuthenticatorInterface::class);

        $collection = new AuthenticatorCollection();
        $collection->set('Form', $authenticator);

        $this->assertContains($authenticator, $collection);
    }
}
