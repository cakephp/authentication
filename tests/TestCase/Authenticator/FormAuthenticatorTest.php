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

use Authentication\Authenticator\FormAuthenticator;
use Authentication\Authenticator\Result;
use Authentication\Identifier\IdentifierFactory;
use Authentication\Identifier\IdentifierInterface;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Http\ServerRequestFactory;
use Cake\Routing\Router;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use RuntimeException;

#[AllowMockObjectsWithoutExpectations]
class FormAuthenticatorTest extends TestCase
{
    /**
     * Fixtures
     */
    protected array $fixtures = [
        'core.AuthUsers',
        'core.Users',
    ];

    /**
     * testAuthenticate
     *
     * @return void
     */
    public function testAuthenticate(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password'],
        );

        $form = new FormAuthenticator($identifier);
        $result = $form->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());
    }

    /**
     * testCredentialsNotPresent
     *
     * @return void
     */
    public function testCredentialsNotPresent(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/does-not-match'],
            [],
            [],
        );

        $form = new FormAuthenticator($identifier);

        $result = $form->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_CREDENTIALS_MISSING, $result->getStatus());
        $this->assertEquals([0 => 'Login credentials not found'], $result->getErrors());
    }

    /**
     * testCredentialsEmpty
     *
     * @return void
     */
    public function testCredentialsEmpty(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/does-not-match'],
            [],
            ['username' => '', 'password' => ''],
        );

        $form = new FormAuthenticator($identifier);

        $result = $form->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_CREDENTIALS_MISSING, $result->getStatus());
        $this->assertEquals([0 => 'Login credentials not found'], $result->getErrors());
    }

    public function testIdentityNotFound(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/does-not-match'],
            [],
            ['username' => 'non-existent', 'password' => 'password'],
        );

        $form = new FormAuthenticator($identifier);

        $result = $form->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_IDENTITY_NOT_FOUND, $result->getStatus());
        $this->assertSame([], $result->getErrors());
    }

    /**
     * testSingleLoginUrlMismatch
     *
     * @return void
     */
    public function testSingleLoginUrlMismatch(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/does-not-match'],
            [],
            ['username' => 'mariano', 'password' => 'password'],
        );

        $form = new FormAuthenticator($identifier, [
            'loginUrl' => '/users/login',
        ]);

        $result = $form->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_OTHER, $result->getStatus());
        $this->assertEquals([0 => 'Login URL `/users/does-not-match` did not match `/users/login`.'], $result->getErrors());
    }

    /**
     * testMultipleLoginUrlMismatch
     *
     * @return void
     */
    public function testMultipleLoginUrlMismatch(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/does-not-match'],
            [],
            ['username' => 'mariano', 'password' => 'password'],
        );

        Router::createRouteBuilder('/')
            ->connect('/{lang}/users/login', ['controller' => 'Users', 'action' => 'login']);

        $form = new FormAuthenticator($identifier, [
            'urlChecker' => 'Authentication.Multi',
            'loginUrl' => [
                ['lang' => 'en', 'controller' => 'Users', 'action' => 'login'],
                ['lang' => 'de', 'controller' => 'Users', 'action' => 'login'],
            ],
        ]);

        $result = $form->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_OTHER, $result->getStatus());
        $this->assertEquals([0 => 'Login URL `/users/does-not-match` did not match `/en/users/login` or `/de/users/login`.'], $result->getErrors());
    }

    /**
     * testLoginUrlMismatchWithBase
     *
     * @return void
     */
    public function testLoginUrlMismatchWithBase(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/login'],
            [],
            ['username' => 'mariano', 'password' => 'password'],
        );
        $request = $request->withAttribute('base', '/base');

        $form = new FormAuthenticator($identifier, [
            'loginUrl' => '/users/login',
        ]);

        $result = $form->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_OTHER, $result->getStatus());
        $this->assertEquals([0 => 'Login URL `/base/users/login` did not match `/users/login`.'], $result->getErrors());
    }

    /**
     * testSingleLoginUrlSuccess
     *
     * @return void
     */
    public function testSingleLoginUrlSuccess(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/Users/login'],
            [],
            ['username' => 'mariano', 'password' => 'password'],
        );

        $form = new FormAuthenticator($identifier, [
            'loginUrl' => '/Users/login',
        ]);

        $result = $form->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());
        $this->assertEquals([], $result->getErrors());
    }

    /**
     * testMultipleLoginUrlSuccess
     *
     * @return void
     */
    public function testMultipleLoginUrlSuccess(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/de/users/login'],
            [],
            ['username' => 'mariano', 'password' => 'password'],
        );

        $form = new FormAuthenticator($identifier, [
            'loginUrl' => [
                '/en/users/login',
                '/de/users/login',
            ],
            'urlChecker' => 'Authentication.Multi',
        ]);

        $result = $form->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());
        $this->assertEquals([], $result->getErrors());
    }

    /**
     * testLoginUrlSuccessWithBase
     *
     * @return void
     */
    public function testLoginUrlSuccessWithBase(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/login'],
            [],
            ['username' => 'mariano', 'password' => 'password'],
        );
        $request = $request->withAttribute('base', '/base');

        $form = new FormAuthenticator($identifier, [
            'loginUrl' => '/base/users/login',
        ]);

        $result = $form->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());
        $this->assertEquals([], $result->getErrors());
    }

    /**
     * testRegexLoginUrlSuccess
     *
     * @return void
     */
    public function testRegexLoginUrlSuccess(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/de/users/login'],
            [],
            ['username' => 'mariano', 'password' => 'password'],
        );

        $form = new FormAuthenticator($identifier, [
            'loginUrl' => '%^/[a-z]{2}/users/login/?$%',
            'urlChecker' => [
                'className' => 'Authentication.String',
                'useRegex' => true,
            ],
        ]);

        $result = $form->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());
        $this->assertEquals([], $result->getErrors());
    }

    /**
     * testFullRegexLoginUrlFailure
     *
     * @return void
     */
    public function testFullRegexLoginUrlFailure(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            [
                'REQUEST_URI' => '/de/users/login',
            ],
            [],
            ['username' => 'mariano', 'password' => 'password'],
        );

        $form = new FormAuthenticator($identifier, [
            'loginUrl' => '%auth\.localhost/[a-z]{2}/users/login/?$%',
            'urlChecker' => [
                'className' => 'Authentication.String',
                'useRegex' => true,
                'checkFullUrl' => true,
            ],
        ]);

        $result = $form->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_OTHER, $result->getStatus());
        $this->assertEquals([0 => 'Login URL `http://localhost/de/users/login` did not match `%auth\.localhost/[a-z]{2}/users/login/?$%`.'], $result->getErrors());
    }

    /**
     * testRegexLoginUrlSuccess
     *
     * @return void
     */
    public function testFullRegexLoginUrlSuccess(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            [
                'REQUEST_URI' => '/de/users/login',
                'SERVER_NAME' => 'auth.localhost',
            ],
            [],
            ['username' => 'mariano', 'password' => 'password'],
        );

        $form = new FormAuthenticator($identifier, [
            'loginUrl' => '%auth\.localhost/[a-z]{2}/users/login/?$%',
            'urlChecker' => [
                'className' => 'Authentication.String',
                'useRegex' => true,
                'checkFullUrl' => true,
            ],
        ]);

        $result = $form->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());
        $this->assertEquals([], $result->getErrors());
    }

    /**
     * testFullLoginUrlFailureWithoutCheckFullUrlOption
     *
     * @return void
     */
    public function testFullLoginUrlFailureWithoutCheckFullUrlOption(): void
    {
        $identifier = IdentifierFactory::create('Authentication.Password');

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/login'],
            [],
            ['username' => 'mariano', 'password' => 'password'],
        );

        $form = new FormAuthenticator($identifier, [
            'loginUrl' => 'http://localhost/users/login',
            'urlChecker' => [
                'className' => 'Authentication.String',
            ],
        ]);

        $result = $form->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::FAILURE_OTHER, $result->getStatus());
        $this->assertEquals([0 => 'Login URL `/users/login` did not match `http://localhost/users/login`.'], $result->getErrors());
    }

    /**
     * testAuthenticateCustomFields
     *
     * @return void
     */
    public function testAuthenticateCustomFields(): void
    {
        $identifier = $this->createMock(IdentifierInterface::class);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/login'],
            [],
            ['email' => 'mariano@cakephp.org', 'secret' => 'password'],
        );

        $form = new FormAuthenticator($identifier, [
            'loginUrl' => '/users/login',
            'fields' => [
                'username' => 'email',
                'password' => 'secret',
            ],
        ]);

        $identifier->expects($this->once())
            ->method('identify')
            ->with([
                'username' => 'mariano@cakephp.org',
                'password' => 'password',
            ])
            ->willReturn([
                'username' => 'mariano@cakephp.org',
                'password' => 'password',
            ]);

        $form->authenticate($request);
    }

    /**
     * testAuthenticateValidData
     *
     * @return void
     */
    public function testAuthenticateValidData(): void
    {
        $identifier = $this->createMock(IdentifierInterface::class);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/login'],
            [],
            ['id' => 1, 'username' => 'mariano', 'password' => 'password'],
        );

        $form = new FormAuthenticator($identifier, [
            'loginUrl' => '/users/login',
        ]);

        $identifier->expects($this->once())
            ->method('identify')
            ->with([
                'username' => 'mariano',
                'password' => 'password',
            ])
            ->willReturn([
                'username' => 'mariano',
                'password' => 'password',
            ]);

        $form->authenticate($request);
    }

    /**
     * testAuthenticateValidData
     *
     * @return void
     */
    public function testAuthenticateMissingChecker(): void
    {
        $identifier = $this->createMock(IdentifierInterface::class);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/login'],
            [],
            ['id' => 1, 'username' => 'mariano', 'password' => 'password'],
        );

        $form = new FormAuthenticator($identifier, [
            'loginUrl' => '/users/login',
            'urlChecker' => 'Foo',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('URL checker class `Foo` was not found.');

        $form->authenticate($request);
    }

    /**
     * testAuthenticateValidData
     *
     * @return void
     */
    public function testAuthenticateInvalidChecker(): void
    {
        $identifier = $this->createMock(IdentifierInterface::class);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/users/login'],
            [],
            ['id' => 1, 'username' => 'mariano', 'password' => 'password'],
        );

        $form = new FormAuthenticator($identifier, [
            'loginUrl' => '/users/login',
            'urlChecker' => self::class,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The provided URL checker class `Authentication\Test\TestCase\Authenticator\FormAuthenticatorTest` ' .
            'does not implement the `Authentication\UrlChecker\UrlCheckerInterface` interface.',
        );

        $form->authenticate($request);
    }

    /**
     * Test that FormAuthenticator uses default Password identifier when none is provided.
     *
     * @return void
     */
    public function testDefaultPasswordIdentifier(): void
    {
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['username' => 'mariano', 'password' => 'password'],
        );

        // FormAuthenticator should automatically configure a Password identifier when null is passed
        $form = new FormAuthenticator();
        $result = $form->authenticate($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(Result::SUCCESS, $result->getStatus());

        // Verify the identifier was lazily created
        $identifier = $form->getIdentifier();
        $this->assertInstanceOf(IdentifierInterface::class, $identifier);
    }

    /**
     * Test that FormAuthenticator respects explicitly configured identifier.
     *
     * @return void
     */
    public function testExplicitIdentifierNotOverridden(): void
    {
        // Create an identifier explicitly
        $identifier = IdentifierFactory::create('Authentication.Password', [
            'fields' => [
                'username' => 'email',
                'password' => 'password',
            ],
        ]);

        // FormAuthenticator should use the provided identifier
        $form = new FormAuthenticator($identifier);

        // The identifier should remain as configured
        $this->assertSame($identifier, $form->getIdentifier(), 'Identifier should be the same.');
    }

    /**
     * Test that default identifier inherits fields configuration from authenticator.
     *
     * @return void
     */
    public function testDefaultIdentifierInheritsFieldsConfig(): void
    {
        // Configure authenticator with custom fields mapping
        // Also set a loginUrl that won't match, so authenticate() returns early
        // without actually trying to identify (which would require database access)
        $config = [
            'fields' => [
                'username' => 'user_name',
                'password' => 'pass_word',
            ],
            'loginUrl' => '/login',
        ];

        // FormAuthenticator should create default identifier with inherited fields
        // The default identifier is loaded lazily when authenticate() or getIdentifier() is called
        $form = new FormAuthenticator(null, $config);

        // Trigger the lazy loading by calling authenticate on a non-matching URL
        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/testpath'],
            [],
            ['user_name' => 'mariano', 'pass_word' => 'password'],
        );
        $form->authenticate($request);

        // Verify the identifier was created with the correct configuration
        $identifier = $form->getIdentifier();
        $this->assertInstanceOf(IdentifierInterface::class, $identifier);

        // Verify the fields are properly configured on the identifier
        $this->assertEquals('user_name', $identifier->getConfig('fields.username'));
        $this->assertEquals('pass_word', $identifier->getConfig('fields.password'));
    }
}
