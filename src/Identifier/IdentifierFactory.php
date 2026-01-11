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
 * @since         4.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Identifier;

use Cake\Core\App;
use RuntimeException;

/**
 * Factory class for creating identifier instances from configuration
 */
class IdentifierFactory
{
    /**
     * Create an identifier from configuration.
     *
     * @param \Authentication\Identifier\IdentifierInterface|array<string, mixed>|string $config Identifier configuration.
     *   Can be a class name string, an instance, or an array with 'className' key.
     * @param array<string, mixed> $defaultConfig Default configuration to merge.
     * @return \Authentication\Identifier\IdentifierInterface
     * @throws \RuntimeException When the identifier class cannot be found or created.
     */
    public static function create(
        string|array|IdentifierInterface $config,
        array $defaultConfig = [],
    ): IdentifierInterface {
        if ($config instanceof IdentifierInterface) {
            return $config;
        }

        if (is_string($config)) {
            $className = $config;
            $config = [];
        } else {
            $className = $config['className'] ?? '';
            unset($config['className']);
        }

        if (empty($className)) {
            throw new RuntimeException('Identifier configuration must specify a class name.');
        }

        $config += $defaultConfig;

        /** @var class-string<\Authentication\Identifier\IdentifierInterface>|null $class */
        $class = App::className($className, 'Identifier', 'Identifier');
        if ($class === null) {
            throw new RuntimeException(sprintf('Identifier class `%s` was not found.', $className));
        }

        return new $class($config);
    }
}
