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

use Authentication\UrlChecker\DefaultUrlChecker;
use Authentication\UrlChecker\UrlCheckerInterface;
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
    protected function _checkUrl(ServerRequestInterface $request)
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
     * @return \Authentication\Authenticator\UrlCheckerInterface
     */
    protected function _getUrlChecker()
    {
        $options = $this->getConfig('urlChecker');
        if (!is_array($options)) {
            $options = [
                'className' => $options
            ];
        }
        if (!isset($options['className'])) {
            $options['className'] = DefaultUrlChecker::class;
        }

        $className = App::className($options['className'], 'UrlChecker', 'UrlChecker');
        $checker = new $className();

        if (!$checker instanceof UrlCheckerInterface) {
            throw new RuntimeException(sprintf(
                'The provided login URL checker `%s` does not implement the `%s` interface',
                $options['className'],
                UrlCheckerInterface::class
            ));
        }

        return $checker;
    }
}
