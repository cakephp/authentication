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
namespace Authentication\Test\TestCase;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

class AuthenticationTestCase extends TestCase
{

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'core.auth_users',
        'core.users'
    ];

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        parent::setUp();
        $this->_setupUsersAndPasswords();
    }

    /**
     * _setupUsersAndPasswords
     *
     * @return void
     */
    protected function _setupUsersAndPasswords()
    {
        $password = password_hash('password', PASSWORD_DEFAULT);
        TableRegistry::clear();

        $Users = TableRegistry::get('Users');
        $Users->updateAll(['password' => $password], []);

        $AuthUsers = TableRegistry::get('AuthUsers', [
            'className' => 'TestApp\Model\Table\AuthUsersTable'
        ]);
        $AuthUsers->updateAll(['password' => $password], []);
    }
}
