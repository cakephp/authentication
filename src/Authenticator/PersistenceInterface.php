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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface PersistenceInterface
{
    /**
     * Persists the users data
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request object.
     * @param \Psr\Http\Message\ResponseInterface $response The response object.
     * @param \ArrayAccess|array $identity Identity data to persist.
     * @return array
     * @psalm-return array{request: ServerRequestInterface, response: ResponseInterface} Returns an array containing the request and response object
     */
    public function persistIdentity(ServerRequestInterface $request, ResponseInterface $response, $identity): array;

    /**
     * Clears the identity data
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request object.
     * @param \Psr\Http\Message\ResponseInterface $response The response object.
     * @return array
     * @psalm-return array{request: ServerRequestInterface, response: ResponseInterface} Returns an array containing the request and response object
     */
    public function clearIdentity(ServerRequestInterface $request, ResponseInterface $response): array;
}
