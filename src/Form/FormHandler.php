<?php

/**
 * Form Handler
 * The forms on the plugin's admin screens, handled on admin_init
 */

namespace YamlCF\Form;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Every form on the admin screens posts back to the page it is on and is
 * handled here, on admin_init, before anything is printed: so a save can
 * redirect (no resubmitting on reload) and its notice shows on the page it
 * lands on. Each handler recognises its own form by its nonce field and ignores
 * every other request.
 */
class FormHandler {
  private $imports;
  private $schemas;
  private $content;
  private $exports;
  private $dataObjects;

  /**
   * @param PostImportHandler $imports The editor's "Import fields"
   * @param SchemaFormHandler $schemas Schema editors
   * @param ContentFormHandler $content Site parts, site details and shared content
   * @param ExportRequestHandler $exports Download links
   * @param \YamlCF\Admin\Controllers\DataObjectController $dataObjects Collections
   */
  public function __construct($imports, $schemas, $content, $exports, $dataObjects) {
    $this->imports = $imports;
    $this->schemas = $schemas;
    $this->content = $content;
    $this->exports = $exports;
    $this->dataObjects = $dataObjects;
  }

  /**
   * Handle whichever of the plugin's own forms was submitted
   *
   * Hooked to admin_init. A form that is recognised redirects and ends the
   * request; anything else falls through untouched.
   *
   * @return void
   */
  public function handleSubmissions() {
    if (self::shouldSkip()) {
      return;
    }

    $this->imports->importPost();
    $this->schemas->saveTemplateSchema();
    $this->content->savePartialData();
    $this->schemas->saveGlobalSchema();
    $this->content->saveGlobalData();
    $this->schemas->saveTemplateGlobalSchema();
    $this->content->saveTemplateGlobalData();
  }

  /**
   * Collections: their own screens, their own controller
   *
   * @return void
   */
  public function handleDataObjectSubmissions() {
    if (self::shouldSkip()) {
      return;
    }

    $this->dataObjects->handleFormSubmissions();
  }

  /**
   * The download links
   *
   * @return ExportRequestHandler
   */
  public function exports() {
    return $this->exports;
  }

  /**
   * Check if we should skip admin_init processing
   * Prevents interference with WordPress plugin management actions
   */
  public static function shouldSkip() {
    global $pagenow;

    // Skip on plugin management pages (activation, deactivation, updates)
    if (in_array($pagenow, ['plugins.php', 'update.php', 'update-core.php', 'plugin-install.php'], true)) {
      return true;
    }

    // Skip during plugin upload/update operations
    if (isset($_GET['action'])) {
      $action = sanitize_text_field(wp_unslash($_GET['action']));
      if (in_array($action, ['activate', 'deactivate', 'upload-plugin', 'update-selected'], true)) {
        return true;
      }
    }

    // Skip during plugin file operations
    if (isset($_POST['action'])) {
      $action = sanitize_text_field(wp_unslash($_POST['action']));
      if (in_array($action, ['upload-plugin', 'update-plugin'], true)) {
        return true;
      }
    }

    return false;
  }
}
