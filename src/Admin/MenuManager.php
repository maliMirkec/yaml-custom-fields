<?php

namespace YamlCF\Admin;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Helpers\RequestHelper;

/**
 * Manages WordPress admin menu registration and customization
 */
class MenuManager {
  private $controllers = [];
  private $schemaParser;
  private $templateCache;

  public function __construct($schemaParser, $templateCache) {
    $this->schemaParser = $schemaParser;
    $this->templateCache = $templateCache;
  }

  /**
   * Set controllers for menu pages
   */
  public function setControllers($controllers) {
    $this->controllers = $controllers;
  }

  /**
   * Register admin menu pages
   */
  public function registerMenu() {
    add_menu_page(
      __('YAML Custom Fields', 'yaml-custom-fields'),
      __('YAML CF', 'yaml-custom-fields'),
      'manage_options',
      'yaml-custom-fields',
      [$this, 'renderMainPage'],
      'dashicons-edit-page',
      30
    );

    // Register hidden pages (accessible via URL but not shown in menu by default)
    add_submenu_page(
      'yaml-custom-fields',
      __('Edit Schema', 'yaml-custom-fields'),
      __('Edit Schema', 'yaml-custom-fields'),
      'manage_options',
      'yaml-cf-edit-schema',
      [$this, 'renderEditSchemaPage']
    );

    add_submenu_page(
      'yaml-custom-fields',
      __('Edit Partial', 'yaml-custom-fields'),
      __('Edit Partial', 'yaml-custom-fields'),
      'manage_options',
      'yaml-cf-edit-partial',
      [$this, 'renderEditPartialPage']
    );

    // Global Schema pages
    add_submenu_page(
      'yaml-custom-fields',
      __('Edit Global Schema', 'yaml-custom-fields'),
      __('Edit Global Schema', 'yaml-custom-fields'),
      'manage_options',
      'yaml-cf-edit-global-schema',
      [$this, 'renderEditGlobalSchemaPage']
    );

    add_submenu_page(
      'yaml-custom-fields',
      __('Manage Global Data', 'yaml-custom-fields'),
      __('Manage Global Data', 'yaml-custom-fields'),
      'manage_options',
      'yaml-cf-manage-global-data',
      [$this, 'renderManageGlobalDataPage']
    );

    // Template Global pages (hidden from menu)
    add_submenu_page(
      'yaml-custom-fields',
      __('Edit Template Global Schema', 'yaml-custom-fields'),
      __('Edit Template Global Schema', 'yaml-custom-fields'),
      'manage_options',
      'yaml-cf-edit-template-global',
      [$this, 'renderEditTemplateGlobalPage']
    );

    add_submenu_page(
      'yaml-custom-fields',
      __('Manage Template Global Data', 'yaml-custom-fields'),
      __('Manage Template Global Data', 'yaml-custom-fields'),
      'manage_options',
      'yaml-cf-manage-template-global',
      [$this, 'renderManageTemplateGlobalPage']
    );

    // Data Validation
    add_submenu_page(
      'yaml-custom-fields',
      __('Data Validation', 'yaml-custom-fields'),
      __('Data Validation', 'yaml-custom-fields'),
      'manage_options',
      'yaml-cf-data-validation',
      [$this, 'renderDataValidationPage']
    );

    // Data Objects
    add_submenu_page(
      'yaml-custom-fields',
      __('Data Objects', 'yaml-custom-fields'),
      __('Data Objects', 'yaml-custom-fields'),
      'manage_options',
      'yaml-cf-data-objects',
      [$this, 'renderDataObjectsPage']
    );

    // Hidden pages for Data Objects
    add_submenu_page(
      'yaml-custom-fields',
      __('Edit Data Object Type', 'yaml-custom-fields'),
      __('Edit Data Object Type', 'yaml-custom-fields'),
      'manage_options',
      'yaml-cf-edit-data-object-type',
      [$this, 'renderEditDataObjectTypePage']
    );

    add_submenu_page(
      'yaml-custom-fields',
      __('Manage Data Object Entries', 'yaml-custom-fields'),
      __('Manage Data Object Entries', 'yaml-custom-fields'),
      'manage_options',
      'yaml-cf-manage-data-object-entries',
      [$this, 'renderManageDataObjectEntriesPage']
    );

    // Export/Import (consolidated - positioned before Documentation)
    add_submenu_page(
      'yaml-custom-fields',
      __('Export/Import', 'yaml-custom-fields'),
      __('Export/Import', 'yaml-custom-fields'),
      'manage_options',
      'yaml-cf-export-data',
      [$this, 'renderExportImportPage']
    );

    // Documentation (added last to appear at the bottom)
    add_submenu_page(
      'yaml-custom-fields',
      __('Documentation', 'yaml-custom-fields'),
      __('Documentation', 'yaml-custom-fields'),
      'manage_options',
      'yaml-cf-docs',
      [$this, 'renderDocsPage']
    );
  }

  /**
   * Hide submenu items conditionally
   */
  public function hideSubmenuItems() {
    global $submenu;

    if (isset($submenu['yaml-custom-fields'])) {
      $current_page = RequestHelper::getParam('page');

      // Check if there are any data object types
      $data_object_types = get_option('yaml_cf_data_object_types', []);
      $has_data_object_types = !empty($data_object_types);

      // Check if there is a global schema with fields
      $global_schema_yaml = get_option('yaml_cf_global_schema', '');
      $global_schema = $this->schemaParser->parse($global_schema_yaml);
      $has_global_schema = !empty($global_schema) && !empty($global_schema['fields']);

      foreach ($submenu['yaml-custom-fields'] as $key => $menu_item) {
        $menu_slug = $menu_item[2];

        // Hide "Edit Schema" if not on edit schema page
        if ($menu_slug === 'yaml-cf-edit-schema' && $current_page !== 'yaml-cf-edit-schema') {
          unset($submenu['yaml-custom-fields'][$key]);
        }

        // Hide "Edit Partial" if not on edit partial page
        if ($menu_slug === 'yaml-cf-edit-partial' && $current_page !== 'yaml-cf-edit-partial') {
          unset($submenu['yaml-custom-fields'][$key]);
        }

        // Always hide "Edit Data Object Type" from menu - it's accessed via Data Objects page
        if ($menu_slug === 'yaml-cf-edit-data-object-type' && $current_page !== 'yaml-cf-edit-data-object-type') {
          unset($submenu['yaml-custom-fields'][$key]);
        }

        // Always hide "Manage Data Object Entries" from menu - it's accessed via Data Objects page
        if ($menu_slug === 'yaml-cf-manage-data-object-entries' && $current_page !== 'yaml-cf-manage-data-object-entries') {
          unset($submenu['yaml-custom-fields'][$key]);
        }

        // Hide "Edit Template Global" if not on that page
        if ($menu_slug === 'yaml-cf-edit-template-global' && $current_page !== 'yaml-cf-edit-template-global') {
          unset($submenu['yaml-custom-fields'][$key]);
        }

        // Hide "Manage Template Global Data" if not on that page
        if ($menu_slug === 'yaml-cf-manage-template-global' && $current_page !== 'yaml-cf-manage-template-global') {
          unset($submenu['yaml-custom-fields'][$key]);
        }

        // Hide "Manage Global Data" if no global schema or no fields defined
        if ($menu_slug === 'yaml-cf-manage-global-data' && !$has_global_schema) {
          unset($submenu['yaml-custom-fields'][$key]);
        }
      }
    }
  }

  /**
   * Customize admin page title
   */
  public function customizeAdminTitle($admin_title, $title) {
    $page = RequestHelper::getParam('page');

    // Handle template-based pages
    $template = RequestHelper::getParam('template');
    if ($template) {
      $theme_files = $this->templateCache->getThemeTemplates();
      $template_name = $template;
      foreach (array_merge($theme_files['templates'], $theme_files['partials']) as $item) {
        if ($item['file'] === $template) {
          $template_name = $item['name'];
          break;
        }
      }

      // Edit Schema page
      if ($page === 'yaml-cf-edit-schema') {
        /* translators: %s: Template name */
        return sprintf(__('Edit Schema: %s', 'yaml-custom-fields'), $template_name) . ' ' . $admin_title;
      }

      // Edit Partial page
      if ($page === 'yaml-cf-edit-partial') {
        /* translators: %s: Template name */
        return sprintf(__('Edit Partial: %s', 'yaml-custom-fields'), $template_name) . ' ' . $admin_title;
      }

      // Edit Template Global Schema page
      if ($page === 'yaml-cf-edit-template-global') {
        /* translators: %s: Template name */
        return sprintf(__('Edit Template Global Schema: %s', 'yaml-custom-fields'), $template_name) . ' ' . $admin_title;
      }

      // Manage Template Global Data page
      if ($page === 'yaml-cf-manage-template-global') {
        /* translators: %s: Template name */
        return sprintf(__('Manage Template Global Data: %s', 'yaml-custom-fields'), $template_name) . ' ' . $admin_title;
      }
    }

    // Handle data-object-based pages
    $type_id = RequestHelper::getParam('type_id');
    if ($type_id) {
      $data_object_types = get_option('yaml_cf_data_object_types', []);
      $type_name = isset($data_object_types[$type_id]['name']) ? $data_object_types[$type_id]['name'] : $type_id;

      if ($page === 'yaml-cf-edit-data-object-type') {
        /* translators: %s: Data object type name */
        return sprintf(__('Edit Data Object Type: %s', 'yaml-custom-fields'), $type_name) . ' ' . $admin_title;
      }

      if ($page === 'yaml-cf-manage-data-object-entries') {
        /* translators: %s: Data object type name */
        return sprintf(__('Manage Entries: %s', 'yaml-custom-fields'), $type_name) . ' ' . $admin_title;
      }
    }

    return $admin_title;
  }

  /**
   * Set parent file for menu highlighting
   */
  public function setParentFile($parent_file) {
    // TODO: Phase 14 - Implement parent file logic
    return $parent_file;
  }

  /**
   * Set submenu file for menu highlighting
   */
  public function setSubmenuFile($submenu_file) {
    // TODO: Phase 14 - Implement submenu file logic
    return $submenu_file;
  }

  // Render methods that delegate to controllers
  public function renderMainPage() {
    if (isset($this->controllers['template_schema'])) {
      $this->controllers['template_schema']->render();
    }
  }

  public function renderEditSchemaPage() {
    if (isset($this->controllers['schema_editor'])) {
      $this->controllers['schema_editor']->render();
    }
  }

  public function renderEditPartialPage() {
    if (isset($this->controllers['partial'])) {
      $this->controllers['partial']->render();
    }
  }

  public function renderEditGlobalSchemaPage() {
    if (isset($this->controllers['global_schema'])) {
      $this->controllers['global_schema']->render();
    }
  }

  public function renderManageGlobalDataPage() {
    if (isset($this->controllers['global_data'])) {
      $this->controllers['global_data']->render();
    }
  }

  public function renderEditTemplateGlobalPage() {
    if (isset($this->controllers['template_global'])) {
      $this->controllers['template_global']->renderSchemaEditor();
    }
  }

  public function renderManageTemplateGlobalPage() {
    if (isset($this->controllers['template_global'])) {
      $this->controllers['template_global']->renderDataManager();
    }
  }

  public function renderDataValidationPage() {
    if (isset($this->controllers['validation'])) {
      $this->controllers['validation']->render();
    }
  }

  public function renderDataObjectsPage() {
    if (isset($this->controllers['data_object'])) {
      $this->controllers['data_object']->renderList();
    }
  }

  public function renderEditDataObjectTypePage() {
    if (isset($this->controllers['data_object'])) {
      $this->controllers['data_object']->renderEdit();
    }
  }

  public function renderManageDataObjectEntriesPage() {
    if (isset($this->controllers['data_object'])) {
      $this->controllers['data_object']->renderEntries();
    }
  }

  public function renderExportImportPage() {
    if (isset($this->controllers['export_import'])) {
      $this->controllers['export_import']->render();
    }
  }

  public function renderDocsPage() {
    if (isset($this->controllers['docs'])) {
      $this->controllers['docs']->render();
    }
  }
}
