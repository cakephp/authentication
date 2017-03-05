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
namespace Authentication\Identifier;

use Cake\Core\App;
use Cake\Core\InstanceConfigTrait;
use RuntimeException;

class IdentifierCollection
{

    use InstanceConfigTrait;

    /**
     * A list of identifier instances
     *
     * @var array
     */
    protected $_identifiers = [];

    /**
     * Config array.
     *
     * @var array
     */
    protected $_defaultConfig = [];

    /**
     * Errors
     *
     * @var array
     */
    protected $_errors = [];

    /**
     * Constructor
     *
     * @param array $config Configuration
     */
    public function __construct(array $config = [])
    {
        $this->setConfig($config);

        foreach ($config as $key => $value) {
            if (is_int($key)) {
                $this->load($value);
                continue;
            }
            $this->load($key, $value);
        }
    }

    /**
     * Returns password hasher object out of a hasher name or a configuration array
     *
     * @param string|array $identifier Name of the identifier
     * @param array $config Configuration settings
     * at least the key `className` set to the name of the class to use
     * @return \Authentication\Identifier\IdentifierInterface Identifier instance
     * @throws \RuntimeException If password hasher class not found or
     *   it does not extend Cake\Auth\AbstractPasswordHasher
     */
    public function get($identifier, array $config = [])
    {
        if (isset($this->_identifiers[$identifier])) {
            return $this->_identifiers[$identifier];
        }

        return $this->_identifiers[$identifier] = $this->load($identifier, $config);
    }

    /**
     * Identifies an user or service by the passed credentials
     *
     * @param mixed $credentials Authentication credentials
     * @return \Cake\Datasource\EntityInterface|null
     */
    public function identify($credentials)
    {
        foreach ($this->_identifiers as $name => $identifier) {
            $result = $identifier->identify($credentials);
            if ($result) {
                return $result;
            }
            $this->_errors[$name] = $identifier->getErrors();
        }

        return null;
    }

    /**
     * Get all loaded identifiers
     *
     * @return array
     */
    public function getAll()
    {
        return $this->_identifiers;
    }

    /**
     * Returns identifier object out of a identifier name or a configuration array
     *
     * @param array $class Name of the identifier
     * at least the key `className` set to the name of the class to use
     * @param array $config Configuration settings
     * @return \Authentication\Identifier\IdentifierInterface Identifier instance
     * @throws \RuntimeException If identifier class not found or
     *   it does not extend Auth\Authentication\Identifier\IdentifierInterface
     */
    public function load($class, array $config = [])
    {
        $className = App::className($class, 'Identifier', 'Identifier');

        if ($className === false) {
            throw new RuntimeException(sprintf('Identifier class `%s` was not found.', $class));
        }

        $identifier = new $className($config);
        if (!($identifier instanceof IdentifierInterface)) {
            throw new RuntimeException(sprintf(
                'Identifier class `%s` must implement \Auth\Authentication\IdentifierInterface',
                $className
            ));
        }

        if (isset($config['alias'])) {
            $class = $config['alias'];
        }

        return $this->_identifiers[$class] = $identifier;
    }

    /**
     * Get errors
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->_errors;
    }
}
