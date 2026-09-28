<?php

/**
 * Meta Box Saver
 * Saving the "Custom fields" box with the post
 */

namespace YamlCF\PostEditor;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Cache\PostTracker;
use YamlCF\Helpers\RequestHelper;
use YamlCF\Schema\SchemaValidator;

class MetaBoxSaver {
  private $sanitizer;
  private $templateResolver;
  private $parser;
  private $schemaDiff;
  private $cacheManager;

  /**
   * @param \YamlCF\Form\DataSanitizer $sanitizer Cleans values against the schema
   * @param \YamlCF\Template\TemplateResolver $templateResolver Which template a post uses
   * @param \YamlCF\Schema\SchemaParser $parser Reads schemas
   * @param \YamlCF\Schema\SchemaDiff $schemaDiff Keeps values of dropped fields
   * @param \YamlCF\Cache\CacheManager $cacheManager Clears what the save affects
   */
  public function __construct($sanitizer, $templateResolver, $parser, $schemaDiff, $cacheManager) {
    $this->sanitizer = $sanitizer;
    $this->templateResolver = $templateResolver;
    $this->parser = $parser;
    $this->schemaDiff = $schemaDiff;
    $this->cacheManager = $cacheManager;
  }

  public function save($post_id) {
    if (!isset($_POST['yaml_cf_meta_box_nonce'])) {
      return;
    }

    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_meta_box_nonce'])), 'yaml_cf_meta_box')) {
      return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
      return;
    }

    // WordPress creates the revision after save_post, and saving it fires
    // save_post again with this form still in $_POST. A revision has no
    // template, so it has no schema, and update_post_meta() writes its meta to
    // the parent: without this, that second pass re-saved every field as plain
    // text over the save that had just been made.
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
      return;
    }

    if (!current_user_can('edit_post', $post_id)) {
      return;
    }

    // Use post_raw() to get unslashed data safely without PHPCS warnings
    // Data will be sanitized against the schema by YamlCF\Form\DataSanitizer
    $posted_data = RequestHelper::postRaw('yaml_cf', []);

    if (!empty($posted_data) && is_array($posted_data)) {
      // Get the template for this post
      $post = get_post($post_id);
      $template = $this->templateResolver->getTemplateForPost($post);

      // Get schema for validation
      $schemas = get_option('yaml_cf_schemas', []);
      $schema = null;
      if (isset($schemas[$template])) {
        $schema = $this->parser->parse($schemas[$template]);
      }

      $sanitized_data = $this->sanitizer->sanitize($posted_data, $this->schemaForPostedFields($template, $schema, $schemas));
      $sanitized_data = $this->keepOrphanedValues($post_id, $sanitized_data, $schema);
      $sanitized_data = $this->keepValuesOfUnrenderableFields($post_id, $sanitized_data, $schema);

      // Update post meta using WordPress API (handles serialization and caching)
      // update_post_meta() unslashes its value; slash first so backslashes in field values survive
      update_post_meta($post_id, '_yaml_cf_data', wp_slash($sanitized_data));

      // Track this post for efficient cache clearing (avoids slow meta_query).
      // A page whose fields are all cleared drops off the list again, so it does
      // not keep growing with posts that have nothing left in them.
      if (empty($sanitized_data)) {
        PostTracker::untrack($post_id);
      } else {
        PostTracker::track($post_id);
      }

      // Clear caches
      $this->cacheManager->clearDataCaches($post_id);
    }

    // Save per-field template global preferences
    $use_template_global_fields = isset($_POST['yaml_cf_use_template_global_fields']) && is_array($_POST['yaml_cf_use_template_global_fields'])
      ? array_map('sanitize_text_field', wp_unslash($_POST['yaml_cf_use_template_global_fields']))
      : [];
    update_post_meta($post_id, '_yaml_cf_use_template_global_fields', wp_slash($use_template_global_fields));
  }

  /**
   * The schema to clean the posted fields against
   *
   * The form holds the fields of the template it was drawn for. Choosing a
   * different template and saving in the same step posts those fields, while
   * the post already has the new template. Cleaning them against the new
   * schema alone treated every field it lacks as plain text, which stripped
   * rich text and code. Fields the new schema has still follow it; the others
   * follow the schema they were typed into, and are then kept as fields the
   * schema no longer has, like any other.
   *
   * @param string $template Template the post uses now
   * @param array|null $schema Parsed schema of that template
   * @param mixed $schemas Every stored schema, keyed by template
   * @return array|null
   */
  private function schemaForPostedFields($template, $schema, $schemas) {
    $rendered = RequestHelper::postSanitized('yaml_cf_rendered_template', '');
    if (!is_string($rendered) || $rendered === '' || $rendered === $template) {
      return $schema;
    }

    if (!is_array($schemas) || empty($schemas[$rendered]) || !is_string($schemas[$rendered])) {
      return $schema;
    }

    $rendered_schema = $this->parser->parse($schemas[$rendered]);
    if (!is_array($rendered_schema) || empty($rendered_schema['fields']) || !is_array($rendered_schema['fields'])) {
      return $schema;
    }

    $fields = is_array($schema) && isset($schema['fields']) && is_array($schema['fields']) ? $schema['fields'] : [];
    $names = [];
    foreach ($fields as $field) {
      if (is_array($field) && isset($field['name'])) {
        $names[(string) $field['name']] = true;
      }
    }

    foreach ($rendered_schema['fields'] as $field) {
      if (is_array($field) && isset($field['name']) && !isset($names[(string) $field['name']])) {
        $fields[] = $field;
      }
    }

    return ['fields' => $fields];
  }

  /**
   * Carry over what is stored for fields no schema mentions any more
   *
   * Saving rebuilds the stored data from what the form sent. A field that has
   * been deleted or renamed in the schema renders no input, so it sends nothing,
   * and its content used to be destroyed the next time anybody pressed Update.
   * The editor promises the opposite when a schema drops a field: that the
   * content is still saved and that putting the name back brings it back.
   * This is what makes that true.
   *
   * Only the schema's TOP-LEVEL names count as still in use. A group's children
   * live inside the group's own value, so a name reused at another depth says
   * nothing about a stranded top-level value: moving "title" into a group leaves
   * the old top-level "title" stranded, and it has to be kept.
   *
   * A page's own copy of a field that has since become a shared field is kept
   * too. It is never shown — YamlCF\PublicAPI\FieldResolver takes shared fields
   * from shared data whatever post meta holds — but keeping it means moving a
   * field into the shared schema and back again does not lose what pages had.
   *
   * A null schema is not a reason to bail: no schema means everything stored is
   * an orphan, which is exactly what this keeps.
   *
   * @param int $post_id Post being saved
   * @param array $data Sanitized data from the form
   * @param array|null $schema Parsed schema for the post's template
   * @return array Data, with stranded values put back
   */
  private function keepOrphanedValues($post_id, $data, $schema) {
    $stored = get_post_meta($post_id, '_yaml_cf_data', true);
    if (!is_array($stored) || empty($stored)) {
      return $data;
    }

    return $this->schemaDiff->restoreStranded(
      is_array($data) ? $data : [],
      $stored,
      $schema,
      SchemaValidator::RESERVED_NAMES
    );
  }

  /**
   * Carry over what is stored for fields the editor could not show
   *
   * Saving replaces the stored data with what the form posted, which is right
   * for every field the editor rendered: a cleared field has to save as cleared.
   * A field whose type this plugin has no input for posts nothing at all, so
   * without this its content would be dropped on the next save of the page,
   * because of a typo in the schema. Schemas saved from now on cannot contain
   * one (SchemaValidator rejects it), but schemas saved by earlier versions can.
   *
   * @param int $post_id Post being saved
   * @param array $data Sanitized data from the form
   * @param array|null $schema Parsed schema for the post's template
   * @return array Data, with unrenderable fields' stored values put back
   */
  private function keepValuesOfUnrenderableFields($post_id, $data, $schema) {
    if (!is_array($schema) || empty($schema['fields']) || !is_array($schema['fields'])) {
      return $data;
    }

    $stored = get_post_meta($post_id, '_yaml_cf_data', true);
    if (!is_array($stored) || empty($stored)) {
      return $data;
    }

    foreach ($schema['fields'] as $field) {
      if (!is_array($field) || !isset($field['name'], $field['type'])) {
        continue;
      }

      if (in_array($field['type'], SchemaValidator::FIELD_TYPES, true)) {
        continue;
      }

      $name = $field['name'];
      if (!array_key_exists($name, $data) && array_key_exists($name, $stored)) {
        $data[$name] = $stored[$name];
      }
    }

    return $data;
  }
}
