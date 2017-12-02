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

use Authentication\Authenticator\FormAuthenticator;
use Authentication\Authenticator\Result;
use Authentication\Identifier\IdentifierCollection;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;

class FormAuthenticatorTest extends TestCase
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
        $identifiers = new IdentifierCollection([
           'Authentication.Password'
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $response = new Response();

        $form = new FormAuthenticator($identifiers);
        $result = $form->authenticate($request, $response);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::SUCCESS, $result->getCode());
    }

    /**
     * testCredentialsNotPresent
     *
     * @return void
     */
    public function testCredentialsNotPresent()
    {
        $identifiers = new IdentifierCollection([
           'Authentication.Password'
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/does-not-match'],
            [],
            []
        );
        $response = new Response();

        $form = new FormAuthenticator($identifiers);

        $result = $form->authenticate($request, $response);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::FAILURE_CREDENTIALS_NOT_FOUND, $result->getCode());
        $this->assertEquals([0 => 'Login credentials not found'], $result->getErrors());
    }

    /**
     * testSingleLoginUrlMismatch
     *
     * @return void
     */
    public function testSingleLoginUrlMismatch()
    {
        $identifiers = new IdentifierCollection([
           'Authentication.Password'
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/does-not-match'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $response = new Response();

        $form = new FormAuthenticator($identifiers, [
            'loginUrl' => '/users/login'
        ]);

        $result = $form->authenticate($request, $response);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::FAILURE_OTHER, $result->getCode());
        $this->assertEquals([0 => 'Login URL `/users/does-not-match` did not match `/users/login`.'], $result->getErrors());
    }

    /**
     * testMultipleLoginUrlMismatch
     *
     * @return void
     */
    public function testMultipleLoginUrlMismatch()
    {
        $identifiers = new IdentifierCollection([
           'Authentication.Password'
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/does-not-match'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $response = new Response();

        $form = new FormAuthenticator($identifiers, [
            'loginUrl' => [
                '/en/users/login',
                '/de/users/login',
            ]
        ]);

        $result = $form->authenticate($request, $response);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::FAILURE_OTHER, $result->getCode());
        $this->assertEquals([0 => 'Login URL `/users/does-not-match` did not match `/en/users/login` or `/de/users/login`.'], $result->getErrors());
    }

    /**
     * testSingleLoginUrlSuccess
     *
     * @return void
     */
    public function testSingleLoginUrlSuccess()
    {
        $identifiers = new IdentifierCollection([
           'Authentication.Password'
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/Users/login'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $response = new Response();

        $form = new FormAuthenticator($identifiers, [
            'loginUrl' => '/Users/login'
        ]);

        $result = $form->authenticate($request, $response);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::SUCCESS, $result->getCode());
        $this->assertEquals([], $result->getErrors());
    }

    /**
     * testMultipleLoginUrlSuccess
     *
     * @return void
     */
    public function testMultipleLoginUrlSuccess()
    {
        $identifiers = new IdentifierCollection([
           'Authentication.Password'
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/de/users/login'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $response = new Response();

        $form = new FormAuthenticator($identifiers, [
            'loginUrl' => [
                '/en/users/login',
                '/de/users/login',
            ]
        ]);

        $result = $form->authenticate($request, $response);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::SUCCESS, $result->getCode());
        $this->assertEquals([], $result->getErrors());
    }

    /**
     * testRegexLoginUrlSuccess
     *
     * @return void
     */
    public function testRegexLoginUrlSuccess()
    {
        $identifiers = new IdentifierCollection([
           'Authentication.Password'
        ]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/de/users/login'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $response = new Response();

        $form = new FormAuthenticator($identifiers, [
            'loginUrl' => '%^/[a-z]{2}/users/login/?$%',
            'useRegex' => true
        ]);

        $result = $form->authenticate($request, $response);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::SUCCESS, $result->getCode());
        $this->assertEquals([], $result->getErrors());
    }

    /**
     * testFullRegexLoginUrlFailure
     *
     * @return void
     */
    public function testFullRegexLoginUrlFailure()
    {
        $identifiers = new IdentifierCollection([
           'Authentication.Password'
        ]);

        $request = ServerRequestFactory::fromGlobals(
            [
                'REQUEST_URI' => '/de/users/login'
            ],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $response = new Response();

        $form = new FormAuthenticator($identifiers, [
            'loginUrl' => '%auth\.localhost/[a-z]{2}/users/login/?$%',
            'useRegex' => true,
            'checkFullUrl' => true
        ]);

        $result = $form->authenticate($request, $response);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::FAILURE_OTHER, $result->getCode());
        $this->assertEquals([0 => 'Login URL `http://localhost/de/users/login` did not match `%auth\.localhost/[a-z]{2}/users/login/?$%`.'], $result->getErrors());
    }

    /**
     * testRegexLoginUrlSuccess
     *
     * @return void
     */
    public function testFullRegexLoginUrlSuccess()
    {
        $identifiers = new IdentifierCollection([
           'Authentication.Password'
        ]);

        $request = ServerRequestFactory::fromGlobals(
            [
                'REQUEST_URI' => '/de/users/login',
                'SERVER_NAME' => 'auth.localhost'
            ],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );
        $response = new Response();

        $form = new FormAuthenticator($identifiers, [
            'loginUrl' => '%auth\.localhost/[a-z]{2}/users/login/?$%',
            'useRegex' => true,
            'checkFullUrl' => true
        ]);

        $result = $form->authenticate($request, $response);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Result::SUCCESS, $result->getCode());
        $this->assertEquals([], $result->getErrors());
    }
}
