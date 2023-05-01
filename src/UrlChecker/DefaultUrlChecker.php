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
 * @since         1.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\UrlChecker;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Checks if a request object contains a valid URL
 */
class DefaultUrlChecker implements UrlCheckerInterface
{
    /**
     * Default Options
     *
     * - `urlChecker` Whether or not to use `loginUrl` as regular expression(s).
     * - `checkFullUrl` Whether or not to check the full request URI.
     *
     * @var array
     */
    protected array $_defaultOptions = [
        'useRegex' => false,
        'checkFullUrl' => false,
    ];

    /**
     * @inheritDoc
     */
    public function check(ServerRequestInterface $request, $loginUrls, array $options = []): bool
    {
        $options = $this->_mergeDefaultOptions($options);

        $urls = (array)$loginUrls;

        if (empty($urls)) {
            return true;
        }

        $checker = $this->_getChecker($options);

        $url = $this->_getUrlFromRequest($request, $options['checkFullUrl']);

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
    protected function _mergeDefaultOptions(array $options): array
    {
        return $options + $this->_defaultOptions;
    }

    /**
     * Gets the checker function name or a callback
     *
     * @param array $options Array of options
     * @return callable
     */
    protected function _getChecker(array $options): callable
    {
        if (!empty($options['useRegex'])) {
            return 'preg_match';
        }

        return function ($validUrl, $url) {
            return $validUrl === $url;
        };
    }

    /**
     * Returns current url.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Server Request
     * @param bool $getFullUrl Get the full URL or just the path
     * @return string
     */
    protected function _getUrlFromRequest(ServerRequestInterface $request, bool $getFullUrl = false): string
    {
        $uri = $request->getUri();

        $requestBase = $request->getAttribute('base');
        if ($requestBase) {
            $uri = $uri->withPath($requestBase . $uri->getPath());
        }

        if ($getFullUrl) {
            return (string)$uri;
        }

        return $uri->getPath();
    }
}
