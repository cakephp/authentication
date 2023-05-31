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

use Cake\Core\App;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * UrlCheckerTrait
 */
trait UrlCheckerTrait
{
    /**
     * Checks the Login URL
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request that contains login information.
     * @return bool
     */
    protected function _checkUrl(ServerRequestInterface $request): bool
    {
        return $this->_getUrlChecker()->check(
            $request,
            $this->getConfig('loginUrl'),
            (array)$this->getConfig('urlChecker')
        );
    }

    /**
     * Gets the login URL checker
     *
     * @return \Authentication\UrlChecker\UrlCheckerInterface
     */
    protected function _getUrlChecker(): UrlCheckerInterface
    {
        $options = $this->getConfig('urlChecker');
        if (!is_array($options)) {
            $options = [
                'className' => $options,
            ];
        }
        if (!isset($options['className'])) {
            $options['className'] = DefaultUrlChecker::class;
        }

        $className = App::className($options['className'], 'UrlChecker', 'UrlChecker');
        if ($className === null) {
            throw new RuntimeException(sprintf('URL checker class `%s` was not found.', $options['className']));
        }

        $interfaces = class_implements($className);

        if (!isset($interfaces[UrlCheckerInterface::class])) {
            throw new RuntimeException(sprintf(
                'The provided URL checker class `%s` does not implement the `%s` interface.',
                $options['className'],
                UrlCheckerInterface::class
            ));
        }

        /** @var \Authentication\UrlChecker\UrlCheckerInterface $obj */
        $obj = new $className();

        return $obj;
    }
}
