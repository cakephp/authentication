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

use Cake\Core\InstanceConfigTrait;
use Cake\Core\ObjectRegistry;

/**
 * @template TObject of \Authentication\Identifier\IdentifierInterface|\Authentication\Authenticator\AuthenticatorInterface
 * @extends \Cake\Core\ObjectRegistry<TObject>
 */
abstract class AbstractCollection extends ObjectRegistry
{
    use InstanceConfigTrait;

    /**
     * Config array.
     *
     * @var array
     */
    protected array $_defaultConfig = [];

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
    public function isEmpty(): bool
    {
        return empty($this->_loaded);
    }
}
