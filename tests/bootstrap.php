<?php
/**
 * PHPUnit bootstrap for YAML Custom Fields plugin
 */

// Load WordPress test environment
$yaml_cf_tests_dir = getenv('WP_TESTS_DIR');
if (!$yaml_cf_tests_dir) {
	$yaml_cf_tests_dir = '/tmp/wordpress-tests-lib';
}

// Give access to tests_add_filter() function
require_once $yaml_cf_tests_dir . '/includes/functions.php';

// Manually load the plugin
tests_add_filter('muplugins_loaded', function() {
	require dirname(__DIR__) . '/yaml-custom-fields.php';
});

// Start up the WP testing environment
require $yaml_cf_tests_dir . '/includes/bootstrap.php';
