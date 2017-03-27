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
namespace Authentication\Authenticator;

use Authentication\Identifier\IdentifierCollection;
use Cake\Core\InstanceConfigTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

abstract class AbstractAuthenticator implements AuthenticatorInterface
{

    use InstanceConfigTrait;

    /**
     * Default config for this object.
     * - `fields` The fields to use to identify a user by.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'fields' => [
            'username' => 'username',
            'password' => 'password'
        ]
    ];

    /**
     * Identifier collection
     *
     * @var \Authentication\Identifier\IdentifierCollection
     */
    protected $_identifiers;

    /**
     * Constructor
     *
     * @param \Authentication\Identifier\IdentifierCollection $identifiers Identifiers collection.
     * @param array $config Configuration settings.
     */
    public function __construct(IdentifierCollection $identifiers, array $config = [])
    {
        $this->setConfig($config);
        $this->_identifiers = $identifiers;
    }

    /**
     * Gets the identifier collection
     *
     * @return \Authentication\Identifier\IdentifierCollection
     */
    public function identifiers()
    {
        return $this->_identifiers;
    }

    /**
     * Authenticate a user based on the request information.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request to get authentication information from.
     * @param \Psr\Http\Message\ResponseInterface $response A response object that can have headers added.
     * @return mixed Either false on failure, or an array of user data on success.
     */
    abstract public function authenticate(ServerRequestInterface $request, ResponseInterface $response);
}
