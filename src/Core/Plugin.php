<?php

/**
 * Plugin Class
 * Main plugin class that coordinates all services
 */

namespace YamlCF\Core;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
class Plugin {
  private static $instance = null;
  private $container;
  private $hookManager;

  /**
   * Get singleton instance
   *
   * @return Plugin
   */
  public static function getInstance() {
    if (null === self::$instance) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Private constructor (singleton pattern)
   */
  private function __construct() {
    $this->container = new ServiceContainer();
    $this->registerServices();
    $this->hookManager = new HookManager($this);
    $this->hookManager->registerHooks();
  }

  /**
   * Register all services in the container
   *
   * @return void
   */
  private function registerServices() {
    // Data Repositories
    $this->container->register('post_data_repository', function($c) {
      return new \YamlCF\Data\Repositories\PostDataRepository();
    });

    $this->container->register('data_object_repository', function($c) {
      return new \YamlCF\Data\Repositories\DataObjectRepository();
    });

    // Schema Management
    $this->container->register('schema_parser', function($c) {
      return new \YamlCF\Schema\SchemaParser();
    });

    $this->container->register('schema_validator', function($c) {
      return new \YamlCF\Schema\SchemaValidator();
    });

    $this->container->register('field_normalizer', function($c) {
      return new \YamlCF\Schema\FieldNormalizer();
    });

    $this->container->register('schema_diff', function($c) {
      return new \YamlCF\Schema\SchemaDiff();
    });

    $this->container->register('section_grouper', function($c) {
      return new \YamlCF\Schema\SectionGrouper();
    });

    // Template Management
    $this->container->register('shortcode', function($c) {
      return new \YamlCF\Rendering\Shortcode(
        $c->get('post_schema_locator'),
        $c->get('field_accessor'),
        $c->get('global_field_accessor'),
        $c->get('field_resolver'),
        $c->get('template_resolver')
      );
    });

    $this->container->register('rest_api', function($c) {
      return new \YamlCF\Rest\RestApi(
        $c->get('post_data_repository'),
        $c->get('data_object_repository'),
        $c->get('post_schema_locator'),
        $c->get('field_resolver'),
        $c->get('global_field_accessor')
      );
    });

    $this->container->register('post_schema_locator', function($c) {
      return new \YamlCF\Schema\PostSchemaLocator(
        $c->get('template_resolver'),
        $c->get('schema_parser'),
        $c->get('field_normalizer')
      );
    });

    $this->container->register('upgrader', function($c) {
      return new \YamlCF\Core\Upgrader();
    });

    $this->container->register('template_scanner', function($c) {
      return new \YamlCF\Template\TemplateScanner();
    });

    $this->container->register('template_cache', function($c) {
      return new \YamlCF\Template\TemplateCache(
        $c->get('template_scanner')
      );
    });

    $this->container->register('template_resolver', function($c) {
      return new \YamlCF\Template\TemplateResolver();
    });

    $this->container->register('template_name_formatter', function($c) {
      return new \YamlCF\Template\TemplateNameFormatter();
    });

    // Field Rendering
    $this->container->register('field_renderer', function($c) {
      return new \YamlCF\Rendering\FieldRenderer(
        $c->get('field_normalizer'),
        $c->get('section_grouper'),
        $c->get('schema_parser'),
        $c->get('data_object_repository')
      );
    });

    // Form Handling
    $this->container->register('data_sanitizer', function($c) {
      return new \YamlCF\Form\DataSanitizer();
    });

    $this->container->register('attachment_validator', function($c) {
      return new \YamlCF\Form\AttachmentValidator();
    });

    // The admin screens' forms, handled on admin_init
    $this->container->register('form_handler', function($c) {
      return new \YamlCF\Form\FormHandler(
        $c->get('post_import_handler'),
        $c->get('schema_form_handler'),
        $c->get('content_form_handler'),
        $c->get('export_request_handler'),
        $c->get('data_object_controller')
      );
    });

    $this->container->register('post_import_handler', function($c) {
      return new \YamlCF\Form\PostImportHandler($c->get('notification_manager'));
    });

    $this->container->register('schema_form_handler', function($c) {
      return new \YamlCF\Form\SchemaFormHandler(
        $c->get('notification_manager'),
        $c->get('schema_validator'),
        $c->get('schema_diff'),
        $c->get('field_resolver')
      );
    });

    $this->container->register('content_form_handler', function($c) {
      return new \YamlCF\Form\ContentFormHandler(
        $c->get('notification_manager'),
        $c->get('schema_parser'),
        $c->get('data_sanitizer'),
        $c->get('schema_diff'),
        $c->get('cache_manager')
      );
    });

    $this->container->register('export_request_handler', function($c) {
      return new \YamlCF\Form\ExportRequestHandler();
    });

    // The "Custom fields" box in the post editor
    $this->container->register('meta_box', function($c) {
      return new \YamlCF\PostEditor\MetaBox(
        $c->get('field_renderer'),
        $c->get('template_resolver'),
        $c->get('schema_parser'),
        $c->get('field_normalizer'),
        $c->get('section_grouper')
      );
    });

    $this->container->register('meta_box_saver', function($c) {
      return new \YamlCF\PostEditor\MetaBoxSaver(
        $c->get('data_sanitizer'),
        $c->get('template_resolver'),
        $c->get('schema_parser'),
        $c->get('schema_diff'),
        $c->get('cache_manager')
      );
    });

    // Cache Management
    $this->container->register('cache_manager', function($c) {
      return new \YamlCF\Cache\CacheManager();
    });

    // Notification Management
    $this->container->register('notification_manager', function($c) {
      return new \YamlCF\Core\NotificationManager();
    });

    // Import/Export
    $this->container->register('settings_exporter', function($c) {
      return new \YamlCF\ImportExport\SettingsExporter();
    });

    $this->container->register('settings_importer', function($c) {
      return new \YamlCF\ImportExport\SettingsImporter(
        $c->get('data_sanitizer'),
        $c->get('schema_validator'),
        $c->get('schema_parser'),
        $c->get('cache_manager')
      );
    });

    $this->container->register('page_data_exporter', function($c) {
      return new \YamlCF\ImportExport\PageDataExporter(
        $c->get('template_resolver'),
        $c->get('post_schema_locator')
      );
    });

    $this->container->register('page_data_importer', function($c) {
      return new \YamlCF\ImportExport\PageDataImporter(
        $c->get('data_sanitizer'),
        $c->get('attachment_validator'),
        $c->get('schema_parser'),
        $c->get('template_resolver'),
        $c->get('cache_manager')
      );
    });

    $this->container->register('data_object_exporter', function($c) {
      return new \YamlCF\ImportExport\DataObjectExporter(
        $c->get('attachment_validator'),
        $c->get('schema_parser')
      );
    });

    $this->container->register('data_object_importer', function($c) {
      return new \YamlCF\ImportExport\DataObjectImporter(
        $c->get('attachment_validator'),
        $c->get('data_sanitizer'),
        $c->get('schema_validator'),
        $c->get('schema_parser')
      );
    });

    $this->container->register('bundle_catalog', function($c) {
      return new \YamlCF\ImportExport\BundleCatalog(
        $c->get('schema_parser'),
        $c->get('template_name_formatter')
      );
    });

    $this->container->register('bundle_exporter', function($c) {
      return new \YamlCF\ImportExport\BundleExporter(
        $c->get('page_data_exporter')
      );
    });

    $this->container->register('bundle_importer', function($c) {
      return new \YamlCF\ImportExport\BundleImporter(
        $c->get('settings_importer'),
        $c->get('page_data_importer'),
        $c->get('data_object_importer')
      );
    });

    // AJAX Handlers (wired to wp_ajax_* actions by HookManager)
    $this->container->register('ajax_save_template_settings', function($c) {
      return new \YamlCF\Ajax\Handlers\SaveTemplateSettings();
    });

    $this->container->register('ajax_refresh_templates', function($c) {
      return new \YamlCF\Ajax\Handlers\RefreshTemplates(
        $c->get('template_cache'),
        $c->get('notification_manager')
      );
    });

    $this->container->register('ajax_toggle_use_global', function($c) {
      return new \YamlCF\Ajax\Handlers\ToggleUseGlobal();
    });

    $this->container->register('ajax_save_schema', function($c) {
      return new \YamlCF\Ajax\Handlers\SaveSchema(
        $c->get('schema_validator'),
        $c->get('schema_parser'),
        $c->get('schema_diff')
      );
    });

    $this->container->register('ajax_get_schema', function($c) {
      return new \YamlCF\Ajax\Handlers\GetSchema();
    });

    $this->container->register('ajax_get_partial_data', function($c) {
      return new \YamlCF\Ajax\Handlers\GetPartialData($c->get('schema_parser'));
    });

    $this->container->register('ajax_save_partial_data', function($c) {
      return new \YamlCF\Ajax\Handlers\SavePartialData(
        $c->get('data_sanitizer'),
        $c->get('schema_parser'),
        $c->get('cache_manager')
      );
    });

    $this->container->register('ajax_export_settings', function($c) {
      return new \YamlCF\Ajax\Handlers\ExportSettings($c->get('settings_exporter'));
    });

    $this->container->register('ajax_import_settings', function($c) {
      return new \YamlCF\Ajax\Handlers\ImportSettings($c->get('settings_importer'));
    });

    $this->container->register('ajax_get_posts_with_data', function($c) {
      return new \YamlCF\Ajax\Handlers\GetPostsWithData(
        $c->get('post_data_repository'),
        $c->get('template_resolver')
      );
    });

    $this->container->register('ajax_export_page_data', function($c) {
      return new \YamlCF\Ajax\Handlers\ExportPageData($c->get('page_data_exporter'));
    });

    $this->container->register('ajax_import_page_data', function($c) {
      return new \YamlCF\Ajax\Handlers\ImportPageData($c->get('page_data_importer'));
    });

    $this->container->register('ajax_import_data_objects', function($c) {
      return new \YamlCF\Ajax\Handlers\ImportDataObjects($c->get('data_object_importer'));
    });

    $this->container->register('ajax_export_bundle', function($c) {
      return new \YamlCF\Ajax\Handlers\ExportBundle($c->get('bundle_exporter'));
    });

    $this->container->register('ajax_inspect_bundle', function($c) {
      return new \YamlCF\Ajax\Handlers\InspectBundle(
        $c->get('bundle_catalog'),
        $c->get('bundle_importer')
      );
    });

    $this->container->register('ajax_import_bundle', function($c) {
      return new \YamlCF\Ajax\Handlers\ImportBundle($c->get('bundle_importer'));
    });

    $this->container->register('ajax_render_block_item', function($c) {
      return new \YamlCF\Ajax\Handlers\RenderBlockItem($c->get('schema_parser'), $c->get('field_renderer'));
    });

    // Search behind the taxonomy and data_object pickers
    $this->container->register('ajax_search_terms', function($c) {
      return new \YamlCF\Ajax\Handlers\SearchTerms();
    });

    $this->container->register('ajax_search_data_objects', function($c) {
      return new \YamlCF\Ajax\Handlers\SearchDataObjects($c->get('data_object_repository'));
    });

    // Admin Management
    $this->container->register('menu_manager', function($c) {
      return new \YamlCF\Admin\MenuManager(
        $c->get('schema_parser'),
        $c->get('template_cache')
      );
    });

    $this->container->register('asset_manager', function($c) {
      return new \YamlCF\Admin\AssetManager($c->get('template_resolver'));
    });

    // Admin Controllers
    $this->container->register('template_schema_controller', function($c) {
      return new \YamlCF\Admin\Controllers\TemplateSchemaController(
        $c->get('template_cache'),
        $c->get('schema_parser'),
        $c->get('notification_manager'),
        new \YamlCF\Template\TemplateSorter()
      );
    });

    $this->container->register('partial_controller', function($c) {
      return new \YamlCF\Admin\Controllers\PartialController(
        $c->get('template_cache'),
        $c->get('schema_parser')
      );
    });

    $this->container->register('schema_editor_controller', function($c) {
      return new \YamlCF\Admin\Controllers\SchemaEditorController(
        $c->get('template_cache')
      );
    });

    $this->container->register('global_schema_controller', function($c) {
      return new \YamlCF\Admin\Controllers\GlobalSchemaController();
    });

    $this->container->register('global_data_controller', function($c) {
      return new \YamlCF\Admin\Controllers\GlobalDataController(
        $c->get('schema_parser')
      );
    });

    $this->container->register('template_global_controller', function($c) {
      return new \YamlCF\Admin\Controllers\TemplateGlobalController(
        $c->get('template_cache'),
        $c->get('schema_parser')
      );
    });

    $this->container->register('data_object_controller', function($c) {
      return new \YamlCF\Admin\Controllers\DataObjectController(
        $c->get('schema_parser'),
        $c->get('schema_validator'),
        $c->get('notification_manager')
      );
    });

    $this->container->register('validation_controller', function($c) {
      return new \YamlCF\Admin\Controllers\ValidationController(
        $c->get('attachment_validator'),
        $c->get('post_data_repository'),
        $c->get('post_schema_locator'),
        $c->get('schema_diff'),
        $c->get('notification_manager')
      );
    });

    $this->container->register('export_import_controller', function($c) {
      return new \YamlCF\Admin\Controllers\ExportImportController($c->get('bundle_catalog'));
    });

    $this->container->register('docs_controller', function($c) {
      return new \YamlCF\Admin\Controllers\DocsController();
    });

    // Template API: what yaml_cf_get_field() and the other theme functions call
    $this->container->register('field_resolver', function($c) {
      return new \YamlCF\PublicAPI\FieldResolver(
        $c->get('template_resolver'),
        $c->get('schema_parser'),
        $c->get('data_sanitizer')
      );
    });

    $this->container->register('field_accessor', function($c) {
      return new \YamlCF\PublicAPI\FieldAccessor($c->get('field_resolver'));
    });

    $this->container->register('global_field_accessor', function($c) {
      return new \YamlCF\PublicAPI\GlobalFieldAccessor($c->get('field_resolver'));
    });

    $this->container->register('image_accessor', function($c) {
      return new \YamlCF\PublicAPI\ImageAccessor($c->get('field_accessor'));
    });

    $this->container->register('file_accessor', function($c) {
      return new \YamlCF\PublicAPI\FileAccessor($c->get('field_accessor'));
    });

    $this->container->register('taxonomy_accessor', function($c) {
      return new \YamlCF\PublicAPI\TaxonomyAccessor($c->get('field_accessor'));
    });

    $this->container->register('post_type_accessor', function($c) {
      return new \YamlCF\PublicAPI\PostTypeAccessor($c->get('field_accessor'));
    });

    $this->container->register('data_object_accessor', function($c) {
      return new \YamlCF\PublicAPI\DataObjectAccessor(
        $c->get('field_accessor'),
        $c->get('data_object_repository'),
        $c->get('field_resolver')
      );
    });
  }

  /**
   * Get the service container
   *
   * @return ServiceContainer
   */
  public function getContainer() {
    return $this->container;
  }

  /**
   * Get a service from the container
   *
   * @param string $name Service name
   * @return mixed Service instance
   */
  public function get($name) {
    return $this->container->get($name);
  }
}
