<?php
/**
 * Base AJAX Handler
 * Common functionality for AJAX endpoints
 */

namespace YamlCF\Ajax;

abstract class AjaxHandler {
  /**
   * Send JSON success response
   */
  protected function sendSuccess($data = null) {
    wp_send_json_success($data);
  }

  /**
   * Send JSON error response
   */
  protected function sendError($message) {
    wp_send_json_error($message);
  }

  /**
   * Check user capability
   */
  protected function checkCapability($cap = 'manage_options') {
    if (!current_user_can($cap)) {
      $this->sendError('Permission denied');
    }
  }

  /**
   * Handle AJAX request
   * Must be implemented by subclasses
   */
  abstract public function handle();
}
