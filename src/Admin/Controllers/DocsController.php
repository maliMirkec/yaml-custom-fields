<?php

namespace YamlCF\Admin\Controllers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controller for documentation page
 */
class DocsController extends AdminController {
  public function render() {
    $this->checkPermission();
    $this->loadTemplate('docs-page.php');
  }
}
