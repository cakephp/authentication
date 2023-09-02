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
namespace Authentication\Identifier;

/**
 * Jwt Subject aka "sub" identifier.
 *
 * This is mostly a convenience class that just overrides the defaults of the
 * TokenIdentifier.
 */
class JwtSubjectIdentifier extends TokenIdentifier
{
    public const CREDENTIAL_JWT_SUBJECT = 'sub';

    /**
     * Default configuration
     *
     * @var array
     */
    protected array $_defaultConfig = [
        'tokenField' => 'id',
        'dataField' => self::CREDENTIAL_JWT_SUBJECT,
        'resolver' => 'Authentication.Orm',
    ];
}
