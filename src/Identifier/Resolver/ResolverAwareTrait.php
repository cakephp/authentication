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
namespace Authentication\Identifier\Resolver;

use Cake\Core\App;
use InvalidArgumentException;
use RuntimeException;

trait ResolverAwareTrait
{
    /**
     * Resolver instance.
     *
     * @var \Authentication\Identifier\Resolver\ResolverInterface|null
     */
    protected ?ResolverInterface $resolver = null;

    /**
     * Returns ResolverInterface instance.
     *
     * @return \Authentication\Identifier\Resolver\ResolverInterface
     * @throws \RuntimeException When resolver has not been set.
     */
    public function getResolver(): ResolverInterface
    {
        if ($this->resolver === null) {
            $config = $this->getConfig('resolver');
            if ($config !== null) {
                $this->resolver = $this->buildResolver($config);
            } else {
                throw new RuntimeException('Resolver has not been set.');
            }
        }

        return $this->resolver;
    }

    /**
     * Sets ResolverInterface instance.
     *
     * @param \Authentication\Identifier\Resolver\ResolverInterface $resolver Resolver instance.
     * @return $this
     */
    public function setResolver(ResolverInterface $resolver)
    {
        $this->resolver = $resolver;

        return $this;
    }

    /**
     * Builds a ResolverInterface instance.
     *
     * @param array|string $config Resolver class name or config.
     * @return \Authentication\Identifier\Resolver\ResolverInterface
     * @throws \InvalidArgumentException When className option is missing or class name does not exist.
     * @throws \RuntimeException When resolver does not implement ResolverInterface.
     */
    protected function buildResolver(array|string $config): ResolverInterface
    {
        if (is_string($config)) {
            $config = [
                'className' => $config,
            ];
        }
        if (!isset($config['className'])) {
            $message = 'Option `className` is not present.';
            throw new InvalidArgumentException($message);
        }

        $class = App::className($config['className'], 'Identifier/Resolver', 'Resolver');
        if ($class === null) {
            $message = sprintf('Resolver class `%s` does not exist.', $config['className']);
            throw new InvalidArgumentException($message);
        }
        $instance = new $class($config);

        if (!($instance instanceof ResolverInterface)) {
            $message = sprintf('Resolver must implement `%s`.', ResolverInterface::class);
            throw new RuntimeException($message);
        }

        return $instance;
    }
}
