<?php
namespace Auth\Authentication;

class Identity implements IdentityInterface {

    /**
     * Identity data
     *
     * @var mixed
     */
    protected $_identity;

    /**
     * array|\ArrayAccess
     */
    public function __construct($identity)
    {
        if (!is_array($identity) && !is_object($identity)) {
            throw new \InvalidArgumentException(sprintf('First arg must be an array or object. `%s` ', gettype($identity)));
        }

        $this->_identity = $identity;
    }

    public function set($path, $value)
    {
        Hash::insert($this->_identity, $path, $value);
    }

    public function get($path = null)
    {
        if ($path === null) {
            return $this->_identity;
        }

        return Hash::get($this->_identity, $path);
    }

    public function has($path)
    {
        return (bool)Hash::get($this->_identity, $path);
    }

}
