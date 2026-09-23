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

    // Queued notices (saves, imports, errors) shown as standard admin notices
    add_action('admin_notices', [$this, 'renderNotices']);

    // Plugin credit in the admin footer on plugin screens
    add_filter('admin_footer_text', [$this, 'adminFooterText']);

    // Body class for plugin screens (used to show notices only after WordPress moves them below the header)
    add_filter('admin_body_class', [$this, 'adminBodyClass']);
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
   * Print queued notices
   */
  public function renderNotices() {
    $this->plugin->get('notification_manager')->renderAdminNotices();
  }

  /**
   * Replace the admin footer text on plugin screens with a plugin credit
   *
   * @param string $text Default footer text
   * @return string Footer text
   */
  public function adminFooterText($text) {
    if (!$this->isPluginScreen()) {
      return $text;
    }

    return sprintf(
      /* translators: 1: plugin name, 2: link to the author's website */
      esc_html__('%1$s by %2$s', 'yaml-custom-fields'),
      '<strong>' . esc_html__('YAML Custom Fields', 'yaml-custom-fields') . '</strong>',
      '<a href="https://www.cita.hr" target="_blank" rel="noopener">Studio Cita</a>'
    );
  }

  /**
   * Add a body class on plugin screens
   *
   * @param string $classes Space-separated body classes
   * @return string Body classes
   */
  public function adminBodyClass($classes) {
    return $this->isPluginScreen() ? $classes . ' yaml-cf-screen' : $classes;
  }

  /**
   * Whether the current admin page belongs to this plugin
   *
   * @return bool
   */
  private function isPluginScreen() {
    $page = \YamlCF\Helpers\RequestHelper::getParam('page');
    return $page === 'yaml-custom-fields' || strpos($page, 'yaml-cf-') === 0;
  }

  /**
   * Register AJAX handlers
   */
  private function registerAjaxHandlers() {
    // wp_ajax_{action} => container service. Handlers are created only when their action runs.
    $handlers = [
      'yaml_cf_save_template_settings' => 'ajax_save_template_settings',
      'yaml_cf_refresh_templates' => 'ajax_refresh_templates',
      'yaml_cf_toggle_use_global' => 'ajax_toggle_use_global',
      'yaml_cf_save_schema' => 'ajax_save_schema',
      'yaml_cf_get_schema' => 'ajax_get_schema',
      'yaml_cf_get_partial_data' => 'ajax_get_partial_data',
      'yaml_cf_save_partial_data' => 'ajax_save_partial_data',
      'yaml_cf_export_settings' => 'ajax_export_settings',
      'yaml_cf_import_settings' => 'ajax_import_settings',
      'yaml_cf_get_posts_with_data' => 'ajax_get_posts_with_data',
      'yaml_cf_export_page_data' => 'ajax_export_page_data',
      'yaml_cf_import_page_data' => 'ajax_import_page_data',
      'yaml_cf_import_data_objects' => 'ajax_import_data_objects',
    ];

    foreach ($handlers as $action => $service) {
      add_action('wp_ajax_' . $action, function() use ($service) {
        $this->plugin->get($service)->handle();
      });
    }
  }
}
