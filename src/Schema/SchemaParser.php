<?php

/**
 * Schema Parser
 * Parse YAML schema strings using Symfony YAML component
 */

namespace YamlCF\Schema;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
use YamlCF\Vendor\Symfony\Component\Yaml\Yaml;
use YamlCF\Vendor\Symfony\Component\Yaml\Exception\ParseException;

class SchemaParser {
  /**
   * Clean schema YAML typed into an editor or read from an import file
   *
   * Not sanitize_textarea_field(): that is for plain text, and YAML is not
   * plain text. It turned `options: ["<10 people", "10-50"]` into entities up
   * to the end of the line, which broke the schema, dropped every %xx out of a
   * link in an info box, and stripped markup from default values.
   *
   * A schema is never printed as it stands. The editor shows it through
   * esc_textarea(), labels and choices go out through esc_html()/esc_attr(),
   * and info boxes through wp_kses_post(). So what is taken out here is only
   * what no YAML needs: invalid UTF-8 and control characters other than tab
   * and newline. Its structure is checked by SchemaValidator.
   *
   * @param mixed $yaml Submitted YAML
   * @return string Cleaned YAML, '' for anything that is not a string
   */
  public static function sanitizeSource($yaml) {
    if (!is_string($yaml)) {
      return '';
    }

    $yaml = wp_check_invalid_utf8($yaml, true);
    $yaml = str_replace(["\r\n", "\r"], "\n", $yaml);
    $yaml = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $yaml);

    // Blank lines around the schema, and spaces at the end, carry no meaning.
    // Leading spaces on the first line do, so they are left alone.
    return rtrim(ltrim((string) $yaml, "\n"));
  }

  /**
   * Parse YAML schema string to array
   *
   * @param string $yaml YAML string
   * @return array|null Parsed schema or null on error
   */
  public function parse($yaml) {
    try {
      return Yaml::parse($yaml);
    } catch (ParseException $e) {
      // Log error for debugging but fail gracefully
      do_action('yaml_cf_log_debug', 'YAML parsing error - ' . $e->getMessage());
      return null;
    }
  }
}
