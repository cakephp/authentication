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
 * @since         2.10.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Authenticator;

use Authentication\Identifier\IdentifierFactory;
use Authentication\Identifier\IdentifierInterface;
use Authentication\UrlChecker\UrlCheckerTrait;
use Cake\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Environment Authenticator
 *
 * Authenticates an identity based on the POST data of the request.
 */
class EnvironmentAuthenticator extends AbstractAuthenticator
{
    use UrlCheckerTrait;

    /**
     * Default config for this object.
     * - `loginUrl` Login URL or an array of URLs.
     * - `urlChecker` Url checker config.
     * - `fields` array of required fields to get from the environment
     * - `optionalFields` array of optional fields to get from the environment
     *
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'loginUrl' => null,
        'urlChecker' => 'Authentication.Default',
        'fields' => [],
        'optionalFields' => [],
    ];

    /**
     * Constructor
     *
     * @param \Authentication\Identifier\IdentifierInterface|null $identifier Identifier instance.
     * @param array<string, mixed> $config Configuration settings.
     */
    public function __construct(?IdentifierInterface $identifier, array $config = [])
    {
        $identifier ??= IdentifierFactory::create('Authentication.Callback');

        parent::__construct($identifier, $config);
    }

    /**
     * Get values from the environment variables configured by `fields`.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request that contains login information.
     * @return array<string, mixed>|null server params defined by `fields` or null if a field is missing.
     */
    protected function _getData(ServerRequestInterface $request): ?array
    {
        $fields = $this->_config['fields'];
        $params = $request->getServerParams();

        $data = [];
        foreach ($fields as $field) {
            if (!isset($params[$field])) {
                return null;
            }

            $value = $params[$field];
            if (!is_string($value) || !strlen($value)) {
                return null;
            }

            $data[$field] = $value;
        }

        return $data;
    }

    /**
     * Get values from the environment variables configured by `optionalFields`.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request that contains login information.
     * @return array<string, mixed> server params defined by optionalFields.
     */
    protected function _getOptionalData(ServerRequestInterface $request): array
    {
        $fields = $this->_config['optionalFields'];
        $params = $request->getServerParams();

        $data = [];
        foreach ($fields as $field) {
            if (isset($params[$field])) {
                $data[$field] = $params[$field];
            }
        }

        return $data;
    }

    /**
     * Prepares the error object for a login URL error
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request that contains login information.
     * @return \Authentication\Authenticator\ResultInterface
     */
    protected function _buildLoginUrlErrorResult(ServerRequestInterface $request): ResultInterface
    {
        $uri = $request->getUri();
        $base = $request->getAttribute('base');
        if ($base !== null) {
            $uri = $uri->withPath((string)$base . $uri->getPath());
        }

        $checkFullUrl = $this->getConfig('urlChecker.checkFullUrl', false);
        if ($checkFullUrl) {
            $uri = (string)$uri;
        } else {
            $uri = $uri->getPath();
        }

        $loginUrls = (array)$this->getConfig('loginUrl');
        foreach ($loginUrls as $key => $loginUrl) {
            if (is_array($loginUrl)) {
                $loginUrls[$key] = Router::url($loginUrl);
            }
        }

        $errors = [
            sprintf(
                'Login URL `%s` did not match `%s`.',
                $uri,
                implode('` or `', $loginUrls),
            ),
        ];

        return new Result(null, Result::FAILURE_OTHER, $errors);
    }

    /**
     * Authenticates the identity contained in a request.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request that contains login information.
     * @return \Authentication\Authenticator\ResultInterface
     */
    public function authenticate(ServerRequestInterface $request): ResultInterface
    {
        if (!$this->_checkUrl($request)) {
            return $this->_buildLoginUrlErrorResult($request);
        }
        $data = $this->_getData($request);
        if (!$data) {
            return new Result(null, Result::FAILURE_CREDENTIALS_MISSING, [
                'Environment credentials not found',
            ]);
        }

        $data = array_merge($this->_getOptionalData($request), $data);

        $identifier = $this->getIdentifier();
        $user = $identifier->identify($data);

        if (!$user) {
            return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND, $identifier->getErrors());
        }

        return new Result($user, Result::SUCCESS);
    }
}
