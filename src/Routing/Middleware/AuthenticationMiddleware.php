<?php
namespace MiddlewareAuth\Routing\Middleware;

use Cake\Core\App;
use Cake\Core\Exception\Exception;
use Cake\Core\InstanceConfigTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AuthenticationMiddleware {

	use InstanceConfigTrait;

	protected $_authenticators = [];

	protected $_defaultConfig = [];

	public function __construct(array $config = []) {
		$this->config($config);
		$this->loadAuthenticators();
	}

	public function loadAuthenticators() {
		if (empty($this->_config['authenticators'])) {
			return null;
		}

		foreach ($this->_config['authenticators'] as $name => $config) {
			if (is_int($name) && is_string($config)) {
				$name = $config;
				$config = [];
			}
			$this->loadAuthenticator($name, $config);
		}
	}

	/**
	 *
	 */
	public function loadAuthenticator($name, array $config = []) {
		if (!empty($config['className'])) {
			$class = $config['className'];
			unset($config['className']);
		} else {
			$class = $name;
		}

		$className = App::className($class, 'Routing/Middleware/Authentication', 'Authenticator');
		if (!class_exists($className)) {
			throw new Exception(sprintf('Authentication adapter "%s" was not found.', $className));
		}
		if (!method_exists($className, 'authenticate')) {
			throw new Exception('Authenticator objects must implement an identify() method.');
		}

		$authenticator = new $className($config);
		if (isset($this->_authenticators)) {
			$this->_authenticators[$name] = $authenticator;
		}

		return $authenticator;
	}

	/**
	 * @param \Psr\Http\Message\ServerRequestInterface $request The request.
	 * @param \Psr\Http\Message\ResponseInterface $response The response.
	 * @param callable $next The next middleware to call.
	 * @return \Psr\Http\Message\ResponseInterface A response.
	 */
	public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $next) {
		foreach ($this->_authenticators as $authenticator) {
			$user = $authenticator->authenticate($request, $response);
			if ($user) {
				debug($user);
				break;
			}
		}

		return $next($request, $response);
	}
}