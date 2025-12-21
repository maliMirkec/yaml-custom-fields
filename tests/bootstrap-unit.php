<?php
/**
 * PHPUnit bootstrap for unit tests (no WordPress required)
 */

// Suppress deprecation warnings from vendor dependencies
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

// Load Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load scoped vendor autoloader (for Symfony YAML and other dependencies)
require_once dirname(__DIR__) . '/build/vendor/scoper-autoload.php';

// Load base test classes
require_once __DIR__ . '/TestCase.php';
// WPTestCase requires WordPress test environment, skip for unit tests

// Mock WordPress functions for unit tests
if (!function_exists('esc_html')) {
	function esc_html($text) {
		return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
	}
}

if (!function_exists('esc_url')) {
	function esc_url($url, $protocols = null) {
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
}

if (!function_exists('wp_kses')) {
	function wp_kses($string, $allowed_html) {
		// Very basic implementation for testing
		// In real WordPress, this is much more sophisticated
		return strip_tags($string, '<' . implode('><', array_keys($allowed_html)) . '>');
	}
}

if (!function_exists('sanitize_text_field')) {
	function sanitize_text_field($str) {
		return trim(strip_tags($str));
	}
}

if (!function_exists('sanitize_key')) {
	function sanitize_key($key) {
		return strtolower(preg_replace('/[^a-z0-9_\-]/', '', $key));
	}
}

if (!function_exists('wp_unslash')) {
	function wp_unslash($value) {
		return stripslashes_deep($value);
	}
}

if (!function_exists('stripslashes_deep')) {
	function stripslashes_deep($value) {
		if (is_array($value)) {
			return array_map('stripslashes_deep', $value);
		}
		return is_string($value) ? stripslashes($value) : $value;
	}
}

if (!function_exists('map_deep')) {
	function map_deep($value, $callback) {
		if (is_array($value)) {
			foreach ($value as $index => $item) {
				$value[$index] = map_deep($item, $callback);
			}
		} elseif (is_object($value)) {
			$object_vars = get_object_vars($value);
			foreach ($object_vars as $property_name => $property_value) {
				$value->$property_name = map_deep($property_value, $callback);
			}
		} else {
			$value = call_user_func($callback, $value);
		}
		return $value;
	}
}

if (!function_exists('esc_attr')) {
	function esc_attr($text) {
		return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
	}
}

if (!function_exists('wp_kses_post')) {
	function wp_kses_post($data) {
		// Basic implementation for testing
		return $data;
	}
}

if (!function_exists('do_action')) {
	function do_action($hook_name, ...$args) {
		// Mock do_action for testing - does nothing
		return;
	}
}
