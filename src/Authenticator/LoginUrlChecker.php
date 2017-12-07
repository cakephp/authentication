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

/**
 * Checks if a request object contains a valid login URL
 */
class LoginUrlChecker implements LoginUrlCheckerInterface
{
    /**
     * Default Options
     *
     * @var array
     */
    protected $_defaultOptions = [
        'loginUrl' => '/users/login',
        'useRegex' => false,
        'checkFullUrl' => false
    ];

    /**
     * {@inheritdoc}
     */
    public function check(ServerRequestInterface $request, $loginUrls, array $options = [])
    {
        $options = $this->_mergeDefaultOptions($options);

        $loginUrls = (array)$loginUrls;

        if (empty($loginUrls)) {
            return true;
        }

        $check = $this->_getChecker($options);

        $getFullUrl = (isset($options['checkFullUrl']) && $options['checkFullUrl']);
        $url = $this->_getUrlFromRequest($request, $getFullUrl);

        foreach ($loginUrls as $loginUrl) {
            if ($check($loginUrl, $url)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Merges given options with the defaults.
     *
     * The reason this method exists is that it makes it easy to override the
     * method and inject additional options without the need to use the
     * MergeVarsTrait.
     *
     * @param array $options Options to merge in
     * @return array
     */
    protected function _mergeDefaultOptions(array $options)
    {
        return $options += $this->_defaultOptions;
    }

    /**
     * Gets the checker function name or a callback
     *
     * @param array $options Array of options
     * @return string|callable
     */
    protected function _getChecker(array $options = [])
    {
        if (isset($options['useRegex']) && $options['useRegex']) {
            return 'preg_match';
        }

        return function ($loginUrl, $url) {
            return $loginUrl === $url;
        };
    }

    /**
     * Returns current url.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Server Request
     * @param bool $getFullUrl Get the full URL or just the path
     * @return string
     */
    protected function _getUrlFromRequest(ServerRequestInterface $request, $getFullUrl = false)
    {
        if ($getFullUrl) {
            return (string)$request->getUri();
        }

        return $request->getUri()->getPath();
    }
}
