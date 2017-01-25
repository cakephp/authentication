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
namespace Authentication\Authenticator;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Interface to mark an authenticator as being able
 * to emit a challenge exception when authentication fails.
 */
interface ChallengerInterface
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
     * @throws \Authentication\Authenticator\ChallengeException
     */
    public function authenticationChallenge(ServerRequestInterface $request);
}
