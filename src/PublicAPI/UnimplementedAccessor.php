<?php

/**
 * Guard for accessor methods that are not finished yet
 */

namespace YamlCF\PublicAPI;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * The accessors in this namespace are registered in the service container but
 * nothing resolves them: the template API is still the yaml_cf_*() / ycf_*()
 * functions in yaml-custom-fields.php.
 *
 * Their unfinished methods used to `return null`, `return []` or `return false`,
 * which is indistinguishable from a field that simply has no value. A theme wired
 * to one of these would have rendered blank pages with nothing in the log. They
 * say so instead.
 */
trait UnimplementedAccessor {
  /**
   * Refuse to answer, loudly in development and in the log otherwise
   *
   * @param string $method Method that was called
   * @throws \LogicException When WP_DEBUG is on
   * @return void
   */
  protected function notImplemented($method) {
    $message = sprintf(
      '%1$s::%2$s() is not implemented yet. The template API lives in the yaml_cf_*() functions in yaml-custom-fields.php; do not resolve this accessor from the service container until it is finished.',
      static::class,
      $method
    );

    if (defined('WP_DEBUG') && WP_DEBUG) {
      // Escaped because an uncaught exception message is rendered to the page
      throw new \LogicException(esc_html($message));
    }

    do_action('yaml_cf_log_error', $message);
  }
}
