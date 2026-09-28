<?php
/**
 * PHPUnit bootstrap for unit tests (no WordPress required)
 */

// Define ABSPATH to prevent exit() in source files
if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(dirname(__FILE__)) . '/');
}

// Export files stamp the plugin version into their header
if (!defined('YAML_CF_VERSION')) {
	define('YAML_CF_VERSION', 'test');
}

// Unfinished field renderers throw instead of logging when WP_DEBUG is on,
// which is what a developer's environment looks like (see tests/wp-tests-config.php)
if (!defined('WP_DEBUG')) {
	define('WP_DEBUG', true);
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
		// Same order as core: lowercase first, then strip disallowed characters
		return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key));
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

if (!function_exists('esc_textarea')) {
	function yaml_cf_test_esc_textarea($text) {
		return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
	}
	// Alias to WordPress core function name for mocking
	function esc_textarea($text) {
		return yaml_cf_test_esc_textarea($text);
	}
}

if (!function_exists('wp_kses_post')) {
	function yaml_cf_test_wp_kses_post($data) {
		// Basic implementation for testing: drop script elements like core kses does
		return preg_replace('#<script\b[^>]*>.*?</script>#is', '', $data);
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

if (!function_exists('sanitize_textarea_field')) {
	function yaml_cf_test_sanitize_textarea_field($str) {
		// Remove all HTML tags but keep newlines
		$str = preg_replace('/<[^>]*>/', '', (string) $str);
		return trim($str);
	}
	// Alias to WordPress core function name for mocking
	function sanitize_textarea_field($str) {
		return yaml_cf_test_sanitize_textarea_field($str);
	}
}

if (!function_exists('current_user_can')) {
	/**
	 * Capabilities granted to the mocked current user, e.g. ['unfiltered_html' => true]
	 */
	$GLOBALS['yaml_cf_test_user_caps'] = [];

	function yaml_cf_test_current_user_can($capability, ...$args) {
		return !empty($GLOBALS['yaml_cf_test_user_caps'][$capability]);
	}
	// Alias to WordPress core function name for mocking
	function current_user_can($capability, ...$args) {
		return yaml_cf_test_current_user_can($capability, ...$args);
	}
}

if (!function_exists('get_post')) {
	/**
	 * Post IDs the mocked get_post() treats as missing, and post types by ID (default: attachment)
	 */
	$GLOBALS['yaml_cf_test_missing_posts'] = [];
	$GLOBALS['yaml_cf_test_post_types'] = [];

	function yaml_cf_test_get_post($post = null) {
		$post_id = is_object($post) ? (int) $post->ID : (int) $post;
		if ($post_id <= 0 || in_array($post_id, $GLOBALS['yaml_cf_test_missing_posts'], true)) {
			return null;
		}
		$post_type = isset($GLOBALS['yaml_cf_test_post_types'][$post_id]) ? $GLOBALS['yaml_cf_test_post_types'][$post_id] : 'attachment';
		return (object) ['ID' => $post_id, 'post_type' => $post_type];
	}
	// Alias to WordPress core function name for mocking
	function get_post($post = null) {
		return yaml_cf_test_get_post($post);
	}
}

if (!function_exists('__')) {
	function yaml_cf_test_translate($text, $domain = 'default') {
		return $text;
	}
	// Alias to WordPress core function name for mocking
	function __($text, $domain = 'default') {
		return yaml_cf_test_translate($text, $domain);
	}
}

/**
 * Options, stored in memory for the duration of a test
 *
 * Reset with yaml_cf_test_reset_options() in setUp().
 */
$GLOBALS['yaml_cf_test_options'] = [];

if (!function_exists('yaml_cf_test_reset_options')) {
	function yaml_cf_test_reset_options($options = []) {
		$GLOBALS['yaml_cf_test_options'] = $options;
	}
}

if (!function_exists('get_option')) {
	function yaml_cf_test_get_option($option, $default_value = false) {
		return array_key_exists($option, $GLOBALS['yaml_cf_test_options'])
			? $GLOBALS['yaml_cf_test_options'][$option]
			: $default_value;
	}
	function get_option($option, $default_value = false) {
		return yaml_cf_test_get_option($option, $default_value);
	}
}

if (!function_exists('update_option')) {
	function yaml_cf_test_update_option($option, $value) {
		$GLOBALS['yaml_cf_test_options'][$option] = $value;
		return true;
	}
	function update_option($option, $value, $autoload = null) {
		return yaml_cf_test_update_option($option, $value);
	}
}

if (!function_exists('_n')) {
	function yaml_cf_test_n($single, $plural, $number) {
		return (int) $number === 1 ? $single : $plural;
	}
	function _n($single, $plural, $number, $domain = null) {
		return yaml_cf_test_n($single, $plural, $number);
	}
}

if (!function_exists('current_time')) {
	function yaml_cf_test_current_time($type = 'mysql') {
		return $type === 'timestamp' ? time() : gmdate('Y-m-d H:i:s');
	}
	function current_time($type = 'mysql', $gmt = 0) {
		return yaml_cf_test_current_time($type);
	}
}

if (!function_exists('get_site_url')) {
	function yaml_cf_test_get_site_url() {
		return 'https://example.test';
	}
	function get_site_url($blog_id = null, $path = '', $scheme = null) {
		return yaml_cf_test_get_site_url();
	}
}

if (!function_exists('absint')) {
	function yaml_cf_test_absint($maybeint) {
		return abs((int) $maybeint);
	}
	function absint($maybeint) {
		return yaml_cf_test_absint($maybeint);
	}
}

if (!function_exists('sanitize_file_name')) {
	function yaml_cf_test_sanitize_file_name($filename) {
		return preg_replace('/[^a-zA-Z0-9._-]/', '', (string) $filename);
	}
	function sanitize_file_name($filename) {
		return yaml_cf_test_sanitize_file_name($filename);
	}
}

if (!function_exists('get_post_types')) {
	function get_post_types($args = [], $output = 'names', $operator = 'and') {
		return isset($GLOBALS['yaml_cf_test_post_types'])
			? $GLOBALS['yaml_cf_test_post_types']
			: ['post' => 'post', 'page' => 'page'];
	}
}

if (!function_exists('sanitize_title')) {
	function yaml_cf_test_sanitize_title($title) {
		$title = strtolower(remove_accents((string) $title));
		$title = preg_replace('/[^a-z0-9_\-]/', '-', $title);
		return trim(preg_replace('/-+/', '-', $title), '-');
	}
	// Alias to WordPress core function name for mocking
	function sanitize_title($title) {
		return yaml_cf_test_sanitize_title($title);
	}
}

if (!function_exists('remove_accents')) {
	function remove_accents($string) {
		return $string;
	}
}

if (!function_exists('get_posts')) {
	function yaml_cf_test_get_posts($args = []) {
		return isset($GLOBALS['yaml_cf_test_posts']) ? $GLOBALS['yaml_cf_test_posts'] : [];
	}
	function get_posts($args = null) {
		return yaml_cf_test_get_posts($args);
	}
}

/**
 * Translation and form helpers used by the field renderers
 */
if (!function_exists('esc_html__')) {
	function yaml_cf_test_esc_html__($text, $domain = 'default') {
		return esc_html($text);
	}
	// Alias to WordPress core function name for mocking
	function esc_html__($text, $domain = 'default') {
		return yaml_cf_test_esc_html__($text, $domain);
	}
}

if (!function_exists('esc_attr__')) {
	function yaml_cf_test_esc_attr__($text, $domain = 'default') {
		return esc_attr($text);
	}
	// Alias to WordPress core function name for mocking
	function esc_attr__($text, $domain = 'default') {
		return yaml_cf_test_esc_attr__($text, $domain);
	}
}

if (!function_exists('esc_js')) {
	function yaml_cf_test_esc_js($text) {
		return str_replace(["\\", "'"], ["\\\\", "\\'"], (string) $text);
	}
	// Alias to WordPress core function name for mocking
	function esc_js($text) {
		return yaml_cf_test_esc_js($text);
	}
}

if (!function_exists('sanitize_html_class')) {
	function yaml_cf_test_sanitize_html_class($classname, $fallback = '') {
		$sanitized = preg_replace('|%[a-fA-F0-9][a-fA-F0-9]|', '', (string) $classname);
		$sanitized = preg_replace('/[^A-Za-z0-9_-]/', '', $sanitized);
		return ($sanitized === '' && $fallback) ? yaml_cf_test_sanitize_html_class($fallback) : $sanitized;
	}
	// Alias to WordPress core function name for mocking
	function sanitize_html_class($classname, $fallback = '') {
		return yaml_cf_test_sanitize_html_class($classname, $fallback);
	}
}

if (!function_exists('checked')) {
	/**
	 * Same comparison as core's __checked_selected_helper()
	 */
	function yaml_cf_test_checked_helper($helper, $current, $display, $type) {
		$result = ((string) $helper === (string) $current) ? " $type='$type'" : '';
		if ($display) {
			echo $result;
		}
		return $result;
	}
	// Alias to WordPress core function name for mocking
	function checked($checked, $current = true, $display = true) {
		return yaml_cf_test_checked_helper($checked, $current, $display, 'checked');
	}
}

if (!function_exists('disabled')) {
	// Alias to WordPress core function name for mocking
	function disabled($disabled, $current = true, $display = true) {
		return yaml_cf_test_checked_helper($disabled, $current, $display, 'disabled');
	}
}
