<?php
/**
 * Application configuration template.
 *
 * Copy this file to config/config.php and adjust the values.
 * config/config.php is ignored by Git and must never be committed.
 */

// Database (use a dedicated MySQL user with limited privileges, not root)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'storesport');
define('DB_USER', getenv('DB_USER') ?: 'storesport_app');
define('DB_PASS', getenv('DB_PASS') ?: 'change-me');

// URL path where the app is served, without trailing slash.
// '' when served from the domain root, '/storesport' when in a sub-folder.
define('BASE_PATH', '/storesport');

// Branding and contact details (leave empty to hide them in the footer)
define('SITE_NAME', 'StoreSport');
define('SITE_TAGLINE', 'Quality sports equipment for every athlete');
define('CONTACT_EMAIL', '');
define('CONTACT_PHONE', '');
define('CONTACT_ADDRESS', '');

// Misc
define('APP_TIMEZONE', 'UTC');
define('APP_DEBUG', false); // true only on a local development machine
