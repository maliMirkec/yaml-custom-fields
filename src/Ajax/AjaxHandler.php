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
   * Nonce action for what a writer may do (localized as yamlCF.nonce)
   */
  const NONCE_ACTION = 'yaml_cf_nonce';

  /**
   * Nonce action for what only an administrator may do (yamlCF.adminNonce)
   *
   * Kept apart from the one above so that the token sitting in a writer's post
   * editor is not also the token that saves a schema or imports a bundle. Only
   * a user who can manage options is given this one, so on every other screen
   * it is an empty string and an administrator-only action fails twice over.
   */
  const ADMIN_NONCE_ACTION = 'yaml_cf_admin_nonce';

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
    if ($cap === 'manage_options') {
      check_ajax_referer(self::ADMIN_NONCE_ACTION, 'admin_nonce');
    } else {
      check_ajax_referer(self::NONCE_ACTION, 'nonce');
    }

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
