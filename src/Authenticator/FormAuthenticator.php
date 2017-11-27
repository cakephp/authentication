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

use Cake\Http\ServerRequest;
use InvalidArgumentException;
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
        if (!$this->_checkLoginUrl($request)) {
            $loginUrl = $this->getConfig('loginUrl');
            if (is_array($loginUrl)) {
                $loginUrl = \Cake\Routing\Router::url($loginUrl, true);
            }
            dd($loginUrl);
            $errors = [
                sprintf(
                    'Login URL %s did not match %s',
                    $request->getUri()->getPath(),
                    $loginUrl
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

    /**
     * Checks the requests if it is the configured login action
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request that contains login information.
     * @return bool
     */
    protected function _checkLoginUrl(ServerRequestInterface $request)
    {
        $loginUrl = $this->getConfig('loginUrl');

        if (!empty($loginUrl)) {
            if (is_string($loginUrl) && $this->_compareStringUrl($loginUrl, $request->getUri()->getPath())) {
                return true;
            }

            return $this->_compareArrayUrl($loginUrl, $request);
        }

        return true;
    }

    /**
     *
     */
    protected function _compareArrayUrl($loginUrl, ServerRequestInterface $request) {
        if (!class_exists('\Cake\Routing\Router')) {
            return false;
        }

        $requestUrl = \Cake\Routing\Router::parseRequest($request);
        unset($request['_matchedRoute']);

        if (is_string($loginUrl)) {
            $loginUrl = \Cake\Routing\Router::parseRequest((new ServerRequest([
                'uri' => $loginUrl
            ])));

            unset($loginUrl['_matchedRoute']);
            $this->setConfig('loginUrl', $loginUrl);
        }

        $keysToCompare = array_keys($loginUrl);
        foreach ($keysToCompare as $key) {
            if (!array_key_exists($key, $requestUrl)
                || $requestUrl[$key] !== $loginUrl[$key]
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks string URLs for login
     *
     * @param string $loginUrl URL of the login to compare against
     * @param string $requestUrl URL from the request
     * @return bool
     */
    protected function _compareStringUrl($loginUrl, $requestUrl) {
        if (!is_string($loginUrl)) {
            throw new InvalidArgumentException();
        }

        if ($loginUrl === $requestUrl) {
            return true;
        }

        $regex = $this->getConfig('loginUrlRegex');
        if (!empty($regex) && preg_match($regex, $loginUrl)) {
            return true;
        }

        return false;
    }
}
