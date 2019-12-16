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
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link https://cakephp.org CakePHP(tm) Project
 * @since 1.0.0
 * @license https://opensource.org/licenses/mit-license.php MIT License
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
    protected $fixtures = [
        'core.AuthUsers',
        'core.Users',
    ];

    /**
     * @inheritdoc
     */
    public function setUp(): void
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
            'className' => 'TestApp\Model\Table\AuthUsersTable',
        ]);
        $AuthUsers->updateAll(['password' => $password], []);
    }
}
