<?php
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         1.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\View\Helper;

use Authentication\IdentityInterface;
use Cake\ORM\Entity;
use Cake\Utility\Hash;
use Cake\View\Helper;
use RuntimeException;

/**
 * Identity Helper
 *
 * A convenience helper to access the identity data
 */
class IdentityHelper extends Helper
{

    /**
     * @inheritDoc
     */
    protected $_defaultConfig = [
        'idField' => 'id'
    ];

    /**
     * User data
     *
     * @array
     */
    protected $_userData = [];

    /**
     * @inheritDoc
     */
    public function initialize(array $config)
    {
        $identity = $this->getView()->request->getAttribute('identity');
        if (empty($identity)) {
            return;
        }

        if (!$identity instanceof IdentityInterface) {
            throw new RuntimeException(sprintf('Identity found in request does not implement %s', IdentityInterface::class));
        }

        $this->_userData = $identity->getOriginalData();

        if ($this->_userData instanceof Entity) {
            $this->_userData = $this->_userData->toArray();
        }
    }

    /**
     * Gets the id of the current logged in identity
     *
     * @return int|null|string
     */
    public function getId()
    {
        $field = $this->getConfig('idField');
        if (is_callable($field)) {
            return $field($this->_userData);
        }

        return $this->get($field);
    }

    /**
     * Checks if a user is logged in
     *
     * @return boolean
     */
    public function isLoggedIn()
    {
        return (!empty($this->_userData));
    }

    /**
     * This check can be used to tell if a record that belongs to some user is the
     * current logged in user
     *
     * @param string|integer $userId
     * @param string $field Name of the field in the user record to check against, id by default
     * @return boolean
     */
    public function is($userId, $field = 'id')
    {
        return ($userId === $this->get($field));
    }

    /**
     * Gets user data
     *
     * @param string $key
     * @return mixed
     */
    public function get($key = null)
    {
        if ($key === null) {
            return $this->_userData();
        }

        return Hash::get($this->_userData, $key);
    }
}
