<?php
/**
 * Template Resolver
 * Resolve which template file a post uses
 */

namespace YamlCF\Template;

class TemplateResolver {
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
    $theme_dir = get_template_directory();

    if (file_exists($theme_dir . '/' . $specific_template)) {
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
   * Resolve template file for a post (alias for getTemplateForPost)
   *
   * @param \WP_Post $post Post object
   * @return string Template file name
   */
  public function resolveForPost($post) {
    return $this->getTemplateForPost($post);
  }
}
