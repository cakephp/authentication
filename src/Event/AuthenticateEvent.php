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
 * @since         3.4.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Event;

use Authentication\AuthenticationServiceInterface;
use Authentication\Authenticator\AuthenticatorInterface;
use Authentication\Authenticator\ResultInterface;
use Cake\Event\Event;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Event triggered when authentication is run.
 *
 * @extends \Cake\Event\Event<\Authentication\AuthenticationServiceInterface>
 */
class AuthenticateEvent extends Event
{
    /**
     * Event name
     *
     * @var string
     */
    public const NAME = 'Authentication.authenticate';

    /**
     * Constructor
     *
     * @param string $name Name of the event
     * @param \Authentication\AuthenticationServiceInterface $subject The Authentication service instance this event applies to.
     * @param \Psr\Http\Message\ServerRequestInterface $request The request instance.
     * @param \Authentication\Authenticator\AuthenticatorInterface $authenticator The authenticator instance.
     * @param \Authentication\Authenticator\ResultInterface $result The authentication result.
     */
    public function __construct(
        string $name,
        AuthenticationServiceInterface $subject,
        ServerRequestInterface $request,
        AuthenticatorInterface $authenticator,
        ResultInterface $result,
    ) {
        $this->result = $result;

        parent::__construct($name, $subject, compact('request', 'authenticator'));
    }

    /**
     * The authentication result.
     *
     * @return \Authentication\Authenticator\ResultInterface
     */
    public function getResult(): ResultInterface
    {
        return $this->result;
    }

    /**
     * Set the authentication result.
     *
     * @param \Authentication\Authenticator\ResultInterface|null $value The result to set.
     * @return $this
     */
    public function setResult(mixed $value = null)
    {
        if (!$value instanceof ResultInterface) {
            throw new InvalidArgumentException(
                'The result for Authentication.authenticate event must be a '
                . '`Authentication\Authenticator\ResultInterface` instance.',
            );
        }

        return parent::setResult($value);
    }

    /**
     * Get the request instance.
     *
     * @return \Psr\Http\Message\RequestInterface
     */
    public function getRequest(): RequestInterface
    {
        return $this->_data['request'];
    }

    /**
     * Get the adapter options.
     *
     * @return \Authentication\Authenticator\AuthenticatorInterface
     */
    public function getAuthenticator(): AuthenticatorInterface
    {
        return $this->_data['authenticator'];
    }
}
