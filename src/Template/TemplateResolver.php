<?php

/**
 * Template Resolver
 * Resolve which template file a post uses
 */

namespace YamlCF\Template;


// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
class TemplateResolver {
  /**
   * Whether a template file exists in the theme, keyed by file name
   *
   * Without this, resolving the template hit the filesystem once per call, and
   * every yaml_cf_get_field() call resolves the template: ten fields across a
   * fifty-post archive meant five hundred stat calls. Theme files do not appear
   * or vanish part-way through a request, so one lookup each is enough.
   *
   * @var array<string, bool>
   */
  private $template_exists = [];

  /**
   * Get template file for a post
   *
   * @param \WP_Post $post Post object
   * @return string Template file name
   */
  public function getTemplateForPost($post) {
    $post_type = $post->post_type;

    // For pages, check if a custom page template is assigned
    if ($post_type === 'page') {
      $template = get_post_meta($post->ID, '_wp_page_template', true);
      if (!empty($template) && $template !== 'default') {
        return $template;
      }
      return 'page.php';
    }

    // For posts and custom post types, use the single template
    // Check for specific template: single-{post_type}.php
    $specific_template = "single-{$post_type}.php";

    if ($this->themeHasTemplate($specific_template)) {
      return $specific_template;
    }

    // Fall back to generic single.php for regular posts
    if ($post_type === 'post') {
      return 'single.php';
    }

    // For custom post types without a specific template, return the expected name
    // This allows the user to create the template and schema
    return $specific_template;
  }

  /**
   * Whether the theme has a template file, remembered for this request
   *
   * @param string $template Template file name
   * @return bool
   */
  private function themeHasTemplate($template) {
    if (!array_key_exists($template, $this->template_exists)) {
      $this->template_exists[$template] = file_exists(get_template_directory() . '/' . $template);
    }

    return $this->template_exists[$template];
  }

  /**
   * Forget which template files exist
   *
   * Only needed when the theme changes inside one request, which is what
   * switching themes and the tests do.
   *
   * @return void
   */
  public function flushTemplateExistsCache() {
    $this->template_exists = [];
  }

  /**
   * Resolve template file for a post (alias for getTemplateForPost)
   *
   * @param \WP_Post $post Post object
   * @return string Template file name
   */
  public function resolveForPost($post) {
    return $this->getTemplateForPost($post);
  }
}
