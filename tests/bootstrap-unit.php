<?php
/**
 * PHPUnit bootstrap for unit tests (no WordPress required)
 */

// Define ABSPATH to prevent exit() in source files
if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(dirname(__FILE__)) . '/');
}

// Load Composer autoloader (includes both PHPUnit and Symfony packages)
// In tests, we use the unscoped Symfony libraries from vendor/
// The scoped version in build/vendor is only used in production WordPress environment
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Create aliases for scoped Symfony classes to unscoped versions for testing
// In production, these are scoped to YamlCF\Vendor\Symfony\...  to avoid conflicts
// In tests, we use the unscoped versions from vendor/
class_alias('Symfony\Component\Yaml\Yaml', 'YamlCF\Vendor\Symfony\Component\Yaml\Yaml');
class_alias('Symfony\Component\Yaml\Exception\ParseException', 'YamlCF\Vendor\Symfony\Component\Yaml\Exception\ParseException');

// Load base test classes
require_once __DIR__ . '/TestCase.php';
// WPTestCase requires WordPress test environment, skip for unit tests

/**
 * Mock WordPress core functions for unit tests
 *
 * Each WordPress function is implemented as:
 * 1. A prefixed implementation function (yaml_cf_test_*) with the actual logic
 * 2. An unprefixed alias function that calls the prefixed version for mocking
 *
 * This approach satisfies both:
 * - WPCS requirement: All functions must be prefixed (yaml_cf_test_*)
 * - Testing requirement: Mocks must match WordPress core function names exactly
 *
 * Unit tests run without WordPress loaded, so these lightweight implementations
 * allow the code to run in isolation while maintaining WPCS compliance.
 */
if (!function_exists('esc_html')) {
	function yaml_cf_esc_html($text) {
		return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
	}
	// Alias to WordPress core function name for mocking
	function esc_html($text) {
		return yaml_cf_esc_html($text);
	}
}

if (!function_exists('esc_url')) {
	function yaml_cf_test_esc_url($url, $protocols = null) {
		// Basic URL sanitization
		$url = trim($url);

		// Block dangerous protocols
		$dangerous = ['javascript:', 'data:', 'vbscript:', 'file:'];
		foreach ($dangerous as $protocol) {
			if (stripos($url, $protocol) === 0) {
				return '';
			}
		}

		return $url;
	}
	// Alias to WordPress core function name for mocking
	function esc_url($url, $protocols = null) {
		return yaml_cf_test_esc_url($url, $protocols);
	}
}

if (!function_exists('wp_kses')) {
	function yaml_cf_test_wp_kses($string, $allowed_html) {
		// Very basic implementation for testing
		// In real WordPress, this is much more sophisticated
		if (empty($allowed_html)) {
			return preg_replace('/<[^>]*>/', '', $string);
		}
		$allowed_tags = '<' . implode('><', array_keys($allowed_html)) . '>';
		return strip_tags($string, $allowed_tags);
	}
	// Alias to WordPress core function name for mocking
	function wp_kses($string, $allowed_html) {
		return yaml_cf_test_wp_kses($string, $allowed_html);
	}
}

if (!function_exists('sanitize_text_field')) {
	function yaml_cf_test_sanitize_text_field($str) {
		// Remove all HTML tags and trim whitespace
		$str = preg_replace('/<[^>]*>/', '', $str);
		return trim($str);
	}
	// Alias to WordPress core function name for mocking
	function sanitize_text_field($str) {
		return yaml_cf_test_sanitize_text_field($str);
	}
}

if (!function_exists('sanitize_key')) {
	function yaml_cf_test_sanitize_key($key) {
		return strtolower(preg_replace('/[^a-z0-9_\-]/', '', $key));
	}
	// Alias to WordPress core function name for mocking
	function sanitize_key($key) {
		return yaml_cf_test_sanitize_key($key);
	}
}

if (!function_exists('wp_unslash')) {
	function yaml_cf_test_wp_unslash($value) {
		return yaml_cf_test_stripslashes_deep($value);
	}
	// Alias to WordPress core function name for mocking
	function wp_unslash($value) {
		return yaml_cf_test_wp_unslash($value);
	}
}

if (!function_exists('stripslashes_deep')) {
	function yaml_cf_test_stripslashes_deep($value) {
		if (is_array($value)) {
			return array_map('yaml_cf_test_stripslashes_deep', $value);
		}
		return is_string($value) ? stripslashes($value) : $value;
	}
	// Alias to WordPress core function name for mocking
	function stripslashes_deep($value) {
		return yaml_cf_test_stripslashes_deep($value);
	}
}

if (!function_exists('map_deep')) {
	function yaml_cf_test_map_deep($value, $callback) {
		if (is_array($value)) {
			foreach ($value as $index => $item) {
				$value[$index] = yaml_cf_test_map_deep($item, $callback);
			}
		} elseif (is_object($value)) {
			$object_vars = get_object_vars($value);
			foreach ($object_vars as $property_name => $property_value) {
				$value->$property_name = yaml_cf_test_map_deep($property_value, $callback);
			}
		} else {
			$value = call_user_func($callback, $value);
		}
		return $value;
	}
	// Alias to WordPress core function name for mocking
	function map_deep($value, $callback) {
		return yaml_cf_test_map_deep($value, $callback);
	}
}

if (!function_exists('esc_attr')) {
	function yaml_cf_test_esc_attr($text) {
		return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
	}
	// Alias to WordPress core function name for mocking
	function esc_attr($text) {
		return yaml_cf_test_esc_attr($text);
	}
}

if (!function_exists('wp_kses_post')) {
	function yaml_cf_test_wp_kses_post($data) {
		// Basic implementation for testing
		return $data;
	}
	// Alias to WordPress core function name for mocking
	function wp_kses_post($data) {
		return yaml_cf_test_wp_kses_post($data);
	}
}

if (!function_exists('do_action')) {
	function yaml_cf_test_do_action($hook_name, ...$args) {
		// Mock do_action for testing - does nothing
		return;
	}
	// Alias to WordPress core function name for mocking
	function do_action($hook_name, ...$args) {
		return yaml_cf_test_do_action($hook_name, ...$args);
	}
}
