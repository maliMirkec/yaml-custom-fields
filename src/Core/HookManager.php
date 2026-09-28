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

    // Bring stored data up to date before any screen reads it
    add_action('admin_init', [$this, 'maybeUpgrade'], 1);

    // The admin screens' forms and download links, before anything is printed
    $this->registerFormHooks();

    // The "Custom fields" box in the post editor
    $this->registerPostEditorHooks();

    // Values worked out for this request, forgotten when what they are built from changes
    $this->registerResolvedFieldHooks();

    // Field content over the REST API, for front ends and other software
    add_action('rest_api_init', [$this, 'registerRestApi']);

    // [ycf field="..."], so a block theme can show a field without a PHP template
    add_action('init', [$this, 'registerShortcode']);

    // Admin menu and assets
    add_action('admin_menu', [$this, 'registerAdminMenu']);
    add_action('admin_init', [$this, 'hideSubmenuItems']);

    // Content Check: remove values before any notice is printed
    add_action('admin_init', [$this, 'handleValidationSubmissions']);

    // Choices on the Settings screen, saved before any notice is printed
    add_action('admin_init', [$this, 'handleSettingsSubmissions']);
    add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);

    // Menu customization
    add_filter('admin_title', [$this, 'customizeAdminTitle'], 10, 2);

    // AJAX handlers
    $this->registerAjaxHandlers();

    // Cache clearing
    add_action('switch_theme', [$this, 'clearTemplateCache']);

    // Queued notices (saves, imports, errors) shown as standard admin notices,
    // or in the block editor's own notice area, which admin_notices never reaches
    add_action('enqueue_block_editor_assets', function () {
      $this->plugin->get('notification_manager')->sendToBlockEditor();
    });
    add_action('admin_notices', [$this, 'renderNotices']);

    // Plugin credit in the admin footer on plugin screens
    add_filter('admin_footer_text', [$this, 'adminFooterText']);

    // Body class for plugin screens (used to show notices only after WordPress moves them below the header)
    add_filter('admin_body_class', [$this, 'adminBodyClass']);
  }

  /**
   * The admin screens' forms and download links
   *
   * Each handler ignores any request that is not its own form's, so running
   * them all on every admin request costs a few isset() checks.
   *
   * @return void
   */
  private function registerFormHooks() {
    $forms = function () {
      return $this->plugin->get('form_handler');
    };

    add_action('admin_init', function () use ($forms) {
      $forms()->handleSubmissions();
    });
    add_action('admin_init', function () use ($forms) {
      $forms()->exports()->singlePost();
    });
    add_action('admin_init', function () use ($forms) {
      $forms()->exports()->settings();
    });
    add_action('admin_init', function () use ($forms) {
      $forms()->exports()->pageData();
    });
    add_action('admin_init', function () use ($forms) {
      $forms()->exports()->dataObjects();
    });
    add_action('admin_init', function () use ($forms) {
      $forms()->handleDataObjectSubmissions();
    });
  }

  /**
   * Show the custom fields box, and save it with the post
   *
   * The block editor gets a real meta box (add_meta_boxes); the classic editor
   * prints the same box above the content (edit_form_after_title), which the
   * block editor never fires. Either way the box posts yaml_cf[...] with the
   * post and is saved on save_post.
   *
   * @return void
   */
  private function registerPostEditorHooks() {
    add_action('add_meta_boxes', function ($post_type, $post = null) {
      $this->plugin->get('meta_box')->register($post_type, $post);
    }, 10, 2);

    add_action('edit_form_after_title', function ($post) {
      $this->plugin->get('meta_box')->renderAfterTitle($post);
    });

    add_action('save_post', function ($post_id) {
      $this->plugin->get('meta_box_saver')->save($post_id);
    });

    // A deleted post no longer needs its caches cleared
    add_action('delete_post', function ($post_id) {
      \YamlCF\Cache\PostTracker::untrack($post_id);
    });
  }

  /**
   * Keep the per-request memo of field values honest
   *
   * Any code that writes the meta or options values are built from, an
   * importer or another plugin included, makes the next read start afresh.
   *
   * @return void
   */
  private function registerResolvedFieldHooks() {
    $flush_for_meta = function ($meta_id, $object_id, $meta_key) {
      $this->plugin->get('field_resolver')->flushForMeta($meta_id, $object_id, $meta_key);
    };
    add_action('added_post_meta', $flush_for_meta, 10, 3);
    add_action('updated_post_meta', $flush_for_meta, 10, 3);
    add_action('deleted_post_meta', $flush_for_meta, 10, 3);

    $flush_for_option = function ($option) {
      $this->plugin->get('field_resolver')->flushForOption($option);
    };
    add_action('updated_option', $flush_for_option);
    add_action('added_option', $flush_for_option);
    add_action('deleted_option', $flush_for_option);

    // A new theme has other templates, so every post's values may change. This
    // used to pass the theme's name on as a post ID, which forgot "post 0" only.
    add_action('switch_theme', function () {
      $this->plugin->get('field_resolver')->flush();
    });
  }

  /**
   * Add the [ycf] shortcode
   */
  public function registerShortcode() {
    $this->plugin->get('shortcode')->register();
  }

  /**
   * Add the REST field and routes
   */
  public function registerRestApi() {
    $this->plugin->get('rest_api')->register();
  }

  /**
   * Run any pending data upgrade, on an administrator's page load
   *
   * admin_init also fires for every admin-ajax.php request, logged out ones
   * included, and front-end plugins make those all the time. The one-off
   * upgrade used to run in whichever came first, so a visitor's request could
   * end up moving every collection item, and several at once could start it.
   */
  public function maybeUpgrade() {
    if (wp_doing_ajax() || !current_user_can('manage_options')) {
      return;
    }

    $this->plugin->get('upgrader')->maybeUpgrade();
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
      'settings' => $this->plugin->get('settings_controller'),
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
   * Handle the forms on the Settings screen
   */
  public function handleSettingsSubmissions() {
    // Checked here so other admin screens never build the controller
    if (\YamlCF\Helpers\RequestHelper::getParam('page') !== \YamlCF\Admin\Controllers\SettingsController::PAGE) {
      return;
    }

    $this->plugin->get('settings_controller')->handleFormSubmissions();
  }

  /**
   * Handle the Content Check removal form
   */
  public function handleValidationSubmissions() {
    // Checked here so other admin screens never build the controller
    if (\YamlCF\Helpers\RequestHelper::getParam('page') !== 'yaml-cf-data-validation') {
      return;
    }

    $this->plugin->get('validation_controller')->handleFormSubmissions();
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
   * Clear template cache
   */
  public function clearTemplateCache() {
    $cacheManager = $this->plugin->get('cache_manager');
    $cacheManager->clearTemplateCache();

    // The new theme has a different set of template files
    $this->plugin->get('template_resolver')->flushTemplateExistsCache();
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
      'yaml_cf_get_posts_with_data' => 'ajax_get_posts_with_data',
      'yaml_cf_export_bundle' => 'ajax_export_bundle',
      'yaml_cf_inspect_bundle' => 'ajax_inspect_bundle',
      'yaml_cf_import_bundle' => 'ajax_import_bundle',
      'yaml_cf_render_block_item' => 'ajax_render_block_item',
      'yaml_cf_search_terms' => 'ajax_search_terms',
      'yaml_cf_search_data_objects' => 'ajax_search_data_objects',
    ];

    foreach ($handlers as $action => $service) {
      add_action('wp_ajax_' . $action, function() use ($service) {
        $this->plugin->get($service)->handle();
      });
    }
  }
}
