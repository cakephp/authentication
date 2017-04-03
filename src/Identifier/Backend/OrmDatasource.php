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
namespace Authentication\Identifier\Backend;

use Cake\Core\InstanceConfigTrait;
use Cake\ORM\Locator\LocatorAwareTrait;

class OrmDatasource implements DatasourceInterface
{

    use InstanceConfigTrait;
    use LocatorAwareTrait;

    /**
     * Default configuration
     *
     * @var array
     */
    protected $_defaultConfig = [
        'userModel' => 'Users',
        'finder' => 'all',
    ];

    /**
     * Constructor.
     *
     * @param array $config Config array.
     */
    public function __construct(array $config = [])
    {
        $this->setConfig($config);
    }

    /**
     * {@inheritDoc}
     */
    public function find(array $conditions)
    {
        $table = $this->tableLocator()->get($this->_config['userModel']);

        $query = $table->query();
        $finders = (array)$this->_config['finder'];
        foreach ($finders as $finder => $options) {
            if (is_string($options)) {
                $query->find($options);
            } else {
                $query->find($finder, $options);
            }
        }

        return $query->where($conditions)->first();
    }
}
