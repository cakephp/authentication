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
namespace Authentication\Authenticator;

use Authentication\AbstractCollection;
use Authentication\Identifier\IdentifierCollection;
use Cake\Core\App;
use RuntimeException;

class AuthenticatorCollection extends AbstractCollection
{

    /**
     * Identifier collection.
     *
     * @var \Authentication\Identifier\IdentifierCollection
     */
    protected $_identifiers;

    /**
     * Constructor.
     *
     * @param \Authentication\Identifier\IdentifierCollection $identifiers Identifiers collection.
     * @param array $config Config array.
     */
    public function __construct(IdentifierCollection $identifiers, array $config = [])
    {
        $this->_identifiers = $identifiers;

        parent::__construct($config);
    }

    /**
     * Creates authenticator instance.
     *
     * @param string $className Authenticator class.
     * @param string $alias Authenticator alias.
     * @param array $config Config array.
     * @return \Authentication\Authenticator\AuthenticatorInterface
     * @throws \RuntimeException
     */
    protected function _create($className, $alias, $config)
    {
        $authenticator = new $className($this->_identifiers, $config);
        if (!($authenticator instanceof AuthenticatorInterface)) {
            throw new RuntimeException(sprintf(
                'Authenticator class `%s` must implement \Auth\Authentication\AuthenticatorInterface',
                $className
            ));
        }

        return $authenticator;
    }

    /**
     * Resolves authenticator class name.
     *
     * @param string $class Class name to be resolved.
     * @return string|null
     */
    protected function _resolveClassName($class)
    {
        $className = App::className($class, 'Authenticator', 'Authenticator');

        return is_string($className) ? $className : null;
    }

    /**
     *
     * @param string $class Missing class.
     * @param string $plugin Class plugin.
     * @return void
     * @throws \RuntimeException
     */
    protected function _throwMissingClassError($class, $plugin)
    {
        $message = sprintf('Authenticator class `%s` was not found.', $class);
        throw new RuntimeException($message);
    }
}
