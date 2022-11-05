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
namespace Authentication;

use ArrayAccess;
use BadMethodCallException;
use Cake\Core\InstanceConfigTrait;
use InvalidArgumentException;
use ReturnTypeWillChange;

/**
 * Identity object
 */
class Identity implements IdentityInterface
{
    use InstanceConfigTrait;

    /**
     * Default configuration.
     *
     * - `fieldMap` Mapping of fields
     *
     * @var array
     */
    protected $_defaultConfig = [
        'fieldMap' => [
            'id' => 'id',
        ],
    ];

    /**
     * Identity data
     *
     * @var array|\ArrayAccess
     */
    protected $data;

    /**
     * Constructor
     *
     * @param array|\ArrayAccess $data Identity data
     * @param array $config Config options
     * @throws \InvalidArgumentException When invalid identity data is passed.
     */
    public function __construct($data, array $config = [])
    {
        if (!is_array($data) && !($data instanceof ArrayAccess)) {
            $type = is_object($data) ? get_class($data) : gettype($data);
            $message = sprintf(
                'Identity data must be an `array` or implement `ArrayAccess` interface, `%s` given.',
                $type
            );
            throw new InvalidArgumentException($message);
        }

        $this->setConfig($config);
        $this->data = $data;
    }

    /**
     * @inheritDoc
     */
    public function getIdentifier()
    {
        return $this->get('id');
    }

    /**
     * Get data from the identity using object access.
     *
     * @param string $field Field in the user data.
     * @return mixed
     */
    public function __get(string $field)
    {
        return $this->get($field);
    }

    /**
     * Check if the field isset() using object access.
     *
     * @param string $field Field in the user data.
     * @return bool
     */
    public function __isset(string $field)
    {
        return $this->get($field) !== null;
    }

    /**
     * Get data from the identity
     *
     * @param string $field Field in the user data.
     * @return mixed
     */
    public function get($field)
    {
        $map = $this->_config['fieldMap'];
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
     * @link https://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset Offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return $this->get($offset) !== null;
    }

    /**
     * Offset to retrieve
     *
     * @link https://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset Offset
     * @return mixed
     */
    #[ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * Offset to set
     *
     * @link https://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset The offset to assign the value to.
     * @param mixed $value Value
     * @throws \BadMethodCallException
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        throw new BadMethodCallException('Identity does not allow wrapped data to be mutated.');
    }

    /**
     * Offset to unset
     *
     * @link https://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset Offset
     * @throws \BadMethodCallException
     * @return void
     */
    public function offsetUnset($offset): void
    {
        throw new BadMethodCallException('Identity does not allow wrapped data to be mutated.');
    }

    /**
     * @inheritDoc
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
    public function __debugInfo(): array
    {
        return [
            'config' => $this->_config,
            'data' => $this->data,
        ];
    }
}
