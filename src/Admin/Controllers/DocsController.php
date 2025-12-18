<?php
namespace YamlCF\Admin\Controllers;

/**
 * Controller for documentation page
 */
class DocsController extends AdminController {
  public function render() {
    $this->checkPermission();
    $this->loadTemplate('docs-page.php');
  }
}
