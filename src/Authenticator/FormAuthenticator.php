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

use Authentication\Identifier\IdentifierInterface;
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
        'fields' => [
            IdentifierInterface::CREDENTIAL_USERNAME => 'username',
            IdentifierInterface::CREDENTIAL_PASSWORD => 'password'
        ],
        'loginUrl' => null,
        'useRegex' => false,
        'checkFullUrl' => false
    ];

    /**
     * Checks the fields to ensure they are supplied.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request that contains login information.
     * @return array|null Username and password retrieved from a request body.
     */
    protected function _getData(ServerRequestInterface $request)
    {
        $fields = $this->_config['fields'];
        $body = $request->getParsedBody();

        $data = [];
        foreach ($fields as $key => $field) {
            if (!isset($body[$field])) {
                return null;
            }

            $value = $body[$field];
            if (!is_string($value) || !strlen($value)) {
                return null;
            }

            $data[$key] = $value;
        }

        return $data;
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
        if (!$this->_checkLoginUrl($request)) {
            $url = $this->_getUrl($request);

            $errors = [
                sprintf(
                    'Login URL `%s` did not match `%s`.',
                    $url,
                    implode('` or `', (array)$this->getConfig('loginUrl'))
                )
            ];

            return new Result(null, Result::FAILURE_OTHER, $errors);
        }

        $data = $this->_getData($request);
        if ($data === null) {
            return new Result(null, Result::FAILURE_CREDENTIALS_NOT_FOUND, [
                'Login credentials not found'
            ]);
        }

        $user = $this->identifiers()->identify($data);

        if (empty($user)) {
            return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND, $this->identifiers()->getErrors());
        }

        return new Result($user, Result::SUCCESS);
    }

    /**
     * Checks the requests if it is the configured login action
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request that contains login information.
     * @return bool
     */
    protected function _checkLoginUrl(ServerRequestInterface $request)
    {
        $loginUrls = (array)$this->getConfig('loginUrl');

        if (empty($loginUrls)) {
            return true;
        }

        if ($this->getConfig('useRegex')) {
            $check = 'preg_match';
        } else {
            $check = function ($loginUrl, $url) {
                return $loginUrl === $url;
            };
        }

        $url = $this->_getUrl($request);
        foreach ($loginUrls as $loginUrl) {
            if ($check($loginUrl, $url)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns current url.
     *
     * @param ServerRequestInterface $request Server request.
     * @return string
     */
    protected function _getUrl(ServerRequestInterface $request)
    {
        if ($this->getConfig('checkFullUrl')) {
            return (string)$request->getUri();
        }

        return $request->getUri()->getPath();
    }
}
