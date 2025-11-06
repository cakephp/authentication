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
 * @since         4.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\UrlChecker;

use Cake\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Multi URL Checker
 *
 * Supports checking multiple login URLs, automatically handling both
 * string URLs and array-based CakePHP routes.
 *
 * This checker automatically detects the URL type and uses the appropriate
 * checker (Default for strings, CakeRouter for arrays).
 */
class MultiUrlChecker implements UrlCheckerInterface
{
    /**
     * Default Options
     *
     * - `useRegex` Whether to use `loginUrl` as regular expression(s).
     * - `checkFullUrl` Whether to check the full request URI.
     *
     * @var array<string, mixed>
     */
    protected array $_defaultOptions = [
        'useRegex' => false,
        'checkFullUrl' => false,
    ];

    /**
     * @inheritDoc
     */
    public function check(ServerRequestInterface $request, array|string $loginUrls, array $options = []): bool
    {
        $options = $this->_mergeDefaultOptions($options);
        $urls = (array)$loginUrls;

        if (!$urls) {
            return true;
        }

        foreach ($urls as $url) {
            if ($this->_checkSingleUrl($request, $url, $options)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check a single URL
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request.
     * @param array|string $url The URL to check (can be string or array).
     * @param array<string, mixed> $options Options array.
     * @return bool
     */
    protected function _checkSingleUrl(ServerRequestInterface $request, array|string $url, array $options): bool
    {
        // Use CakeRouterUrlChecker for array URLs
        if (is_array($url) && class_exists(Router::class)) {
            $checker = new CakeRouterUrlChecker();

            return $checker->check($request, [$url], $options);
        }

        // Use DefaultUrlChecker for string URLs
        $checker = new DefaultUrlChecker();

        return $checker->check($request, $url, $options);
    }

    /**
     * Merge default options with provided options
     *
     * @param array<string, mixed> $options The options to merge.
     * @return array<string, mixed>
     */
    protected function _mergeDefaultOptions(array $options): array
    {
        return $options + $this->_defaultOptions;
    }
}
