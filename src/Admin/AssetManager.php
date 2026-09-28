<?php

namespace YamlCF\Admin;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Helpers\RequestHelper;

/**
 * Manages admin asset enqueuing (CSS, JavaScript)
 */
class AssetManager {
  private $templateResolver;

  /**
   * @param \YamlCF\Template\TemplateResolver $templateResolver Which template a post uses
   */
  public function __construct($templateResolver) {
    $this->templateResolver = $templateResolver;
  }

  /**
   * Whether the post being edited has any fields to show
   *
   * Loading the editor scripts and the media library on every post editor,
   * including on sites where one template out of twenty uses this plugin, is
   * weight on a screen that will not show a single field.
   *
   * @return bool
   */
  private function postScreenHasFields() {
    $post = $this->postBeingEdited();
    if (!$post) {
      return true;
    }

    $template = $this->templateResolver->resolveForPost($post);

    $template_settings = get_option('yaml_cf_template_settings', []);
    if (empty($template_settings[$template])) {
      return false;
    }

    $schemas = get_option('yaml_cf_schemas', []);

    return !empty($schemas[$template]);
  }

  /**
   * The post the editor is open on
   *
   * A new post has no ?post= yet, but WordPress has already made its draft
   * (post-new.php) and put it in $post before any script is enqueued.
   *
   * @return \WP_Post|null
   */
  private function postBeingEdited() {
    $post_id = RequestHelper::getParamInt('post', 0);
    if ($post_id) {
      $post = get_post($post_id);
      return $post instanceof \WP_Post ? $post : null;
    }

    return isset($GLOBALS['post']) && $GLOBALS['post'] instanceof \WP_Post ? $GLOBALS['post'] : null;
  }

  /**
   * Which stored schema the fields on this screen come from
   *
   * The "Add section" button sends this to the server, which renders the new
   * section from that schema. Without it the button only said the schema could
   * not be found, which it did on the shared content screen (looked for under
   * a page name that does not exist), on the collection item screen (never
   * looked for) and on a new post (no post ID in the address yet).
   *
   * @param bool $is_post_edit Whether this is the post editor
   * @param string $page The plugin screen (?page=), or '' elsewhere
   * @return array|null ['source' => string, 'key' => string], or null
   */
  public function schemaSource($is_post_edit, $page) {
    if ($is_post_edit) {
      $post = $this->postBeingEdited();
      if (!$post) {
        return null;
      }

      $template = $this->templateResolver->resolveForPost($post);
      $schemas = get_option('yaml_cf_schemas', []);

      return !empty($schemas[$template]) ? ['source' => 'template', 'key' => $template] : null;
    }

    if ($page === 'yaml-cf-manage-global-data') {
      return get_option('yaml_cf_global_schema', '') !== '' ? ['source' => 'global', 'key' => ''] : null;
    }

    $template = RequestHelper::getParam('template');

    if ($page === 'yaml-cf-manage-template-global') {
      $schemas = get_option('yaml_cf_template_global_schemas', []);
      return $template !== '' && !empty($schemas[$template]) ? ['source' => 'template_global', 'key' => $template] : null;
    }

    if ($page === 'yaml-cf-edit-partial') {
      $schemas = get_option('yaml_cf_schemas', []);
      return $template !== '' && !empty($schemas[$template]) ? ['source' => 'partial', 'key' => $template] : null;
    }

    if ($page === 'yaml-cf-manage-data-object-entries') {
      $type_id = RequestHelper::getParam('type_id');
      $types = get_option('yaml_cf_data_object_types', []);
      return $type_id !== '' && !empty($types[$type_id]['schema']) ? ['source' => 'data_object', 'key' => $type_id] : null;
    }

    return null;
  }

  /**
   * Enqueue admin assets
   */
  public function enqueueAssets($hook) {
    // Check if we're on any YAML Custom Fields admin page
    $is_plugin_page = (strpos($hook, 'yaml-cf') !== false || $hook === 'toplevel_page_yaml-custom-fields');

    // Load on post edit screens
    $current_screen = get_current_screen();
    $is_post_edit = false;

    if ($current_screen) {
      // Check if editing any public post type
      $post_type_object = get_post_type_object($current_screen->post_type);
      $is_post_edit = in_array($current_screen->base, ['post', 'post-new'], true) &&
                      $post_type_object && $post_type_object->public;
    }

    // Only load if on plugin pages or post edit screen
    if (!$is_plugin_page && !$is_post_edit) {
      return;
    }

    // A post whose template has no schema shows no fields, so there is nothing
    // for the scripts or the media library to do on that screen.
    if ($is_post_edit && !$is_plugin_page && !$this->postScreenHasFields()) {
      return;
    }

    // Enqueue WordPress media library (needed for image/file uploads)
    wp_enqueue_media();

    wp_enqueue_style('yaml-cf-admin', YAML_CF_PLUGIN_URL . 'admin-assets/admin.css', [], YAML_CF_VERSION);
    // jquery-ui-sortable: reordering a gallery (image field with list: true) by dragging
    wp_enqueue_script('yaml-cf-admin', YAML_CF_PLUGIN_URL . 'admin-assets/admin.js', ['jquery', 'jquery-ui-sortable', 'wp-i18n'], YAML_CF_VERSION, true);
    $this->setScriptTranslations('yaml-cf-admin');

    // Enqueue page initialization script (replaces inline scripts in templates)
    wp_enqueue_script(
      'yaml-cf-admin-page-init',
      YAML_CF_PLUGIN_URL . 'admin-assets/admin-page-init.js',
      ['jquery', 'wp-i18n', 'yaml-cf-admin'],
      YAML_CF_VERSION,
      true
    );
    $this->setScriptTranslations('yaml-cf-admin-page-init');

    // Which schema this screen is editing, so that adding a section can ask the
    // server to render it (see YamlCF\Ajax\Handlers\RenderBlockItem)
    $schema_source = $this->schemaSource($is_post_edit, RequestHelper::getParam('page'));

    wp_localize_script('yaml-cf-admin', 'yamlCF', [
      'ajax_url' => admin_url('admin-ajax.php'),
      'admin_url' => admin_url(),
      'nonce' => wp_create_nonce('yaml_cf_nonce'),
      // Only an administrator is handed the token for administrator-only actions
      'adminNonce' => current_user_can('manage_options') ? wp_create_nonce('yaml_cf_admin_nonce') : '',
      'schemaSource' => $schema_source,
    ]);

    // Page-specific conditional enqueuing
    $current_page = RequestHelper::getParam('page');

    if ($current_page === 'yaml-cf-data-validation') {
      wp_enqueue_script(
        'yaml-cf-validation',
        YAML_CF_PLUGIN_URL . 'admin-assets/admin-validation.js',
        ['jquery', 'wp-i18n'],
        YAML_CF_VERSION,
        true
      );
      $this->setScriptTranslations('yaml-cf-validation');
    }

    if ($current_page === 'yaml-cf-export-data') {
      wp_enqueue_script(
        'yaml-cf-export-import',
        YAML_CF_PLUGIN_URL . 'admin-assets/admin-export-import.js',
        ['jquery', 'wp-i18n', 'yaml-cf-admin'],
        YAML_CF_VERSION,
        true
      );
      $this->setScriptTranslations('yaml-cf-export-import');
    }
  }

  /**
   * Load JavaScript translations (wp.i18n) for a script handle
   * Uses language packs from translate.wordpress.org, or JSON files in /languages for local development.
   *
   * @param string $handle Script handle
   */
  private function setScriptTranslations($handle) {
    wp_set_script_translations($handle, 'yaml-custom-fields', YAML_CF_PLUGIN_DIR . 'languages');
  }
}
