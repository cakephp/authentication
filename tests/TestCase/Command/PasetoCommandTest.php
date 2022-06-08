<?php
declare(strict_types=1);

namespace Authentication\Test\TestCase\Command;

use Cake\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;

class PasetoCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->useCommandRunner();
    }

    /**
     * @dataProvider dataProviderForVersions
     * @param string $version
     * @return void
     */
    public function testGenLocal(string $version): void
    {
        $this->exec("paseto gen $version local");
        $this->assertArrayHasKey(1, $this->_out->messages());
        $length = strlen($this->_out->messages()[1]);
        $this->assertGreaterThanOrEqual(16, $length);
        $this->assertLessThanOrEqual(64, $length);
    }

    /**
     * @dataProvider dataProviderForVersions
     * @param string $version
     * @return void
     */
    public function testGenPublic(string $version): void
    {
        $this->exec("paseto gen $version public");
        $this->assertCount(3, $this->_out->messages());
        $this->assertGreaterThanOrEqual(32, strlen($this->_out->messages()[1]));
        $this->assertGreaterThanOrEqual(32, strlen($this->_out->messages()[2]));
    }

    /**
     * Returns an array of version and purpose args.
     *
     * @return array
     */
    public function dataProviderForVersions(): array
    {
        return [
            ['v3'],
            ['v4'],
        ];
    }
}
