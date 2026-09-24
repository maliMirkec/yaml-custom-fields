<?php

namespace YamlCF\Admin\Controllers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Helpers\RequestHelper;

/**
 * Controller for data object management pages
 * Handles: data objects list, edit data object type, manage entries
 */
class DataObjectController extends AdminController {
  private $schemaStorage;
  private $savedTypeSlug = null;
  private $schemaValidator;
  private $submittedType = null;

  private $notifications;

  public function __construct($schemaStorage, $schemaValidator = null, $notifications = null) {
    $this->schemaStorage = $schemaStorage;
    $this->schemaValidator = $schemaValidator ? $schemaValidator : new \YamlCF\Schema\SchemaValidator();
    $this->notifications = $notifications ? $notifications : new \YamlCF\Core\NotificationManager();
  }

  /**
   * Render data objects list page
   */
  public function renderList() {
    $this->checkPermission();
    $this->loadTemplate('data-objects-page.php');
  }

  /**
   * Handle form submission for data object types and entries
   * This runs on admin_init, before any output
   */
  public function handleFormSubmissions() {
    global $pagenow;

    // Skip on plugin management pages (activation, deactivation, updates, installation)
    if (in_array($pagenow, ['plugins.php', 'update.php', 'update-core.php', 'plugin-install.php'], true)) {
      return;
    }

    // Skip during plugin upload/update operations
    if (isset($_GET['action']) && in_array(sanitize_text_field(wp_unslash($_GET['action'])), ['activate', 'deactivate', 'upload-plugin', 'update-selected'], true)) {
      return;
    }

    // Skip during plugin file operations
    if (isset($_POST['action']) && in_array(sanitize_text_field(wp_unslash($_POST['action'])), ['upload-plugin', 'update-plugin'], true)) {
      return;
    }

    // Handle data object type delete
    if (isset($_POST['yaml_cf_delete_type_nonce'])) {
      if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_delete_type_nonce'])), 'yaml_cf_delete_type')) {
        wp_die(esc_html__('This link has expired. Go back, reload the page and try again.', 'yaml-custom-fields'));
      }

      if (!current_user_can('manage_options')) {
        wp_die(esc_html__('This action needs administrator permissions.', 'yaml-custom-fields'));
      }

      // Not sanitize_key(): older imports may have stored slugs it would alter (the slug must exist below)
      $type_slug = isset($_POST['type_slug']) ? sanitize_text_field(wp_unslash($_POST['type_slug'])) : '';

      if (!empty($type_slug)) {
        $data_object_types = get_option('yaml_cf_data_object_types', []);

        if (isset($data_object_types[$type_slug])) {
          // Delete the type
          unset($data_object_types[$type_slug]);
          update_option('yaml_cf_data_object_types', $data_object_types);

          // Delete all entries for this type
          delete_option('yaml_cf_data_object_entries_' . $type_slug);

          /* translators: %s: data object type slug */
          $this->notifications->success(sprintf(__('The list "%s" and all its items were deleted.', 'yaml-custom-fields'), $type_slug));
        }

        // Redirect to prevent form resubmission
        wp_safe_redirect(admin_url('admin.php?page=yaml-cf-data-objects'));
        exit;
      }
    }

    // Handle data object type save
    if (isset($_POST['yaml_cf_save_data_object_type_nonce'])) {
      if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_save_data_object_type_nonce'])), 'yaml_cf_save_data_object_type')) {
        wp_die(esc_html__('This link has expired. Go back, reload the page and try again.', 'yaml-custom-fields'));
      }

      if (!current_user_can('manage_options')) {
        wp_die(esc_html__('This action needs administrator permissions.', 'yaml-custom-fields'));
      }

      // Same lookup as the entry handlers; older imports may have stored slugs that sanitize_key() would alter
      $type_id = RequestHelper::getParam('type_id', '');
      $type_name = \YAML_Custom_Fields::post_sanitized('type_name', '', 'sanitize_text_field');
      $new_type_slug = \YAML_Custom_Fields::post_sanitized('type_slug', '', 'sanitize_key');
      // Sanitized like the other schema editors and the data object import
      $schema_yaml = \YAML_Custom_Fields::post_sanitized('schema', '', 'sanitize_textarea_field');

      if (empty($type_name) || empty($new_type_slug)) {
        $this->notifications->error(__('Enter a name and a short name.', 'yaml-custom-fields'), ['title' => __('The list was not saved.', 'yaml-custom-fields')]);
        $this->submittedType = ['slug' => $new_type_slug, 'name' => $type_name, 'schema' => $schema_yaml];
        return;
      }

      // Validate like template schemas (the data object import rejects invalid schemas too)
      $validation_result = $this->schemaValidator->validate($schema_yaml);
      if (!$validation_result['valid']) {
        $this->notifications->error($validation_result['message'], [
          'title' => __('The list was not saved.', 'yaml-custom-fields'),
          'details' => [__('The changes are still in the form below. Fix the problem and save again.', 'yaml-custom-fields')],
        ]);
        $this->submittedType = ['slug' => $new_type_slug, 'name' => $type_name, 'schema' => $schema_yaml];
        return;
      }

      $data_object_types = get_option('yaml_cf_data_object_types', []);
      $data_object_types[$new_type_slug] = [
        'name' => $type_name,
        'schema' => $schema_yaml,
      ];

      update_option('yaml_cf_data_object_types', $data_object_types);

      /* translators: %s: data object type name */
      $this->notifications->success(sprintf(__('The list "%s" was saved.', 'yaml-custom-fields'), $type_name));
      $this->savedTypeSlug = $new_type_slug;

      // Update URL if slug changed (only redirect if creating new or slug changed)
      if ($type_id !== $new_type_slug) {
        wp_safe_redirect(admin_url('admin.php?page=yaml-cf-edit-data-object-type&type_id=' . rawurlencode($new_type_slug)));
        exit;
      }
    }

    // Handle data object entry save
    if (isset($_POST['yaml_cf_save_entry_nonce'])) {
      if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_save_entry_nonce'])), 'yaml_cf_save_entry')) {
        wp_die(esc_html__('This link has expired. Go back, reload the page and try again.', 'yaml-custom-fields'));
      }

      if (!current_user_can('manage_options')) {
        wp_die(esc_html__('This action needs administrator permissions.', 'yaml-custom-fields'));
      }

      $type_id = RequestHelper::getParam('type_id');
      if (!$type_id) {
        wp_safe_redirect(admin_url('admin.php?page=yaml-cf-data-objects'));
        exit;
      }

      // Get the schema for this type
      $data_object_types = get_option('yaml_cf_data_object_types', []);
      if (!isset($data_object_types[$type_id])) {
        wp_safe_redirect(admin_url('admin.php?page=yaml-cf-data-objects'));
        exit;
      }

      $schema_yaml = $data_object_types[$type_id]['schema'];
      $schema = $this->schemaStorage->parseSchema($schema_yaml);

      // Get entry ID from form, or generate new one
      $entry_id_from_form = \YAML_Custom_Fields::post_raw('entry_id', '');
      if (!empty($entry_id_from_form)) {
        $entry_id_to_save = sanitize_text_field($entry_id_from_form);
      } else {
        $entry_id_to_save = uniqid('entry_');
      }

      // Sanitize entry data using the plugin instance
      $plugin = \YAML_Custom_Fields::get_instance();

      // Get raw form data; it is sanitized per field type against the schema below
      // (a sanitize_text_field() pre-pass would strip rich-text HTML and code newlines)
      $raw_form_data = \YAML_Custom_Fields::post_raw('yaml_cf', []);
      if (!is_array($raw_form_data)) {
        $raw_form_data = [];
      }

      $entry_data = $plugin->sanitize_field_data($raw_form_data, $schema);

      $entries = get_option('yaml_cf_data_object_entries_' . $type_id, []);
      $entries[$entry_id_to_save] = $entry_data;
      update_option('yaml_cf_data_object_entries_' . $type_id, $entries);

      $this->notifications->success(__('Item saved.', 'yaml-custom-fields'));

      // Redirect back to edit page to prevent form resubmission
      wp_safe_redirect(admin_url('admin.php?page=yaml-cf-manage-data-object-entries&type_id=' . rawurlencode($type_id) . '&action=edit&entry=' . rawurlencode($entry_id_to_save)));
      exit;
    }

    // Handle data object entry delete
    if (isset($_POST['yaml_cf_delete_entry_nonce'])) {
      if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_delete_entry_nonce'])), 'yaml_cf_delete_entry')) {
        wp_die(esc_html__('This link has expired. Go back, reload the page and try again.', 'yaml-custom-fields'));
      }

      if (!current_user_can('manage_options')) {
        wp_die(esc_html__('This action needs administrator permissions.', 'yaml-custom-fields'));
      }

      $type_id = RequestHelper::getParam('type_id');
      if (!$type_id) {
        wp_safe_redirect(admin_url('admin.php?page=yaml-cf-data-objects'));
        exit;
      }

      $entry_id_to_delete = isset($_POST['entry_id']) ? sanitize_text_field(wp_unslash($_POST['entry_id'])) : '';
      $entries = get_option('yaml_cf_data_object_entries_' . $type_id, []);

      if (isset($entries[$entry_id_to_delete])) {
        unset($entries[$entry_id_to_delete]);
        update_option('yaml_cf_data_object_entries_' . $type_id, $entries);

        $this->notifications->success(__('Item deleted.', 'yaml-custom-fields'));
      }

      // Redirect to prevent form resubmission
      wp_safe_redirect(admin_url('admin.php?page=yaml-cf-manage-data-object-entries&type_id=' . rawurlencode($type_id)));
      exit;
    }
  }

  /**
   * Render edit data object type page
   */
  public function renderEdit() {
    $this->checkPermission();

    // Get type_id from URL (or use saved slug if we just saved)
    $type_id = $this->savedTypeSlug ? $this->savedTypeSlug : RequestHelper::getParam('type_id', '');

    // Get data object types
    $data_object_types = get_option('yaml_cf_data_object_types', []);
    $is_editing = !empty($type_id) && isset($data_object_types[$type_id]);

    // Get current data
    $type_name = $is_editing ? $data_object_types[$type_id]['name'] : '';
    $schema_yaml = $is_editing ? $data_object_types[$type_id]['schema'] : '';

    // After a failed save, show what was submitted instead of the stored values
    if ($this->submittedType !== null) {
      $type_name = $this->submittedType['name'];
      $schema_yaml = $this->submittedType['schema'];
      if (!$is_editing) {
        $type_id = $this->submittedType['slug'];
      }
    }

    // Get messages
    // Messages are shown as queued admin notices (see NotificationManager)
    $success_message = '';
    $error_message = '';

    // Load template
    $this->loadTemplate('edit-data-object-type-page.php', compact(
      'type_id',
      'type_name',
      'schema_yaml',
      'is_editing',
      'success_message',
      'error_message'
    ));
  }

  /**
   * Render manage data object entries page
   */
  public function renderEntries() {
    $this->checkPermission();

    // Get type_id from URL
    $type_id = RequestHelper::getParam('type_id');
    if (!$type_id) {
      wp_safe_redirect(admin_url('admin.php?page=yaml-cf-data-objects'));
      exit;
    }

    // Get data object types
    $data_object_types = get_option('yaml_cf_data_object_types', []);
    if (!isset($data_object_types[$type_id])) {
      wp_safe_redirect(admin_url('admin.php?page=yaml-cf-data-objects'));
      exit;
    }

    $type_name = $data_object_types[$type_id]['name'];
    $schema_yaml = $data_object_types[$type_id]['schema'];

    // Parse schema using schemaStorage
    $schema = $this->schemaStorage->parseSchema($schema_yaml);

    // Get all entries
    $entries = get_option('yaml_cf_data_object_entries_' . $type_id, []);

    // Localize the schema and which one it is, so that adding a section can ask
    // the server to render it (see YamlCF\Ajax\Handlers\RenderBlockItem). The
    // collection entries that used to travel with the page are gone: the admin
    // script no longer builds fields itself.
    $this->localizeScript([
      'schema' => $schema,
      'schemaSource' => ['source' => 'data_object', 'key' => $type_id],
    ]);

    // Load template
    $this->loadTemplate('manage-data-object-entries-page.php', compact(
      'type_id',
      'type_name',
      'schema',
      'entries'
    ));
  }

  /**
   * Render method - determines which view to show based on page
   */
  public function render() {
    // Default to list view
    $this->renderList();
  }
}
