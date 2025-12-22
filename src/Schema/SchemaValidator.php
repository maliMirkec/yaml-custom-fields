<?php

/**
 * Schema Validator
 * Validate YAML schema structure and field definitions
 */

namespace YamlCF\Schema;


// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
use YamlCF\Vendor\Symfony\Component\Yaml\Yaml;
use YamlCF\Vendor\Symfony\Component\Yaml\Exception\ParseException;

class SchemaValidator {
  private $parser;
  private $normalizer;

  public function __construct() {
    $this->parser = new SchemaParser();
    $this->normalizer = new FieldNormalizer();
  }

  /**
   * Validate YAML schema
   *
   * @param string $yaml YAML schema string
   * @param string|null $template Template name (for info field validation)
   * @return array Array with 'valid' => bool, 'message' => string
   */
  public function validate($yaml, $template = null) {
    try {
      $parsed = Yaml::parse($yaml);

      // Check if parsed successfully
      if ($parsed === null) {
        return [
          'valid' => false,
          'message' => 'Empty or invalid YAML content'
        ];
      }

      // Check for required 'fields' key
      if (!isset($parsed['fields']) || !is_array($parsed['fields'])) {
        return [
          'valid' => false,
          'message' => 'Schema must contain a "fields" array'
        ];
      }

      // Normalize info field shorthand for validation
      $fields = $this->normalizer->normalizeInfoFields($parsed['fields']);

      // Basic validation of field structure
      foreach ($fields as $index => $field) {
        if (!is_array($field)) {
          return [
            'valid' => false,
            'message' => 'Field at index ' . $index . ' is not a valid array'
          ];
        }

        if (!isset($field['name'])) {
          return [
            'valid' => false,
            'message' => 'Field at index ' . $index . ' is missing required "name" property'
          ];
        }

        if (!isset($field['type'])) {
          return [
            'valid' => false,
            'message' => 'Field "' . $field['name'] . '" is missing required "type" property'
          ];
        }

        // Validate info field template restrictions
        if ($field['type'] === 'info' && $template !== null) {
          if (!$this->isTemplateAllowedForInfoField($template)) {
            return [
              'valid' => false,
              'message' => 'Info fields are not allowed for template partials and archives. Current template: ' . $template
            ];
          }
        }
      }

      return [
        'valid' => true,
        'message' => 'Schema is valid'
      ];
    } catch (ParseException $e) {
      return [
        'valid' => false,
        'message' => 'YAML syntax error: ' . $e->getMessage()
      ];
    }
  }

  /**
   * Check if a template is allowed to use info fields
   * Info fields are NOT allowed for partials and archives (global data templates)
   *
   * @param string $template The template name
   * @return bool True if allowed, false otherwise
   */
  private function isTemplateAllowedForInfoField($template) {
    // Get just the basename if path is provided
    $basename = basename($template);

    // Partial/Archive patterns that should NOT have info fields
    // These match the partial_patterns from get_theme_templates()
    $disallowed_patterns = [
      // Traditional partials
      'header.php',
      'footer.php',
      'sidebar.php',
      'header-*.php',
      'footer-*.php',
      'sidebar-*.php',
      'content.php',
      'content-*.php',
      'comments.php',
      'searchform.php',
      // Archive/listing templates (global data)
      'index.php',
      'front-page.php',
      'home.php',
      'archive.php',
      'archive-*.php',
      'category.php',
      'category-*.php',
      'tag.php',
      'tag-*.php',
      'taxonomy.php',
      'taxonomy-*.php',
      'author.php',
      'author-*.php',
      'date.php',
      'search.php',
      '404.php',
    ];

    // Check if template matches any disallowed pattern
    foreach ($disallowed_patterns as $pattern) {
      if ($pattern === $basename || fnmatch($pattern, $basename)) {
        return false; // This is a partial/archive, not allowed
      }
    }

    // All other templates are allowed (page.php, single.php, custom page templates, etc.)
    return true;
  }
}
