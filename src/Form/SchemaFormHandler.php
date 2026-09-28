<?php

/**
 * Schema Form Handler
 * Saving a template schema, the site details schema, or a shared fields schema
 */

namespace YamlCF\Form;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * A schema is validated before it is saved. One that does not validate is kept
 * for a minute so the editor can show it again with the problem, and a schema
 * that drops fields says which, since their content is kept but no longer shown.
 */
class SchemaFormHandler {
  private $notifications;
  private $validator;
  private $schemaDiff;
  private $resolver;

  /**
   * @param \YamlCF\Core\NotificationManager $notifications Queued notices
   * @param \YamlCF\Schema\SchemaValidator $validator Checks a schema before it is saved
   * @param \YamlCF\Schema\SchemaDiff $schemaDiff Works out which fields a change drops
   * @param \YamlCF\PublicAPI\FieldResolver $resolver Parses schemas, once per request
   */
  public function __construct($notifications, $validator, $schemaDiff, $resolver) {
    $this->notifications = $notifications;
    $this->validator = $validator;
    $this->schemaDiff = $schemaDiff;
    $this->resolver = $resolver;
  }

  /**
   * Save a template's schema
   *
   * @return void
   */
  public function saveTemplateSchema() {
    // Handle schema save
    if (isset($_POST['yaml_cf_save_schema_nonce']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_save_schema_nonce'])), 'yaml_cf_save_schema')) {

      if (!current_user_can('manage_options')) {
        wp_die(esc_html__('This action needs administrator permissions.', 'yaml-custom-fields'));
      }

      $template = isset($_POST['template']) ? sanitize_text_field(wp_unslash($_POST['template'])) : '';
      $schema = \YamlCF\Schema\SchemaParser::sanitizeSource(\YamlCF\Helpers\RequestHelper::postRaw('schema', ''));

      // Validate YAML syntax before saving
      if (!empty($schema)) {
        $validation_result = $this->validator->validate($schema, $template);
        if (!$validation_result['valid']) {
          // Store the invalid schema in a transient so we can display it back
          set_transient('yaml_cf_invalid_schema_' . get_current_user_id(), $schema, 60);

          // Redirect back with error message (message kept out of the URL)
          $this->notify('error', $validation_result['message'], [
            'title' => __('Schema not saved:', 'yaml-custom-fields'),
            'details' => [__('The changes are still in the editor below. Fix the problem and save again.', 'yaml-custom-fields')],
          ]);
          wp_safe_redirect(add_query_arg([
            'page' => 'yaml-cf-edit-schema',
            'template' => rawurlencode($template),
            'error' => '1'
          ], admin_url('admin.php')));
          exit;
        }
      }

      $schemas = get_option('yaml_cf_schemas', []);

      // Work out what is about to be stranded before the old schema is gone
      $removed_fields = $this->removedFields(
        isset($schemas[$template]) ? $schemas[$template] : '',
        $schema
      );

      $schemas[$template] = $schema;
      update_option('yaml_cf_schemas', $schemas, false);

      // Clear any stored invalid schema
      delete_transient('yaml_cf_invalid_schema_' . get_current_user_id());

      /* translators: %s: template file name */
      $this->notify('success', sprintf(__('Schema for %s saved.', 'yaml-custom-fields'), $template));

      $this->notifyRemovedFields($removed_fields);

      wp_safe_redirect(add_query_arg([
        'page' => 'yaml-cf-edit-schema',
        'template' => rawurlencode($template)
      ], admin_url('admin.php')));
      exit;
    }
  }

  /**
   * Save the site details schema
   *
   * @return void
   */
  public function saveGlobalSchema() {
    // Handle global schema save
    if (isset($_POST['yaml_cf_save_global_schema_nonce']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_save_global_schema_nonce'])), 'yaml_cf_save_global_schema')) {

      if (!current_user_can('manage_options')) {
        wp_die(esc_html__('This action needs administrator permissions.', 'yaml-custom-fields'));
      }

      $global_schema = \YamlCF\Schema\SchemaParser::sanitizeSource(\YamlCF\Helpers\RequestHelper::postRaw('global_schema', ''));

      // Validate YAML syntax before saving
      if (!empty($global_schema)) {
        $validation_result = $this->validator->validate($global_schema);
        if (!$validation_result['valid']) {
          // Store the invalid schema in a transient so we can display it back
          set_transient('yaml_cf_invalid_global_schema_' . get_current_user_id(), $global_schema, 60);

          // Redirect back with error message (message kept out of the URL)
          $this->notify('error', $validation_result['message'], [
            'title' => __('Schema not saved:', 'yaml-custom-fields'),
            'details' => [__('The changes are still in the editor below. Fix the problem and save again.', 'yaml-custom-fields')],
          ]);
          wp_safe_redirect(add_query_arg([
            'page' => 'yaml-cf-edit-global-schema',
            'error' => '1'
          ], admin_url('admin.php')));
          exit;
        }
      }

      // Say which fields are losing their place, as the template schema editor
      // does. This one said nothing at all, so content quietly stopped showing.
      $removed_fields = $this->removedFields(
        (string) get_option('yaml_cf_global_schema', ''),
        $global_schema
      );

      update_option('yaml_cf_global_schema', $global_schema, false);

      $this->notifyRemovedFields($removed_fields);

      // Clear any stored invalid schema
      delete_transient('yaml_cf_invalid_global_schema_' . get_current_user_id());

      $this->notify('success', __('Site details schema saved.', 'yaml-custom-fields'));

      wp_safe_redirect(add_query_arg([
        'page' => 'yaml-cf-edit-global-schema'
      ], admin_url('admin.php')));
      exit;
    }
  }

  /**
   * Save a template's shared fields schema
   *
   * @return void
   */
  public function saveTemplateGlobalSchema() {
    // Handle template global schema save
    if (isset($_POST['yaml_cf_save_template_global_schema_nonce']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_save_template_global_schema_nonce'])), 'yaml_cf_save_template_global_schema')) {

      if (!current_user_can('manage_options')) {
        wp_die(esc_html__('This action needs administrator permissions.', 'yaml-custom-fields'));
      }

      $template = isset($_POST['template']) ? sanitize_text_field(wp_unslash($_POST['template'])) : '';
      $template_global_schema = \YamlCF\Schema\SchemaParser::sanitizeSource(\YamlCF\Helpers\RequestHelper::postRaw('template_global_schema', ''));

      // Validate YAML syntax before saving
      if (!empty($template_global_schema)) {
        $validation_result = $this->validator->validate($template_global_schema, $template);
        if (!$validation_result['valid']) {
          // Store the invalid schema in a transient so we can display it back
          set_transient('yaml_cf_invalid_template_global_schema_' . get_current_user_id(), $template_global_schema, 60);

          // Redirect back with error message (message kept out of the URL)
          $this->notify('error', $validation_result['message'], [
            'title' => __('Schema not saved:', 'yaml-custom-fields'),
            'details' => [__('The changes are still in the editor below. Fix the problem and save again.', 'yaml-custom-fields')],
          ]);
          wp_safe_redirect(add_query_arg([
            'page' => 'yaml-cf-edit-template-global',
            'template' => rawurlencode($template),
            'error' => '1'
          ], admin_url('admin.php')));
          exit;
        }
      }

      $template_global_schemas = get_option('yaml_cf_template_global_schemas', []);

      // Say which shared fields are losing their place, as the template schema
      // editor does. This one said nothing at all.
      $removed_fields = $this->removedFields(
        isset($template_global_schemas[$template]) ? (string) $template_global_schemas[$template] : '',
        $template_global_schema
      );

      $template_global_schemas[$template] = $template_global_schema;
      update_option('yaml_cf_template_global_schemas', $template_global_schemas, false);

      $this->notifyRemovedFields($removed_fields);

      // Clear any stored invalid schema
      delete_transient('yaml_cf_invalid_template_global_schema_' . get_current_user_id());

      /* translators: %s: template file name */
      $this->notify('success', sprintf(__('Shared fields schema for %s saved.', 'yaml-custom-fields'), $template));

      wp_safe_redirect(add_query_arg([
        'page' => 'yaml-cf-edit-template-global',
        'template' => rawurlencode($template)
      ], admin_url('admin.php')));
      exit;
    }
  }

  /**
   * Field names that a schema change is about to strand
   *
   * @param string $old_yaml The schema being replaced
   * @param string $new_yaml The schema being saved
   * @return string[] Field names present in the old schema but not the new one
   */
  private function removedFields($old_yaml, $new_yaml) {
    if (!is_string($old_yaml) || trim($old_yaml) === '') {
      return [];
    }

    return $this->schemaDiff->removedFieldNames(
      $this->resolver->parseSchemaCached($old_yaml),
      is_string($new_yaml) && trim($new_yaml) !== '' ? $this->resolver->parseSchemaCached($new_yaml) : null
    );
  }

  /**
   * Say which fields lost their place, so vanished content is never a surprise
   *
   * The values stay in the database, so putting the field name back brings the
   * content back with it. That is worth saying: the alternative is an editor
   * who thinks the text is gone.
   *
   * @param string[] $removed_fields Field names no longer in the schema
   * @return void
   */
  private function notifyRemovedFields($removed_fields) {
    if (empty($removed_fields)) {
      return;
    }

    $this->notify(
      'warning',
      sprintf(
        /* translators: %s: comma-separated list of field names */
        _n(
          'The field %s is no longer in this schema.',
          'These fields are no longer in this schema: %s',
          count($removed_fields),
          'yaml-custom-fields'
        ),
        implode(', ', $removed_fields)
      ),
      [
        'title' => __('Content is no longer shown:', 'yaml-custom-fields'),
        'details' => [
          __('Whatever was filled in for them is still saved, but nothing displays it any more.', 'yaml-custom-fields'),
          __('Adding a field back under the same name brings its content back too.', 'yaml-custom-fields'),
        ],
      ]
    );
  }

  /**
   * Queue a notice for the current user, shown on the next page load
   *
   * @param string $type success|error|warning|info
   * @param string $message Message
   * @param array $args Optional 'title' and 'details'
   * @return void
   */
  private function notify($type, $message, $args = []) {
    $this->notifications->add($message, $type, $args);
  }
}
