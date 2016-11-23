<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         4.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Auth\Authentication;

use Cake\Network\Exception\UnauthorizedException;
use Auth\Authentication\Identifier\IdentifierCollection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Session Authenticator
 */
class SessionAuthenticator extends AbstractAuthenticator
{

    /**
     * Constructor
     *
     * @param array $identifiers Array of config to use.
     */
    public function __construct(IdentifierCollection $identifiers, array $config = [])
    {
        $this->_defaultConfig['sessionKey'] = 'Auth';
        parent::__construct($identifiers, $config);
    }

    /**
     * Authenticate a user using session data.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request to authenticate with.
     * @param \Psr\Http\Message\ResponseInterface $response The response to add headers to.
     * @return mixed Either false on failure, or an array of user data on success.
     */
    public function authenticate(ServerRequestInterface $request, ResponseInterface $response)
    {
        $sessionKey = $this->config('sessionKey');
        $session = $request->getAttribute('session');
        $user = $session->read($sessionKey);

        if (empty($user)) {
            return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND);
        }

        if ($this->config('verifyByDatabase') === true) {
            $user = $this->identifiers()->identify([
                'username' => $user[$this->config('fields')['username']],
                'password' => $user[$this->config('fields')['password']]
            ]);

            if (empty($user)) {
                return new Result(null, Result::FAILURE_CREDENTIAL_INVALID);
            }
        }

        return new Result($user, Result::SUCCESS);
    }
}
