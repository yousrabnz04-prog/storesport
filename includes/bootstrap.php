<?php
/**
 * Application bootstrap: configuration, session, database connection, helpers.
 * Every page starts with: require_once __DIR__ . '/includes/bootstrap.php';
 */

define('ROOT_PATH', dirname(__DIR__));

$configFile = ROOT_PATH . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Configuration missing: copy config/config.example.php to config/config.php and edit it.');
}
require_once $configFile;

defined('BASE_PATH') || define('BASE_PATH', '');
defined('SITE_NAME') || define('SITE_NAME', 'StoreSport');
defined('SITE_TAGLINE') || define('SITE_TAGLINE', '');
defined('CONTACT_EMAIL') || define('CONTACT_EMAIL', '');
defined('CONTACT_PHONE') || define('CONTACT_PHONE', '');
defined('CONTACT_ADDRESS') || define('CONTACT_ADDRESS', '');
defined('APP_TIMEZONE') || define('APP_TIMEZONE', 'UTC');
defined('APP_DEBUG') || define('APP_DEBUG', false);

define('UPLOADS_DIR', ROOT_PATH . '/uploads');
define('PRODUCTS_DIR', UPLOADS_DIR . '/products');
define('MESSAGES_DIR', UPLOADS_DIR . '/messages');

date_default_timezone_set(APP_TIMEZONE);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
error_reporting(E_ALL);

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('storesport_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_PATH === '' ? '/' : BASE_PATH . '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit(APP_DEBUG ? 'Database connection failed: ' . $e->getMessage() : 'Service temporarily unavailable.');
}

require_once __DIR__ . '/functions.php';
