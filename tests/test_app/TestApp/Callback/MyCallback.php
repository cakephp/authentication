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
namespace TestApp\Callback;

use Cake\ORM\Entity;

class MyCallback
{
    public static function callme($data)
    {
        return new Entity();
    }
}
