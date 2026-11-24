<?php declare(strict_types=1);

namespace Bref\SymfonyBridge\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use PDO;

/**
 * Connecting to an unreachable database (e.g. RDS from outside its VPC) waits for about 2 minutes by default:
 * the Lambda invocation times out without any error. On Lambda, fail with a connection error instead.
 *
 * Applies to `pdo_mysql` and `pdo_pgsql` connections that don't set `PDO::ATTR_TIMEOUT` in their `options`.
 */
final class ConnectTimeoutMiddleware implements Middleware
{
    /**
     * Seconds. With Postgres, it includes the authentication: this stays above the ~15 seconds
     * an Aurora Serverless v2 database takes to resume.
     */
    public const TIMEOUT = 20;

    public function wrap(Driver $driver): Driver
    {
        return new class($driver) extends AbstractDriverMiddleware {
            public function connect(array $params): Connection
            {
                if (ConnectTimeoutMiddleware::appliesTo($params)) {
                    $params['driverOptions'][PDO::ATTR_TIMEOUT] = ConnectTimeoutMiddleware::TIMEOUT;
                }

                return parent::connect($params);
            }
        };
    }

    /**
     * @param array<string, mixed> $params
     *
     * @internal
     */
    public static function appliesTo(array $params): bool
    {
        return isset($_SERVER['LAMBDA_TASK_ROOT'])
            && in_array($params['driver'] ?? null, ['pdo_mysql', 'pdo_pgsql'], true)
            && ! isset($params['driverOptions'][PDO::ATTR_TIMEOUT]);
    }
}
