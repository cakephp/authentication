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

use Authentication\Authenticator\OauthAuthenticator;
use Authentication\Identifier\IdentifierCollection;
use Authentication\Result;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;
use Cake\Routing\Router;
use SocialConnect\Auth\Service;
use SocialConnect\Common\Http\Client\Curl;
use SocialConnect\OAuth2\Provider\Github;
use SocialConnect\Provider\Session\Session;

class OauthAuthenticatorTest extends TestCase
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
     * setup
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->identifiers = new IdentifierCollection([
           'Authentication.Orm'
        ]);

        $this->sessionMock = $this->createMock(Session::class);
    }

    /**
     * testMissingRedirectUrl
     *
     * @return void
     * @expectedException \RuntimeException
     * @expectedExceptionMessage You must pass the `redirectUrl` option.
     */
    public function testMissingRedirectUrl()
    {
        $oauth = new OauthAuthenticator($this->identifiers, [
            'loginUrl' => [
                'controller' => 'Users',
                'action' => 'login'
            ],
            'authService' => new Service(
                new Curl,
                $this->sessionMock,
                []
            )
        ]);
    }

    /**
     * testMissingLoginUrl
     *
     * @return void
     * @expectedException \RuntimeException
     * @expectedExceptionMessage You must pass the `loginUrl` option.
     */
    public function testMissingLoginUrl()
    {
        $oauth = new OauthAuthenticator($this->identifiers, [
            'redirectUrl' => [
                'controller' => 'Users',
                'action' => 'callback'
            ],
            'authService' => new Service(
                new Curl,
                $this->sessionMock,
                []
            )
        ]);
    }

    /**
     * testMissingAuthService
     *
     * @return void
     * @expectedException \RuntimeException
     * @expectedExceptionMessage You must pass the `authService` option.
     */
    public function testMissingAuthService()
    {
        $oauth = new OauthAuthenticator($this->identifiers, [
            'loginUrl' => [
                'controller' => 'Users',
                'action' => 'login'
            ],
            'redirectUrl' => [
                'controller' => 'Users',
                'action' => 'callback'
            ]
        ]);
    }

    /**
     * testWrongAuthService
     *
     * @return void
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Option `authService` must be an insatce of \SocialConnect\Auth\Service.
     */
    public function testWrongAuthService()
    {
        $oauth = new OauthAuthenticator($this->identifiers, [
            'loginUrl' => [
                'controller' => 'Users',
                'action' => 'login'
            ],
            'redirectUrl' => [
                'controller' => 'Users',
                'action' => 'callback'
            ],
            'authService' => 'foo'
        ]);
    }
}
