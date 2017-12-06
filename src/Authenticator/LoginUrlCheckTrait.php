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

use Psr\Http\Message\ServerRequestInterface;

trait LoginUrlCheckTrait
{

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
