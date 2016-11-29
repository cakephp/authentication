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
 * @since         4.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Auth\Authentication\Identifier;

use Cake\Core\InstanceConfigTrait;
use Cake\Log\LogTrait;

abstract class AbstractIdentifier implements IdentifierInterface
{

    use InstanceConfigTrait;
    use LogTrait;

    /**
     * Default configuration
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
        $this->config($config);
    }

    /**
     * Returns errors
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->_errors;
    }
}