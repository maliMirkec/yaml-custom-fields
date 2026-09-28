<?php

/**
 * Field Renderer
 * Prints the fields of a schema as a form, on every screen that has one
 */

namespace YamlCF\Rendering;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Rendering\FieldRenderers;

/**
 * The post editor, site details, shared content, site parts and collection items
 * all print their fields through here. Each field type has its own renderer in
 * FieldRenderers/; this class prints what every field shares (wrapper, label,
 * help text, "Copy code") around the input the type's renderer prints.
 *
 * Render contexts, as the screens pass them:
 *
 *   ['type' => 'page']                          a post's own fields
 *   ['type' => 'template_global']               shared content
 *   ['type' => 'global']                        site details
 *   ['type' => 'partial', 'template' => file]   a site part
 *   ['type' => 'data_object', 'object_type' => slug]  a collection item
 *
 * plus 'readonly' => true for display-only copies, and 'id_suffix' to keep
 * element IDs unique when the same field is printed twice.
 */
class FieldRenderer {
  private $normalizer;
  private $grouper;
  private $parser;
  private $repository;

  private $picker;
  private $media;
  private $snippets;
  private $blockItems;

  /** @var FieldRendererInterface[] Keyed by field type */
  private $renderers = [];

  /**
   * @param \YamlCF\Schema\FieldNormalizer|null $normalizer Expands "- info:" and "- section:" shorthand
   * @param \YamlCF\Schema\SectionGrouper|null $grouper Splits fields at section breaks
   * @param \YamlCF\Schema\SchemaParser|null $parser Reads collection schemas
   * @param \YamlCF\Data\Repositories\DataObjectRepository|null $repository Collection items
   */
  public function __construct($normalizer = null, $grouper = null, $parser = null, $repository = null) {
    $this->normalizer = $normalizer ?: new \YamlCF\Schema\FieldNormalizer();
    $this->grouper = $grouper ?: new \YamlCF\Schema\SectionGrouper();
    $this->parser = $parser ?: new \YamlCF\Schema\SchemaParser();
    $this->repository = $repository ?: new \YamlCF\Data\Repositories\DataObjectRepository();

    $this->picker = new PickerRenderer($this->parser, $this->repository);
    $this->media = new MediaListRenderer();
    $this->snippets = new SnippetBuilder();
    $this->blockItems = new BlockItemRenderer($this);

    $this->renderers = [
      'boolean' => new FieldRenderers\BooleanRenderer($this),
      'string' => new FieldRenderers\StringRenderer($this),
      'text' => new FieldRenderers\TextRenderer($this),
      'rich-text' => new FieldRenderers\RichTextRenderer($this),
      'code' => new FieldRenderers\CodeRenderer($this),
      'section' => new FieldRenderers\SectionRenderer($this),
      'info' => new FieldRenderers\InfoRenderer($this),
      'number' => new FieldRenderers\NumberRenderer($this),
      'date' => new FieldRenderers\DateRenderer($this),
      'select' => new FieldRenderers\SelectRenderer($this),
      'taxonomy' => new FieldRenderers\TaxonomyRenderer($this),
      'post_type' => new FieldRenderers\PostTypeRenderer($this),
      'data_object' => new FieldRenderers\DataObjectRenderer($this),
      'image' => new FieldRenderers\ImageRenderer($this),
      'file' => new FieldRenderers\FileRenderer($this),
      'object' => new FieldRenderers\ObjectRenderer($this),
      'block' => new FieldRenderers\BlockRenderer($this),
    ];
  }

  /**
   * Whether a field type has an input
   *
   * The same list as \YamlCF\Schema\SchemaValidator::FIELD_TYPES.
   *
   * @param string $type Field type
   * @return bool
   */
  public function supports($type) {
    return is_string($type) && isset($this->renderers[$type]);
  }

  /**
   * The renderer for a field type
   *
   * @param mixed $type Field type
   * @return FieldRendererInterface|null
   */
  public function rendererFor($type) {
    return $this->supports($type) ? $this->renderers[$type] : null;
  }

  /**
   * Print a set of fields, split into the segments marked by section breaks
   *
   * Without any "- section:" in the schema this prints a flat list.
   *
   * @param array $fields Fields from the schema
   * @param array $saved_data Saved values keyed by field name
   * @param string $prefix Name prefix; one ending in "[" nests the fields under it
   * @param array|null $context Render context
   * @return void
   */
  public function renderFields($fields, $saved_data, $prefix = '', $context = null) {
    if (!is_array($fields)) {
      return;
    }

    $fields = $this->normalizer->normalizeAll($fields);

    if (!$this->grouper->hasSections($fields)) {
      $this->renderFlat($fields, $saved_data, $prefix, $context);
      return;
    }

    foreach ($this->grouper->group($fields) as $group) {
      // Fields before the first section break keep the old, unwrapped markup
      if ($group['section'] === null) {
        $this->renderFlat($group['fields'], $saved_data, $prefix, $context);
        continue;
      }

      $this->renderSectionOpen($group['section']);
      $this->renderFlat($group['fields'], $saved_data, $prefix, $context);
      echo '</div></section>';
    }
  }

  /**
   * Open a section wrapper and print its heading
   *
   * The caller closes it with '</div></section>'.
   *
   * @param array $section Section field definition
   * @return void
   */
  public function renderSectionOpen($section) {
    $is_break = !empty($section['break']);
    $label = (!$is_break && isset($section['label'])) ? trim((string) $section['label']) : '';
    $description = (!$is_break && isset($section['description'])) ? trim((string) $section['description']) : '';

    // "- break:" draws nothing at all, it only starts a new row
    if ($is_break) {
      $classes = 'yaml-cf-field-group is-break';
    } elseif ($label === '' && $description === '') {
      $classes = 'yaml-cf-field-group is-untitled';
    } else {
      $classes = 'yaml-cf-field-group';
    }

    echo '<section class="' . esc_attr($classes) . '">';

    if ($label !== '' || $description !== '') {
      echo '<div class="yaml-cf-field-group-intro">';
      if ($label !== '') {
        echo '<h3>' . esc_html($label) . '</h3>';
      }
      if ($description !== '') {
        echo '<p>' . esc_html($description) . '</p>';
      }
      echo '</div>';
    }

    echo '<div class="yaml-cf-field-group-fields">';
  }

  /**
   * Print a field that exists both in a page's schema and in the shared one
   *
   * The shared value (read-only, with a link to edit it), the page's own, and
   * the tick box that chooses between them.
   *
   * @param array $field Field definition
   * @param array $saved_data The page's own values
   * @param array $template_global_data Shared values
   * @param array $use_template_global_fields Ticked fields, [name => '1']
   * @param string $template Template file name
   * @return void
   */
  public function renderDualField($field, $saved_data, $template_global_data, $use_template_global_fields, $template) {
    $field_name = $field['name'];
    $local_value = isset($saved_data[$field_name]) ? $saved_data[$field_name] : '';
    $template_global_value = isset($template_global_data[$field_name]) ? $template_global_data[$field_name] : '';
    $use_global = isset($use_template_global_fields[$field_name]) && $use_template_global_fields[$field_name] === '1';

    $manage_template_global_url = admin_url('admin.php?page=yaml-cf-manage-template-global&template=' . rawurlencode($template));

    echo '<div class="yaml-cf-dual-field" data-field-name="' . esc_attr($field_name) . '">';

    echo '<h4 class="yaml-cf-dual-field-title">' . esc_html($field['label'] ?? $field_name) . '</h4>';

    // The shared value: read-only here, edited on its own screen
    echo '<div class="yaml-cf-template-global-part">';
    echo '<div class="yaml-cf-part-header">';
    echo '<strong class="yaml-cf-part-title is-global">';
    \YamlCF\Helpers\Icons::render('shared');
    echo esc_html__('Shared value (all pages with this template)', 'yaml-custom-fields') . '</strong>';
    echo '<a href="' . esc_url($manage_template_global_url) . '" target="_blank" class="button button-small">';
    \YamlCF\Helpers\Icons::render('edit');
    echo esc_html__('Edit', 'yaml-custom-fields') . '</a>';
    echo '</div>';
    $this->renderFields([$field], [$field_name => $template_global_value], '', ['type' => 'template_global', 'readonly' => true, 'id_suffix' => '_global']);
    echo '</div>';

    // The page's own value. Always editable in the markup; the script and
    // styles block it visually while the tick box below is ticked.
    echo '<div class="yaml-cf-local-part">';
    echo '<div class="yaml-cf-part-header">';
    echo '<strong class="yaml-cf-part-title is-local">';
    \YamlCF\Helpers\Icons::render('page_only');
    echo esc_html__('This page only', 'yaml-custom-fields') . '</strong>';
    echo '</div>';
    $this->renderFields([$field], [$field_name => $local_value], '', ['type' => 'page']);
    echo '</div>';

    echo '<div class="yaml-cf-field-toggle">';
    echo '<label>';
    echo '<input type="checkbox" name="yaml_cf_use_template_global_fields[' . esc_attr($field_name) . ']" value="1" class="yaml-cf-use-global-checkbox" ' . checked($use_global, true, false) . ' />';
    echo '<span>' . esc_html__('Use the shared value on this page', 'yaml-custom-fields') . '</span>';
    echo '</label>';
    echo '</div>';

    echo '</div>';
  }

  /**
   * Print one empty block item, for the "Add section" button
   *
   * @see BlockItemRenderer::renderNew()
   * @return bool Whether the block field was found
   */
  public function renderNewBlockItem($schema, $field_name, $block_type, $index, $context = null) {
    return $this->blockItems->renderNew($schema, $field_name, $block_type, $index, $context);
  }

  /**
   * Print a field's help text for writers (the optional "description")
   *
   * @param array $field Field definition
   * @return void
   */
  public function renderHelp($field) {
    if (!empty($field['description']) && is_string($field['description'])) {
      echo '<p class="yaml-cf-field-help">' . esc_html($field['description']) . '</p>';
    }
  }

  /** @return \YamlCF\Schema\FieldNormalizer */
  public function normalizer() {
    return $this->normalizer;
  }

  /** @return \YamlCF\Schema\SchemaParser */
  public function parser() {
    return $this->parser;
  }

  /** @return \YamlCF\Data\Repositories\DataObjectRepository */
  public function repository() {
    return $this->repository;
  }

  /** @return PickerRenderer */
  public function picker() {
    return $this->picker;
  }

  /** @return MediaListRenderer */
  public function media() {
    return $this->media;
  }

  /** @return SnippetBuilder */
  public function snippets() {
    return $this->snippets;
  }

  /** @return BlockItemRenderer */
  public function blockItems() {
    return $this->blockItems;
  }

  /**
   * Print fields one after another, without looking for section breaks
   *
   * @param array $fields Fields, shorthand already expanded
   * @param array $saved_data Saved values keyed by field name
   * @param string $prefix Name prefix
   * @param array|null $context Render context
   * @return void
   */
  private function renderFlat($fields, $saved_data, $prefix = '', $context = null) {
    $readonly = ($context && is_array($context) && isset($context['readonly']) && $context['readonly']);
    $readonly_class = $readonly ? ' yaml-cf-readonly' : '';

    // Keeps IDs unique when the same field is printed twice (dual fields)
    $id_suffix = ($context && is_array($context) && isset($context['id_suffix'])) ? $context['id_suffix'] : '';

    foreach ($fields as $field) {
      // A prefix ending in "[" nests the field: "hero[" + "title" is yaml_cf[hero][title]
      if (!empty($prefix) && substr($prefix, -1) === '[') {
        $field_name = substr($prefix, 0, -1) . '][' . $field['name'];
      } else {
        $field_name = $prefix . $field['name'];
      }
      $field_id = 'ycf_' . str_replace(['[', ']'], ['_', ''], $field_name) . $id_suffix;
      $field_value = isset($saved_data[$field['name']]) ? $saved_data[$field['name']] : (isset($field['default']) ? $field['default'] : '');
      $field_label = isset($field['label']) ? $field['label'] : ucfirst($field['name']);

      // Read-only fields sit in a disabled fieldset so their inputs are never submitted
      // (they share yaml_cf[...] names with editable fields and would overwrite them)
      if ($readonly) {
        echo '<fieldset class="yaml-cf-readonly-fieldset" disabled="disabled">';
      }

      echo '<div class="yaml-cf-field' . esc_attr($readonly_class) . '" data-type="' . esc_attr($field['type']) . '">';

      $code_snippet = $this->snippets->forField($field, $context);

      $this->renderLabel($field, $field_id, $field_label);

      $renderer = $this->rendererFor($field['type']);
      if ($renderer !== null) {
        $renderer->render($field, $field_value, [
          'field_name' => $field_name,
          'field_id' => $field_id,
          'field_label' => $field_label,
          'readonly' => $readonly,
          'context' => $context,
        ]);
      } else {
        $this->renderUnknownType($field, $field_name);
      }

      // A block field prints its own snippet, below its sections
      if ($field['type'] !== 'block' && $code_snippet) {
        $this->snippets->render($code_snippet, 'snippet-' . sanitize_html_class($field_id));
      }

      echo '</div>';

      if ($readonly) {
        echo '</fieldset>';
      }
    }
  }

  /**
   * Print a field's label and help text
   *
   * Block, info and section fields print their own headings, if any. Fields
   * with no single input to point at get a heading instead of a <label>.
   *
   * @param array $field Field definition
   * @param string $field_id Element ID of the input
   * @param string $field_label Label
   * @return void
   */
  private function renderLabel($field, $field_id, $field_label) {
    if ($field['type'] === 'block' || $field['type'] === 'info' || $field['type'] === 'section') {
      return;
    }

    $is_multiple_taxonomy = $field['type'] === 'taxonomy' && isset($field['multiple']) && $field['multiple'];

    // list: true, or multiple: true for consistency with taxonomy fields
    $is_multiple_data_object = $field['type'] === 'data_object'
      && ((isset($field['list']) && $field['list']) || (isset($field['multiple']) && $field['multiple']));

    if ($field['type'] === 'image' || $field['type'] === 'file') {
      echo '<p class="yaml-cf-field-label">' . esc_html($field_label) . '</p>';
    } elseif ($is_multiple_taxonomy || $is_multiple_data_object || $field['type'] === 'object') {
      // Tick boxes and groups have no single input for a <label> to point at
      echo '<h4 class="yaml-cf-field-heading">' . esc_html($field_label) . '</h4>';
    } else {
      echo '<label for="' . esc_attr($field_id) . '">' . esc_html($field_label) . '</label>';
    }

    $this->renderHelp($field);
  }

  /**
   * Say that a field's type has no input, instead of printing nothing
   *
   * The value already stored is kept when the page is saved, so fixing the
   * schema brings it back.
   *
   * @param array $field Field definition
   * @param string $field_name Path inside yaml_cf[...]
   * @return void
   */
  private function renderUnknownType($field, $field_name) {
    echo '<p class="yaml-cf-unknown-type notice notice-warning inline yaml-cf-notice">';
    echo '<span class="screen-reader-text">' . esc_html(\YamlCF\Core\NotificationManager::typeLabel('warning')) . ' </span>';
    echo esc_html(sprintf(
      /* translators: 1: field name, 2: type as written in the schema */
      __('The field "%1$s" has a type this plugin does not know: "%2$s". Nothing is shown for it, and whatever it already holds is left alone. An administrator can fix the type in the schema.', 'yaml-custom-fields'),
      $field_name,
      is_string($field['type']) ? $field['type'] : gettype($field['type'])
    ));
    echo '</p>';
  }
}
