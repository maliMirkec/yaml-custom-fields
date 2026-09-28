<?php

namespace YamlCF\Ajax\Handlers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Ajax\AjaxHandler;

/**
 * Render one empty block item for the "Add section" button.
 *
 * The admin script used to build this markup in JavaScript, duplicating
 * the PHP renderer (YamlCF\Rendering\BlockItemRenderer). Rendering it here instead means every
 * field type behaves the same whether its section came with the page or was
 * added on it.
 *
 * Action: wp_ajax_yaml_cf_render_block_item
 */
class RenderBlockItem extends AjaxHandler {
  /** Schema sources a block field can come from, one per screen that renders fields */
  const SOURCES = ['template', 'global', 'template_global', 'partial', 'data_object'];

  /**
   * Sources that only ever appear on a settings screen.
   *
   * The post editor only ever asks for 'template'. The rest belong to screens
   * an administrator opens, so there is no reason for a writer's browser to be
   * able to ask for the shape of a site details or collection schema.
   */
  const ADMIN_ONLY_SOURCES = ['global', 'template_global', 'partial', 'data_object'];

  private $schemaParser;
  private $fieldRenderer;

  /**
   * @param \YamlCF\Schema\SchemaParser $schemaParser Reads the stored schema
   * @param \YamlCF\Rendering\FieldRenderer|null $fieldRenderer Prints the item
   */
  public function __construct($schemaParser, $fieldRenderer = null) {
    $this->schemaParser = $schemaParser;
    $this->fieldRenderer = $fieldRenderer;
  }

  public function handle() {
    // Writers add sections while editing a post
    $this->verifyRequest('edit_posts');

    $source = $this->postText('source');
    if (!in_array($source, self::SOURCES, true)) {
      $this->sendError(__('That schema could not be found.', 'yaml-custom-fields'));
    }

    if (in_array($source, self::ADMIN_ONLY_SOURCES, true)) {
      $this->checkCapability();
    }

    // Site parts keep their schemas beside the templates' in yaml_cf_schemas,
    // so "template" with a site part's name would hand a writer that schema.
    // A writer gets the schema of a post they can edit, and nothing else.
    if ($source === 'template' && !current_user_can('manage_options') && !$this->writerMayUseTemplate($this->postText('key'))) {
      $this->sendError(__('That schema could not be found.', 'yaml-custom-fields'));
    }

    $schema = $this->getSchema($source, $this->postText('key'));
    if ($schema === null) {
      $this->sendError(__('That schema could not be found.', 'yaml-custom-fields'));
    }

    $key = $this->postText('key');
    $field_name = $this->postText('field_name');
    $block_type = $this->postText('block_type');
    $index = absint($this->postText('index', '0'));

    ob_start();
    $renderer = $this->fieldRenderer ?: \YamlCF\Core\Plugin::getInstance()->get('field_renderer');
    $rendered = $renderer->renderNewBlockItem(
      $schema,
      $field_name,
      $block_type,
      $index,
      $this->getContext($source, $key)
    );
    $html = ob_get_clean();

    if (!$rendered || trim($html) === '') {
      $this->sendError(__('That section type is no longer in the schema.', 'yaml-custom-fields'));
    }

    $this->sendSuccess(['html' => $html, 'index' => $index]);
  }

  /**
   * Whether the post in the request uses this template and may be edited
   *
   * @param string $template Template the request asks for
   * @return bool
   */
  private function writerMayUseTemplate($template) {
    $post_id = absint($this->postText('post_id', '0'));
    $post = $post_id ? get_post($post_id) : null;

    if (!$post || !current_user_can('edit_post', $post_id)) {
      return false;
    }

    $resolver = \YamlCF\Core\Plugin::getInstance()->get('template_resolver');

    return $resolver->getTemplateForPost($post) === $template;
  }

  /**
   * The rendering context each screen passes to render_schema_fields()
   *
   * It decides whether the "Copy code" snippets appear and which post ID they
   * mention, so it has to match what the screen itself uses.
   *
   * @param string $source One of self::SOURCES
   * @param string $key Template file name or collection slug
   * @return array Context
   */
  private function getContext($source, $key) {
    if ($source === 'data_object') {
      return ['type' => 'data_object', 'object_type' => $key];
    }

    if ($source === 'partial') {
      return ['type' => 'partial', 'template' => $key];
    }

    if ($source === 'global') {
      return ['type' => 'global'];
    }

    if ($source === 'template_global') {
      return ['type' => 'template_global'];
    }

    return ['type' => 'page'];
  }

  /**
   * Load and parse the schema a block field lives in
   *
   * @param string $source One of self::SOURCES
   * @param string $key Template file name or collection slug, where one is needed
   * @return array|null Parsed schema, or null when there is none
   */
  private function getSchema($source, $key) {
    $yaml = '';

    if ($source === 'global') {
      $yaml = get_option('yaml_cf_global_schema', '');
    } elseif ($source === 'template_global') {
      $schemas = get_option('yaml_cf_template_global_schemas', []);
      $yaml = isset($schemas[$key]) ? $schemas[$key] : '';
    } elseif ($source === 'data_object') {
      $types = get_option('yaml_cf_data_object_types', []);
      $yaml = isset($types[$key]['schema']) ? $types[$key]['schema'] : '';
    } else {
      // 'template' and 'partial' both live in yaml_cf_schemas
      $schemas = get_option('yaml_cf_schemas', []);
      $yaml = isset($schemas[$key]) ? $schemas[$key] : '';
    }

    if (!is_string($yaml) || trim($yaml) === '') {
      return null;
    }

    $parsed = $this->schemaParser->parse($yaml);
    return is_array($parsed) ? $parsed : null;
  }
}
