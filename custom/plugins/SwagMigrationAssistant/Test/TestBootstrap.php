<?php declare(strict_types=1);

/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Doctrine\DBAL\DriverManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use SwagMigrationAssistant\Test\Shopware5DatabaseConnection;
use Symfony\Component\Dotenv\Dotenv;

function getProjectDir(): string
{
    if (isset($_SERVER['PROJECT_ROOT']) && file_exists($_SERVER['PROJECT_ROOT'])) {
        return $_SERVER['PROJECT_ROOT'];
    }
    if (isset($_ENV['PROJECT_ROOT']) && file_exists($_ENV['PROJECT_ROOT'])) {
        return $_ENV['PROJECT_ROOT'];
    }

    $rootDir = __DIR__;
    $dir = $rootDir;
    while (!file_exists($dir . '/.env')) {
        if ($dir === dirname($dir)) {
            return $rootDir;
        }
        $dir = dirname($dir);
    }

    return $dir;
}

$testProjectDir = getProjectDir();

$loader = require $testProjectDir . '/vendor/autoload.php';
KernelLifecycleManager::prepare($loader);
$pluginVendorDir = __DIR__ . '/../vendor';
if (is_dir($pluginVendorDir)) {
    require_once $pluginVendorDir . '/autoload.php';
} else {
    echo 'vendor directory not found. Please execute "composer dump-autoload"';
    exit(1);
}

if (!class_exists(Dotenv::class)) {
    throw new RuntimeException('APP_ENV environment variable is not defined. You need to define environment variables for configuration or add "symfony/dotenv" as a Composer dependency to load variables from a .env file.');
}
(new Dotenv())->load($testProjectDir . '/.env');

$dbUrl = getenv('DATABASE_URL');
if ($dbUrl !== false) {
    $testDbUrl = $dbUrl . '_test';
    putenv('DATABASE_URL=' . $testDbUrl);
    $_ENV['DATABASE_URL'] = $testDbUrl;
}

// Test the connection to the Shopware 5 Test Setup. May not be available locally

$connectionParams = [
    'dbname' => Shopware5DatabaseConnection::DB_NAME,
    'user' => Shopware5DatabaseConnection::DB_USER,
    'password' => Shopware5DatabaseConnection::DB_PASSWORD,
    'host' => Shopware5DatabaseConnection::DB_HOST,
    'port' => Shopware5DatabaseConnection::DB_PORT,
    'driver' => 'pdo_mysql',
    'charset' => 'utf8mb4',
];

$connection = DriverManager::getConnection($connectionParams);

$ping = true;

try {
    $ping = $connection->ping();
} catch (\Exception $e) {
    putenv('SWAG_MIGRATION_ASSISTANT_SKIP_SW5_TESTS=true');
}

if ($ping === false) {
    putenv('SWAG_MIGRATION_ASSISTANT_SKIP_SW5_TESTS=true');
}
