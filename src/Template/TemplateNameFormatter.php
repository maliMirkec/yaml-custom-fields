<?php

/**
 * Template Name Formatter
 * Format template file names for display
 */

namespace YamlCF\Template;


// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
class TemplateNameFormatter {
  /**
   * Format template file name for display
   *
   * @param string $filename Template file name
   * @return string Formatted name
   */
  public function format($filename) {
    $name = str_replace(['-', '_', '.php'], [' ', ' ', ''], $filename);
    return ucwords($name);
  }
}
