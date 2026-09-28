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

    // A template chosen in the editor. Pages have always been resolved by it.
    $assigned = get_post_meta($post->ID, '_wp_page_template', true);
    $has_assigned = !empty($assigned) && $assigned !== 'default';

    if ($post_type === 'page') {
      if ($has_assigned) {
        return $assigned;
      }

      // page-{slug}.php and page-{id}.php are listed as templates a schema can
      // be written for, so a page they render has to be able to reach it.
      $specific = $this->firstTemplateWithSchema([
        'page-' . $this->decodedSlug($post) . '.php',
        'page-' . $post->ID . '.php',
      ]);

      return $specific !== null ? $specific : $this->generalTemplate('page.php');
    }

    // Any other post type can offer templates too ("Template Post Type:"). Only
    // one with a schema of its own is followed, so a post that used to show
    // single.php's fields keeps showing them.
    if ($has_assigned && $this->templateHasSchema($assigned)) {
      return $assigned;
    }

    if ($post_type === 'attachment') {
      $specific = $this->firstTemplateWithSchema(['attachment.php']);
      if ($specific !== null) {
        return $specific;
      }
    }

    $specific = $this->firstTemplateWithSchema([
      "single-{$post_type}-" . $this->decodedSlug($post) . '.php',
    ]);
    if ($specific !== null) {
      return $specific;
    }

    // For posts and custom post types, use the single template
    // Check for specific template: single-{post_type}.php
    $specific_template = "single-{$post_type}.php";

    if ($this->themeHasTemplate($specific_template)) {
      return $specific_template;
    }

    // Fall back to generic single.php for regular posts
    if ($post_type === 'post') {
      return $this->generalTemplate('single.php');
    }

    // For custom post types without a specific template, return the expected name
    // This allows the user to create the template and schema
    return $specific_template;
  }

  /**
   * page.php or single.php, or singular.php in a theme that has only that
   *
   * WordPress renders singular.php for a page or a post when the theme has no
   * page.php or single.php, and themes such as Twenty Twenty are built that
   * way. Resolving to a page.php that does not exist gave their pages a
   * template no schema could be written for, because the template list only
   * shows files the theme has.
   *
   * The general template still wins wherever it has a schema, so a site that
   * wrote one before switching to such a theme keeps its fields.
   *
   * @param string $general page.php or single.php
   * @return string Template file name
   */
  private function generalTemplate($general) {
    if ($this->themeHasTemplate($general) || $this->templateHasSchema($general)) {
      return $general;
    }

    return $this->themeHasTemplate('singular.php') ? 'singular.php' : $general;
  }

  /**
   * The first of these templates that the theme has and that has a schema on
   *
   * WordPress would render a more specific template such as page-about.php
   * whether or not it has a schema. It only takes over the fields here when an
   * administrator has written it one, so pages that have been showing the
   * general template's fields keep doing so after an update.
   *
   * @param string[] $candidates Template file names, most specific first
   * @return string|null
   */
  private function firstTemplateWithSchema(array $candidates) {
    foreach ($candidates as $candidate) {
      if ($this->templateHasSchema($candidate) && $this->themeHasTemplate($candidate)) {
        return $candidate;
      }
    }

    return null;
  }

  /**
   * Whether a template is switched on and has a schema
   *
   * @param string $template Template file name
   * @return bool
   */
  private function templateHasSchema($template) {
    $settings = get_option('yaml_cf_template_settings', []);
    if (!is_array($settings) || empty($settings[$template])) {
      return false;
    }

    $schemas = get_option('yaml_cf_schemas', []);

    return is_array($schemas) && isset($schemas[$template]) && is_string($schemas[$template]) && trim($schemas[$template]) !== '';
  }

  /**
   * The post's slug as it appears in a template file name
   *
   * @param \WP_Post $post Post object
   * @return string
   */
  private function decodedSlug($post) {
    return urldecode((string) $post->post_name);
  }

  /**
   * Which block template a post uses
   *
   * Mirrors the classic rules, with slugs in place of file names: a template
   * chosen in the editor wins, then page-{slug} or page-{id} when they have a
   * schema, then single-{post type}, then the general one for the kind of
   * content, or singular when the theme has only that.
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
      // page-{slug} and page-{id} take over only once they have a schema,
      // the same rule as page-about.php on a classic theme
      foreach (['page-' . $this->decodedSlug($post), 'page-' . $post->ID] as $candidate) {
        if ($this->templateHasSchema($candidate) && $this->blockTemplateExists($candidate)) {
          return $candidate;
        }
      }

      return $this->generalBlockTemplate('page');
    }

    $specific_template = "single-{$post_type}";
    if ($this->themeHasBlockTemplate($specific_template)) {
      return $specific_template;
    }

    if ($post_type === 'post') {
      return $this->generalBlockTemplate('single');
    }

    // No template of its own yet: name the one to create
    return $specific_template;
  }

  /**
   * page or single, or singular in a block theme that has only that
   *
   * @param string $general "page" or "single"
   * @return string Block template slug
   */
  private function generalBlockTemplate($general) {
    if ($this->themeHasBlockTemplate($general) || $this->templateHasSchema($general)) {
      return $general;
    }

    return $this->themeHasBlockTemplate('singular') ? 'singular' : $general;
  }

  /**
   * Whether a block template exists, in the theme's files or the Site Editor
   *
   * Only asked about a template that already has a schema, so the database is
   * read for a handful of candidates, not on every lookup.
   *
   * @param string $slug Block template slug
   * @return bool
   */
  private function blockTemplateExists($slug) {
    if ($this->themeHasBlockTemplate($slug)) {
      return true;
    }

    return function_exists('get_block_template')
      && get_block_template(get_stylesheet() . '//' . $slug, 'wp_template') !== null;
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
      $this->is_block_theme = (bool) wp_is_block_theme();
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
