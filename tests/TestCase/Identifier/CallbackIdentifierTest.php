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
namespace Authentication\Test\TestCase\Identifier;

use ArrayAccess;
use Authentication\Identifier\CallbackIdentifier;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\ORM\Entity;
use stdClass;
use TestApp\Callback\MyCallback;

class CallbackIdentifierTest extends TestCase
{
    /**
     * testIdentify
     *
     * @return void
     */
    public function testIdentify()
    {
        $callback = function ($data) {
            if (isset($data['username']) && $data['username'] === 'florian') {
                return new Entity($data);
            }

            return null;
        };

        $identifier = new CallbackIdentifier([
            'callback' => $callback,
        ]);

        $result = $identifier->identify([]);
        $this->assertNull($result);

        $result = $identifier->identify(['username' => 'larry']);
        $this->assertNull($result);

        $result = $identifier->identify(['username' => 'florian']);
        $this->assertInstanceOf(ArrayAccess::class, $result);
    }

    /**
     * testValidCallable
     *
     * @return void
     */
    public function testValidCallable()
    {
        $identifier = new CallbackIdentifier([
            'callback' => function () {
                return new Entity();
            },
        ]);
        $result = $identifier->identify([]);

        $this->assertInstanceOf(ArrayAccess::class, $result);

        $identifier = new CallbackIdentifier([
            'callback' => [MyCallback::class, 'callme'],
        ]);
        $result = $identifier->identify([]);

        $this->assertInstanceOf(ArrayAccess::class, $result);
    }

    /**
     * testInvalidCallbackType
     */
    public function testInvalidCallbackTypeString()
    {
        $this->expectException('InvalidArgumentException');
        new CallbackIdentifier([
            'callback' => 'no',
        ]);
    }

    /**
     * testInvalidCallbackTypeObject
     */
    public function testInvalidCallbackTypeObject()
    {
        $this->expectException('InvalidArgumentException');
        new CallbackIdentifier([
            'callback' => new stdClass(),
        ]);
    }

    /**
     * testInvalidCallbackTypeObject
     */
    public function testInvalidReturnValue()
    {
        $this->expectException('RuntimeException');
        $identifier = new CallbackIdentifier([
            'callback' => function ($data) {
                return 'no';
            },
        ]);
        $identifier->identify([]);
    }
}
