<?php
namespace MiddlewareAuth\Test\TestCase\Routing\Middleware\Authentication;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use MiddlewareAuth\Routing\Middleware\AuthenticationMiddleware;
use Zend\Diactoros\Request;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequestFactory;

class FormAuthenticatorTest extends TestCase {

	public $fixtures = [
		'core.auth_users',
		'core.users'
	];

}