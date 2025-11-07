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

use Psr\Http\Message\ServerRequestInterface;

/**
 * Multi URL Checker
 *
 * Supports checking multiple login URLs, handling both
 * string URLs and array-based CakePHP routes.
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

        // For a single URL (string or array route), convert to array
        if (is_string($loginUrls) || $this->_isSingleRoute($loginUrls)) {
            $urls = [$loginUrls];
        } else {
            $urls = $loginUrls;
        }

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
     * Check if the array is a single CakePHP route (not an array of routes)
     *
     * @param array|string $value The value to check
     * @return bool
     */
    protected function _isSingleRoute(array|string $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        if (!$value) {
            return false;
        }

        // A single route has string keys like ['controller' => 'Users']
        // An array of routes has numeric keys [0 => '/login', 1 => '/signin']
        reset($value);
        $firstKey = key($value);

        return !is_int($firstKey);
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
