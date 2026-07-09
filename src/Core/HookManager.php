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

use YamlCF\Helpers\RequestHelper;

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
    // Admin menu and assets
    add_action('admin_menu', [$this, 'registerAdminMenu']);
    add_action('admin_init', [$this, 'hideSubmenuItems']);
    add_action('admin_init', [$this, 'redirectInvalidPageSelections']);
    add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);

    // Menu customization
    add_filter('admin_title', [$this, 'customizeAdminTitle'], 10, 2);
    add_filter('parent_file', [$this, 'setParentFile']);
    add_filter('submenu_file', [$this, 'setSubmenuFile']);

    // NOTE: AJAX handlers are registered by the legacy YAML_Custom_Fields
    // class (yaml-custom-fields.php init_hooks()), not here.

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
   * Redirect away from pages that require a valid selection (a data object
   * type, a global schema, a template) when none is present.
   *
   * This MUST run on admin_init, before admin-header.php sends any output.
   * add_submenu_page callbacks (the page render methods) run too late for a
   * redirect to work -- WordPress core already streamed the page <head> and
   * nav menu by the time they execute, so wp_safe_redirect() calls made from
   * inside a render() method silently fail and the response just stops,
   * producing a blank content area. Centralizing the checks here, keyed off
   * the requested page slug, keeps that timing constraint in one place
   * instead of relying on every controller to know about it.
   */
  public function redirectInvalidPageSelections() {
    $page = RequestHelper::getParam('page');

    switch ($page) {
      case 'yaml-cf-manage-data-object-entries':
        $this->plugin->get('data_object_controller')->maybeRedirectInvalidEntries();
        break;

      case 'yaml-cf-manage-global-data':
        $this->plugin->get('global_data_controller')->maybeRedirectInvalidSchema();
        break;

      case 'yaml-cf-edit-template-global':
        $this->plugin->get('template_global_controller')->maybeRedirectMissingTemplate();
        break;

      case 'yaml-cf-manage-template-global':
        $templateGlobalController = $this->plugin->get('template_global_controller');
        $templateGlobalController->maybeRedirectMissingTemplate();
        $templateGlobalController->maybeRedirectInvalidData();
        break;

      case 'yaml-cf-edit-schema':
        $this->plugin->get('schema_editor_controller')->maybeRedirectMissingTemplate();
        break;

      case 'yaml-cf-edit-partial':
        $this->plugin->get('partial_controller')->maybeRedirectInvalid();
        break;
    }
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
}
