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
namespace Authentication\Authenticator;

use Authentication\Identifier\IdentifierInterface;
use Authentication\Identifier\PasswordIdentifier;
use Cake\Core\InstanceConfigTrait;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

abstract class AbstractAuthenticator implements AuthenticatorInterface
{
    use InstanceConfigTrait;

    /**
     * Default config for this object.
     * - `fields` The fields to use to identify a user by.
     *
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'fields' => [
            PasswordIdentifier::CREDENTIAL_USERNAME => 'username',
            PasswordIdentifier::CREDENTIAL_PASSWORD => 'password',
        ],
    ];

    /**
     * Identifier instance.
     *
     * @var \Authentication\Identifier\IdentifierInterface|null
     */
    protected ?IdentifierInterface $_identifier = null;

    /**
     * Constructor
     *
     * @param \Authentication\Identifier\IdentifierInterface|null $identifier Identifier instance.
     * @param array<string, mixed> $config Configuration settings.
     */
    public function __construct(?IdentifierInterface $identifier = null, array $config = [])
    {
        $this->_identifier = $identifier;
        $this->setConfig($config);
    }

    /**
     * Gets the identifier.
     *
     * Subclasses can override this method to provide a default identifier
     * when none was configured, enabling lazy initialization.
     *
     * @return \Authentication\Identifier\IdentifierInterface
     * @throws \RuntimeException When identifier is null.
     */
    public function getIdentifier(): IdentifierInterface
    {
        if ($this->_identifier === null) {
            throw new RuntimeException(
                sprintf(
                    'Identifier is required for `%s`. Please provide an identifier instance.',
                    static::class,
                ),
            );
        }

        return $this->_identifier;
    }

    /**
     * Sets the identifier.
     *
     * @param \Authentication\Identifier\IdentifierInterface $identifier IdentifierInterface instance.
     * @return $this
     */
    public function setIdentifier(IdentifierInterface $identifier)
    {
        $this->_identifier = $identifier;

        return $this;
    }

    /**
     * Authenticate a user based on the request information.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request to get authentication information from.
     * @return \Authentication\Authenticator\ResultInterface Returns a result object.
     */
    abstract public function authenticate(ServerRequestInterface $request): ResultInterface;
}
