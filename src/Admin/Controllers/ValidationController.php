<?php

namespace YamlCF\Admin\Controllers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controller for data validation page
 */
class ValidationController extends AdminController {
  public function render() {
    $this->checkPermission();
    $this->loadTemplate('data-validation-page.php');
  }
}
