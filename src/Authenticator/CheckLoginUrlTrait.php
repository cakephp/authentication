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
use RuntimeException;

/**
 * CheckLoginUrlTrait
 */
trait CheckLoginUrlTrait
{
    /**
     * Checks the Login URL
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request that contains login information.
     * @return bool
     */
    protected function _checkLoginUrl(ServerRequestInterface $request)
    {
        return $this->_getLoginUrlChecker()->check($request, $this->getConfig('loginUrl'), [
            'useRegex' => $this->getConfig('useRegex'),
            'checkFullUrl' => $this->getConfig('checkFullUrl')
        ]);
    }

    /**
     * Gets the login URL checker
     *
     * @return \Authentication\Authenticator\LoginUrlCheckerInterface
     */
    protected function _getLoginUrlChecker()
    {
        $checkerClass = $this->getConfig('loginUrlChecker');
        $checker = new $checkerClass();

        if (!$checker instanceof LoginUrlCheckerInterface) {
            throw new RuntimeException(sprintf(
                'The provided login URL checker `%s` does not implement the `%s` interface',
                $checkerClass,
                LoginUrlCheckerInterface::class
            ));
        }

        return $checker;
    }
}
