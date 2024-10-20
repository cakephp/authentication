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

use Authentication\Authenticator\AbstractAuthenticator;
use Authentication\Authenticator\Result;
use Authentication\Authenticator\ResultInterface;
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
        $authenticator = new class ($identifier) extends AbstractAuthenticator {
            public function authenticate($request): Result
            {
                return new Result([], ResultInterface::SUCCESS);
            }
        };

        $this->assertSame($identifier, $authenticator->getIdentifier());
    }

    /**
     * testSetIdentifier
     *
     * @return void
     */
    public function testSetIdentifier()
    {
        $identifier = $this->createMock(IdentifierInterface::class);
        $authenticator = new class ($identifier) extends AbstractAuthenticator {
            public function authenticate($request): Result
            {
                return new Result([], ResultInterface::SUCCESS);
            }
        };

        $otherIdentifier = $this->createMock(IdentifierInterface::class);
        $authenticator->setIdentifier($otherIdentifier);

        $this->assertSame($otherIdentifier, $authenticator->getIdentifier());
    }
}
