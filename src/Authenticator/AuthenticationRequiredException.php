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

use Cake\Http\Exception\HttpException;

/**
 * An exception for stateless authenticators when credentials are wrong/missing.
 *
 * Unlike `UnauthenticatedException` this class can carry authentication challenge headers.
 * and is used by stateless authenticators.
 */
class AuthenticationRequiredException extends HttpException
{
    /**
     * @var array
     */
    protected $headers = [];

    /**
     * @var string
     */
    protected $body = '';

    /**
     * Constructor
     *
     * @param array $headers The headers that should be sent in the unauthorized challenge response.
     * @param string $body The response body that should be sent in the challenge response.
     * @param int $code The exception code that will be used as a HTTP status code
     */
    public function __construct(array $headers, string $body = '', int $code = 401)
    {
        parent::__construct('Authentication is required to continue', $code);
        $this->headers = $headers;
        $this->body = $body;
    }

    /**
     * Get the headers.
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get the body.
     *
     * @return string
     */
    public function getBody(): string
    {
        return $this->body;
    }
}
