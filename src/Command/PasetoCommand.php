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
namespace Authentication\Command;

use Authentication\Authenticator\PasetoAuthenticator;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use ParagonIE\Paseto\Keys\AsymmetricSecretKey;
use ParagonIE\Paseto\Keys\SymmetricKey;
use ParagonIE\Paseto\Protocol\Version3;
use ParagonIE\Paseto\Protocol\Version4;
use ParagonIE\Paseto\ProtocolInterface;

class PasetoCommand extends Command
{
    /**
     * @param \Cake\Console\ConsoleOptionParser $parser ConsoleOptionParser
     * @return \Cake\Console\ConsoleOptionParser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Generate keys for PASETO')
            ->addArgument('version', [
                'help' => 'The PASETO version',
                'choices' => ['v3', 'v4'],
            ])
            ->addArgument('purpose', [
                'help' => 'The PASETO purpose',
                'choices' => [PasetoAuthenticator::LOCAL, PasetoAuthenticator::PUBLIC],
            ])
            ->setEpilog('Example: <info>bin/cake paseto gen v4 local</info>');

        return $parser;
    }

    /**
     * @param \Cake\Console\Arguments $args Arguments
     * @param \Cake\Console\ConsoleIo $io ConsoleIo
     * @return int|void|null
     * @throws \ParagonIE\Paseto\Exception\PasetoException
     * @throws \Exception
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $version = strtolower($args->getArgument('version') ?? 'v4');
        $version = trim($version);
        $purpose = strtolower($args->getArgument('purpose') ?? PasetoAuthenticator::LOCAL);
        switch ($purpose) {
            case PasetoAuthenticator::LOCAL:
                $io->info('Generating base64 ' . $version . ' local secret...');
                $key = SymmetricKey::generate($this->versionFromString($version));
                $io->out($key->encode());
                break;
            case PasetoAuthenticator::PUBLIC:
                $io->info('Generating base64 ' . $version . ' public keypair...');
                $key = AsymmetricSecretKey::generate($this->versionFromString($version));
                $io->out('Public: ' . $key->getPublicKey()->encode());
                $io->out('Private: ' . $key->encode());
                break;
        }
    }

    /**
     * Return an instance of ProtocolInterface (Version) from a string, defaults to Version4
     *
     * @param string $version Version string (e.g. "v4")
     * @return \ParagonIE\Paseto\ProtocolInterface
     */
    private function versionFromString(string $version): ProtocolInterface
    {
        switch ($version) {
            case 'v3':
                return new Version3();
            case 'v4':
            default:
                return new Version4();
        }
    }
}
