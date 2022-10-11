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
 * @since 2.10.0
 * @license https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Test\TestCase\Authenticator;

use Authentication\Authenticator\EnvironmentAuthenticator;
use Authentication\Authenticator\Result;
use Authentication\Identifier\IdentifierCollection;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Http\ServerRequestFactory;
use Cake\Http\Uri;
use RuntimeException;

class EnvironmentAuthenticatorTest extends TestCase
{
    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'core.AuthUsers',
        'core.Users',
    ];

    /**
     * Identifiers
     *
     * @var \Authentication\Identifier\IdentifierCollection
     */
    public $identifiers;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->identifiers = new IdentifierCollection([
           'Authentication.Token' => [
               'tokenField' => 'username',
               'dataField' => 'USER_ID',
           ],
        ]);
    }

    /**
     * testAuthenticate
     *
     * @return void
     */
    public function testAuthenticate()
    {
        $identifiers = new IdentifierCollection([
            'Authentication.Callback' => [
                'callback' => function ($data) {
                    if (isset($data['USER_ID']) && isset($data['ATTRIBUTE'])) {
                        return new Result($data, RESULT::SUCCESS);
                    }

                       return null;
                },
            ],
        ]);
        $envAuth = new EnvironmentAuthenticator($identifiers, [
            'loginUrl' => '/secure',
            'fields' => [
                'USER_ID',
                'ATTRIBUTE',
            ],
        ]);
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/secure',
            'USER_ID' => 'mariano',
            'ATTRIBUTE' => 'anything',
        ]);

        $result = $envAuth->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus(), '2 required fields used for authentication');
    }

    /**
     * testFailedAuthentication
     *
     * @return void
     */
    public function testFailedAuthentication()
    {
        $envAuth = new EnvironmentAuthenticator($this->identifiers, [
            'loginUrl' => '/secure',
            'fields' => [
                'USER_ID',
                'SOME_ATTRIBUTE',
            ],
        ]);
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/secure',
            'USER_ID' => 'Guy',
            'SOME_ATTRIBUTE' => 'dondecourse',
        ]);

        $result = $envAuth->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_IDENTITY_NOT_FOUND, $result->getStatus());
    }

    /**
     * testWithoutFieldConfig
     *
     * @return void
     */
    public function testWithoutFieldConfig()
    {
        $envAuth = new EnvironmentAuthenticator($this->identifiers);

        $result = $envAuth->authenticate(ServerRequestFactory::fromGlobals());
        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_CREDENTIALS_MISSING, $result->getStatus());
    }

    /**
     * testWithIncorrectFieldConfig
     *
     * @return void
     */
    public function testWithIncorrectFieldConfig()
    {
        $envAuth = new EnvironmentAuthenticator($this->identifiers, [
            'loginUrl' => '/secure',
            'fields' => [
                'INCORRECT_USER_ID',
                'SOME_ATTRIBUTE',
            ],
        ]);
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/secure',
            'USER_ID' => 'mariano',
            'SOME_ATTRIBUTE' => 'anything',
        ]);

        $result = $envAuth->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_CREDENTIALS_MISSING, $result->getStatus());
    }

    /**
     * testCredentialsEmpty
     *
     * @return void
     */
    public function testCredentialsEmpty()
    {
        $envAuth = new EnvironmentAuthenticator($this->identifiers, [
            'loginUrl' => '/secure',
            'fields' => [
                'USER_ID',
                'SOME_ATTRIBUTE',
            ],
        ]);
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/secure',
            'USER_ID' => '',
            'SOME_ATTRIBUTE' => 'anything',
        ]);

        $result = $envAuth->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_CREDENTIALS_MISSING, $result->getStatus());
    }

    /**
     * testOptionalFields
     *
     * @return void
     */
    public function testOptionalFields()
    {
        $identifiers = new IdentifierCollection([
           'Authentication.Callback' => [
               'callback' => function ($data) {
                if (isset($data['USER_ID']) && isset($data['OPTIONAL_FIELD'])) {
                    return new Result($data, RESULT::SUCCESS);
                }

                       return null;
               },
           ],
        ]);
        $envAuth = new EnvironmentAuthenticator($identifiers, [
            'loginUrl' => '/secure',
            'fields' => [
                'USER_ID',
                'SOME_ATTRIBUTE',
            ],
            'optional_fields' => [
                'OPTIONAL_FIELD',
            ],
        ]);
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/secure',
            'USER_ID' => 'mariano',
            'SOME_ATTRIBUTE' => 'anything',
            'OPTIONAL_FIELD' => 'gotcha',
        ]);

        $result = $envAuth->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus(), 'Optional field used for authentication');
    }

    /**
     * testSingleLoginUrlMismatch
     *
     * @return void
     */
    public function testSingleLoginUrlMismatch()
    {
        $envAuth = new EnvironmentAuthenticator($this->identifiers, [
            'loginUrl' => '/secure',
            'fields' => [
                'USER_ID',
                'ATTRIBUTE',
            ],
        ]);
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/de/secure',
            'USER_ID' => 'mariano',
            'ATTRIBUTE' => 'anything',
        ]);

        $result = $envAuth->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_OTHER, $result->getStatus());
        $this->assertEquals([0 => 'Login URL `/de/secure` did not match `/secure`.'], $result->getErrors());
    }

    /**
     * testMultipleLoginUrlMismatch
     *
     * @return void
     */
    public function testMultipleLoginUrlMismatch()
    {
        $envAuth = new EnvironmentAuthenticator($this->identifiers, [
            'loginUrl' => [
                '/en/secure',
                '/de/secure',
            ],
            'fields' => [
                'USER_ID',
                'ATTRIBUTE',
            ],
        ]);
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/fr/secure',
            'USER_ID' => 'mariano',
            'ATTRIBUTE' => 'anything',
        ]);

        $result = $envAuth->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_OTHER, $result->getStatus());
        $this->assertEquals([0 => 'Login URL `/fr/secure` did not match `/en/secure` or `/de/secure`.'], $result->getErrors());
    }

    /**
     * testSingleLoginUrlSuccess
     *
     * @return void
     */
    public function testSingleLoginUrlSuccess()
    {
        $envAuth = new EnvironmentAuthenticator($this->identifiers, [
            'loginUrl' => '/en/secure',
            'fields' => [
                'USER_ID',
            ],
        ]);
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/en/secure',
            'USER_ID' => 'mariano',
            'ATTRIBUTE' => 'anything',
        ]);

        $result = $envAuth->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());
        $this->assertEquals([], $result->getErrors());
    }

    /**
     * testMultipleLoginUrlSuccess
     *
     * @return void
     */
    public function testMultipleLoginUrlSuccess()
    {
        $envAuth = new EnvironmentAuthenticator($this->identifiers, [
            'loginUrl' => [
                '/en/secure',
                '/de/secure',
            ],
            'fields' => [
                'USER_ID',
                'ATTRIBUTE',
            ],
        ]);
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/de/secure',
            'USER_ID' => 'mariano',
            'ATTRIBUTE' => 'anything',
        ]);

        $result = $envAuth->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());
        $this->assertEquals([], $result->getErrors());
    }

    /**
     * testLoginUrlSuccessWithBase
     *
     * @return void
     */
    public function testLoginUrlSuccessWithBase()
    {
        $envAuth = new EnvironmentAuthenticator($this->identifiers, [
            'loginUrl' => '/base/fr/secure',
            'fields' => [
                'USER_ID',
                'ATTRIBUTE',
            ],
        ]);
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/fr/secure',
            'USER_ID' => 'mariano',
            'ATTRIBUTE' => 'anything',
        ]);
        $uri = new Uri($request->getUri(), '/base', '/');
        $request = $request->withUri($uri);
        $request = $request->withAttribute('base', $uri->getBase());

        $result = $envAuth->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());
        $this->assertEquals([], $result->getErrors());
    }

    /**
     * testRegexLoginUrlSuccess
     *
     * @return void
     */
    public function testRegexLoginUrlSuccess()
    {
        $envAuth = new EnvironmentAuthenticator($this->identifiers, [
            'loginUrl' => '%^/[a-z]{2}/users/secure/?$%',
            'urlChecker' => [
                'useRegex' => true,
            ],
            'fields' => [
                'USER_ID',
                'ATTRIBUTE',
            ],
        ]);
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/fr/users/secure',
            'USER_ID' => 'mariano',
            'ATTRIBUTE' => 'anything',
        ]);

        $result = $envAuth->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());
        $this->assertEquals([], $result->getErrors());
    }

    /**
     * testFullRegexLoginUrlFailure
     *
     * @return void
     */
    public function testFullRegexLoginUrlFailure()
    {
        $envAuth = new EnvironmentAuthenticator($this->identifiers, [
            'loginUrl' => '%auth\.localhost/[a-z]{2}/users/secure/?$%',
            'urlChecker' => [
                'useRegex' => true,
                'checkFullUrl' => true,
            ],
            'fields' => [
                'USER_ID',
                'ATTRIBUTE',
            ],
        ]);
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/fr/users/secure',
            'USER_ID' => 'mariano',
            'ATTRIBUTE' => 'anything',
        ]);

        $result = $envAuth->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_OTHER, $result->getStatus());
        $this->assertEquals([0 => 'Login URL `http://localhost/fr/users/secure` did not match `%auth\.localhost/[a-z]{2}/users/secure/?$%`.'], $result->getErrors());
    }

    /**
     * testRegexLoginUrlSuccess
     *
     * @return void
     */
    public function testFullRegexLoginUrlSuccess()
    {
        $envAuth = new EnvironmentAuthenticator($this->identifiers, [
            'loginUrl' => '%auth\.localhost/[a-z]{2}/users/secure/?$%',
            'urlChecker' => [
                'useRegex' => true,
                'checkFullUrl' => true,
            ],
            'fields' => [
                'USER_ID',
                'ATTRIBUTE',
            ],
        ]);
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/fr/users/secure',
            'SERVER_NAME' => 'auth.localhost',
            'USER_ID' => 'mariano',
            'ATTRIBUTE' => 'anything',
        ]);

        $result = $envAuth->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());
        $this->assertEquals([], $result->getErrors());
    }

    /**
     * testFullLoginUrlFailureWithoutCheckFullUrlOption
     *
     * @return void
     */
    public function testFullLoginUrlFailureWithoutCheckFullUrlOption()
    {
        $envAuth = new EnvironmentAuthenticator($this->identifiers, [
            'loginUrl' => 'http://localhost/secure',
            'fields' => [
                'USER_ID',
                'ATTRIBUTE',
            ],
        ]);
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/secure',
            'USER_ID' => 'mariano',
            'ATTRIBUTE' => 'anything',
        ]);

        $result = $envAuth->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_OTHER, $result->getStatus());
        $this->assertEquals([0 => 'Login URL `/secure` did not match `http://localhost/secure`.'], $result->getErrors());
    }

    /**
     * testAuthenticateValidData
     *
     * @return void
     */
    public function testAuthenticateMissingChecker()
    {
        $this->createMock(IdentifierCollection::class);
        $envAuth = new EnvironmentAuthenticator($this->identifiers, [
            'loginUrl' => '/secure',
            'fields' => [
                'USER_ID',
                'SOME_ATTRIBUTE',
            ],
            'urlChecker' => 'Foo',
        ]);
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/secure',
            'USER_ID' => 'mariano',
            'SOME_ATTRIBUTE' => 'anything',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('URL checker class `Foo` was not found.');

        $envAuth->authenticate($request);
    }

    /**
     * testAuthenticateInvalidChecker
     *
     * @return void
     */
    public function testAuthenticateInvalidChecker()
    {
        $this->createMock(IdentifierCollection::class);
        $envAuth = new EnvironmentAuthenticator($this->identifiers, [
            'loginUrl' => '/secure',
            'fields' => [
                'USER_ID',
                'SOME_ATTRIBUTE',
            ],
            'urlChecker' => self::class,
        ]);
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/secure',
            'USER_ID' => 'mariano',
            'SOME_ATTRIBUTE' => 'anything',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The provided URL checker class `Authentication\Test\TestCase\Authenticator\EnvironmentAuthenticatorTest` ' .
            'does not implement the `Authentication\UrlChecker\UrlCheckerInterface` interface.'
        );

        $envAuth->authenticate($request);
    }
}
