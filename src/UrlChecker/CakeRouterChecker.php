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
namespace Authentication\UrlChecker;

use Cake\Routing\Exception\MissingRouteException;
use Cake\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * Checks if a request object contains a valid URL
 */
class CakeRouterChecker implements UrlCheckerInterface
{
    /**
     * Default Options
     *
     * - `checkFullUrl` Whether or not to check the full request URI.
     *
     * @var array
     */
    protected $_defaultOptions = [
        'checkFullUrl' => false
    ];

    /**
     * {@inheritdoc}
     */
    public function check(ServerRequestInterface $request, $loginUrls, array $options = [])
    {
        $options = $this->_mergeDefaultOptions($options);
        $url = $this->_getUrlFromRequest($request->getUri(), $options['checkFullUrl']);

        // Support string loginUrls
        if (is_string($loginUrls)) {
            return ($loginUrls === $url);
        }

        if (!is_array($loginUrls)) {
            return false;
        }
        if (empty($loginUrls)) {
            return true;
        }

        // If it's a single route array add to another
        if (!is_numeric(key($loginUrls))) {
            $loginUrls = [$loginUrls];
        }

        foreach ($loginUrls as $validUrl) {
            try {
                $validUrl = Router::url($validUrl, $options['checkFullUrl']);
            } catch (MissingRouteException $e) {
            }
            if ($validUrl === $url) {
                return true;
            }
        }

        return false;
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
}
