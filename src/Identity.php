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
namespace Authentication;

use ArrayAccess;
use Cake\Core\InstanceConfigTrait;

/**
 * Identity object
 */
class Identity implements IdentityInterface
{
    use InstanceConfigTrait;

    /**
     * Default configuration.
     * - `fieldMap` Mapping of fields
     *
     * @var array
     */
    protected $_defaultConfig = [
        'fieldMap' => [
            'id' => 'id'
        ]
    ];

    /**
     * Identity data
     *
     * @var \ArrayAccess
     */
    protected $data;

    /**
     * Constructor
     *
     * @param \ArrayAccess $data Identity data.
     * @param array $config Config options.
     */
    public function __construct(ArrayAccess $data, array $config = [])
    {
        $this->setConfig($config);
        $this->data = $data;
    }

    /**
     * {@inheritdoc}
     */
    public function getIdentifier()
    {
        return $this->get('id');
    }

    /**
     * Get data from the identity
     *
     * @param string $field Field in the user data.
     * @return mixed
     */
    public function get($field)
    {
        $map = $this->getConfig('fieldMap');
        if (isset($map[$field])) {
            $field = $map[$field];
        }

        if (isset($this->data[$field])) {
            return $this->data[$field];
        }

        return null;
    }

    /**
     * Whether a offset exists
     *
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset Offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    /**
     * Offset to retrieve
     *
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset Offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        if (isset($this->data[$offset])) {
            return $this->data[$offset];
        }

        return null;
    }

    /**
     * Offset to set
     *
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset The offset to assign the value to.
     * @param mixed $value Value
     * @return mixed
     */
    public function offsetSet($offset, $value)
    {
        return $this->data[$offset] = $value;
    }

    /**
     * Offset to unset
     *
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset Offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }

    /**
     * Gets the original data object.
     *
     * @return \ArrayAccess
     */
    public function getOriginalData()
    {
        return $this->data;
    }

    /**
     * Debug info
     *
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'config' => $this->_config,
            'data' => $this->data
        ];
    }
}
