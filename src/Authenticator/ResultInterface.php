<?php
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

use Authentication\Identifier\IdentifierInterface;

interface ResultInterface
{
    /**
     * Failure due to invalid credentials being supplied.
     */
    const FAILURE_CREDENTIALS_INVALID = 'FAILURE_CREDENTIALS_INVALID';

    /**
     * The authentication credentials were not found in the request.
     */
    const FAILURE_CREDENTIALS_MISSING = 'FAILURE_CREDENTIALS_MISSING';

    /**
     * Failure due to identity not being found.
     */
    const FAILURE_IDENTITY_NOT_FOUND = 'FAILURE_IDENTITY_NOT_FOUND';

    /**
     * General failure due to any other circumstances.
     */
    const FAILURE_OTHER = 'FAILURE_OTHER';

    /**
     * Authentication success.
     */
    const SUCCESS = 'SUCCESS';

    /**
     * Returns whether the result represents a successful authentication attempt.
     *
     * @return bool
     */
    public function isValid();

    /**
     * Get the result status for this authentication attempt.
     *
     * @return string
     */
    public function getStatus();

    /**
     * Returns the identity data used in the authentication attempt.
     *
     * @return \ArrayAccess|array|null
     */
    public function getData();

    /**
     * Returns an array of string reasons why the authentication attempt was unsuccessful.
     *
     * If authentication was successful, this method returns an empty array.
     *
     * @return array
     */
    public function getErrors();

    /**
     * Return null or the identifier who match the correct identity.
     * @return \Authentication\Identifier\IdentifierInterface|null
     */
    public function getIdentifier();

    /**
     * Set the identifier who match the correct identity or null.
     * @param \Authentication\Identifier\IdentifierInterface|null $identifier The matching identifier.
     * @return void
     */
    public function setIdentifier(IdentifierInterface $identifier = null);

    /**
     * Return null or the authenticator who match the correct identity.
     * @return \Authentication\Authenticator\AuthenticatorInterface|null
     */
    public function getAuthenticator();

    /**
     * Set the authenticator who match the correct identity or null.
     * @param \Authentication\Authenticator\AuthenticatorInterface|null $authenticator The matching authenticator.
     * @return void
     */
    public function setAuthenticator(AuthenticatorInterface $authenticator = null);
}
