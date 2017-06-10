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
namespace Authentication\Authenticator;

use ArrayObject;
use Authentication\Identifier\IdentifierCollection;
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
    public function __construct(IdentifierCollection $identifiers, array $config = [])
    {
        parent::__construct($identifiers, $config);

        if (empty($this->_config['secretKey'])) {
            if (!class_exists('Cake\Utility\Security')) {
                throw new RuntimeException('You must set the `secretKey` config key for JWT authentication.');
            }
            $this->setConfig('secretKey', \Cake\Utility\Security::salt());
        }
    }

    /**
     * Authenticates the identity based on a JWT token contained in a request.
     *
     * @link https://jwt.io/
     * @param \Psr\Http\Message\ServerRequestInterface $request The request that contains login information.
     * @param \Psr\Http\Message\ResponseInterface $response Unused response object.
     * @return \Authentication\ResultInterface
     */
    public function authenticate(ServerRequestInterface $request, ResponseInterface $response)
    {
        $result = $this->getPayload($request);

        if (!$result instanceof stdClass) {
            return new Result(null, Result::FAILURE_CREDENTIAL_INVALID);
        }

        $result = json_decode(json_encode($result), true);

        if (empty($result)) {
            return new Result(null, Result::FAILURE_CREDENTIALS_NOT_FOUND);
        }

        if ($this->getConfig('returnPayload')) {
            $user = new ArrayObject($result);

            return new Result($user, Result::SUCCESS);
        }

        $user = $this->identifiers()->identify($result);

        if (empty($user)) {
            return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND, $this->identifiers()->getErrors());
        }

        return new Result($user, Result::SUCCESS);
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

        if ($token) {
            $payload = $this->decodeToken($token);
        }

        return $this->payload = $payload;
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
