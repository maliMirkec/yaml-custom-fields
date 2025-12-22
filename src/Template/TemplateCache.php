<?php

/**
 * Template Cache
 * Cache template lists for performance
 */

namespace YamlCF\Template;


// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
class TemplateCache {
  private $scanner;

  public function __construct($scanner) {
    $this->scanner = $scanner;
  }

  /**
   * Get cache key for current theme
   *
   * @return string Cache key
   */
  private function getCacheKey() {
    return 'yaml_cf_templates_' . get_stylesheet();
  }

  /**
   * Get cached template data
   *
   * @return array|false Template data or false if not cached
   */
  public function get() {
    return get_transient($this->getCacheKey());
  }

  /**
   * Set cached template data
   *
   * @param array $data Template data
   * @param int $expiration Expiration time in seconds (default: 1 hour)
   * @return bool Success
   */
  public function set($data, $expiration = HOUR_IN_SECONDS) {
    return set_transient($this->getCacheKey(), $data, $expiration);
  }

  /**
   * Clear template cache
   *
   * @return bool Success
   */
  public function clear() {
    return delete_transient($this->getCacheKey());
  }

  /**
   * Get theme templates (cached)
   *
   * @return array Array with 'templates' and 'partials' keys
   */
  public function getThemeTemplates() {
    // Check cache first
    $cached = $this->get();
    if ($cached !== false) {
      return $cached;
    }

    // Scan templates
    $templates = $this->scanner->scan();

    // Cache the results
    $this->set($templates);

    return $templates;
  }
}
