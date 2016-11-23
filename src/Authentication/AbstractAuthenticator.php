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
namespace Auth\Authentication;

use Auth\Authentication\Identifier\IdentifierCollection;
use Auth\PasswordHasherTrait;
use Auth\PasswordHasher\DefaultPasswordHasher;
use Cake\Core\InstanceConfigTrait;
use Cake\ORM\TableRegistry;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

abstract class AbstractAuthenticator implements AuthenticateInterface
{

    use InstanceConfigTrait;
    use PasswordHasherTrait;

    /**
     * Default config for this object.
     * - `fields` The fields to use to identify a user by.
     * - `userModel` The alias for users table, defaults to Users.
     * - `finder` The finder method to use to fetch user record. Defaults to 'all'.
     *   You can set finder name as string or an array where key is finder name and value
     *   is an array passed to `Table::find()` options.
     *   E.g. ['finderName' => ['some_finder_option' => 'some_value']]
     * - `passwordHasher` Password hasher class. Can be a string specifying class name
     *    or an array containing `className` key, any other keys will be passed as
     *    config to the class. Defaults to 'Default'.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'fields' => [
            'username' => 'username',
            'password' => 'password'
        ],
        'userModel' => 'Users',
        'finder' => 'all',
        'passwordHasher' => DefaultPasswordHasher::class
    ];

    /**
     * Identifier collection
     *
     * @var \Auth\Authentication\Identifier\IdentifierCollection
     */
    protected $_identifiers;

    /**
     * Constructor
     *
     * @param array $identifiers Array of config to use.
     */
    public function __construct(IdentifierCollection $identifiers, array $config = [])
    {
        $this->config($config);
        $this->_identifiers = $identifiers;
    }

    /**
     * Gets the identifier collection
     *
     * @return \Auth\Authentication\Identifier\IdentifierCollection
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

    /**
     * Handle unauthenticated access attempt. In implementation valid return values
     * can be:
     *
     * - Null - No action taken, should return appropriate response.
     * - Psr\Http\Message\ResponseInterface - A response object, which will cause to
     *   simply return that response.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request A request object.
     * @param \Psr\Http\Message\ResponseInterface $response A response object.
     * @return void
     */
    public function unauthenticated(ServerRequestInterface $request, ResponseInterface $response)
    {
        return null;
    }
}
