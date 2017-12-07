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

/**
 * Form Authenticator
 *
 * Authenticates an identity based on the POST data of the request.
 */
class FormAuthenticator extends AbstractAuthenticator
{

    /**
     * Default config for this object.
     * - `fields` The fields to use to identify a user by.
     * - `loginUrl` Login URL or an array of URLs.
     * - `useRegex` Whether or not to use `loginUrl` as regular expression(s).
     * - `checkFullUrl` Whether or not to check the full request URI.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'loginUrlChecker' => LoginUrlChecker::class,
        'fields' => [
            'username' => 'username',
            'password' => 'password'
        ],
        'loginUrl' => null,
        'useRegex' => false,
        'checkFullUrl' => false
    ];

    /**
     * Checks the fields to ensure they are supplied.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request that contains login information.
     * @param array $fields The fields to be checked.
     * @return bool False if the fields have not been supplied. True if they exist.
     */
    protected function _checkBody(ServerRequestInterface $request, array $fields)
    {
        $body = $request->getParsedBody();

        foreach ([$fields['username'], $fields['password']] as $field) {
            if (!isset($body[$field])) {
                return false;
            }

            $value = $body[$field];
            if (empty($value) || !is_string($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Authenticates the identity contained in a request. Will use the `config.userModel`, and `config.fields`
     * to find POST data that is used to find a matching record in the `config.userModel`. Will return false if
     * there is no post data, either username or password is missing, or if the scope conditions have not been met.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request that contains login information.
     * @param \Psr\Http\Message\ResponseInterface $response Unused response object.
     * @return \Authentication\Authenticator\ResultInterface
     */
    public function authenticate(ServerRequestInterface $request, ResponseInterface $response)
    {
        $checkerClass = $this->getConfig('loginUrlChecker');
        $checker = new $checkerClass();
        $isLoginUrl = $checker->check($request, $this->getConfig('loginUrl'), [
            'useRegex' => $this->getConfig('useRegex'),
            'checkFullUrl' => $this->getConfig('checkFullUrl')
        ]);

        if (!$isLoginUrl) {
            if ($this->getConfig('checkFullUrl')) {
                $url = (string)$request->getUri();
            } else {
                $url = $request->getUri()->getPath();
            }

            $errors = [
                sprintf(
                    'Login URL `%s` did not match `%s`.',
                    $url,
                    implode('` or `', (array)$this->getConfig('loginUrl'))
                )
            ];

            return new Result(null, Result::FAILURE_OTHER, $errors);
        }

        $fields = $this->_config['fields'];
        if (!$this->_checkBody($request, $fields)) {
            return new Result(null, Result::FAILURE_CREDENTIALS_NOT_FOUND, [
                'Login credentials not found'
            ]);
        }

        $body = $request->getParsedBody();
        $user = $this->identifiers()->identify($body);

        if (empty($user)) {
            return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND, $this->identifiers()->getErrors());
        }

        return new Result($user, Result::SUCCESS);
    }
}
