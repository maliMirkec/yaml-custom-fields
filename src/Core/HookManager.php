<?php

/**
 * Hook Manager
 * Centralized WordPress hook registration
 */

namespace YamlCF\Core;


// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
class HookManager {
  private $plugin;

  /**
   * Constructor
   *
   * @param Plugin $plugin Plugin instance
   */
  public function __construct(Plugin $plugin) {
    $this->plugin = $plugin;
  }

  /**
   * Register all WordPress hooks
   *
   * @return void
   */
  public function registerHooks() {
    $container = $this->plugin->getContainer();

    // Admin menu and assets
    add_action('admin_menu', [$this, 'registerAdminMenu']);
    add_action('admin_init', [$this, 'hideSubmenuItems']);
    add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);

    // Menu customization
    add_filter('admin_title', [$this, 'customizeAdminTitle'], 10, 2);
    add_filter('parent_file', [$this, 'setParentFile']);
    add_filter('submenu_file', [$this, 'setSubmenuFile']);

    // AJAX handlers
    $this->registerAjaxHandlers();

    // Cache clearing
    add_action('switch_theme', [$this, 'clearTemplateCache']);
  }

  /**
   * Register admin menu
   */
  public function registerAdminMenu() {
    $menuManager = $this->plugin->get('menu_manager');

    // Set controllers
    $menuManager->setControllers([
      'template_schema' => $this->plugin->get('template_schema_controller'),
      'schema_editor' => $this->plugin->get('schema_editor_controller'),
      'partial' => $this->plugin->get('partial_controller'),
      'global_schema' => $this->plugin->get('global_schema_controller'),
      'global_data' => $this->plugin->get('global_data_controller'),
      'template_global' => $this->plugin->get('template_global_controller'),
      'validation' => $this->plugin->get('validation_controller'),
      'data_object' => $this->plugin->get('data_object_controller'),
      'export_import' => $this->plugin->get('export_import_controller'),
      'docs' => $this->plugin->get('docs_controller'),
    ]);

    $menuManager->registerMenu();
  }

  /**
   * Hide submenu items
   */
  public function hideSubmenuItems() {
    $menuManager = $this->plugin->get('menu_manager');
    $menuManager->hideSubmenuItems();
  }

  /**
   * Enqueue admin assets
   */
  public function enqueueAdminAssets($hook) {
    $assetManager = $this->plugin->get('asset_manager');
    $assetManager->enqueueAssets($hook);
  }

  /**
   * Customize admin title
   */
  public function customizeAdminTitle($admin_title, $title) {
    $menuManager = $this->plugin->get('menu_manager');
    return $menuManager->customizeAdminTitle($admin_title, $title);
  }

  /**
   * Set parent file
   */
  public function setParentFile($parent_file) {
    $menuManager = $this->plugin->get('menu_manager');
    return $menuManager->setParentFile($parent_file);
  }

  /**
   * Set submenu file
   */
  public function setSubmenuFile($submenu_file) {
    $menuManager = $this->plugin->get('menu_manager');
    return $menuManager->setSubmenuFile($submenu_file);
  }

  /**
   * Clear template cache
   */
  public function clearTemplateCache() {
    $cacheManager = $this->plugin->get('cache_manager');
    $cacheManager->clearTemplateCache();
  }

  /**
   * Register AJAX handlers
   */
  private function registerAjaxHandlers() {
    // AJAX handlers will be registered here
    // For now, the old class continues to handle AJAX
    // TODO: Implement AJAX handler registration
  }
}
