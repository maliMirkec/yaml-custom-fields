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

    // A schema refused on save comes back into the editor
    if (RequestHelper::getParam('error') === '1') {
      $invalid_schema = get_transient('yaml_cf_invalid_global_schema_' . get_current_user_id());
      if ($invalid_schema !== false) {
        $global_schema = $invalid_schema;
        delete_transient('yaml_cf_invalid_global_schema_' . get_current_user_id());
      }
    }

    // Save results are shown as queued notices (see NotificationManager)

    // Load template
    $this->loadTemplate('edit-global-schema-page.php', compact('global_schema'));
  }
}
