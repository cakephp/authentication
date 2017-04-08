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

use Authentication\Result;
use Cake\ORM\Entity;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;

class ResultTest extends TestCase
{

    /**
     * testConstructor
     *
     * @return void
     */
    public function testConstructor()
    {
        try {
            new Result(null, Result::SUCCESS);
            $this->fail('InvalidArgumentException not thrown!');
        } catch (InvalidArgumentException $e) {
            $result = $e->getMessage();
            $expected = 'Identity can not be empty with status success.';
            $this->assertEquals($expected, $result);
        }

        try {
            new Result([], Result::FAILURE_CREDENTIAL_INVALID);
            $this->fail('InvalidArgumentException not thrown!');
        } catch (InvalidArgumentException $e) {
            $result = $e->getMessage();
            $expected = 'Identity must be `null` or an object implementing \ArrayAccess';
            $this->assertEquals($expected, $result);
        }
    }

    /**
     * testIsValid
     *
     * @return void
     */
    public function testIsValid()
    {
        $result = new Result(null, Result::FAILURE);
        $this->assertFalse($result->isValid());

        $result = new Result(null, Result::FAILURE_CREDENTIAL_INVALID);
        $this->assertFalse($result->isValid());

        $result = new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND);
        $this->assertFalse($result->isValid());

        $entity = new Entity(['user' => 'florian']);
        $result = new Result($entity, Result::SUCCESS);
        $this->assertTrue($result->isValid());
    }

    /**
     * testGetIdentity
     *
     * @return void
     */
    public function testGetIdentity()
    {
        $entity = new Entity(['user' => 'florian']);
        $result = new Result($entity, Result::SUCCESS);
        $this->assertEquals($entity, $result->getIdentity());
    }

    /**
     * testGetCode
     *
     * @return void
     */
    public function testGetCode()
    {
        $result = new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND);
        $this->assertEquals(Result::FAILURE_IDENTITY_NOT_FOUND, $result->getCode());

        $entity = new Entity(['user' => 'florian']);
        $result = new Result($entity, Result::SUCCESS);
        $this->assertEquals(Result::SUCCESS, $result->getCode());
    }

    /**
     * testGetCode
     *
     * @return void
     */
    public function testGetErrors()
    {
        $messages = [
            'Out of coffee!',
            'Out of beer!'
        ];
        $entity = new Entity(['user' => 'florian']);
        $result = new Result($entity, Result::FAILURE, $messages);
        $this->assertEquals($messages, $result->getErrors());
    }
}
