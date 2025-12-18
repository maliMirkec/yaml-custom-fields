<?php
/**
 * Notification Manager
 * Manages admin notifications using transients instead of URL parameters
 */

namespace YamlCF\Core;

class NotificationManager {
  /**
   * Get transient key for current user
   *
   * @return string Transient key
   */
  private function getTransientKey() {
    return 'yaml_cf_notification_' . get_current_user_id();
  }

  /**
   * Add a notification message
   *
   * @param string $message Notification message
   * @param string $type Notification type (success, error, warning, info)
   * @return bool Success
   */
  public function add($message, $type = 'success') {
    $notification = [
      'message' => $message,
      'type' => $type,
      'timestamp' => time()
    ];

    return set_transient($this->getTransientKey(), $notification, 60); // 60 seconds expiration
  }

  /**
   * Get notification and delete it (one-time display)
   *
   * @return array|false Notification array or false if none
   */
  public function get() {
    $notification = get_transient($this->getTransientKey());

    if ($notification !== false) {
      // Delete immediately after reading
      delete_transient($this->getTransientKey());
    }

    return $notification;
  }

  /**
   * Check if there's a pending notification
   *
   * @return bool True if notification exists
   */
  public function has() {
    return get_transient($this->getTransientKey()) !== false;
  }

  /**
   * Clear any pending notifications
   *
   * @return bool Success
   */
  public function clear() {
    return delete_transient($this->getTransientKey());
  }

  /**
   * Add a success notification
   *
   * @param string $message Message
   * @return bool Success
   */
  public function success($message) {
    return $this->add($message, 'success');
  }

  /**
   * Add an error notification
   *
   * @param string $message Message
   * @return bool Success
   */
  public function error($message) {
    return $this->add($message, 'error');
  }

  /**
   * Add a warning notification
   *
   * @param string $message Message
   * @return bool Success
   */
  public function warning($message) {
    return $this->add($message, 'warning');
  }

  /**
   * Add an info notification
   *
   * @param string $message Message
   * @return bool Success
   */
  public function info($message) {
    return $this->add($message, 'info');
  }
}
