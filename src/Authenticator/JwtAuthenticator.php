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
 * @since         4.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Authenticator;

use Authentication\Identifier\IdentifierCollection;
use Authentication\Result;
use Cake\ORM\Entity;
use Firebase\JWT\JWT;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use stdClass;

class JwtAuthenticator extends TokenAuthenticator {

    /**
     * @inheritDoc
     */
    protected $_defaultConfig = [
        'header' => 'Authorization',
        'queryParam' => 'token',
        'tokenPrefix' => 'bearer',
        'allowedAlgs' => ['HS256'],
        'entityClass' => Entity::class,
        'returnPayload' => true,
        'key' => null,
        'salt' => null
    ];

    /**
     * @inheritdoc
     */
    public function __construct(IdentifierCollection $identifiers, array $config = [])
    {
        parent::__construct($identifiers, $config);

        if (empty($this->_config['salt'])) {
            if (class_exists('\Cake\Utility\Security')) {
                $this->getConfig('salt', \Cake\Utility\Security::salt());
            } else {
                throw new RuntimeException('You must set the `salt` config key');
            }
        }
    }

    /**
     * Authenticates the identity contained in a request. Will use the `config.userModel`, and `config.fields`
     * to find POST data that is used to find a matching record in the `config.userModel`. Will return false if
     * there is no post data, either username or password is missing, or if the scope conditions have not been met.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request that contains login information.
     * @param \Psr\Http\Message\ResponseInterface $response Unused response object.
     * @return \Authentication\ResultInterface
     */
    public function authenticate(ServerRequestInterface $request, ResponseInterface $response)
    {
        $token = $this->_getToken($request);
        $result = $this->_decode($token);

        if (!$result instanceof stdClass) {
            return new Result(null, Result::FAILURE_CREDENTIAL_INVALID);
        }

        $result = json_decode(json_encode($result), true);

        if (empty($result)) {
            return new Result(null, Result::FAILURE_CREDENTIALS_NOT_FOUND);
        }

        if ($this->getConfig('returnPayload')) {
            $entityClass = $this->getConfig('entityClass');
            $entity = new $entityClass($result);

            return new Result($entity, Result::SUCCESS);
        }

        $user = $this->identifiers()->identify($result);

        if (empty($user)) {
            return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND, $this->identifiers()->getErrors());
        }

        return new Result($user, Result::SUCCESS);
    }

    /**
     * Decode JWT token.
     *
     * @param string $token JWT token to decode.
     * @return object|null The JWT's payload as a PHP object, null on failure.
     */
    protected function _decode($token)
    {
        $config = $this->config();

        $token = str_replace($config['tokenPrefix'] . ' ', '', $token);

        try {
            $payload = JWT::decode($token, $config['key'] ?: $config['salt'], $config['allowedAlgs']);

            return $payload;
        } catch (Exception $e) {
            if ($config['debug']) {
                throw $e;
            }
        }
    }
}
