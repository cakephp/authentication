<?php
namespace Auth\Authentication;

class Identity implements IdentityInterface
{

    /**
     * Identity data
     *
     * @var mixed
     */
    protected $_identity;

    /**
     * Constructor
     *
     * @param array|\ArrayAccess $identity The identity data.
     */
    public function __construct($identity)
    {
        if (!is_array($identity) && !is_object($identity)) {
            throw new \InvalidArgumentException(sprintf('First arg must be an array or object. `%s` ', gettype($identity)));
        }

        $this->_identity = $identity;
    }

    /**
     * {@inheritDoc}
     */
    public function set($path, $value)
    {
        Hash::insert($this->_identity, $path, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function get($path = null)
    {
        if ($path === null) {
            return $this->_identity;
        }

        return Hash::get($this->_identity, $path);
    }

    /**
     * {@inheritDoc}
     */
    public function has($path)
    {
        return (bool)Hash::get($this->_identity, $path);
    }
}
