<?php

/**
 * Notification Manager
 * One place for user feedback after a request (save, import, errors)
 */

namespace YamlCF\Core;


// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Queues notices for the current user and shows them as standard WordPress admin notices
 * on the next page load (or later in the same request, before admin_notices runs).
 *
 * Usage before a redirect:
 *   $notifications->success(__('Schema saved.', 'yaml-custom-fields'));
 *   $notifications->error($message, ['title' => __('Schema not saved', 'yaml-custom-fields')]);
 *
 * Instant feedback for AJAX actions is shown by YamlCF.showMessage() in admin.js, which uses the same visual style.
 */
class NotificationManager {
  const TYPES = ['success', 'error', 'warning', 'info'];

  /**
   * Transient key for the current user's queue
   *
   * @return string
   */
  private function getTransientKey() {
    return 'yaml_cf_notices_' . get_current_user_id();
  }

  /**
   * Queue a notice
   *
   * @param string $message Message text
   * @param string $type success|error|warning|info
   * @param array $args {
   *   @type string   $title   Optional bold lead-in
   *   @type string[] $details Optional list of detail lines
   * }
   * @return bool
   */
  public function add($message, $type = 'success', $args = []) {
    $notice = [
      'message' => (string) $message,
      'type' => in_array($type, self::TYPES, true) ? $type : 'info',
      'title' => isset($args['title']) ? (string) $args['title'] : '',
      'details' => isset($args['details']) ? array_values(array_map('strval', (array) $args['details'])) : [],
    ];

    $notices = $this->peek();
    if (in_array($notice, $notices, true)) {
      return true;
    }
    $notices[] = $notice;

    return set_transient($this->getTransientKey(), $notices, 5 * MINUTE_IN_SECONDS);
  }

  /**
   * Get queued notices without removing them
   *
   * @return array[] Notices
   */
  public function peek() {
    $notices = get_transient($this->getTransientKey());
    return is_array($notices) ? array_values(array_filter($notices, 'is_array')) : [];
  }

  /**
   * Get queued notices and clear the queue
   *
   * @return array[] Notices
   */
  public function pull() {
    $notices = $this->peek();
    $this->clear();
    return $notices;
  }

  /**
   * Backward compatible alias of pull() (returns the first notice or false)
   *
   * @deprecated Use pull()
   * @return array|false
   */
  public function get() {
    $notices = $this->pull();
    return empty($notices) ? false : $notices[0];
  }

  public function has() {
    return !empty($this->peek());
  }

  public function clear() {
    return delete_transient($this->getTransientKey());
  }

  public function success($message, $args = []) {
    return $this->add($message, 'success', $args);
  }

  public function error($message, $args = []) {
    return $this->add($message, 'error', $args);
  }

  public function warning($message, $args = []) {
    return $this->add($message, 'warning', $args);
  }

  public function info($message, $args = []) {
    return $this->add($message, 'info', $args);
  }

  /**
   * Print queued notices (hooked to admin_notices)
   *
   * WordPress moves notices below the element with class "wp-header-end", which plugin
   * pages place right after their branded header.
   */
  public function renderAdminNotices() {
    foreach ($this->pull() as $notice) {
      $this->renderNotice($notice);
    }
  }

  /**
   * Print one notice
   *
   * @param array $notice Notice from the queue
   */
  public function renderNotice($notice) {
    $type = in_array($notice['type'], self::TYPES, true) ? $notice['type'] : 'info';
    // Errors interrupt screen readers; everything else is announced politely
    $role = $type === 'error' ? 'alert' : 'status';

    echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible yaml-cf-notice" role="' . esc_attr($role) . '">';
    echo '<p>';
    if ($notice['title'] !== '') {
      echo '<strong>' . esc_html($notice['title']) . '</strong> ';
    }
    echo esc_html($notice['message']);
    echo '</p>';
    if (!empty($notice['details'])) {
      echo '<ul class="yaml-cf-notice-details">';
      foreach ($notice['details'] as $detail) {
        echo '<li>' . esc_html($detail) . '</li>';
      }
      echo '</ul>';
    }
    echo '</div>';
  }
}
