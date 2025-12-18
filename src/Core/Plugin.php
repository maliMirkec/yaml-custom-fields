<?php
/**
 * Plugin Class
 * Main plugin class that coordinates all services
 */

namespace YamlCF\Core;

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

    $this->container->register('global_data_repository', function($c) {
      return new \YamlCF\Data\Repositories\GlobalDataRepository();
    });

    $this->container->register('partial_data_repository', function($c) {
      return new \YamlCF\Data\Repositories\PartialDataRepository();
    });

    $this->container->register('data_object_repository', function($c) {
      return new \YamlCF\Data\Repositories\DataObjectRepository();
    });

    $this->container->register('template_settings_repository', function($c) {
      return new \YamlCF\Data\Repositories\TemplateSettingsRepository();
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

    $this->container->register('schema_storage', function($c) {
      return new \YamlCF\Schema\SchemaStorage(
        $c->get('schema_parser')
      );
    });

    // Template Management
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
      return new \YamlCF\Rendering\FieldRenderer();
    });

    // Form Handling
    $this->container->register('nonce_verifier', function($c) {
      return new \YamlCF\Form\NonceVerifier();
    });

    $this->container->register('data_sanitizer', function($c) {
      return new \YamlCF\Form\DataSanitizer();
    });

    $this->container->register('attachment_validator', function($c) {
      return new \YamlCF\Form\AttachmentValidator();
    });

    $this->container->register('form_handler', function($c) {
      return new \YamlCF\Form\FormHandler(
        $c->get('nonce_verifier'),
        $c->get('data_sanitizer'),
        $c->get('attachment_validator')
      );
    });

    // Cache Management
    $this->container->register('cache_manager', function($c) {
      return new \YamlCF\Cache\CacheManager();
    });

    $this->container->register('transient_manager', function($c) {
      return new \YamlCF\Cache\TransientManager();
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
      return new \YamlCF\ImportExport\SettingsImporter();
    });

    $this->container->register('page_data_exporter', function($c) {
      return new \YamlCF\ImportExport\PageDataExporter();
    });

    $this->container->register('page_data_importer', function($c) {
      return new \YamlCF\ImportExport\PageDataImporter();
    });

    $this->container->register('data_object_exporter', function($c) {
      return new \YamlCF\ImportExport\DataObjectExporter(
        $c->get('attachment_validator')
      );
    });

    $this->container->register('data_object_importer', function($c) {
      return new \YamlCF\ImportExport\DataObjectImporter(
        $c->get('attachment_validator')
      );
    });

    // Admin Management
    $this->container->register('menu_manager', function($c) {
      return new \YamlCF\Admin\MenuManager(
        $c->get('schema_parser'),
        $c->get('template_cache')
      );
    });

    $this->container->register('asset_manager', function($c) {
      return new \YamlCF\Admin\AssetManager(
        $c->get('template_resolver'),
        $c->get('schema_parser')
      );
    });

    // Admin Controllers
    $this->container->register('template_schema_controller', function($c) {
      return new \YamlCF\Admin\Controllers\TemplateSchemaController(
        $c->get('template_cache'),
        $c->get('schema_storage'),
        $c->get('notification_manager')
      );
    });

    $this->container->register('partial_controller', function($c) {
      return new \YamlCF\Admin\Controllers\PartialController(
        $c->get('template_cache'),
        $c->get('schema_storage')
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
        $c->get('schema_storage')
      );
    });

    $this->container->register('template_global_controller', function($c) {
      return new \YamlCF\Admin\Controllers\TemplateGlobalController(
        $c->get('template_cache'),
        $c->get('schema_storage')
      );
    });

    $this->container->register('data_object_controller', function($c) {
      return new \YamlCF\Admin\Controllers\DataObjectController();
    });

    $this->container->register('validation_controller', function($c) {
      return new \YamlCF\Admin\Controllers\ValidationController();
    });

    $this->container->register('export_import_controller', function($c) {
      return new \YamlCF\Admin\Controllers\ExportImportController();
    });

    $this->container->register('docs_controller', function($c) {
      return new \YamlCF\Admin\Controllers\DocsController();
    });

    // Public API Accessors
    $this->container->register('field_accessor', function($c) {
      return new \YamlCF\PublicAPI\FieldAccessor(
        $c->get('post_data_repository'),
        $c->get('template_resolver'),
        $c->get('schema_storage')
      );
    });

    $this->container->register('global_field_accessor', function($c) {
      return new \YamlCF\PublicAPI\GlobalFieldAccessor(
        $c->get('global_data_repository'),
        $c->get('schema_storage')
      );
    });

    $this->container->register('image_accessor', function($c) {
      return new \YamlCF\PublicAPI\ImageAccessor(
        $c->get('field_accessor')
      );
    });

    $this->container->register('file_accessor', function($c) {
      return new \YamlCF\PublicAPI\FileAccessor(
        $c->get('field_accessor')
      );
    });

    $this->container->register('taxonomy_accessor', function($c) {
      return new \YamlCF\PublicAPI\TaxonomyAccessor(
        $c->get('field_accessor')
      );
    });

    $this->container->register('data_object_accessor', function($c) {
      return new \YamlCF\PublicAPI\DataObjectAccessor(
        $c->get('field_accessor'),
        $c->get('data_object_repository')
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

  /**
   * Uninstall plugin (clean up all data)
   *
   * @return void
   */
  public function uninstall() {
    // Delete all plugin options
    delete_option('yaml_cf_template_settings');
    delete_option('yaml_cf_schemas');
    delete_option('yaml_cf_partial_data');
    delete_option('yaml_cf_global_schema');
    delete_option('yaml_cf_global_data');
    delete_option('yaml_cf_template_global_data');
    delete_option('yaml_cf_tracked_posts');

    // Delete all post meta for this plugin
    delete_post_meta_by_key('_yaml_cf_data');
    delete_post_meta_by_key('_yaml_cf_use_template_global');
    delete_post_meta_by_key('_yaml_cf_use_template_global_fields');

    // Delete data object types and their entries
    $data_object_types = get_option('yaml_cf_data_object_types', []);
    if (!empty($data_object_types)) {
      foreach ($data_object_types as $type_slug => $type_data) {
        delete_option('yaml_cf_data_object_entries_' . $type_slug);
      }
    }
    delete_option('yaml_cf_data_object_types');

    // Clear template cache
    delete_transient('yaml_cf_theme_templates');
  }
}
