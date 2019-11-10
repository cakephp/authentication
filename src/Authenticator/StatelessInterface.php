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
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         1.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Authenticator;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Interface to mark an authenticator as being stateless and able
 * to emit a challenge exception when authentication fails.
 */
interface StatelessInterface
{
    /**
     * Create a challenge exception
     *
     * Create an exception with the appropriate headers and response body
     * to challenge a request that has missing or invalid credentials.
     *
     * This is primarily used by authentication methods that use the WWW-Authorization
     * header.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request.
     * @return void
     * @throws \Authentication\Authenticator\AuthenticationRequiredException
     */
    public function unauthorizedChallenge(ServerRequestInterface $request): void;
}
