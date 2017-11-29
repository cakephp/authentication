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
namespace Authentication\Identifier;

use Authentication\Identifier\Resolver\ResolverAwareTrait;

/**
 * Identity verification identifier
 */
class VerificationIdentifier extends AbstractIdentifier
{

    use ResolverAwareTrait;

    /**
     * Default configuration.
     * - `fields` A list of fields used for verification.
     * - `resolver` Identity resolver to use.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'fields' => [
            'id' => 'id'
        ],
        'resolver' => 'Authentication.Orm'
    ];

    /**
     * {@inheritDoc}
     */
    public function identify(array $data)
    {
        $conditions = [];
        foreach ($this->getConfig('fields') as $key => $field) {
            if (!isset($data[$key])) {
                return null;
            }
            $conditions[$field] = $data[$key];
        }

        return $this->getResolver()->find($conditions);
    }
}
