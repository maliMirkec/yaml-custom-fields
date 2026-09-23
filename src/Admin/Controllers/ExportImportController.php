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
  private $catalog;

  public function __construct($catalog = null) {
    $this->catalog = $catalog ?: new \YamlCF\ImportExport\BundleCatalog();
  }

  public function render() {
    $this->checkPermission();
    $this->loadTemplate('export-data-page.php', [
      'yaml_cf_catalog' => $this->catalog->fromSite(),
    ]);
  }
}
