<?php

namespace YamlCF\Admin\Controllers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controller for the main dashboard (templates, partials, global schema)
 */
class TemplateSchemaController extends AdminController {
  /**
   * Whether deleting the plugin should leave the content in place
   */
  const KEEP_DATA_OPTION = 'yaml_cf_keep_data_on_uninstall';

  private $templateCache;
  private $schemaParser;
  private $notificationManager;
  private $sorter;

  public function __construct($templateCache, $schemaParser, $notificationManager, $sorter = null) {
    $this->templateCache = $templateCache;
    $this->schemaParser = $schemaParser;
    $this->notificationManager = $notificationManager;
    $this->sorter = $sorter ? $sorter : new \YamlCF\Template\TemplateSorter();
  }

  /**
   * Render the dashboard
   *
   * Notices (e.g. "Template list refreshed") are printed by the admin_notices hook.
   */
  public function render() {
    $this->checkPermission();

    $theme_files = $this->templateCache->getThemeTemplates();
    $template_settings = (array) get_option('yaml_cf_template_settings', []);
    $schemas = (array) get_option('yaml_cf_schemas', []);
    $template_global_schemas = (array) get_option('yaml_cf_template_global_schemas', []);

    // Most used first (standard page, blog post / header, footer, sidebar), then A–Z
    $theme_files['templates'] = $this->sorter->sortTemplates($theme_files['templates']);
    $theme_files['partials'] = $this->sorter->sortPartials($theme_files['partials']);

    $template_rows = [];
    foreach ($theme_files['templates'] as $template) {
      $template_rows[] = $this->buildRow($template, $template_settings, $schemas, $template_global_schemas);
    }

    $partial_rows = [];
    foreach ($theme_files['partials'] as $partial) {
      $partial_rows[] = $this->buildRow($partial, $template_settings, $schemas, []);
    }

    $global_schema = get_option('yaml_cf_global_schema', '');
    $global_field_count = $this->countFields($global_schema);

    // First-run guidance is shown until the first schema exists
    $show_getting_started = count(array_filter($schemas)) === 0 && $global_field_count === 0;

    // An empty table needs to say why. A theme with no templates of its own is
    // almost always a child theme whose parent could not be read, or a theme
    // this plugin cannot see into.
    $theme_kind = $this->themeKind();

    $keep_data_on_uninstall = (bool) get_option(self::KEEP_DATA_OPTION, false);
    $clear_post_caches = (string) get_option(\YamlCF\Cache\CacheManager::CLEAR_ALL_POSTS_OPTION, '1') !== '0';
    $rest_public_read = \YamlCF\Rest\RestApi::publicReadEnabled();

    $this->localizePageInit(['hasRefreshButton' => true]);

    $this->loadTemplate('admin-page.php', compact(
      'template_rows',
      'partial_rows',
      'global_field_count',
      'show_getting_started',
      'theme_kind',
      'keep_data_on_uninstall',
      'clear_post_caches',
      'rest_public_read'
    ));
  }

  /**
   * Save the choices made on the main screen, then come back to it
   *
   * Hooked to admin_init. Saved inside render(), as they were, the choice was
   * made after WordPress had printed its notices, so the confirmation only
   * showed on whatever page was opened next.
   *
   * @return void
   */
  public function handleFormSubmissions() {
    if (\YamlCF\Helpers\RequestHelper::getParam('page') !== 'yaml-custom-fields') {
      return;
    }

    if ($this->saveSubmittedChoices()) {
      wp_safe_redirect(admin_url('admin.php?page=yaml-custom-fields'));
      exit;
    }
  }

  /**
   * Save whichever of the main screen's choices was submitted
   *
   * @return bool Whether a form was submitted
   */
  public function saveSubmittedChoices() {
    return $this->handleKeepDataSubmission() || $this->handleClearCachesSubmission() || $this->handleRestAccessSubmission();
  }

  /**
   * Save the choice about reading site details and collections over the REST API
   *
   * @return bool Whether this form was submitted
   */
  private function handleRestAccessSubmission() {
    if (!isset($_POST['yaml_cf_rest_access_nonce'])) {
      return false;
    }

    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_rest_access_nonce'])), 'yaml_cf_rest_access')) {
      wp_die(esc_html__('This link has expired. Go back, reload the page and try again.', 'yaml-custom-fields'));
    }

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('This action needs administrator permissions.', 'yaml-custom-fields'));
    }

    $public = isset($_POST['yaml_cf_rest_public_read']) && $_POST['yaml_cf_rest_public_read'] === '1';
    update_option(\YamlCF\Rest\RestApi::PUBLIC_READ_OPTION, $public ? '1' : '0', false);

    $this->notificationManager->success(
      $public
        ? __('Site details and collections can now be read over the REST API without logging in.', 'yaml-custom-fields')
        : __('Reading site details and collections over the REST API now needs a logged-in user who can edit posts.', 'yaml-custom-fields')
    );

    return true;
  }

  /**
   * Save the choice about page caches when site-wide content is saved
   *
   * @return bool Whether this form was submitted
   */
  private function handleClearCachesSubmission() {
    if (!isset($_POST['yaml_cf_clear_caches_nonce'])) {
      return false;
    }

    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_clear_caches_nonce'])), 'yaml_cf_clear_caches')) {
      wp_die(esc_html__('This link has expired. Go back, reload the page and try again.', 'yaml-custom-fields'));
    }

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('This action needs administrator permissions.', 'yaml-custom-fields'));
    }

    $clear = isset($_POST['yaml_cf_clear_caches']) && $_POST['yaml_cf_clear_caches'] === '1';
    // '0' rather than false: update_option() does not save a false over a missing option
    update_option(\YamlCF\Cache\CacheManager::CLEAR_ALL_POSTS_OPTION, $clear ? '1' : '0', false);

    $this->notificationManager->success(
      $clear
        ? __('Saving site-wide content will now clear the cache of every page with fields.', 'yaml-custom-fields')
        : __('Saving site-wide content will no longer clear the cache of every page with fields.', 'yaml-custom-fields')
    );

    return true;
  }

  /**
   * Save the choice about what happens to the content if the plugin is deleted
   *
   * Deleting the plugin removes the field content from every page, and that is
   * the site's own writing, not settings. It cannot be undone and there was no
   * way to ask for it to be kept.
   *
   * @return bool Whether this form was submitted
   */
  private function handleKeepDataSubmission() {
    if (!isset($_POST['yaml_cf_keep_data_nonce'])) {
      return false;
    }

    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_keep_data_nonce'])), 'yaml_cf_keep_data')) {
      wp_die(esc_html__('This link has expired. Go back, reload the page and try again.', 'yaml-custom-fields'));
    }

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('This action needs administrator permissions.', 'yaml-custom-fields'));
    }

    $keep = isset($_POST['yaml_cf_keep_data']) && $_POST['yaml_cf_keep_data'] === '1';
    update_option(self::KEEP_DATA_OPTION, $keep, false);

    $this->notificationManager->success(
      $keep
        ? __('Deleting the plugin will now leave the field content in place.', 'yaml-custom-fields')
        : __('Deleting the plugin will now remove the field content as well.', 'yaml-custom-fields')
    );

    return true;
  }

  /**
   * What the empty tables should say about the active theme
   *
   * @return array {
   *   @type string $name Theme name
   *   @type bool $is_block Whether it builds its pages out of blocks
   *   @type bool $is_child Whether it is a child of another theme
   *   @type string $parent Parent theme name, when there is one
   * }
   */
  private function themeKind() {
    $theme = wp_get_theme();
    $parent = $theme->parent();

    $is_block = (bool) wp_is_block_theme();

    return [
      'name' => $theme->get('Name'),
      'is_block' => $is_block,
      'is_child' => (bool) $parent,
      'parent' => $parent ? $parent->get('Name') : '',
    ];
  }

  /**
   * Build the status of one template or partial row
   *
   * @param array $file Template info from the scanner ('file', 'name')
   * @param array $template_settings Enabled flags keyed by file
   * @param array $schemas YAML schemas keyed by file
   * @param array $template_global_schemas Shared (template global) YAML schemas keyed by file
   * @return array Row data
   */
  private function buildRow($file, $template_settings, $schemas, $template_global_schemas) {
    $key = $file['file'];
    return [
      'file' => $key,
      'name' => $file['name'],
      'enabled' => !empty($template_settings[$key]),
      'field_count' => $this->countFields(isset($schemas[$key]) ? $schemas[$key] : ''),
      'has_schema' => !empty($schemas[$key]),
      'shared_field_count' => $this->countFields(isset($template_global_schemas[$key]) ? $template_global_schemas[$key] : ''),
      'has_shared_schema' => !empty($template_global_schemas[$key]),
      'uses_site_details' => !empty($template_settings[$key . '_use_global']),
    ];
  }

  /**
   * Count the top-level fields defined by a YAML schema
   *
   * @param string $yaml YAML schema
   * @return int Number of fields (0 when empty or invalid)
   */
  private function countFields($yaml) {
    if (!is_string($yaml) || trim($yaml) === '') {
      return 0;
    }
    $parsed = $this->schemaParser->parse($yaml);
    if (!is_array($parsed) || !isset($parsed['fields']) || !is_array($parsed['fields'])) {
      return 0;
    }

    // Section breaks, row breaks and info boxes hold no content, so they are not fields
    $decorative = ['section', 'break', 'info'];
    $count = 0;
    foreach ($parsed['fields'] as $field) {
      if (is_array($field) && isset($field['type']) && in_array($field['type'], $decorative, true)) {
        continue;
      }
      // Shorthand ("- info: text" / "- section: Title" / "- break:") has no type
      if (is_array($field) && !isset($field['type']) && !isset($field['name'])
        && (array_key_exists('info', $field)
          || array_key_exists('section', $field)
          || array_key_exists('break', $field))) {
        continue;
      }
      $count++;
    }

    return $count;
  }
}
