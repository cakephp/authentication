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
namespace Authentication\UrlChecker;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * Checks if a request object contains a valid URL
 */
class DefaultUrlChecker implements UrlCheckerInterface
{
    /**
     * Default Options
     *
     * @var array
     */
    protected $_defaultOptions = [
        'useRegex' => false,
        'checkFullUrl' => false
    ];

    /**
     * {@inheritdoc}
     */
    public function check(ServerRequestInterface $request, $urls, array $options = [])
    {
        $options = $this->_mergeDefaultOptions($options);

        $urls = (array)$urls;

        if (empty($urls)) {
            return true;
        }

        $checker = $this->_getChecker($options);

        $url = $this->_getUrlFromRequest($request->getUri(), $options['checkFullUrl']);

        foreach ($urls as $validUrl) {
            if ($checker($validUrl, $url)) {
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

        return function ($validUrl, $url) {
            return $validUrl === $url;
        };
    }

    /**
     * Returns current url.
     *
     * @param \Psr\Http\Message\UriInterface $uri Server Request
     * @param bool $getFullUrl Get the full URL or just the path
     * @return string
     */
    protected function _getUrlFromRequest(UriInterface $uri, $getFullUrl = false)
    {
        if ($getFullUrl) {
            return (string)$uri;
        }

        return $uri->getPath();
    }
}
