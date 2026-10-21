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

use Authentication\AuthenticationService;
use Authentication\Authenticator\CookieAuthenticator;
use Authentication\Authenticator\HttpDigestAuthenticator;
use Authentication\Authenticator\JwtAuthenticator;
use Cake\Error\Debugger;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Ensures secrets are redacted from debug output (`var_dump()` and
 * CakePHP's `Debugger::exportVar()`) via `__debugInfo()`, while keeping
 * the last 4 bytes visible so configured values can be verified.
 */
class DebugRedactionTest extends TestCase
{
    /**
     * Renders an object with both debug output mechanisms used in practice.
     *
     * @param object $object Object to render
     * @return string Combined debug output
     */
    protected function debugOutput(object $object): string
    {
        ob_start();
        var_dump($object);
        $varDump = (string)ob_get_clean();

        return $varDump . Debugger::exportVar($object);
    }

    /**
     * Secrets and the redacted value expected for them.
     *
     * Secrets longer than 8 bytes keep their last 4 bytes visible, shorter
     * ones are masked entirely.
     *
     * @return array<string, array{string, string}>
     */
    public static function secretProvider(): array
    {
        return [
            'long secret keeps last 4 bytes' => ['planted-hmac-salt-2f9c', '*****2f9c'],
            'secret of 9 bytes keeps last 4 bytes' => ['123456789', '*****6789'],
            'secret of 8 bytes is fully masked' => ['12345678', '*****'],
            'secret of 4 bytes is fully masked' => ['1234', '*****'],
            'empty secret' => ['', '*****'],
        ];
    }

    /**
     * @param string $secret Configured secret
     * @param string $expected Expected redacted value
     * @return void
     */
    #[DataProvider('secretProvider')]
    public function testRedactedSecretFormat(string $secret, string $expected): void
    {
        $authenticator = new CookieAuthenticator(null, ['salt' => $secret]);

        $this->assertSame($expected, $authenticator->__debugInfo()['config']['salt']);
    }

    public function testJwtAuthenticatorRedactsSecretKey(): void
    {
        $authenticator = new JwtAuthenticator(null, [
            'secretKey' => 'planted-jwt-secret',
            'algorithm' => 'HS256',
        ]);

        $output = $this->debugOutput($authenticator);

        $this->assertStringNotContainsString('planted-jwt-secret', $output);
        $this->assertSame('*****cret', $authenticator->__debugInfo()['config']['secretKey']);
        $this->assertStringContainsString('HS256', $output, 'Non sensitive config keys must stay visible.');
    }

    public function testHttpDigestAuthenticatorRedactsSecret(): void
    {
        $authenticator = new HttpDigestAuthenticator(null, [
            'secret' => 'planted-digest-nonce-4f2e',
            'qop' => 'auth',
        ]);

        $output = $this->debugOutput($authenticator);

        $this->assertStringNotContainsString('planted-digest-nonce-4f2e', $output);
        $this->assertSame('*****4f2e', $authenticator->__debugInfo()['config']['secret']);
        $this->assertStringContainsString('qop', $output, 'Non sensitive config keys must stay visible.');
    }

    public function testCookieAuthenticatorRedactsStringSalt(): void
    {
        $authenticator = new CookieAuthenticator(null, [
            'salt' => 'planted-hmac-salt',
            'rememberMeField' => 'remember_me',
        ]);

        $output = $this->debugOutput($authenticator);

        $this->assertStringNotContainsString('planted-hmac-salt', $output);
        $this->assertSame('*****salt', $authenticator->__debugInfo()['config']['salt']);
        $this->assertStringContainsString('remember_me', $output, 'Non sensitive config keys must stay visible.');
    }

    public function testCookieAuthenticatorKeepsBooleanSalt(): void
    {
        $authenticator = new CookieAuthenticator(null, ['salt' => true]);

        $this->assertTrue($authenticator->__debugInfo()['config']['salt'], 'A boolean salt flag is not a secret.');
    }

    public function testAuthenticationServiceRedactsNestedAuthenticatorSecrets(): void
    {
        $service = new AuthenticationService([
            'authenticators' => [
                'Authentication.Jwt' => [
                    'secretKey' => 'planted-service-jwt-key-a1b2',
                ],
                'Authentication.HttpDigest' => [
                    'secret' => 'planted-service-digest-c3d4',
                ],
                'Authentication.Cookie' => [
                    'salt' => 'planted-service-cookie-salt',
                    'rememberMeField' => 'remember_me',
                ],
                'Authentication.Session' => [
                    'salt' => true,
                ],
            ],
        ]);

        $output = $this->debugOutput($service);

        $this->assertStringNotContainsString('planted-service-jwt-key-a1b2', $output);
        $this->assertStringNotContainsString('planted-service-digest-c3d4', $output);
        $this->assertStringNotContainsString('planted-service-cookie-salt', $output);

        $nested = $service->__debugInfo()['config']['authenticators'];
        $this->assertSame('*****a1b2', $nested['Authentication.Jwt']['secretKey']);
        $this->assertSame('*****c3d4', $nested['Authentication.HttpDigest']['secret']);
        $this->assertSame('*****salt', $nested['Authentication.Cookie']['salt']);
        $this->assertSame('remember_me', $nested['Authentication.Cookie']['rememberMeField'], 'Non sensitive config keys must stay visible.');
        $this->assertTrue(
            $nested['Authentication.Session']['salt'],
            'A boolean salt flag is not a secret and must stay visible.',
        );

        $this->assertStringContainsString('remember_me', $output, 'Non sensitive config keys must stay visible.');
        $this->assertStringContainsString('identityClass', $output, 'Top level config keys must stay visible.');
    }
}
