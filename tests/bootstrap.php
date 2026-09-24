<?php
/**
 * PHPUnit bootstrap for YAML Custom Fields plugin
 */

// Load WordPress test environment
$yaml_cf_tests_dir = getenv('WP_TESTS_DIR');
if (!$yaml_cf_tests_dir) {
	// Test library from the wp-phpunit/wp-phpunit dev dependency, configured by tests/wp-tests-config.php
	$yaml_cf_tests_dir = dirname(__DIR__) . '/vendor/wp-phpunit/wp-phpunit';
	if (!getenv('WP_PHPUNIT__TESTS_CONFIG')) {
		putenv('WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php');
	}
}

// Composer autoloader (PHPUnit polyfills, test case classes)
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Give access to tests_add_filter() function
require_once $yaml_cf_tests_dir . '/includes/functions.php';

// Manually load the plugin
tests_add_filter('muplugins_loaded', function() {
	require dirname(__DIR__) . '/yaml-custom-fields.php';
});

// Start up the WP testing environment
require $yaml_cf_tests_dir . '/includes/bootstrap.php';

// Base test case for integration tests
require_once __DIR__ . '/FailsOnPluginWarnings.php';
require_once __DIR__ . '/WPTestCase.php';
