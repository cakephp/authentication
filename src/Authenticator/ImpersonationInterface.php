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
 * @since         2.10.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Authenticator;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface ImpersonationInterface extends PersistenceInterface
{
    /**
     * Impersonates a user
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request
     * @param \Psr\Http\Message\ResponseInterface $response The response
     * @param \ArrayAccess $impersonator User who impersonates
     * @param \ArrayAccess $impersonated User impersonated
     * @return array
     */
    public function impersonate(
        ServerRequestInterface $request,
        ResponseInterface $response,
        \ArrayAccess $impersonator,
        \ArrayAccess $impersonated
    ): array;

    /**
     * Stops impersonation
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request
     * @param \Psr\Http\Message\ResponseInterface $response The response
     * @return array
     */
    public function stopImpersonating(ServerRequestInterface $request, ResponseInterface $response): array;

    /**
     * Returns true if impersonation is being done
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request
     * @return bool
     */
    public function isImpersonating(ServerRequestInterface $request): bool;
}
