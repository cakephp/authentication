<?php
namespace Auth\Test\TestCase\Middleware\Authentication;

use Auth\Authentication\Result;
use Cake\TestSuite\TestCase;

class ResultTest extends TestCase
{

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

        $result = new Result(['user' => 'florian'], Result::SUCCESS);
        $this->assertTrue($result->isValid());
    }

    /**
     * testGetIdentity
     *
     * @return void
     */
    public function testGetIdentity()
    {
        $identity = ['user' => 'florian'];
        $result = new Result($identity, Result::SUCCESS);
        $this->assertEquals($identity, $result->getIdentity());
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

        $result = new Result(['user' => 'florian'], Result::SUCCESS);
        $this->assertEquals(Result::SUCCESS, $result->getCode());
    }

    /**
     * testGetCode
     *
     * @return void
     */
    public function testGetMessages()
    {
        $messages = [
            'Out of coffee!',
            'Out of beer!'
        ];
        $result = new Result(['user' => 'florian'], Result::FAILURE, $messages);
        $this->assertEquals($messages, $result->getMessages());
    }
}
