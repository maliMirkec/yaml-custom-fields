<?php
/**
 * WordPress test suite configuration (used by wp-phpunit/wp-phpunit)
 *
 * Every value can be set with an environment variable, so the same file works locally and in CI.
 * The database is emptied on every run: never point it at a real site's database.
 *
 * @package YamlCF
 */

$yaml_cf_env = function ($name, $default) {
	$value = getenv($name);
	return ($value === false || $value === '') ? $default : $value;
};

define('ABSPATH', rtrim($yaml_cf_env('WP_CORE_DIR', '/tmp/wordpress'), '/') . '/');

define('WP_DEFAULT_THEME', 'default');
define('WP_DEBUG', true);

define('DB_NAME', $yaml_cf_env('WP_TESTS_DB_NAME', 'wordpress_test'));
define('DB_USER', $yaml_cf_env('WP_TESTS_DB_USER', 'root'));
define('DB_PASSWORD', $yaml_cf_env('WP_TESTS_DB_PASSWORD', ''));
define('DB_HOST', $yaml_cf_env('WP_TESTS_DB_HOST', 'localhost'));
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');

// Test-only salts
define('AUTH_KEY', 'yaml-cf-tests');
define('SECURE_AUTH_KEY', 'yaml-cf-tests');
define('LOGGED_IN_KEY', 'yaml-cf-tests');
define('NONCE_KEY', 'yaml-cf-tests');
define('AUTH_SALT', 'yaml-cf-tests');
define('SECURE_AUTH_SALT', 'yaml-cf-tests');
define('LOGGED_IN_SALT', 'yaml-cf-tests');
define('NONCE_SALT', 'yaml-cf-tests');

$table_prefix = 'wptests_';

define('WP_TESTS_DOMAIN', 'example.org');
define('WP_TESTS_EMAIL', 'admin@example.org');
define('WP_TESTS_TITLE', 'Test Blog');
define('WP_PHP_BINARY', 'php');
define('WPLANG', '');

define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname(__DIR__) . '/vendor/yoast/phpunit-polyfills');
