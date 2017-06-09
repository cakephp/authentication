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
namespace Authentication\Test\TestCase\Authenticator;

use Authentication\Identity;
use Cake\TestSuite\TestCase;

class ResultTest extends TestCase
{
    /**
     * Test getIdentifier()
     *
     * @return void
     */
    public function testGetIdentifier()
    {
        $data = [
            'id' => 1,
            'username' => 'florian'
        ];

        $identity = new Identity($data);

        $result = $identity->getIdentifier();
        $this->assertEquals(1, $result);

        $result = $identity->get('username');
        $this->assertEquals('florian', $result);
    }

    /**
     * Test mapping fields
     *
     * @return void
     */
    public function testFieldMapping()
    {
        $data = [
            'id' => 1,
            'first_name' => 'florian',
            'mail' => 'info@cakephp.org'
        ];

        $identity = new Identity($data, [
            'fieldMap' => [
                'username' => 'first_name',
                'email' => 'mail'
            ]
        ]);

        $result = $identity->get('username');
        $this->assertEquals('florian', $result);

        $result = $identity->get('email');
        $this->assertEquals('info@cakephp.org', $result);
    }
}
