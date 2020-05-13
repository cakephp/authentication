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
namespace Authentication\Identifier;

use Authentication\Identifier\Resolver\ResolverAwareTrait;

/**
 * Token Identifier
 */
class TokenIdentifier extends AbstractIdentifier
{
    use ResolverAwareTrait;

    /**
     * Default configuration.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'tokenField' => 'token',
        'dataField' => self::CREDENTIAL_TOKEN,
        'resolver' => 'Authentication.Orm',
    ];

    /**
     * @inheritDoc
     */
    public function identify(array $data)
    {
        $dataField = $this->getConfig('dataField');
        if (!isset($data[$dataField])) {
            return null;
        }

        $conditions = [
            $this->getConfig('tokenField') => $data[$dataField],
        ];

        return $this->getResolver()->find($conditions);
    }
}
