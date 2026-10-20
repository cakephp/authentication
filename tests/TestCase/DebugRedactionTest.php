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

/**
 * Ensures secrets are redacted from debug output (`var_dump()` and
 * CakePHP's `Debugger::exportVar()`) via `__debugInfo()`.
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

    public function testJwtAuthenticatorRedactsSecretKey(): void
    {
        $authenticator = new JwtAuthenticator(null, [
            'secretKey' => 'planted-jwt-secret',
            'algorithm' => 'HS256',
        ]);

        $output = $this->debugOutput($authenticator);

        $this->assertStringNotContainsString('planted-jwt-secret', $output);
        $this->assertSame('*****', $authenticator->__debugInfo()['config']['secretKey']);
        $this->assertStringContainsString('HS256', $output, 'Non sensitive config keys must stay visible.');
    }

    public function testHttpDigestAuthenticatorRedactsSecret(): void
    {
        $authenticator = new HttpDigestAuthenticator(null, [
            'secret' => 'planted-digest-secret',
            'qop' => 'auth',
        ]);

        $output = $this->debugOutput($authenticator);

        $this->assertStringNotContainsString('planted-digest-secret', $output);
        $this->assertSame('*****', $authenticator->__debugInfo()['config']['secret']);
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
        $this->assertSame('*****', $authenticator->__debugInfo()['config']['salt']);
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
                    'secretKey' => 'planted-service-jwt-secret',
                ],
                'Authentication.HttpDigest' => [
                    'secret' => 'planted-service-digest-secret',
                ],
                'Authentication.Cookie' => [
                    'salt' => 'planted-service-cookie-salt',
                    'rememberMeField' => 'remember_me',
                ],
            ],
        ]);

        $output = $this->debugOutput($service);

        $this->assertStringNotContainsString('planted-service-jwt-secret', $output);
        $this->assertStringNotContainsString('planted-service-digest-secret', $output);
        $this->assertStringNotContainsString('planted-service-cookie-salt', $output);

        $nested = $service->__debugInfo()['config']['authenticators'];
        $this->assertSame('*****', $nested['Authentication.Jwt']['secretKey']);
        $this->assertSame('*****', $nested['Authentication.HttpDigest']['secret']);
        $this->assertSame('*****', $nested['Authentication.Cookie']['salt']);
        $this->assertSame('remember_me', $nested['Authentication.Cookie']['rememberMeField'], 'Non sensitive config keys must stay visible.');

        $this->assertStringContainsString('remember_me', $output, 'Non sensitive config keys must stay visible.');
        $this->assertStringContainsString('identityClass', $output, 'Top level config keys must stay visible.');
    }
}
