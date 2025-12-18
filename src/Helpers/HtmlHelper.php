<?php
/**
 * HTML Helper
 * Build and output HTML attributes safely
 */

namespace YamlCF\Helpers;

class HtmlHelper {
  /**
   * Build HTML attributes string from array
   * Properly escapes all values and returns PHPCS-compliant output
   *
   * @param array $attrs Attributes array
   * @return string Escaped HTML attributes string
   */
  public static function buildAttrs($attrs) {
    if (empty($attrs)) {
      return '';
    }

    $parts = [];
    foreach ($attrs as $key => $value) {
      if ($value === false || $value === null || $value === '') {
        continue;
      }
      if ($value === true) {
        $parts[] = esc_attr($key);
      } else {
        $parts[] = esc_attr($key) . '="' . esc_attr($value) . '"';
      }
    }

    return !empty($parts) ? ' ' . implode(' ', $parts) : '';
  }

  /**
   * Output HTML attributes (escaping already done by buildAttrs)
   * This wrapper makes it clear to PHPCS that output is safe
   *
   * @param array $attrs Attributes array
   * @return void
   */
  public static function outputAttrs($attrs) {
    // Attributes are already escaped by buildAttrs using esc_attr()
    // This wrapper makes WPCS happy by making the escaping chain explicit
    echo wp_kses_post(self::buildAttrs($attrs));
  }
}
