<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @license https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace TestApp\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;

/**
 * AuthUser class
 */
class AuthUsersTable extends Table
{
    /**
     * Custom finder
     *
     * @param \Cake\ORM\Query\SelectQuery $query The query to find with
     * @param bool $returnCreated Whether to return 'created' field.
     * @return \Cake\ORM\Query\SelectQuery The query builder
     */
    public function findAuth(SelectQuery $query, bool $returnCreated = false): SelectQuery
    {
        $query->select(['id', 'username', 'password']);
        if ($returnCreated) {
            $query->select(['created']);
        }

        return $query;
    }

    /**
     * Custom finder
     *
     * @param \Cake\ORM\Query\SelectQuery $query The query to find with
     * @param string $username String username
     * @return \Cake\ORM\Query\SelectQuery The query builder
     */
    public function findUsername(SelectQuery $query, string $username): SelectQuery
    {
        return $this->find()
            ->where(['username' => $username])
            ->select(['id', 'username', 'password']);
    }
}
