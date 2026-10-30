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
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         4.3.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Test\TestCase;

use Authentication\Authenticator\CookieAuthenticator;
use Authentication\Authenticator\HttpDigestAuthenticator;
use Authentication\Authenticator\JwtAuthenticator;
use Authentication\Authenticator\TokenAuthenticator;
use Authentication\Identifier\CallbackIdentifier;
use Authentication\Identifier\IdentifierInterface;
use Authentication\Identifier\Ldap\AdapterInterface;
use Authentication\Identifier\Ldap\ExtensionAdapter;
use Authentication\Identifier\LdapIdentifier;
use Authentication\Identifier\PasswordIdentifier;
use Authentication\Identifier\TokenIdentifier;
use Authentication\PasswordHasher\AbstractPasswordHasher;
use Authentication\PasswordHasher\DefaultPasswordHasher;
use Authentication\PasswordHasher\FallbackPasswordHasher;
use Authentication\PasswordHasher\LegacyPasswordHasher;
use Authentication\PasswordHasher\PasswordHasherInterface;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use ReflectionParameter;
use SensitiveParameter;

/**
 * Ensures credential carrying parameters stay marked with the
 * `SensitiveParameter` attribute so stack traces redact their values.
 */
class SensitiveParameterTest extends TestCase
{
    /**
     * Parameters that must carry the SensitiveParameter attribute.
     *
     * @return array<string, array{class-string, string, array<string>}>
     */
    public static function sensitiveParametersProvider(): array
    {
        return [
            'PasswordHasherInterface::hash' => [
                PasswordHasherInterface::class,
                'hash',
                ['password'],
            ],
            'PasswordHasherInterface::check' => [
                PasswordHasherInterface::class,
                'check',
                ['password', 'hashedPassword'],
            ],
            'PasswordHasherInterface::needsRehash' => [
                PasswordHasherInterface::class,
                'needsRehash',
                ['password'],
            ],
            'AbstractPasswordHasher::needsRehash' => [
                AbstractPasswordHasher::class,
                'needsRehash',
                ['password'],
            ],
            'DefaultPasswordHasher::hash' => [
                DefaultPasswordHasher::class,
                'hash',
                ['password'],
            ],
            'DefaultPasswordHasher::check' => [
                DefaultPasswordHasher::class,
                'check',
                ['password', 'hashedPassword'],
            ],
            'DefaultPasswordHasher::needsRehash' => [
                DefaultPasswordHasher::class,
                'needsRehash',
                ['password'],
            ],
            'LegacyPasswordHasher::hash' => [
                LegacyPasswordHasher::class,
                'hash',
                ['password'],
            ],
            'LegacyPasswordHasher::check' => [
                LegacyPasswordHasher::class,
                'check',
                ['password', 'hashedPassword'],
            ],
            'FallbackPasswordHasher::hash' => [
                FallbackPasswordHasher::class,
                'hash',
                ['password'],
            ],
            'FallbackPasswordHasher::check' => [
                FallbackPasswordHasher::class,
                'check',
                ['password', 'hashedPassword'],
            ],
            'FallbackPasswordHasher::needsRehash' => [
                FallbackPasswordHasher::class,
                'needsRehash',
                ['password'],
            ],
            'IdentifierInterface::identify' => [
                IdentifierInterface::class,
                'identify',
                ['credentials'],
            ],
            'PasswordIdentifier::identify' => [
                PasswordIdentifier::class,
                'identify',
                ['credentials'],
            ],
            'PasswordIdentifier::_checkPassword' => [
                PasswordIdentifier::class,
                'checkPassword',
                ['identity', 'password'],
            ],
            'TokenIdentifier::identify' => [
                TokenIdentifier::class,
                'identify',
                ['credentials'],
            ],
            'LdapIdentifier::identify' => [
                LdapIdentifier::class,
                'identify',
                ['credentials'],
            ],
            'LdapIdentifier::_bindUser' => [
                LdapIdentifier::class,
                'bindUser',
                ['password'],
            ],
            'CallbackIdentifier::identify' => [
                CallbackIdentifier::class,
                'identify',
                ['credentials'],
            ],
            'Ldap AdapterInterface::bind' => [
                AdapterInterface::class,
                'bind',
                ['password'],
            ],
            'ExtensionAdapter::bind' => [
                ExtensionAdapter::class,
                'bind',
                ['password'],
            ],
            'HttpDigestAuthenticator::parseAuthData' => [
                HttpDigestAuthenticator::class,
                'parseAuthData',
                ['digest'],
            ],
            'HttpDigestAuthenticator::generateResponseHash' => [
                HttpDigestAuthenticator::class,
                'generateResponseHash',
                ['digest', 'password'],
            ],
            'HttpDigestAuthenticator::password' => [
                HttpDigestAuthenticator::class,
                'password',
                ['password'],
            ],
            'TokenAuthenticator::stripTokenPrefix' => [
                TokenAuthenticator::class,
                'stripTokenPrefix',
                ['token'],
            ],
            'JwtAuthenticator::decodeToken' => [
                JwtAuthenticator::class,
                'decodeToken',
                ['token'],
            ],
            'CookieAuthenticator::_authenticateToken' => [
                CookieAuthenticator::class,
                'authenticateToken',
                ['token'],
            ],
            'CookieAuthenticator::_authenticateLegacyToken' => [
                CookieAuthenticator::class,
                'authenticateLegacyToken',
                ['token'],
            ],
            'CookieAuthenticator::_createLegacyPlainToken' => [
                CookieAuthenticator::class,
                'createLegacyPlainToken',
                ['identity'],
            ],
            'CookieAuthenticator::_createToken' => [
                CookieAuthenticator::class,
                'createToken',
                ['identity'],
            ],
        ];
    }

    /**
     * Parameters that must stay unmarked as they are not secret.
     *
     * @return array<string, array{class-string, string, array<string>}>
     */
    public static function plainParametersProvider(): array
    {
        return [
            'LdapIdentifier::_bindUser username' => [
                LdapIdentifier::class,
                'bindUser',
                ['username'],
            ],
            'HttpDigestAuthenticator::password username' => [
                HttpDigestAuthenticator::class,
                'password',
                ['username'],
            ],
            'HttpDigestAuthenticator::password realm' => [
                HttpDigestAuthenticator::class,
                'password',
                ['realm'],
            ],
            'HttpDigestAuthenticator::generateResponseHash method' => [
                HttpDigestAuthenticator::class,
                'generateResponseHash',
                ['method'],
            ],
            'TokenAuthenticator::stripTokenPrefix prefix' => [
                TokenAuthenticator::class,
                'stripTokenPrefix',
                ['prefix'],
            ],
        ];
    }

    /**
     * @param class-string $class Class name
     * @param string $method Method name
     * @param array<string> $parameters Parameter names expected to be sensitive
     * @return void
     */
    #[DataProvider('sensitiveParametersProvider')]
    public function testParameterIsMarkedSensitive(string $class, string $method, array $parameters): void
    {
        $reflection = new ReflectionMethod($class, $method);
        foreach ($parameters as $name) {
            $parameter = $this->parameterNamed($reflection, $name);
            $this->assertNotNull($parameter, sprintf('Parameter `%s` not found on `%s::%s()`.', $name, $class, $method));
            $this->assertNotEmpty(
                $parameter->getAttributes(SensitiveParameter::class),
                sprintf('Parameter `$%s` of `%s::%s()` must be marked with #[SensitiveParameter].', $name, $class, $method),
            );
        }
    }

    /**
     * @param class-string $class Class name
     * @param string $method Method name
     * @param array<string> $parameters Parameter names expected to stay unmarked
     * @return void
     */
    #[DataProvider('plainParametersProvider')]
    public function testPlainParameterIsNotMarkedSensitive(string $class, string $method, array $parameters): void
    {
        $reflection = new ReflectionMethod($class, $method);
        foreach ($parameters as $name) {
            $parameter = $this->parameterNamed($reflection, $name);
            $this->assertNotNull($parameter, sprintf('Parameter `%s` not found on `%s::%s()`.', $name, $class, $method));
            $this->assertEmpty(
                $parameter->getAttributes(SensitiveParameter::class),
                sprintf('Parameter `$%s` of `%s::%s()` is not secret and must not be marked sensitive.', $name, $class, $method),
            );
        }
    }

    /**
     * Finds a parameter by name.
     *
     * @param \ReflectionMethod $method Method to inspect
     * @param string $name Parameter name
     * @return \ReflectionParameter|null The parameter or null when not found
     */
    protected function parameterNamed(ReflectionMethod $method, string $name): ?ReflectionParameter
    {
        foreach ($method->getParameters() as $parameter) {
            if ($parameter->getName() === $name) {
                return $parameter;
            }
        }

        return null;
    }
}
