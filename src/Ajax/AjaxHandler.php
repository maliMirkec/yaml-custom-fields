<?php

/**
 * Base AJAX Handler
 * Abstract base class for all AJAX handlers
 */

namespace YamlCF\Ajax;


// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
abstract class AjaxHandler {
  /**
   * Nonce action shared by the admin scripts (localized as yamlCF.nonce)
   */
  const NONCE_ACTION = 'yaml_cf_nonce';

  /**
   * Send success response (ends the request)
   *
   * @param mixed $data Response data
   */
  protected function sendSuccess($data = null) {
    wp_send_json_success($data);
  }

  /**
   * Send error response (ends the request)
   *
   * @param string $message Error message
   */
  protected function sendError($message) {
    wp_send_json_error($message);
  }

  /**
   * Check user capability (ends the request with an error if missing)
   *
   * @param string $cap Capability to check
   */
  protected function checkCapability($cap = 'manage_options') {
    if (!current_user_can($cap)) {
      $this->sendError(__('This action needs administrator permissions.', 'yaml-custom-fields'));
    }
  }

  /**
   * Verify the request nonce and the user's capability
   *
   * @param string $cap Capability to check
   */
  protected function verifyRequest($cap = 'manage_options') {
    check_ajax_referer(self::NONCE_ACTION, 'nonce');
    $this->checkCapability($cap);
  }

  /**
   * Get a sanitized text value from POST
   *
   * @param string $key POST key
   * @param string $default Default value
   * @return string Sanitized value
   */
  protected function postText($key, $default = '') {
    // Nonce is verified in verifyRequest() before handlers read input
    return isset($_POST[$key]) && is_string($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : $default;
  }

  /**
   * Decode a JSON string from POST
   *
   * The raw string is decoded first; callers sanitize every value against a schema
   * (sanitize_textarea_field() on the JSON string would strip HTML from rich-text values).
   *
   * @param string $key POST key
   * @return array|null Decoded array, or null if missing or invalid
   */
  protected function postJson($key) {
    if (!isset($_POST[$key]) || !is_string($_POST[$key])) {
      return null;
    }
    $decoded = json_decode(wp_unslash($_POST[$key]), true);
    return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : null;
  }

  /**
   * Handle the AJAX request
   * Must be implemented by child classes
   */
  abstract public function handle();
}
