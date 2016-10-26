<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         3.1.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Auth\Authentication\Storage;

use Cake\Core\InstanceConfigTrait;
use Cake\Network\Session;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Session based persistent storage for authenticated user record.
 */
class SessionStorage implements StorageInterface
{
    use InstanceConfigTrait;

    /**
     * User record.
     *
     * Stores user record array if fetched from session or false if session
     * does not have user record.
     *
     * @var array|bool
     */
    protected $_user;

    /**
     * Session object.
     *
     * @var \Cake\Network\Session
     */
    protected $_session;

    /**
     * Default configuration for this class.
     *
     * Keys:
     *
     * - `key` - Session key used to store user record.
     * - `redirect` - Session key used to store redirect URL.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'key' => 'Auth.User',
        'redirect' => 'Auth.redirect'
    ];

    /**
     * Constructor.
     *
     * @param \Cake\Network\Request $request Request instance.
     * @param \Cake\Network\Response $response Response instance.
     * @param array $config Configuration list.
     */
    public function __construct(ServerRequestInterface $request, ResponseInterface $response, array $config = [])
    {
        $this->_session = $request->getAttribute('session');
        if (!$this->_session instanceof Session) {
            if (is_object($this->_session)) {
                $message = sprintf('Invalid session object of type `%s` passed', get_class($this->_session));
            } else {
                $message = sprintf('Invalid session value of type `%s` passed', gettype($this->_session));
            }
            throw new \RuntimeException($message);
        }
        $this->config($config);
    }

    /**
     * Read user record from session.
     *
     * @return array|null User record if available else null.
     */
    public function read()
    {
        if ($this->_user !== null) {
            return $this->_user ?: null;
        }

        $this->_user = $this->_session->read($this->_config['key']) ?: false;

        return $this->_user;
    }

    /**
     * Write user record to session.
     *
     * The session id is also renewed to help mitigate issues with session replays.
     *
     * @param array|\ArrayAccess $user User record.
     * @return void
     */
    public function write($user)
    {
        $this->_user = $user;

        $this->_session->renew();
        $this->_session->write($this->_config['key'], $user);
    }

    /**
     * Delete user record from session.
     *
     * The session id is also renewed to help mitigate issues with session replays.
     *
     * @return void
     */
    public function delete()
    {
        $this->_user = false;

        $this->_session->delete($this->_config['key']);
        $this->_session->renew();
    }

    /**
     * {@inheritDoc}
     */
    public function redirectUrl($url = null)
    {
        if ($url === null) {
            return $this->_session->read($this->_config['redirect']);
        }

        if ($url === false) {
            $this->_session->delete($this->_config['redirect']);

            return null;
        }

        $this->_session->write($this->_config['redirect'], $url);
    }
}
