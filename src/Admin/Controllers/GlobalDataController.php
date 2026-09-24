<?php

namespace YamlCF\Admin\Controllers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Helpers\RequestHelper;

/**
 * Controller for managing global data
 */
class GlobalDataController extends AdminController {
  private $schemaStorage;

  public function __construct($schemaStorage) {
    $this->schemaStorage = $schemaStorage;
  }

  public function render() {
    $this->checkPermission();

    $global_schema_yaml = get_option('yaml_cf_global_schema', '');
    $global_schema = $this->schemaStorage->parseSchema($global_schema_yaml);

    if (!$global_schema || !isset($global_schema['fields'])) {
      wp_safe_redirect(admin_url('admin.php?page=yaml-cf-edit-global-schema'));
      exit;
    }

    $global_data = get_option('yaml_cf_global_data', []);

    // Save results are shown as queued admin notices (see NotificationManager)
    $success_message = '';

    // Localize the schema and which one it is, so that adding a section can ask
    // the server to render it (see YamlCF\Ajax\Handlers\RenderBlockItem).
    //
    // The terms, post types and collection entries that used to travel with the
    // page are gone: the admin script no longer builds fields itself, and on a
    // big site they were the bulk of the payload.
    $this->localizeScript([
      'schema' => $global_schema,
      'schemaSource' => ['source' => 'global', 'key' => ''],
    ]);

    // Pass messages and form tracking config to JavaScript
    $page_config = [];
    if ($success_message) {
      $page_config['successMessage'] = $success_message;
    }
    $page_config['formTracking'] = [
      'enabled' => true,
      'container' => '#yaml-cf-global-data-form',
      'fieldsSelector' => '.yaml-cf-fields',
      'message' => __('There are changes that are not saved yet.', 'yaml-custom-fields'),
      'submitSelector' => '#yaml-cf-global-data-form',
      'storageKey' => 'originalGlobalDataFormData',
      'hasChangesKey' => 'hasGlobalDataFormChanges',
      'beforeUnloadMessage' => __('There are changes that are not saved yet. Leave anyway?', 'yaml-custom-fields'),
      'gutenbergSupport' => false,
      'captureDelay' => 500
    ];
    $this->localizePageInit($page_config);

    // Load template
    $this->loadTemplate('manage-global-data-page.php', compact(
      'global_schema',
      'global_data'
    ));
  }
}
