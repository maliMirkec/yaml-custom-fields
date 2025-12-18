<?php
namespace YamlCF\Admin\Controllers;

/**
 * Controller for data validation page
 */
class ValidationController extends AdminController {
  public function render() {
    $this->checkPermission();
    $this->loadTemplate('data-validation-page.php');
  }
}
