<?php
namespace YamlCF\Admin\Controllers;

/**
 * Controller for data object management pages
 * Handles: data objects list, edit data object type, manage entries
 */
class DataObjectController extends AdminController {
  /**
   * Render data objects list page
   */
  public function renderList() {
    $this->checkPermission();
    $this->loadTemplate('data-objects-page.php');
  }

  /**
   * Render edit data object type page
   */
  public function renderEdit() {
    $this->checkPermission();
    $this->loadTemplate('edit-data-object-type-page.php');
  }

  /**
   * Render manage data object entries page
   */
  public function renderEntries() {
    $this->checkPermission();
    $this->loadTemplate('manage-data-object-entries-page.php');
  }

  /**
   * Render method - determines which view to show based on page
   */
  public function render() {
    // Default to list view
    $this->renderList();
  }
}
