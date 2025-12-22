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

  /**
   * Parse and return error message if parsing fails
   *
   * @param string $yaml YAML string
   * @return array Array with 'success' => bool, 'data' => array|null, 'error' => string|null
   */
  public function parseWithError($yaml) {
    try {
      $parsed = Yaml::parse($yaml);
      return [
        'success' => true,
        'data' => $parsed,
        'error' => null
      ];
    } catch (ParseException $e) {
      return [
        'success' => false,
        'data' => null,
        'error' => $e->getMessage()
      ];
    }
  }
}
