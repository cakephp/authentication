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

use Authentication\Authenticator\AbstractAuthenticator;
use Authentication\Identifier\IdentifierInterface;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;

class AbstractAuthenticatorTest extends TestCase
{

    /**
     * testGetIdentifier
     *
     * @return void
     */
    public function testGetIdentifier()
    {
        $identifier = $this->createMock(IdentifierInterface::class);
        $authenticator = $this->getMockForAbstractClass(AbstractAuthenticator::class, [$identifier]);

        $this->assertSame($identifier, $authenticator->getIdentifier());
    }

    /**
     * testSetIdentifier
     *
     * @return void
     */
    public function testSetIdentifier()
    {
        $authenticator = $this->getMockForAbstractClass(AbstractAuthenticator::class, [], '', false);

        $identifier = $this->createMock(IdentifierInterface::class);
        $authenticator->setIdentifier($identifier);

        $this->assertSame($identifier, $authenticator->getIdentifier());
    }
}
