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

use Cake\Core\App;
use InvalidArgumentException;
use RuntimeException;

trait DatasourceAwareTrait
{

    /**
     * Datasource instance.
     *
     * @var \Authentication\Identifier\Backend\DatasourceInterface
     */
    protected $datasource;

    /**
     * Returns DatasourceInterface instance.
     *
     * @return \Authentication\Identifier\Backend\DatasourceInterface
     * @throws \RuntimeException When datasource has not been set.
     */
    public function getDatasource()
    {
        if ($this->datasource === null) {
            $config = $this->getConfig('datasource');
            if ($config !== null) {
                $this->datasource = $this->buildDatasource($config);
            } else {
                throw new RuntimeException('Datasource has not been set.');
            }
        }

        return $this->datasource;
    }

    /**
     * Sets DatasourceInterface instance.
     *
     * @param \Authentication\Identifier\Backend\DatasourceInterface $datasource Datasource instance.
     * @return $this
     */
    public function setDatasource(DatasourceInterface $datasource)
    {
        $this->datasource = $datasource;

        return $this;
    }

    /**
     * Builds a DatasourceInterface instance.
     *
     * @param string|array $config Datasource class name or config.
     * @return \Authentication\Identifier\Backend\DatasourceInterface
     * @throws \InvalidArgumentException When className option is missing or class name does not exist.
     * @throws \RuntimeException When datasource does not implement DatasourceInterface.
     */
    protected function buildDatasource($config)
    {
        if (is_string($config)) {
            $config = [
                'className' => $config
            ];
        }

        if (!isset($config['className'])) {
            $message = 'Option `className` is not present.';
            throw new InvalidArgumentException($message);
        }

        $class = App::className($config['className'], 'Identifier/Backend', 'Datasource');
        if ($class === false) {
            $message = sprintf('Datasource class %s does not exist.', $config['className']);
            throw new InvalidArgumentException($message);
        }
        $instance = new $class($config);

        if (!$instance instanceof DatasourceInterface) {
            $message = sprintf('Datasource must implement %s.', DatasourceInterface::class);
            throw new RuntimeException($message);
        }

        return $instance;
    }
}
