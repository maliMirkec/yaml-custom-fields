<?php

/**
 * Request Helper
 * Safely handle GET and POST parameters
 */

namespace YamlCF\Helpers;


// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
class RequestHelper {
  /**
   * Get sanitized GET parameter (string)
   *
   * Uses PHP's filter_input() for safe parameter access without phpcs suppressions.
   * Nonce verification is not required here as this only reads GET parameters.
   * Methods that perform actions must verify nonces separately.
   *
   * @param string $key Parameter key
   * @param string $default Default value
   * @return string Sanitized value
   */
  public static function getParam($key, $default = '') {
    $value = filter_input(INPUT_GET, $key, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    if ($value === null || $value === false) {
      return $default;
    }
    return sanitize_text_field($value);
  }

  /**
   * Get integer GET parameter
   *
   * @param string $key Parameter key
   * @param int $default Default value
   * @return int Validated integer
   */
  public static function getParamInt($key, $default = 0) {
    $value = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT);
    if ($value === null || $value === false) {
      return $default;
    }
    return $value;
  }

  /**
   * Get sanitized key from GET parameter
   *
   * @param string $key Parameter key
   * @param string $default Default value
   * @return string Sanitized key
   */
  public static function getParamKey($key, $default = '') {
    $value = filter_input(INPUT_GET, $key, FILTER_CALLBACK, [
      'options' => 'sanitize_key'
    ]);
    if ($value === null || $value === false) {
      return $default;
    }
    return $value;
  }

  /**
   * Get POST data with basic sanitization for further processing
   * Use this when data will be sanitized by a custom function (e.g., parse_yaml_schema, sanitize_field_data)
   *
   * Note: Caller must verify nonce before using this method
   *
   * @param string $key POST key to retrieve
   * @param mixed $default Default value if key doesn't exist
   * @return mixed Sanitized POST data
   */
  public static function postRaw($key, $default = '') {
    // Check if POST data exists and is set
    // Using filter_input for proper superglobal access without PHPCS warnings
    $value = filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY | FILTER_NULL_ON_FAILURE);

    if ($value === null) {
      // Try as non-array
      $value = filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW);
    }

    if ($value === null || $value === false) {
      return $default;
    }

    // Apply wp_unslash to handle magic quotes
    return wp_unslash($value);
  }

  /**
   * Get sanitized POST data
   *
   * @param string $key POST key to retrieve
   * @param mixed $default Default value if key doesn't exist
   * @param callable $callback Sanitization callback
   * @return mixed Sanitized POST data
   */
  public static function postSanitized($key, $default = '', $callback = 'sanitize_text_field') {
    $value = self::postRaw($key, $default);
    if (is_array($value)) {
      return map_deep($value, $callback);
    }
    return call_user_func($callback, $value);
  }
}
