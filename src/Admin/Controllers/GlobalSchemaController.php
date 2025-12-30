<?php

namespace YamlCF\Admin\Controllers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Helpers\RequestHelper;

/**
 * Controller for editing global schema
 */
class GlobalSchemaController extends AdminController {
  public function render() {
    $this->checkPermission();

    $global_schema = get_option('yaml_cf_global_schema', '');

    // Check if there's a validation error
    $error_message = '';
    if (RequestHelper::getParam('error') === '1') {
      $invalid_schema = get_transient('yaml_cf_invalid_global_schema_' . get_current_user_id());
      if ($invalid_schema !== false) {
        $global_schema = $invalid_schema;
        delete_transient('yaml_cf_invalid_global_schema_' . get_current_user_id());
      }

      $error_msg = RequestHelper::getParam('error_msg');
      if ($error_msg) {
        $error_message = $error_msg;
      } else {
        $error_message = __('Invalid YAML schema. Please check your syntax and try again.', 'yaml-custom-fields');
      }
    }

    // Check for success message
    $success_message = '';
    if (RequestHelper::getParam('saved') === '1') {
      $success_message = __('Global schema saved successfully!', 'yaml-custom-fields');
    }

    // Pass messages to JavaScript via localization
    $page_config = [];
    if ($success_message) {
      $page_config['successMessage'] = $success_message;
    }
    if ($error_message) {
      $page_config['errorMessage'] = $error_message;
    }

    if (!empty($page_config)) {
      $this->localizePageInit($page_config);
    }

    // Load template
    $this->loadTemplate('edit-global-schema-page.php', compact('global_schema'));
  }
}
