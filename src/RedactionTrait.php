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
 * @since         4.3.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication;

/**
 * Redaction helper for keeping secrets out of debug output.
 */
trait RedactionTrait
{
    /**
     * Redacts a secret while preserving its last 4 bytes.
     *
     * The trailing bytes are kept visible so that developers can verify
     * that the correct secret/key is configured without the full value
     * ending up in debug output or logs. Secrets of 8 bytes or less are
     * masked entirely, as preserving a suffix would reveal most of the
     * value.
     *
     * @param string $secret The secret to redact.
     * @return string The redacted value.
     */
    protected static function redactSecret(string $secret): string
    {
        if (strlen($secret) <= 8) {
            return '*****';
        }

        return '*****' . substr($secret, -4);
    }
}
