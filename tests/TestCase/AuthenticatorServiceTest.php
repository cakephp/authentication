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
namespace Authentication\Test\TestCase\Authenticator;

use Authentication\AuthenticationService;
use Authentication\Authenticator\FormAuthenticator;
use Authentication\Identifier\IdentifierCollection;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Http\ServerRequestFactory;
use Zend\Diactoros\Response;

class AuthenticatorServiceTest extends TestCase
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
     * testAuthenticate
     *
     * @return void
     */
    public function testAuthenticate()
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $response = new Response();

        $service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Orm'
            ],
            'authenticators' => [
                'Authentication.Form'
            ]
        ]);

        $result = $service->authenticate($request, $response);
        $this->assertTrue($result->isValid());

        $result = $service->getAuthenticationProvider();
        $this->assertInstanceOf(FormAuthenticator::class, $result);
    }

    /**
     * testLoadAuthenticatorException
     *
     * @expectedException \Cake\Core\Exception\Exception
     */
    public function testLoadAuthenticatorException()
    {
        $service = new AuthenticationService();
        $service->loadAuthenticator('does-not-exist');
    }

    /**
     * testIdentifiers
     *
     * @return void
     */
    public function testIdentifiers()
    {
        $service = new AuthenticationService();
        $result = $service->identifiers();
        $this->assertInstanceOf(IdentifierCollection::class, $result);
    }
}
