<?php
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
 * @since         1.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Authenticator;

use ArrayObject;
use Authentication\Identifier\IdentifierInterface;
use Cake\Utility\Security;
use Exception;
use Firebase\JWT\JWT;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use stdClass;

class JwtAuthenticator extends TokenAuthenticator
{

    /**
     * {@inheritDoc}
     */
    protected $_defaultConfig = [
        'header' => 'Authorization',
        'queryParam' => 'token',
        'tokenPrefix' => 'bearer',
        'algorithms' => ['HS256'],
        'returnPayload' => true,
        'secretKey' => null,
    ];

    /**
     * Payload data.
     *
     * @var object|null
     */
    protected $payload;

    /**
     * {@inheritDoc}
     */
    public function __construct(IdentifierInterface $identifier, array $config = [])
    {
        parent::__construct($identifier, $config);

        if (empty($this->_config['secretKey'])) {
            if (!class_exists(Security::class)) {
                throw new RuntimeException('You must set the `secretKey` config key for JWT authentication.');
            }
            $this->setConfig('secretKey', \Cake\Utility\Security::getSalt());
        }
    }

    /**
     * Authenticates the identity based on a JWT token contained in a request.
     *
     * @link https://jwt.io/
     * @param \Psr\Http\Message\ServerRequestInterface $request The request that contains login information.
     * @param \Psr\Http\Message\ResponseInterface $response Unused response object.
     * @return \Authentication\Authenticator\ResultInterface
     */
    public function authenticate(ServerRequestInterface $request, ResponseInterface $response)
    {
        try {
            $result = $this->getPayload($request);
        } catch (Exception $e) {
            return $this->_buildLastResult(
                null,
                Result::FAILURE_CREDENTIALS_INVALID,
                [
                    'message' => $e->getMessage(),
                    'exception' => $e
                ]
            );
        }

        if (!($result instanceof stdClass)) {
            return $this->_buildLastResult(null, Result::FAILURE_CREDENTIALS_INVALID);
        }

        $result = json_decode(json_encode($result), true);

        $key = IdentifierInterface::CREDENTIAL_JWT_SUBJECT;
        if (empty($result[$key])) {
            return $this->_buildLastResult(null, Result::FAILURE_CREDENTIALS_MISSING);
        }

        if ($this->getConfig('returnPayload')) {
            $user = new ArrayObject($result);

            return $this->_buildLastResult($user, Result::SUCCESS);
        }

        $user = $this->_identifier->identify([
            $key => $result[$key]
        ]);

        if (empty($user)) {
            return $this->_buildLastResult(null, Result::FAILURE_IDENTITY_NOT_FOUND, $this->_identifier->getErrors());
        }

        return $this->_buildLastResult($user, Result::SUCCESS);
    }

    /**
     * Get payload data.
     *
     * @param \Psr\Http\Message\ServerRequestInterface|null $request Request to get authentication information from.
     * @return object|null Payload object on success, null on failure
     */
    public function getPayload(ServerRequestInterface $request = null)
    {
        if (!$request) {
            return $this->payload;
        }

        $payload = null;
        $token = $this->getToken($request);

        if ($token !== null) {
            $payload = $this->decodeToken($token);
        }

        $this->payload = $payload;

        return $this->payload;
    }

    /**
     * Decode JWT token.
     *
     * @param string $token JWT token to decode.
     * @return object|null The JWT's payload as a PHP object, null on failure.
     */
    protected function decodeToken($token)
    {
        return JWT::decode(
            $token,
            $this->getConfig('secretKey'),
            $this->getConfig('algorithms')
        );
    }
}
