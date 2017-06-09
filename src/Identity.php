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
     * @var array
     */
    protected $data;

    /**
     * {@inheritdoc}
     */
    public function __construct($identityData, array $config = [])
    {
        $this->setConfig($config);
        $this->data = $identityData;
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
        return $this->data[$offset] = $value;
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
        unset($this->data[$offset]);
    }
}
