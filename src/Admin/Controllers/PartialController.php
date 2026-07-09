<?php

namespace YamlCF\Admin\Controllers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Helpers\RequestHelper;

/**
 * Controller for editing partial template data
 */
class PartialController extends AdminController {
  private $templateCache;
  private $schemaStorage;

  public function __construct($templateCache, $schemaStorage) {
    $this->templateCache = $templateCache;
    $this->schemaStorage = $schemaStorage;
  }

  /**
   * Redirect away from the partial editor if no template is specified, if the
   * template has no schema, or if that schema is invalid.
   *
   * Must run on admin_init (see HookManager::redirectInvalidPageSelections()),
   * NOT from inside render() itself: add_submenu_page callbacks run after
   * admin-header.php has already sent output, so a redirect attempted from
   * within render() cannot succeed.
   */
  public function maybeRedirectInvalid() {
    $template = RequestHelper::getParam('template');
    if (!$template) {
      wp_safe_redirect(admin_url('admin.php?page=yaml-custom-fields'));
      exit;
    }

    $schemas = get_option('yaml_cf_schemas', []);
    if (!isset($schemas[$template])) {
      wp_safe_redirect(admin_url('admin.php?page=yaml-cf-edit-schema&template=' . urlencode($template)));
      exit;
    }

    $schema = $this->schemaStorage->parseSchema($schemas[$template]);
    if (!$schema || !isset($schema['fields'])) {
      wp_safe_redirect(admin_url('admin.php?page=yaml-cf-edit-schema&template=' . urlencode($template)));
      exit;
    }
  }

  public function render() {
    $this->checkPermission();

    // Get template parameter
    // NOTE: This is a read-only display page behind manage_options capability.
    // WordPress core doesn't require nonces for authenticated GET requests to admin pages.
    // The checkPermission() call above verifies current_user_can('manage_options').
    $template = RequestHelper::getParam('template');
    // Normally unreachable beyond this point: maybeRedirectInvalid() already
    // redirected away on admin_init for any of these three cases.
    if (!$template) {
      $this->renderSelectionRequiredNotice(
        __('Edit Partial', 'yaml-custom-fields'),
        __('No template was specified. Choose a template from the main page first.', 'yaml-custom-fields'),
        admin_url('admin.php?page=yaml-custom-fields'),
        __('Back to Templates', 'yaml-custom-fields')
      );
      return;
    }

    $schemas = get_option('yaml_cf_schemas', []);

    if (!isset($schemas[$template])) {
      $this->renderSelectionRequiredNotice(
        __('Edit Partial', 'yaml-custom-fields'),
        __('No schema has been defined for this template yet. Define one first.', 'yaml-custom-fields'),
        admin_url('admin.php?page=yaml-cf-edit-schema&template=' . urlencode($template)),
        __('Define Schema', 'yaml-custom-fields')
      );
      return;
    }

    $schema_yaml = $schemas[$template];
    $schema = $this->schemaStorage->parseSchema($schema_yaml);

    if (!$schema || !isset($schema['fields'])) {
      $this->renderSelectionRequiredNotice(
        __('Edit Partial', 'yaml-custom-fields'),
        __('The schema for this template is invalid. Please fix it before editing partial data.', 'yaml-custom-fields'),
        admin_url('admin.php?page=yaml-cf-edit-schema&template=' . urlencode($template)),
        __('Edit Schema', 'yaml-custom-fields')
      );
      return;
    }

    // Get partial data
    $partial_data = get_option('yaml_cf_partial_data', []);
    $template_data = isset($partial_data[$template]) ? $partial_data[$template] : [];

    // Get template name from theme files
    $theme_files = $this->templateCache->getThemeTemplates();
    $template_name = $template;
    foreach ($theme_files['partials'] as $partial) {
      if ($partial['file'] === $template) {
        $template_name = $partial['name'];
        break;
      }
    }

    // Check for success message
    $success_message = '';
    if (RequestHelper::getParam('saved') === '1') {
      $success_message = __('Partial data saved successfully!', 'yaml-custom-fields');
    }

    // Localize schema data for JavaScript
    $this->localizeScript(['schema' => $schema]);

    // Pass page initialization data (replaces inline scripts)
    $page_data = [
      'formTracking' => [
        'enabled' => true,
        'container' => '#yaml-cf-partial-form',
        'fieldsSelector' => '.yaml-cf-fields',
        'message' => __('You have unsaved changes', 'yaml-custom-fields'),
        'submitSelector' => '#yaml-cf-partial-form',
        'storageKey' => 'originalPartialFormData',
        'hasChangesKey' => 'hasPartialFormChanges',
        'beforeUnloadMessage' => __('You have unsaved changes. Are you sure you want to leave?', 'yaml-custom-fields'),
        'gutenbergSupport' => false,
        'captureDelay' => 500,
      ]
    ];
    if (!empty($success_message)) {
      $page_data['successMessage'] = $success_message;
    }
    $this->localizePageInit($page_data);

    // Load template
    $this->loadTemplate('edit-partial-page.php', compact(
      'template',
      'template_name',
      'schema',
      'template_data',
      'success_message'
    ));
  }
}
