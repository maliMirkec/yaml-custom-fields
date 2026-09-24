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
   * Whether the active theme is a block theme, worked out once per request
   *
   * @var bool|null
   */
  private $is_block_theme = null;

  /**
   * Get template file for a post
   *
   * @param \WP_Post $post Post object
   * @return string Template file name
   */
  public function getTemplateForPost($post) {
    $post_type = $post->post_type;

    // A block theme's templates are .html files addressed by slug, so they are
    // named without the .php a classic theme's templates carry.
    if ($this->themeIsBlockTheme()) {
      return $this->blockTemplateForPost($post);
    }

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
   * Which block template a post uses
   *
   * Mirrors the classic rules one for one, with slugs in place of file names:
   * a template chosen in the editor wins, then single-{post type}, then the
   * general one for the kind of content.
   *
   * @param \WP_Post $post Post object
   * @return string Block template slug
   */
  private function blockTemplateForPost($post) {
    $post_type = $post->post_type;

    // Choosing a template in the editor stores its slug here, for pages and for
    // any other post type that offers the choice.
    $assigned = get_post_meta($post->ID, '_wp_page_template', true);
    if (!empty($assigned) && $assigned !== 'default') {
      // A classic custom template can still be assigned on a theme that has both
      return $assigned;
    }

    if ($post_type === 'page') {
      return 'page';
    }

    $specific_template = "single-{$post_type}";
    if ($this->themeHasBlockTemplate($specific_template)) {
      return $specific_template;
    }

    if ($post_type === 'post') {
      return 'single';
    }

    // No template of its own yet: name the one to create
    return $specific_template;
  }

  /**
   * Whether the theme ships a block template with this slug
   *
   * Only the theme's own files are checked, which is cheap and needs no query.
   * A template made in the Site Editor and never saved to a file is assigned to
   * a post through post meta, which blockTemplateForPost() reads first.
   *
   * @param string $slug Block template slug
   * @return bool
   */
  private function themeHasBlockTemplate($slug) {
    return $this->themeHasTemplate('templates/' . $slug . '.html')
      || $this->themeHasTemplate('block-templates/' . $slug . '.html');
  }

  /**
   * Whether the active theme builds its pages out of blocks
   *
   * @return bool
   */
  private function themeIsBlockTheme() {
    if ($this->is_block_theme === null) {
      if (function_exists('wp_is_block_theme')) {
        $this->is_block_theme = (bool) wp_is_block_theme();
      } else {
        $theme = wp_get_theme();
        $this->is_block_theme = method_exists($theme, 'is_block_theme') && $theme->is_block_theme();
      }
    }

    return $this->is_block_theme;
  }

  /**
   * Whether the theme has a template file, remembered for this request
   *
   * @param string $template Template file name
   * @return bool
   */
  private function themeHasTemplate($template) {
    if (!array_key_exists($template, $this->template_exists)) {
      // The active theme first, then the parent it builds on: a child theme's
      // own template overrides the parent's, and checking only the parent made
      // a child theme's templates invisible.
      $this->template_exists[$template] = file_exists(get_stylesheet_directory() . '/' . $template)
        || file_exists(get_template_directory() . '/' . $template);
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
    $this->is_block_theme = null;
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
