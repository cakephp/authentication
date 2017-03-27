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
namespace Authentication\Test\TestCase\Authenticator;

use Authentication\Authenticator\AuthenticatorCollection;
use Authentication\Authenticator\AuthenticatorInterface;
use Authentication\Authenticator\FormAuthenticator;
use Authentication\Identifier\IdentifierCollection;
use Cake\TestSuite\TestCase;
use TestApp\Authentication\Identifier\InvalidIdentifier;

class AuthenticatorCollectionTest extends TestCase
{

    /**
     * Test constructor.
     *
     * @return void
     */
    public function testConstruct()
    {
        $identifiers = $this->createMock(IdentifierCollection::class);
        $collection = new AuthenticatorCollection($identifiers, [
            'Authentication.Form'
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
        $identifiers = $this->createMock(IdentifierCollection::class);
        $collection = new AuthenticatorCollection($identifiers);
        $result = $collection->load('Authentication.Form');
        $this->assertInstanceOf(FormAuthenticator::class, $result);
    }

    /**
     * testSet
     *
     * @return void
     */
    public function testSet()
    {
        $identifiers = $this->createMock(IdentifierCollection::class);
        $authenticator = $this->createMock(AuthenticatorInterface::class);

        $collection = new AuthenticatorCollection($identifiers);
        $collection->set('Form', $authenticator);
        $this->assertSame($authenticator, $collection->get('Form'));
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Authenticator class `Does-not-exist` was not found.
     */
    public function testLoadException()
    {
        $identifiers = $this->createMock(IdentifierCollection::class);
        $collection = new AuthenticatorCollection($identifiers);
        $collection->load('Does-not-exist');
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Authenticator class `TestApp\Authentication\Identifier\InvalidIdentifier`
     */
    public function testLoadExceptionInterfaceNotImplemented()
    {
        $identifiers = $this->createMock(IdentifierCollection::class);
        $collection = new AuthenticatorCollection($identifiers);
        $collection->load(InvalidIdentifier::class);
    }

    /**
     * testIsEmpty
     *
     * @return void
     */
    public function testIsEmpty()
    {
        $identifiers = $this->createMock(IdentifierCollection::class);
        $collection = new AuthenticatorCollection($identifiers);
        $this->assertTrue($collection->isEmpty());

        $collection->load('Authentication.Form');
        $this->assertFalse($collection->isEmpty());
    }

    /**
     * testIterator
     *
     * @return void
     */
    public function testIterator()
    {
        $identifiers = $this->createMock(IdentifierCollection::class);
        $authenticator = $this->createMock(AuthenticatorInterface::class);

        $collection = new AuthenticatorCollection($identifiers);
        $collection->set('Form', $authenticator);

        $this->assertContains($authenticator, $collection);
    }
}
