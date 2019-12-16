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
namespace Authentication\Identifier;

use Authentication\AbstractCollection;
use Cake\Core\App;
use RuntimeException;

/**
 * @method \Authentication\Identifier\IdentifierInterface|null get(string $name)
 */
class IdentifierCollection extends AbstractCollection implements IdentifierInterface
{
    /**
     * Errors
     *
     * @var array
     */
    protected $_errors = [];

    /**
     * Identifier that successfully Identified the identity.
     *
     * @var \Authentication\Identifier\IdentifierInterface|null
     */
    protected $_successfulIdentifier;

    /**
     * Identifies an user or service by the passed credentials
     *
     * @param array $credentials Authentication credentials
     * @return \ArrayAccess|array|null
     */
    public function identify(array $credentials)
    {
        /** @var \Authentication\Identifier\IdentifierInterface $identifier */
        foreach ($this->_loaded as $name => $identifier) {
            $result = $identifier->identify($credentials);
            if ($result) {
                $this->_successfulIdentifier = $identifier;

                return $result;
            }
            $this->_errors[$name] = $identifier->getErrors();
        }

        $this->_successfulIdentifier = null;

        return null;
    }

    /**
     * Creates identifier instance.
     *
     * @param string $className Identifier class.
     * @param string $alias Identifier alias.
     * @param array $config Config array.
     * @return \Authentication\Identifier\IdentifierInterface
     * @throws \RuntimeException
     */
    protected function _create($className, string $alias, array $config): IdentifierInterface
    {
        $identifier = new $className($config);
        if (!($identifier instanceof IdentifierInterface)) {
            throw new RuntimeException(sprintf(
                'Identifier class `%s` must implement `%s`.',
                $className,
                IdentifierInterface::class
            ));
        }

        return $identifier;
    }

    /**
     * Get errors
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->_errors;
    }

    /**
     * Resolves identifier class name.
     *
     * @param string $class Class name to be resolved.
     * @return string|null
     * @psalm-return class-string|null
     */
    protected function _resolveClassName($class): ?string
    {
        $className = App::className($class, 'Identifier', 'Identifier');

        return is_string($className) ? $className : null;
    }

    /**
     *
     * @param string $class Missing class.
     * @param string $plugin Class plugin.
     * @return void
     * @throws \RuntimeException
     */
    protected function _throwMissingClassError(string $class, ?string $plugin): void
    {
        $message = sprintf('Identifier class `%s` was not found.', $class);
        throw new RuntimeException($message);
    }

    /**
     * Gets the successful identifier instance if one was successful after calling identify.
     *
     * @return \Authentication\Identifier\IdentifierInterface|null
     */
    public function getIdentificationProvider()
    {
        return $this->_successfulIdentifier;
    }
}
