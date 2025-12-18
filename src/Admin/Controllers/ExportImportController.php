<?php
namespace YamlCF\Admin\Controllers;

/**
 * Controller for export/import data page
 */
class ExportImportController extends AdminController {
  public function render() {
    $this->checkPermission();
    $this->loadTemplate('export-data-page.php');
  }
}
