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
 * @since         1.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Authenticator;

use ArrayObject;
use Authentication\Identifier\IdentifierInterface;
use Cake\Utility\Hash;
use Cake\Utility\Security;
use Exception;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use stdClass;

class JwtAuthenticator extends TokenAuthenticator
{
    /**
     * @inheritDoc
     */
    protected $_defaultConfig = [
        'header' => 'Authorization',
        'queryParam' => 'token',
        'tokenPrefix' => 'bearer',
        'algorithm' => 'HS256',
        'returnPayload' => true,
        'secretKey' => null,
        'subjectKey' => IdentifierInterface::CREDENTIAL_JWT_SUBJECT,
        'jwks' => null,
    ];

    /**
     * Payload data.
     *
     * @var object|null
     */
    protected $payload;

    /**
     * @inheritDoc
     */
    public function __construct(IdentifierInterface $identifier, array $config = [])
    {
        parent::__construct($identifier, $config);

        if (isset($config['algorithms'])) {
            $this->setConfig('algorithms', $config['algorithms'], false);
        }

        if (empty($this->_config['secretKey'])) {
            if (!class_exists(Security::class)) {
                throw new RuntimeException('You must set the `secretKey` config key for JWT authentication.');
            }
            $this->setConfig('secretKey', \Cake\Utility\Security::getSalt());
        }

        if (isset($config['algorithms'])) {
            deprecationWarning(
                'The `algorithms` array config is deprecated, use the `algorithm` string config instead.'
                . ' This is due to the new recommended usage of `firebase/php-jwt`.'
                . 'See https://github.com/firebase/php-jwt/releases/tag/v5.5.0'
            );
        }
    }

    /**
     * Authenticates the identity based on a JWT token contained in a request.
     *
     * @link https://jwt.io/
     * @param \Psr\Http\Message\ServerRequestInterface $request The request that contains login information.
     * @return \Authentication\Authenticator\ResultInterface
     */
    public function authenticate(ServerRequestInterface $request): ResultInterface
    {
        try {
            $result = $this->getPayload($request);
        } catch (Exception $e) {
            return new Result(
                null,
                Result::FAILURE_CREDENTIALS_INVALID,
                [
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]
            );
        }

        if (!($result instanceof stdClass)) {
            return new Result(null, Result::FAILURE_CREDENTIALS_INVALID);
        }

        $result = json_decode(json_encode($result), true);

        $subjectKey = $this->getConfig('subjectKey');
        if (empty($result[$subjectKey])) {
            return new Result(null, Result::FAILURE_CREDENTIALS_MISSING);
        }

        if ($this->getConfig('returnPayload')) {
            $user = new ArrayObject($result);

            return new Result($user, Result::SUCCESS);
        }

        $user = $this->_identifier->identify([
            $subjectKey => $result[$subjectKey],
        ]);

        if (empty($user)) {
            return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND, $this->_identifier->getErrors());
        }

        return new Result($user, Result::SUCCESS);
    }

    /**
     * Get payload data.
     *
     * @param \Psr\Http\Message\ServerRequestInterface|null $request Request to get authentication information from.
     * @return object|null Payload object on success, null on failure
     */
    public function getPayload(?ServerRequestInterface $request = null): ?object
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
    protected function decodeToken(string $token): ?object
    {
        $algorithms = $this->getConfig('algorithms');
        if ($algorithms) {
            return JWT::decode(
                $token,
                $this->getConfig('secretKey'),
                $algorithms
            );
        }

        $jsonWebKeySet = $this->getConfig('jwks');
        if ($jsonWebKeySet) {
            $keySet = JWK::parseKeySet($jsonWebKeySet);
            /*
             * TODO Converting Keys to Key Objects is no longer needed in firebase/php-jwt ^6.0
             * @link https://github.com/firebase/php-jwt/pull/376/files#diff-374f5998b3c572d86be0e79432aac3de362c79e8fb146b9ce422dc2388cdc5daR50
             */
            $keyAlgorithms = Hash::combine($jsonWebKeySet['keys'], '{n}.kid', '{n}.alg');
            array_walk($keySet, function (&$keyMaterial, $k) use ($keyAlgorithms) {
                $keyMaterial = new Key($keyMaterial, $keyAlgorithms[$k]);
            });

            return JWT::decode(
                $token,
                $keySet
            );
        }

        $key = new Key($this->getConfig('secretKey'), $this->getConfig('algorithm'));

        return JWT::decode($token, $key);
    }
}
