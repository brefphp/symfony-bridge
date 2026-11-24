<?php declare(strict_types=1);

namespace Bref\SymfonyBridge\Test\Unit\Doctrine;

use Bref\SymfonyBridge\Doctrine\ConnectTimeoutMiddleware;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver as SQLiteDriver;
use PDO;
use PHPUnit\Framework\TestCase;

class ConnectTimeoutMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER['LAMBDA_TASK_ROOT'] = '/var/task';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['LAMBDA_TASK_ROOT']);
    }

    public function testSetsTheConnectTimeoutOnMysqlAndPostgres()
    {
        self::assertSame([PDO::ATTR_PERSISTENT => true, PDO::ATTR_TIMEOUT => 20], $this->connect([
            'driver' => 'pdo_mysql',
            'driverOptions' => [PDO::ATTR_PERSISTENT => true],
        ]));
        self::assertSame([PDO::ATTR_TIMEOUT => 20], $this->connect(['driver' => 'pdo_pgsql']));
    }

    public function testKeepsTheConfiguredTimeout()
    {
        self::assertSame([PDO::ATTR_TIMEOUT => 3], $this->connect([
            'driver' => 'pdo_mysql',
            'driverOptions' => [PDO::ATTR_TIMEOUT => 3],
        ]));
    }

    public function testIgnoresOtherDrivers()
    {
        // SQLite uses PDO::ATTR_TIMEOUT for its busy timeout, not for connecting
        self::assertNull($this->connect(['driver' => 'pdo_sqlite']));
    }

    public function testDoesNothingOutsideLambda()
    {
        unset($_SERVER['LAMBDA_TASK_ROOT']);

        self::assertNull($this->connect(['driver' => 'pdo_mysql']));
    }

    /**
     * @return array<int, mixed>|null The driver options the connection is opened with
     */
    private function connect(array $params): ?array
    {
        $recorder = new class(new SQLiteDriver) extends AbstractDriverMiddleware {
            /** @var array<string, mixed> */
            public array $params = [];

            public function connect(array $params): Connection
            {
                $this->params = $params;
                throw new \RuntimeException('Not connecting in tests');
            }
        };

        try {
            (new ConnectTimeoutMiddleware)->wrap($recorder)->connect($params);
        } catch (\RuntimeException) {
        }

        return $recorder->params['driverOptions'] ?? null;
    }
}
