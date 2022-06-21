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

use ArrayAccess;
use Authentication\Authenticator\Result;
use InvalidArgumentException;
use RuntimeException;

/**
 * Callback Identifier
 */
class CallbackIdentifier extends AbstractIdentifier
{
    /**
     * Default configuration
     *
     * @var array
     */
    protected array $_defaultConfig = [
        'callback' => null,
    ];

    /**
     * @inheritDoc
     */
    public function __construct(array $config)
    {
        parent::__construct($config);

        $this->checkCallable();
    }

    /**
     * Check the callable option
     *
     * @throws \InvalidArgumentException
     * @return void
     */
    protected function checkCallable(): void
    {
        $callback = $this->getConfig('callback');

        if (!is_callable($callback)) {
            throw new InvalidArgumentException(sprintf(
                'The `callback` option is not a callable. Got `%s` instead.',
                gettype($callback)
            ));
        }
    }

    /**
     * @inheritDoc
     */
    public function identify(array $credentials): ArrayAccess|array|null
    {
        $callback = $this->getConfig('callback');

        $result = $callback($credentials);
        if ($result instanceof Result) {
            $this->_errors = $result->getErrors();

            return $result->getData();
        }
        if ($result === null || $result instanceof ArrayAccess) {
            return $result;
        }

        throw new RuntimeException(sprintf(
            'Invalid return type of `%s`. Expecting `%s` or `null`.',
            gettype($result),
            ArrayAccess::class
        ));
    }
}
