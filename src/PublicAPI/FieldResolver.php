<?php

/**
 * Field Resolver
 * Works out every field value a theme sees, from every source that can supply one
 */

namespace YamlCF\PublicAPI;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Helpers\CodeFieldCodec;

/**
 * The single place the precedence rules live. The template functions
 * (yaml_cf_get_field() and the rest), the REST API and the shortcode all read
 * values through here, so they can never disagree about the same field.
 *
 * Sources for a post's field, highest priority first:
 *
 *   1. The shared (template global) value, for each field the editor ticked
 *      "Use the shared value on this page" for.
 *   2. The shared value, for fields that exist only in the shared schema. Older
 *      versions froze a copy of these into post meta; the copy is discarded.
 *   3. The value stored on the page.
 *   4. The shared value, for a field the page has never been saved with. A page
 *      saved with the field left empty keeps its empty value.
 *   5. Site details, when the template uses them.
 *
 * This is the order the Documentation page describes.
 */
class FieldResolver {
  private $templateResolver;
  private $parser;
  private $sanitizer;

  /**
   * Resolved values keyed by post ID, for the rest of the request
   *
   * A template that reads thirty fields used to re-read the same six options
   * thirty times over.
   *
   * @var array<int, array>
   */
  private $resolved = [];

  /**
   * Parsed schemas keyed by the md5 of their YAML
   *
   * @var array<string, array|null>
   */
  private $parsed = [];

  /**
   * Names whose value came from site details alone, keyed by post ID
   *
   * Kept beside $resolved so the REST API can hold site details back from
   * somebody who may read a page but not the site details themselves.
   *
   * @var array<int, string[]>
   */
  private $siteDetailNames = [];

  /**
   * @param \YamlCF\Template\TemplateResolver $templateResolver Which template a post uses
   * @param \YamlCF\Schema\SchemaParser $parser Reads schemas
   * @param \YamlCF\Form\DataSanitizer $sanitizer Finds field definitions in a schema
   */
  public function __construct($templateResolver, $parser, $sanitizer) {
    $this->templateResolver = $templateResolver;
    $this->parser = $parser;
    $this->sanitizer = $sanitizer;
  }

  /**
   * Every field value for a post, code fields decoded
   *
   * @param int|string $post_id Post ID
   * @return array Field values keyed by field name
   */
  public function resolvePostFields($post_id) {
    $post_id = (int) $post_id;
    if (isset($this->resolved[$post_id])) {
      return $this->resolved[$post_id];
    }

    $stored = get_post_meta($post_id, '_yaml_cf_data', true);
    $stored = is_array($stored) ? $stored : [];

    $post = get_post($post_id);
    if (!$post) {
      // No post to read a template from, so there is no schema and nothing to
      // merge. Whatever is stored is still handed back, as it always has been.
      return $this->decodeAll($stored);
    }

    $template = $this->templateResolver->getTemplateForPost($post);

    $schemas = get_option('yaml_cf_schemas', []);
    $post_schema_yaml = isset($schemas[$template]) ? $schemas[$template] : null;
    $post_schema = $this->parseSchemaCached($post_schema_yaml);

    $shared_schema_yaml = $this->templateGlobalSchema($template);
    $shared_schema = $this->parseSchemaCached($shared_schema_yaml);

    $shared_data_all = get_option('yaml_cf_template_global_data', []);
    $shared_values = isset($shared_data_all[$template]) && is_array($shared_data_all[$template])
      ? $this->decodeAll($shared_data_all[$template], $shared_schema_yaml)
      : [];

    // Start from what is stored on the page; everything below either replaces a
    // value or fills in one that is not there.
    $values = $this->decodeAll($stored, $post_schema_yaml);

    // Fields that exist only in the shared schema always come from shared data, so
    // a copy frozen into post meta by an older version cannot win.
    if (is_array($shared_schema) && !empty($shared_schema['fields']) && is_array($shared_schema['fields'])) {
      foreach ($shared_schema['fields'] as $shared_field) {
        if (!is_array($shared_field) || !isset($shared_field['name'])) {
          continue;
        }
        $name = $shared_field['name'];
        if ($this->sanitizer->getFieldDefinition($post_schema, $name)) {
          continue; // Also a page field: the tick below decides this one.
        }
        if (array_key_exists($name, $shared_values)) {
          $values[$name] = $shared_values[$name];
        } else {
          unset($values[$name]);
        }
      }
    }

    // "Use the shared value on this page", ticked per field in the editor. This is
    // the highest-ranked source, so it is applied over everything above.
    $ticked = get_post_meta($post_id, '_yaml_cf_use_template_global_fields', true);
    if (is_array($ticked)) {
      foreach ($ticked as $name => $is_ticked) {
        // A tick with nothing filled in on the shared side leaves the page value alone.
        if ($is_ticked === '1' && array_key_exists($name, $shared_values)) {
          $values[$name] = $shared_values[$name];
        }
      }
    }

    // A page never saved since the field was added has no value of its own, so the
    // shared value stands in. A page saved with the field left empty keeps the empty
    // value: clearing a field is a decision.
    foreach ($shared_values as $name => $shared_value) {
      if (!array_key_exists($name, $values)) {
        $values[$name] = $shared_value;
      }
    }

    // Site details are the last resort, filling in names nothing above supplied.
    $site_only = [];
    $template_settings = get_option('yaml_cf_template_settings', []);
    if (!empty($template_settings[$template . '_use_global'])) {
      $global_data = get_option('yaml_cf_global_data', []);
      if (is_array($global_data)) {
        $global_data = $this->decodeAll($global_data, get_option('yaml_cf_global_schema', ''));
        $site_only = array_keys(array_diff_key($global_data, $values));
        $values += $global_data;
      }
    }

    // A key the plugin reserves, never a field. Only unreleased development
    // builds before 1.2.0 wrote it, from an "Override" button that shipped as
    // the per-field tick instead, so it is dropped rather than obeyed: obeying
    // it outranked the tick and contradicted the order documented above.
    unset($values['_template_global_override']);

    $this->resolved[$post_id] = $values;
    $this->siteDetailNames[$post_id] = array_map('strval', array_values($site_only));

    return $values;
  }

  /**
   * Forget resolved values, so the next read sees what was just saved
   *
   * @param int|null $post_id Post to forget, or null for every post
   * @return void
   */
  public function flush($post_id = null) {
    if ($post_id === null) {
      $this->resolved = [];
      $this->siteDetailNames = [];
      return;
    }

    unset($this->resolved[(int) $post_id], $this->siteDetailNames[(int) $post_id]);
  }

  /**
   * Names in a post's values that only site details supplied
   *
   * @param int|string $post_id Post ID
   * @return string[]
   */
  public function siteDetailNames($post_id) {
    $post_id = (int) $post_id;
    if (!isset($this->siteDetailNames[$post_id])) {
      // Resolving records them; a value placed in the memo from outside has none
      $this->flush($post_id);
      $this->resolvePostFields($post_id);
    }

    return isset($this->siteDetailNames[$post_id]) ? $this->siteDetailNames[$post_id] : [];
  }

  /**
   * Forget a post's values when post meta they are built from changes
   *
   * Hooked to added_post_meta, updated_post_meta and deleted_post_meta, so an
   * importer or any other code that writes meta directly cannot leave a stale
   * value behind for the rest of the request.
   *
   * @param int|array $meta_id Meta row ID, or IDs when a meta row is deleted (unused)
   * @param int $object_id Post ID
   * @param string $meta_key Meta key that changed
   * @return void
   */
  public function flushForMeta($meta_id, $object_id, $meta_key) {
    $watched = [
      '_yaml_cf_data',
      '_yaml_cf_use_template_global_fields',
      // Changes which template the post uses, and so which schema applies
      '_wp_page_template',
    ];

    if (in_array($meta_key, $watched, true)) {
      $this->flush($object_id);
    }
  }

  /**
   * Forget every post's values when a plugin option changes
   *
   * Schemas, shared content and site details apply across posts, so there is no
   * single post to forget.
   *
   * @param string $option Option name that changed
   * @return void
   */
  public function flushForOption($option) {
    if (is_string($option) && strpos($option, 'yaml_cf_') === 0) {
      $this->flush();
    }
  }

  /**
   * The memo of resolved values itself, by reference
   *
   * Only for yaml_cf_resolved_post_fields_cache(), which has always handed it out.
   *
   * @return array
   */
  public function &resolvedCache() {
    return $this->resolved;
  }

  /**
   * Parse a YAML schema once per request
   *
   * @param mixed $yaml YAML schema
   * @return array|null Parsed schema, or null when there is none or it is invalid
   */
  public function parseSchemaCached($yaml) {
    if (!is_string($yaml) || $yaml === '') {
      return null;
    }

    $key = md5($yaml);
    if (!array_key_exists($key, $this->parsed)) {
      $parsed = $this->parser->parse($yaml);
      $this->parsed[$key] = is_array($parsed) ? $parsed : null;
    }

    return $this->parsed[$key];
  }

  /**
   * The definition of a top-level field, looked for where its value comes from
   *
   * A site part's own schema, or for a post: its template's schema, then the
   * template's shared fields, then site details when the template uses them.
   *
   * @param string $field_name The field's name
   * @param int|string|null $post_id Post ID, 'partial:<file>', or null for the current post
   * @return array|null Field definition, or null when no schema has the field
   */
  public function fieldDefinitionFor($field_name, $post_id = null) {
    $schemas = get_option('yaml_cf_schemas', []);
    $schemas = is_array($schemas) ? $schemas : [];

    if (is_string($post_id) && strpos($post_id, 'partial:') === 0) {
      $file = substr($post_id, strlen('partial:'));
      $yaml = isset($schemas[$file]) ? $schemas[$file] : '';

      return $this->sanitizer->getFieldDefinition($this->parseSchemaCached($yaml), $field_name);
    }

    $post_id = $post_id === null ? get_the_ID() : $post_id;
    $post = $post_id ? get_post((int) $post_id) : null;
    if (!$post) {
      return null;
    }

    $template = $this->templateResolver->getTemplateForPost($post);
    $sources = [
      isset($schemas[$template]) ? $schemas[$template] : '',
      $this->templateGlobalSchema($template),
    ];

    $settings = get_option('yaml_cf_template_settings', []);
    if (is_array($settings) && !empty($settings[$template . '_use_global'])) {
      $sources[] = get_option('yaml_cf_global_schema', '');
    }

    foreach ($sources as $yaml) {
      $definition = $this->sanitizer->getFieldDefinition($this->parseSchemaCached($yaml), $field_name);
      if ($definition !== null) {
        return $definition;
      }
    }

    return null;
  }

  /**
   * The YAML of a template's shared (template global) schema
   *
   * @param string $template Template file name
   * @return string YAML, or '' when there is none
   */
  public function templateGlobalSchema($template) {
    $template_global_schemas = get_option('yaml_cf_template_global_schemas', []);
    return isset($template_global_schemas[$template]) && is_string($template_global_schemas[$template]) ? $template_global_schemas[$template] : '';
  }

  /**
   * Decode a value if it belongs to a code field
   *
   * When the schema defines the field with a type other than 'code', the value
   * is returned as stored, so a marker-prefixed value in a text field is never
   * decoded.
   *
   * @param mixed $value The field value
   * @param array|string|null $schema Parsed schema or YAML for the data source
   * @param string $field_name The top-level field name
   * @return mixed
   */
  public function autoDecodeIfCodeField($value, $schema, $field_name) {
    if (is_string($value) && $schema && $field_name !== '') {
      $parsed = is_array($schema) ? $schema : $this->parseSchemaCached($schema);
      $field_def = $this->sanitizer->getFieldDefinition($parsed, $field_name);
      if ($field_def && (!isset($field_def['type']) || $field_def['type'] !== 'code')) {
        return $value;
      }
    }

    return CodeFieldCodec::maybeDecode($value);
  }

  /**
   * Decode every code field value in a set of values, at any depth
   *
   * @param mixed $data The values
   * @param array|string|null $schema Schema for the top level; top-level fields it
   *                                  defines with a type other than 'code' are not decoded
   * @return mixed
   */
  public function decodeAll($data, $schema = null) {
    if (!is_array($data)) {
      return CodeFieldCodec::maybeDecode($data);
    }

    foreach ($data as $key => $value) {
      if (is_array($value)) {
        $data[$key] = $this->decodeAll($value);
      } elseif (is_string($value)) {
        $data[$key] = $schema ? $this->autoDecodeIfCodeField($value, $schema, (string) $key) : CodeFieldCodec::maybeDecode($value);
      }
    }

    return $data;
  }
}
