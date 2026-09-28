<?php

/**
 * Meta Box
 * The "Custom fields" box in the post editor
 */

namespace YamlCF\PostEditor;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Where the box appears depends on which editor is open. The classic editor
 * prints it directly under the title, above the content, which is where it has
 * always been. The block editor cannot: a meta box there sits either below the
 * content or in the narrow sidebar, and the space above the content is the
 * block canvas. So the block editor gets a real meta box and the classic editor
 * keeps its old position. edit_form_after_title does not fire in the block
 * editor, so the two never both run.
 */
class MetaBox {
  private $renderer;
  private $templateResolver;
  private $parser;
  private $normalizer;
  private $grouper;

  /**
   * @param \YamlCF\Rendering\FieldRenderer $renderer Prints the fields
   * @param \YamlCF\Template\TemplateResolver $templateResolver Which template a post uses
   * @param \YamlCF\Schema\SchemaParser $parser Reads schemas
   * @param \YamlCF\Schema\FieldNormalizer $normalizer Expands shorthand
   * @param \YamlCF\Schema\SectionGrouper $grouper Splits fields at section breaks
   */
  public function __construct($renderer, $templateResolver, $parser, $normalizer, $grouper) {
    $this->renderer = $renderer;
    $this->templateResolver = $templateResolver;
    $this->parser = $parser;
    $this->normalizer = $normalizer;
    $this->grouper = $grouper;
  }

  /**
   * Find the schema that applies to a post, if any
   *
   * Shared by the meta box registration and its render callback so the box is
   * never registered for a post that would render nothing.
   *
   * @param \WP_Post $post Post being edited
   * @return array|null ['template' => string, 'schema' => array] or null
   */
  public function schemaForPost($post) {
    $post_type_object = get_post_type_object($post->post_type);
    if (!$post_type_object || !$post_type_object->public) {
      return null;
    }

    $template = $this->templateResolver->getTemplateForPost($post);

    $template_settings = get_option('yaml_cf_template_settings', []);
    if (!isset($template_settings[$template]) || !$template_settings[$template]) {
      return null;
    }

    $schemas = get_option('yaml_cf_schemas', []);
    if (!isset($schemas[$template]) || empty($schemas[$template])) {
      return null;
    }

    $schema = $this->parser->parse($schemas[$template]);
    if (!$schema || !isset($schema['fields'])) {
      return null;
    }

    // Normalize shorthand syntax (info boxes, section breaks)
    $schema['fields'] = $this->normalizer->normalizeAll($schema['fields']);

    return ['template' => $template, 'schema' => $schema];
  }

  /**
   * Register the custom fields meta box for posts that have a schema
   *
   * A real meta box works in both editors: the block editor renders classic
   * meta boxes in its bottom panel and submits them with the post, so the
   * existing $_POST['yaml_cf'] save path is unchanged.
   *
   * @param string $post_type Post type being edited
   * @param \WP_Post $post Post being edited
   * @return void
   */
  public function register($post_type, $post) {
    if (!($post instanceof \WP_Post)) {
      return;
    }

    if ($this->schemaForPost($post) === null) {
      return;
    }

    // The classic editor prints the box above the content instead, on
    // edit_form_after_title, so registering it here as well would show it twice.
    if (!$this->usesBlockEditor($post)) {
      return;
    }

    add_meta_box(
      'yaml-cf-meta-box',
      __('Custom fields', 'yaml-custom-fields'),
      [$this, 'render'],
      $post_type,
      'normal',
      'high'
    );
  }

  /**
   * Print the custom fields meta box
   *
   * Core prints the .postbox wrapper, its heading and the collapse toggle, and
   * remembers the open or closed state per user.
   *
   * @param \WP_Post $post Post being edited
   * @return void
   */
  public function render($post) {
    $resolved = $this->schemaForPost($post);
    if ($resolved === null) {
      return;
    }

    $template = $resolved['template'];
    $schema = $resolved['schema'];
    $template_settings = get_option('yaml_cf_template_settings', []);

    wp_nonce_field('yaml_cf_meta_box', 'yaml_cf_meta_box_nonce');

    // Which template these fields were drawn for. Choosing another template and
    // saving in one go posts this template's fields; see MetaBoxSaver.
    echo '<input type="hidden" name="yaml_cf_rendered_template" value="' . esc_attr($template) . '">';

    // Add link to edit schema
    $edit_schema_url = admin_url('admin.php?page=yaml-cf-edit-schema&template=' . rawurlencode($template));

    $export_url = wp_nonce_url(
      add_query_arg('yaml_cf_export_post', $post->ID, admin_url('post.php')),
      'yaml_cf_export_post_' . $post->ID
    );

    echo '<div class="yaml-cf-meta-box-header">';
    echo '<div class="yaml-cf-meta-box-intro">';
    echo '<span class="yaml-cf-template-chip"><span class="dashicons dashicons-layout" aria-hidden="true"></span>';
    /* translators: %s: template file name */
    echo esc_html(sprintf(__('Template: %s', 'yaml-custom-fields'), $template));
    echo '</span>';
    echo '<span class="yaml-cf-hint">' . esc_html__('Fill in the fields below. Click Update to save them.', 'yaml-custom-fields') . '</span>';
    echo '</div>';

    echo '<div class="yaml-cf-meta-box-actions">';
    if (current_user_can('manage_options')) {
      echo '<a href="' . esc_url($edit_schema_url) . '" target="_blank" class="button button-small">';
      echo '<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>' . esc_html__('Edit schema', 'yaml-custom-fields');
      echo '<span class="screen-reader-text"> ' . esc_html__('(opens in a new tab)', 'yaml-custom-fields') . '</span>';
      echo '</a>';
    }

    echo '<a href="' . esc_url($export_url) . '" class="button button-small">';
    echo '<span class="dashicons dashicons-download" aria-hidden="true"></span>' . esc_html__('Export fields', 'yaml-custom-fields');
    echo '</a>';

    // Import: the button opens the hidden file input (handled by admin.js); a button also works with the keyboard
    echo '<input type="file" name="yaml_cf_import_file_hidden" accept=".json" id="yaml-cf-import-file-' . esc_attr($post->ID) . '" class="yaml-cf-hidden" data-post-id="' . esc_attr($post->ID) . '" data-nonce="' . esc_attr(wp_create_nonce('yaml_cf_import_post')) . '" tabindex="-1">';
    echo '<button type="button" class="button button-small yaml-cf-import-trigger" data-target="yaml-cf-import-file-' . esc_attr($post->ID) . '">';
    echo '<span class="dashicons dashicons-upload" aria-hidden="true"></span>' . esc_html__('Import fields', 'yaml-custom-fields');
    echo '</button>';

    echo '<a href="#" class="button button-small yaml-cf-reset-data" data-post-id="' . esc_attr($post->ID) . '">';
    echo '<span class="dashicons dashicons-trash" aria-hidden="true"></span>' . esc_html__('Empty all fields', 'yaml-custom-fields');
    echo '</a>';
    echo '</div>';
    echo '</div>';

    $saved_data = get_post_meta($post->ID, '_yaml_cf_data', true);
    if (empty($saved_data)) {
      $saved_data = [];
    }

    // Get template global schema if it exists
    $template_global_schemas = get_option('yaml_cf_template_global_schemas', []);
    $has_template_global = isset($template_global_schemas[$template]) && !empty($template_global_schemas[$template]);
    $template_global_schema = null;
    $template_global_data = [];
    $use_template_global_fields = get_post_meta($post->ID, '_yaml_cf_use_template_global_fields', true);
    if (empty($use_template_global_fields)) {
      $use_template_global_fields = [];
    }

    if ($has_template_global) {
      $template_global_schema = $this->parser->parse($template_global_schemas[$template]);

      // Expand shorthand the same way get_schema_for_post() does for the post's
      // own schema. Without this, "- info:" and "- section:" reach the code
      // below as fields with no name at all.
      if (is_array($template_global_schema) && isset($template_global_schema['fields']) && is_array($template_global_schema['fields'])) {
        $template_global_schema['fields'] = $this->normalizer->normalizeAll($template_global_schema['fields']);
      }

      $template_global_data_array = get_option('yaml_cf_template_global_data', []);
      $template_global_data = isset($template_global_data_array[$template]) ? $template_global_data_array[$template] : [];
    }

    echo '<div class="yaml-cf-fields">';

    // Fields are rendered one by one here (each may become a dual field), so the
    // section wrappers are opened and closed around the groups by hand.
    $grouper = $this->grouper;

    foreach ($grouper->group($schema['fields']) as $group) {
      if ($group['section'] !== null) {
        $this->renderer->renderSectionOpen($group['section']);
      }

      foreach ($group['fields'] as $field) {
        $field_name = $field['name'];
        $has_template_global_field = $template_global_schema && isset($template_global_schema['fields']) && $this->fieldExistsInSchema($field_name, $template_global_schema['fields']);

        if ($has_template_global_field) {
          // Render dual field (template global + local + checkbox)
          $this->renderer->renderDualField($field, $saved_data, $template_global_data, $use_template_global_fields, $template);
        } else {
          // Render normal local-only field
          $context = ['type' => 'page'];
          $this->renderer->renderFields([$field], $saved_data, '', $context);
        }
      }

      if ($group['section'] !== null) {
        echo '</div></section>';
      }
    }

    echo '</div>';

    // Render template-global-only fields as readonly
    if ($has_template_global && $template_global_schema && isset($template_global_schema['fields']) && isset($schema['fields'])) {
      $manage_template_global_url = admin_url('admin.php?page=yaml-cf-manage-template-global&template=' . rawurlencode($template));

      $has_global_only_fields = false;

      foreach ($template_global_schema['fields'] as $global_field) {
        if (!is_array($global_field) || !isset($global_field['name'])) {
          continue;
        }

        // Section breaks and info boxes hold no value to share, and the names
        // they are given are positional, so the same one can appear in both
        // schemas without meaning the same thing.
        if (isset($global_field['type']) && in_array($global_field['type'], ['section', 'info'], true)) {
          continue;
        }

        $global_field_name = $global_field['name'];

        // Check if this field exists ONLY in template global (NOT in local schema)
        if (!$this->fieldExistsInSchema($global_field_name, $schema['fields'])) {
          // First global-only field: render section header
          if (!$has_global_only_fields) {
            $has_global_only_fields = true;
            echo '<div class="yaml-cf-template-global-only-fields">';
            echo '<div class="yaml-cf-section-intro">';
            echo '<h3>' . esc_html__('Shared fields', 'yaml-custom-fields') . '</h3>';
            echo '<p>';
            echo esc_html__('These fields are the same on every page with this template. Edit them in one place.', 'yaml-custom-fields');
            echo ' <a href="' . esc_url($manage_template_global_url) . '" target="_blank">' . esc_html__('Edit shared content', 'yaml-custom-fields') . '</a>';
            echo '</p>';
            echo '</div>';
          }

          // Render the field as readonly with template global data
          $readonly_context = [
            'type' => 'template_global',
            'readonly' => true,
            'id_suffix' => '_global_only'
          ];
          $global_field_value = isset($template_global_data[$global_field_name]) ? $template_global_data[$global_field_name] : '';
          $this->renderer->renderFields([$global_field], [$global_field_name => $global_field_value], '', $readonly_context);
        }
      }

      if ($has_global_only_fields) {
        echo '</div>'; // Close yaml-cf-template-global-only-fields
      }
    }

    // Check if this template uses global schema
    $use_global = isset($template_settings[$template . '_use_global']) && $template_settings[$template . '_use_global'];
    if ($use_global) {
      $global_schema_yaml = get_option('yaml_cf_global_schema', '');
      if (!empty($global_schema_yaml)) {
        $global_schema = $this->parser->parse($global_schema_yaml);
        if ($global_schema && isset($global_schema['fields'])) {
          $global_data = get_option('yaml_cf_global_data', []);
          $manage_global_url = admin_url('admin.php?page=yaml-cf-manage-global-data');

          echo '<div class="yaml-cf-global-fields">';
          echo '<div class="yaml-cf-section-intro">';
          echo '<h3>' . esc_html__('Site details', 'yaml-custom-fields') . '</h3>';
          echo '<p>';
          echo esc_html__('These details are the same on the whole website. Edit them in one place.', 'yaml-custom-fields');
          echo ' <a href="' . esc_url($manage_global_url) . '" target="_blank">' . esc_html__('Edit site details', 'yaml-custom-fields') . '</a>';
          echo '</p>';
          echo '</div>';

          $global_context = ['type' => 'global', 'readonly' => true];
          $this->renderer->renderFields($global_schema['fields'], $global_data, '', $global_context);
          echo '</div>';
        }
      }
    }
  }

  /**
   * Print the custom fields box directly under the title, in the classic editor
   *
   * Only ever runs there: the block editor does not fire edit_form_after_title,
   * and register_meta_box() stands down when this hook is going to fire, so the
   * box is printed once and only once.
   *
   * @param \WP_Post $post Post being edited
   * @return void
   */
  public function renderAfterTitle($post) {
    if (!($post instanceof \WP_Post) || $this->usesBlockEditor($post)) {
      return;
    }

    // Nothing to show for this post: print no wrapper either, or the editor
    // grows an empty box with a heading and nothing under it.
    if ($this->schemaForPost($post) === null) {
      return;
    }

    // Same id and same inner structure as the meta box WordPress draws in the
    // block editor. Two dozen style rules and every script that touches the
    // fields — "Empty all fields", gallery drag-sorting — are written against
    // "#yaml-cf-meta-box > .inside > .yaml-cf-fields", so anything printed here
    // under a different name would look wrong and half of it would not work.
    // Only one of the two paths ever runs, so the id stays unique.
    echo '<div id="yaml-cf-meta-box" class="yaml-cf-after-title postbox">';
    echo '<h2 class="hndle"><span>' . esc_html__('Custom fields', 'yaml-custom-fields') . '</span></h2>';
    echo '<div class="inside">';
    $this->render($post);
    echo '</div>';
    echo '</div>';
  }

  /**
   * Whether this post opens in the block editor
   *
   * Decides where the fields are printed. Defaults to the block editor when
   * WordPress cannot say, because a meta box shows up in both editors whereas
   * edit_form_after_title shows up in only one: guessing that way round can
   * misplace the box, never hide it.
   *
   * @param \WP_Post $post Post being edited
   * @return bool
   */
  private function usesBlockEditor($post) {
    if (!function_exists('use_block_editor_for_post')) {
      return true;
    }

    return (bool) use_block_editor_for_post($post);
  }

  /**
   * Check if a field exists in a schema fields array
   */
  private function fieldExistsInSchema($field_name, $fields) {
    if (!is_array($fields)) {
      return false;
    }

    foreach ($fields as $field) {
      // Shorthand that has not been expanded, or a schema written by hand with
      // a field missing its name: neither is a match, and neither is a warning
      if (!is_array($field) || !isset($field['name'])) {
        continue;
      }

      if ($field['name'] === $field_name) {
        return true;
      }
    }

    return false;
  }
}
