<?php
namespace Auth\Authentication\Identifier;

use ArrayAccess;
use Cake\Core\App;
use Cake\Core\InstanceConfigTrait;

class IdentifierCollection implements ArrayAccess {

    use InstanceConfigTrait;

    protected $_identifiers = [];

    protected $_defaultConfig = [];

    public function __construct(array $config = []) {
        $this->config($config);

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
     * @param string|array $passwordHasher Name of the password hasher or an array with
     * at least the key `className` set to the name of the class to use
     * @return \Cake\Auth\AbstractPasswordHasher Password hasher instance
     * @throws \RuntimeException If password hasher class not found or
     *   it does not extend Cake\Auth\AbstractPasswordHasher
     */
    public function get($identifier, array $config = [])
    {
        if (isset($this->_identifiers[$identifier])) {
            return $this->_identifiers[$identifier];
        }

        $this->_identifiers[$identifier] = $this->load($identifier, $config);
        return $this->_identifiers[$identifier];
    }

    public function load($class, array $config = [])
    {
        $className = App::className($class, 'Authentication/Identifier', 'Identifier');

        if ($className === false) {
            throw new RuntimeException(sprintf('Identifier class "%s" was not found.', $class));
        }

        $identifier = new $className($config);
        if (!($identifier instanceof IdentifierInterface)) {
            throw new RuntimeException('Identifier must implement \Auth\Authentication\IdentifierInterface');
        }

        return $identifier;
    }

    /**
     * Whether a offset exists
     *
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     * @since 5.0.0
     */
    public function offsetExists($offset)
    {
        isset($this->_identifiers[$offset]);
    }

    /**
     * Offset to retrieve
     *
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     * @since 5.0.0
     */
    public function offsetGet($offset)
    {
        if (isset($this->_identifiers[$offset])) {
            return $this->_identifiers[$offset];
        }

        return null;
    }

    /**
     * Offset to set
     *
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     * @since 5.0.0
     */
    public function offsetSet($offset, $value)
    {
        $this->_identifiers[$offset] = $value;
    }

    /**
     * Offset to unset
     *
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     * @since 5.0.0
     */
    public function offsetUnset($offset)
    {
        unset($this->_identifiers[$offset]);
    }

}
