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
 * @since         3.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Authenticator;

use ArrayObject;
use Authentication\Identifier\IdentifierInterface;
use Exception;
use ParagonIE\Paseto\JsonToken;
use ParagonIE\Paseto\Keys\AsymmetricSecretKey;
use ParagonIE\Paseto\Keys\SymmetricKey;
use ParagonIE\Paseto\Parser;
use ParagonIE\Paseto\Protocol\Version3;
use ParagonIE\Paseto\Protocol\Version4;
use ParagonIE\Paseto\ProtocolCollection;
use ParagonIE\Paseto\ProtocolInterface;
use ParagonIE\Paseto\Purpose;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * PASETO Authenticator
 *
 * Authenticates an identity based on a PASETO token.
 *
 * @link https://github.com/paragonie/paseto
 * @link https://github.com/paseto-standard/paseto-spec
 */
class PasetoAuthenticator extends TokenAuthenticator
{
    public const LOCAL = 'local';
    public const PUBLIC = 'public';

    /**
     * @inheritDoc
     */
    protected $_defaultConfig = [
        'header' => 'Authorization',
        'queryParam' => 'token',
        'tokenPrefix' => 'bearer',
        'returnPayload' => true,
        'version' => null,
        'purpose' => null,
        'secretKey' => null,
    ];

    /**
     * Payload data.
     *
     * @var object|null
     */
    protected $payload;

    /**
     * @var \ParagonIE\Paseto\ProtocolInterface|null
     */
    private $version;

    /**
     * @inheritDoc
     */
    public function __construct(IdentifierInterface $identifier, array $config = [])
    {
        parent::__construct($identifier, $config);

        $this->version = $this->whichVersion();

        if ($this->version === null) {
            throw new RuntimeException('PASETO `version` must be one of: v3 or v4');
        }

        if (!in_array($this->getConfig('purpose'), [self::PUBLIC, self::LOCAL])) {
            throw new RuntimeException('PASETO `purpose` config must one of: local or public');
        }

        if (empty($this->getConfig('secretKey'))) {
            if (!class_exists(\Cake\Utility\Security::class)) {
                throw new RuntimeException('PASETO `secretKey` config must be defined');
            }
            $this->setConfig('secretKey', \Cake\Utility\Security::getSalt());
        }
    }

    /**
     * Authenticates the identity based on a PASETO token contained in a request.
     *
     * @link https://paseto.io/
     * @param \Psr\Http\Message\ServerRequestInterface $request The request that contains login information.
     * @return \Authentication\Authenticator\ResultInterface
     * @throws \Exception
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

        if (!$result instanceof JsonToken) {
            return new Result(null, Result::FAILURE_CREDENTIALS_INVALID);
        }

        if (empty($result->getSubject())) {
            return new Result(null, Result::FAILURE_CREDENTIALS_MISSING);
        }

        if ($this->getConfig('returnPayload')) {
            $array = array_merge(
                $result->getClaims(),
                ['footer' => $result->getFooterArray()]
            );

            return new Result(new ArrayObject($array), Result::SUCCESS);
        }

        $user = $this->_identifier->identify([
            'sub' => $result->getSubject(),
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
     * @throws \Exception
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
     * Decode PASETO token.
     *
     * @param string $token PASETO token to decode.
     * @return null|\ParagonIE\Paseto\JsonToken The PASETO payload as a JsonToken object, null on failure.
     * @throws \Exception
     */
    protected function decodeToken(string $token): ?JsonToken
    {
        if ($this->getConfig('purpose') === self::PUBLIC) {
            $receivingKey = AsymmetricSecretKey::fromEncodedString(
                $this->getConfig('secretKey'),
                $this->version
            )->getPublicKey();
        } else {
            $receivingKey = new SymmetricKey(
                $this->getConfig('secretKey'),
                $this->version
            );
        }

        $parser = new Parser(
            new ProtocolCollection($receivingKey->getProtocol()),
            new Purpose($this->getConfig('purpose')),
            $receivingKey
        );

        return $parser->parse($token);
    }

    /**
     * Returns instance of ProtocolInterface from configured version.
     *
     * @return \ParagonIE\Paseto\ProtocolInterface|null
     */
    private function whichVersion(): ?ProtocolInterface
    {
        switch ($this->getConfig('version')) {
            case 'v3':
                return new Version3();
            case 'v4':
                return new Version4();
        }

        return null;
    }
}
