<?php

namespace YamlCF\Admin\Controllers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controller for export/import data page
 */
class ExportImportController extends AdminController {
  public function render() {
    $this->checkPermission();
    $this->loadTemplate('export-data-page.php');
  }
}
