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
namespace Authentication\Test\TestCase\Authenticator;

use Authentication\Authenticator\Result;
use Cake\ORM\Entity;
use Cake\TestSuite\TestCase;
use stdClass;

class ResultTest extends TestCase
{
    /**
     * testConstructorEmptyData
     *
     * @return void
     */
    public function testConstructorEmptyData()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Identity data can not be empty with status success.');
        new Result(null, Result::SUCCESS);
    }

    /**
     * testConstructorInvalidData
     *
     * @return void
     */
    public function testConstructorInvalidData()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Identity data must be `null`, an `array` or implement `ArrayAccess` interface, `stdClass` given.');
        new Result(new stdClass(), Result::FAILURE_CREDENTIALS_INVALID);
    }

    /**
     * testIsValid
     *
     * @return void
     */
    public function testIsValid()
    {
        $result = new Result(null, Result::FAILURE_CREDENTIALS_INVALID);
        $this->assertFalse($result->isValid());

        $result = new Result(null, Result::FAILURE_CREDENTIALS_MISSING);
        $this->assertFalse($result->isValid());

        $result = new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND);
        $this->assertFalse($result->isValid());

        $result = new Result(null, Result::FAILURE_OTHER);
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
        $this->assertEquals($entity, $result->getData());
    }

    /**
     * testGetIdentityArray
     *
     * @return void
     */
    public function testGetIdentityArray()
    {
        $data = ['user' => 'florian'];
        $result = new Result($data, Result::SUCCESS);
        $this->assertEquals($data, $result->getData());
    }

    /**
     * testGetCode
     *
     * @return void
     */
    public function testGetCode()
    {
        $result = new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND);
        $this->assertEquals(Result::FAILURE_IDENTITY_NOT_FOUND, $result->getStatus());

        $entity = new Entity(['user' => 'florian']);
        $result = new Result($entity, Result::SUCCESS);
        $this->assertEquals(Result::SUCCESS, $result->getStatus());
    }

    /**
     * testGetErrors
     *
     * @return void
     */
    public function testGetErrors()
    {
        $messages = [
            'Out of coffee!',
            'Out of beer!',
        ];
        $entity = new Entity(['user' => 'florian']);
        $result = new Result($entity, Result::FAILURE_OTHER, $messages);
        $this->assertEquals($messages, $result->getErrors());
    }
}
