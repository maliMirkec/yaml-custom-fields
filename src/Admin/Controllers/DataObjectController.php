<?php
namespace YamlCF\Admin\Controllers;

use YamlCF\Helpers\RequestHelper;

/**
 * Controller for data object management pages
 * Handles: data objects list, edit data object type, manage entries
 */
class DataObjectController extends AdminController {
  private $schemaStorage;
  private $successMessage = null;
  private $errorMessage = null;
  private $savedTypeSlug = null;

  public function __construct($schemaStorage) {
    $this->schemaStorage = $schemaStorage;
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
    // Handle data object type save
    if (isset($_POST['yaml_cf_save_data_object_type_nonce'])) {
      if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_save_data_object_type_nonce'])), 'yaml_cf_save_data_object_type')) {
        wp_die(esc_html__('Security check failed', 'yaml-custom-fields'));
      }

      if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Permission denied', 'yaml-custom-fields'));
      }

      $type_id = RequestHelper::getParamKey('type_id', '');
      $type_name = \YAML_Custom_Fields::post_sanitized('type_name', '', 'sanitize_text_field');
      $new_type_slug = \YAML_Custom_Fields::post_sanitized('type_slug', '', 'sanitize_key');
      $schema_yaml = \YAML_Custom_Fields::post_raw('schema', '');

      if (empty($type_name) || empty($new_type_slug)) {
        $this->errorMessage = __('Type name and slug are required.', 'yaml-custom-fields');
        return;
      }

      $data_object_types = get_option('yaml_cf_data_object_types', []);
      $data_object_types[$new_type_slug] = [
        'name' => $type_name,
        'schema' => $schema_yaml,
      ];

      update_option('yaml_cf_data_object_types', $data_object_types);

      $this->successMessage = __('Data object type saved successfully!', 'yaml-custom-fields');
      $this->savedTypeSlug = $new_type_slug;

      // Update URL if slug changed (only redirect if creating new or slug changed)
      if ($type_id !== $new_type_slug) {
        wp_safe_redirect(admin_url('admin.php?page=yaml-cf-edit-data-object-type&type_id=' . urlencode($new_type_slug)));
        exit;
      }
    }

    // Handle data object entry save
    if (isset($_POST['yaml_cf_save_entry_nonce'])) {
      if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_save_entry_nonce'])), 'yaml_cf_save_entry')) {
        wp_die(esc_html__('Security check failed', 'yaml-custom-fields'));
      }

      if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Permission denied', 'yaml-custom-fields'));
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

      // Get form data from yaml_cf array (includes all fields including blocks)
      // First pass: basic sanitization to satisfy PHPCS
      $raw_form_data = isset($_POST['yaml_cf']) && is_array($_POST['yaml_cf']) ?
        map_deep(wp_unslash($_POST['yaml_cf']), 'sanitize_text_field') : [];

      // Second pass: schema-based sanitization that properly handles all field types
      $entry_data = $plugin->sanitize_field_data($raw_form_data, $schema);

      $entries = get_option('yaml_cf_data_object_entries_' . $type_id, []);
      $entries[$entry_id_to_save] = $entry_data;
      update_option('yaml_cf_data_object_entries_' . $type_id, $entries);

      // Set success message transient
      set_transient('yaml_cf_data_object_success_' . get_current_user_id(), 'entry_saved', 60);

      // Redirect back to edit page to prevent form resubmission
      wp_safe_redirect(admin_url('admin.php?page=yaml-cf-manage-data-object-entries&type_id=' . urlencode($type_id) . '&action=edit&entry=' . urlencode($entry_id_to_save)));
      exit;
    }

    // Handle data object entry delete
    if (isset($_POST['yaml_cf_delete_entry_nonce'])) {
      if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_delete_entry_nonce'])), 'yaml_cf_delete_entry')) {
        wp_die(esc_html__('Security check failed', 'yaml-custom-fields'));
      }

      if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Permission denied', 'yaml-custom-fields'));
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

        // Set success message transient
        set_transient('yaml_cf_data_object_success_' . get_current_user_id(), 'entry_deleted', 60);
      }

      // Redirect to prevent form resubmission
      wp_safe_redirect(admin_url('admin.php?page=yaml-cf-manage-data-object-entries&type_id=' . urlencode($type_id)));
      exit;
    }
  }

  /**
   * Render edit data object type page
   */
  public function renderEdit() {
    $this->checkPermission();

    // Get type_id from URL (or use saved slug if we just saved)
    $type_id = $this->savedTypeSlug ? $this->savedTypeSlug : RequestHelper::getParamKey('type_id', '');

    // Get data object types
    $data_object_types = get_option('yaml_cf_data_object_types', []);
    $is_editing = !empty($type_id) && isset($data_object_types[$type_id]);

    // Get current data
    $type_name = $is_editing ? $data_object_types[$type_id]['name'] : '';
    $schema_yaml = $is_editing ? $data_object_types[$type_id]['schema'] : '';

    // Get messages
    $success_message = $this->successMessage;
    $error_message = $this->errorMessage;

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

    // Localize schema data for JavaScript
    $this->localizeScript(['schema' => $schema]);

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
