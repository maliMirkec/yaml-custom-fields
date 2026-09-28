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
   * Reads $_GET, which WordPress slashes (wp_magic_quotes), so values are unslashed first.
   * Nonce verification is not required here as this only reads GET parameters.
   * Methods that perform actions must verify nonces separately.
   *
   * @param string $key Parameter key
   * @param string $default Default value
   * @return string Sanitized value
   */
  public static function getParam($key, $default = '') {
    if (!isset($_GET[$key]) || !is_scalar($_GET[$key])) {
      return $default;
    }
    return sanitize_text_field(wp_unslash((string) $_GET[$key]));
  }

  /**
   * Get integer GET parameter
   *
   * @param string $key Parameter key
   * @param int $default Default value
   * @return int Validated integer
   */
  public static function getParamInt($key, $default = 0) {
    if (!isset($_GET[$key]) || !is_scalar($_GET[$key])) {
      return $default;
    }
    $value = filter_var(wp_unslash((string) $_GET[$key]), FILTER_VALIDATE_INT);
    return $value === false ? $default : $value;
  }

  /**
   * Get sanitized key from GET parameter
   *
   * @param string $key Parameter key
   * @param string $default Default value
   * @return string Sanitized key
   */
  public static function getParamKey($key, $default = '') {
    if (!isset($_GET[$key]) || !is_scalar($_GET[$key])) {
      return $default;
    }
    return sanitize_key(wp_unslash((string) $_GET[$key]));
  }

  /**
   * Stop a form submission whose nonce has expired, and say why
   *
   * These forms post back to the screen they are on. A check that only asked
   * "is the nonce valid?" treated an expired one like no submission at all: the
   * screen drew itself again from what was stored, and whatever had been typed
   * into it was gone without a word.
   *
   * @return void Never returns
   */
  public static function stopStaleForm() {
    wp_die(
      esc_html__('Nothing was saved: this page was open for too long. Go back, copy anything you typed, reload the page and save again.', 'yaml-custom-fields'),
      esc_html__('Not saved', 'yaml-custom-fields'),
      ['response' => 403, 'back_link' => true]
    );
  }

  /**
   * Get unslashed POST data for schema-aware sanitization
   * Use this when data will be sanitized by a custom function (e.g., DataSanitizer)
   *
   * Note: Caller must verify nonce before using this method
   *
   * @param string $key POST key to retrieve
   * @param mixed $default Default value if key doesn't exist
   * @return mixed Unslashed POST data (not sanitized)
   */
  public static function postRaw($key, $default = '') {
    if (!isset($_POST[$key])) {
      return $default;
    }
    // $_POST is slashed by WordPress (wp_magic_quotes); unslash to get the submitted value
    return wp_unslash($_POST[$key]);
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
