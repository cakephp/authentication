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
use Throwable;

/**
 * An exception that signals that authentication was required but missing.
 *
 * This class cannot carry authentication challenge headers. This exception
 * uses the 401 status code by default as this exception is used when the application
 * has rejected a request but we do not know which authenticator the user should try.
 */
class UnauthenticatedException extends HttpException
{
    /**
     * Constructor
     *
     * @param string $message The exception message
     * @param int $code The exception code that will be used as a HTTP status code
     * @param \Throwable|null $previous The previous exception or null
     */
    public function __construct(string $message = '', int $code = 401, ?Throwable $previous = null)
    {
        if (!$message) {
            $message = 'Authentication is required to continue';
        }
        parent::__construct($message, $code, $previous);
    }
}
