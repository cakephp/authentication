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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface PersistenceInterface
{
    /**
     * Persists the users data
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request object.
     * @param mixed $identity Identity data to persist.
     * @return void
     */
    public function persistIdentity(ServerRequestInterface $request, $identity);

    /**
     * Clears the identity data
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request object.
     * @return void
     */
    public function clearIdentity(ServerRequestInterface $request, ResponseInterface $response);
}
