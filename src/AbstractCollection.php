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
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication;

use ArrayIterator;
use Cake\Core\InstanceConfigTrait;
use Cake\Core\ObjectRegistry;
use IteratorAggregate;

abstract class AbstractCollection extends ObjectRegistry implements IteratorAggregate
{
    use InstanceConfigTrait;

    /**
     * Config array.
     *
     * @var array
     */
    protected $_defaultConfig = [];

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
     * Returns true if a collection is empty.
     *
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->_loaded);
    }

    /**
     * Returns iterator.
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->_loaded);
    }
}
