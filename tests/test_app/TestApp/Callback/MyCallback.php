<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace TestApp\Callback;

use Cake\ORM\Entity;

class MyCallback
{
    public static function callme($data)
    {
        return new Entity();
    }
}
