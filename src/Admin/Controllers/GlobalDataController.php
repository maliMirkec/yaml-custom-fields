<?php
namespace YamlCF\Admin\Controllers;

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

    // Check for success message
    $success_message = '';
    if (RequestHelper::getParam('saved') === '1') {
      $success_message = __('Global data saved successfully!', 'yaml-custom-fields');
    }

    // Localize schema data for JavaScript
    $this->localizeScript(['schema' => $global_schema]);

    // Load template
    $this->loadTemplate('manage-global-data-page.php', compact(
      'global_schema',
      'global_data',
      'success_message'
    ));
  }
}
