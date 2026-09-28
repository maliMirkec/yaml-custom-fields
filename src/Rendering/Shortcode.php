<?php

/**
 * Shortcode
 * Puts a field's content on the page without editing a theme file
 */

namespace YamlCF\Rendering;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * A field shows up on the website when the theme asks for it, which means
 * editing a PHP template. A block theme has no PHP template to edit: its pages
 * are built out of blocks, so until now a block theme could hold field content
 * and had no way to display any of it.
 *
 *     [ycf field="headline"]
 *     [ycf field="hero" size="large"]
 *     [ycf field="phone" source="site"]
 *
 * Drop one into a Shortcode block, a widget, or a classic theme's content.
 *
 * Each value is escaped for what it is: text is escaped as text, rich text
 * passes through wp_kses_post(), an image becomes an <img>, and a code field
 * prints as it stands, which is the whole point of a code field.
 */
class Shortcode {
  const TAG = 'ycf';

  private $schemaLocator;
  private $fields;
  private $siteDetails;
  private $resolver;
  private $templateResolver;

  /**
   * @param \YamlCF\Schema\PostSchemaLocator $schemaLocator Finds a post's schema
   * @param \YamlCF\PublicAPI\FieldAccessor|null $fields Reads a post's fields
   * @param \YamlCF\PublicAPI\GlobalFieldAccessor|null $siteDetails Reads site details
   * @param \YamlCF\PublicAPI\FieldResolver|null $resolver Parses the shared and site details schemas
   * @param \YamlCF\Template\TemplateResolver|null $templateResolver Which template a post uses
   */
  public function __construct($schemaLocator, $fields = null, $siteDetails = null, $resolver = null, $templateResolver = null) {
    $plugin = \YamlCF\Core\Plugin::getInstance();
    $this->schemaLocator = $schemaLocator;
    $this->fields = $fields ?: $plugin->get('field_accessor');
    $this->siteDetails = $siteDetails ?: $plugin->get('global_field_accessor');
    $this->resolver = $resolver ?: $plugin->get('field_resolver');
    $this->templateResolver = $templateResolver ?: $plugin->get('template_resolver');
  }

  /**
   * @return void
   */
  public function register() {
    add_shortcode(self::TAG, [$this, 'render']);
  }

  /**
   * Turn one shortcode into markup
   *
   * @param array|string $atts Attributes as written in the content
   * @return string
   */
  public function render($atts) {
    $atts = shortcode_atts([
      'field' => '',
      'post' => '',
      'source' => 'page',
      'size' => 'full',
      'default' => '',
    ], is_array($atts) ? $atts : [], self::TAG);

    $field = trim((string) $atts['field']);
    if ($field === '') {
      return '';
    }

    if ($atts['source'] === 'site') {
      $value = $this->siteDetails->getField($field);
      $definition = $this->siteFieldDefinition($field);

      return $this->present($value, $definition, $atts);
    }

    $post_id = $this->resolvePostId($atts['post']);
    if ($post_id === null) {
      return '';
    }

    $value = $this->fields->getField($field, $post_id);
    $post = get_post($post_id);
    $definition = $post ? $this->postFieldDefinition($post, $field) : null;

    return $this->present($value, $definition, $atts);
  }

  /**
   * Which post to read from
   *
   * A shortcode can be written by anybody who may edit a post, so naming
   * another post has to be checked: without this it would be a way to read a
   * field off a draft, or off somebody else's private page.
   *
   * @param string $requested The post attribute as written
   * @return int|null Post ID, or null when there is nothing to read
   */
  private function resolvePostId($requested) {
    $requested = trim((string) $requested);

    if ($requested === '') {
      $current = get_the_ID();

      return $current && !$this->isLockedByPassword((int) $current) ? (int) $current : null;
    }

    $post_id = (int) $requested;
    if ($post_id <= 0 || !get_post($post_id)) {
      return null;
    }

    // Published and public, but behind a password this visitor has not entered:
    // WordPress hides its content, so its fields stay hidden as well.
    if ($this->isLockedByPassword($post_id)) {
      return null;
    }

    // A published, publicly viewable page is readable by anyone, logged in or
    // not. Checking read_post alone would refuse every anonymous visitor, since
    // a logged-out user holds no capabilities at all — which would break the
    // ordinary case this attribute exists for.
    if ($this->isPubliclyViewable($post_id)) {
      return $post_id;
    }

    // Anything else — a draft, a private page, a scheduled one — is only
    // readable by somebody allowed to read it.
    return current_user_can('read_post', $post_id) ? $post_id : null;
  }

  /**
   * Whether a post's password is still standing between this visitor and it
   *
   * post_password_required() reads the visitor's password cookie, so somebody
   * who has entered the password sees the fields as they see the content.
   *
   * @param int $post_id Post to check
   * @return bool
   */
  private function isLockedByPassword($post_id) {
    return post_password_required($post_id) && !current_user_can('edit_post', $post_id);
  }

  /**
   * Whether a post can be seen by anybody, logged in or not
   *
   * Not read_post: a logged-out visitor holds no capabilities, so that would
   * refuse every visitor, which is the case the post attribute mostly exists for.
   *
   * @param int $post_id Post to check
   * @return bool
   */
  private function isPubliclyViewable($post_id) {
    return (bool) is_post_publicly_viewable($post_id);
  }

  /**
   * The definition of a top-level field in a parsed schema
   *
   * @param array|null $schema Parsed schema
   * @param string $field Field name
   * @return array|null
   */
  private function fieldDefinition($schema, $field) {
    if (!is_array($schema) || empty($schema['fields']) || !is_array($schema['fields'])) {
      return null;
    }

    foreach ($schema['fields'] as $definition) {
      if (is_array($definition) && isset($definition['name']) && (string) $definition['name'] === $field) {
        return $definition;
      }
    }

    return null;
  }

  /**
   * The definition of a field as a post sees it
   *
   * A post's value can come from its own schema, from the shared schema of
   * its template, or from site details (see YamlCF\PublicAPI\FieldResolver).
   * The definition is looked up in the same places, so a shared image prints
   * as an image rather than as its attachment ID.
   *
   * @param \WP_Post $post Post the value was read from
   * @param string $field Field name
   * @return array|null
   */
  private function postFieldDefinition($post, $field) {
    $definition = $this->fieldDefinition($this->schemaLocator->forPost($post), $field);
    if ($definition !== null) {
      return $definition;
    }

    $template = $this->templateResolver->getTemplateForPost($post);

    $shared_yaml = $this->resolver->templateGlobalSchema($template);
    if (trim($shared_yaml) !== '') {
      $definition = $this->fieldDefinition($this->resolver->parseSchemaCached($shared_yaml), $field);
      if ($definition !== null) {
        return $definition;
      }
    }

    $settings = get_option('yaml_cf_template_settings', []);
    if (is_array($settings) && !empty($settings[$template . '_use_global'])) {
      return $this->siteFieldDefinition($field);
    }

    return null;
  }

  /**
   * The definition of a site details field
   *
   * @param string $field Field name
   * @return array|null
   */
  private function siteFieldDefinition($field) {
    $yaml = get_option('yaml_cf_global_schema', '');
    if (!is_string($yaml) || trim($yaml) === '') {
      return null;
    }

    return $this->fieldDefinition($this->resolver->parseSchemaCached($yaml), $field);
  }

  /**
   * Turn a value into markup suited to the kind of field it came from
   *
   * @param mixed $value The value
   * @param array|null $definition Its field definition, when the schema has one
   * @param array $atts Shortcode attributes
   * @return string
   */
  private function present($value, $definition, array $atts) {
    if ($value === null || $value === '' || $value === []) {
      return esc_html((string) $atts['default']);
    }

    $type = is_array($definition) && isset($definition['type']) ? $definition['type'] : '';
    $is_list = is_array($definition) && !empty($definition['list']);

    switch ($type) {
      case 'image':
        return $this->images($value, $atts['size'], $is_list);

      case 'file':
        return $this->files($value, $is_list);

      case 'rich-text':
        return wp_kses_post((string) $value);

      case 'code':
        // A code field exists to put code on the page. Whoever typed it needed
        // unfiltered_html to keep it as written; everyone else's was cleaned
        // when it was saved.
        return (string) $value;

      case 'boolean':
        return $value ? esc_html__('Yes', 'yaml-custom-fields') : esc_html__('No', 'yaml-custom-fields');

      case 'taxonomy':
        return $this->terms($value);

      case 'data_object':
        return $this->collectionItems($value, $definition);

      case 'object':
      case 'block':
        // A group or a set of sections is not one value, so there is nothing
        // sensible to print. The theme or a block reads those field by field.
        return '';
    }

    if (is_array($value)) {
      return esc_html(implode(', ', array_filter(array_map(function ($item) {
        return is_scalar($item) ? (string) $item : '';
      }, $value))));
    }

    return esc_html((string) $value);
  }

  /**
   * One image, or a gallery
   *
   * @param mixed $value Attachment ID or IDs
   * @param string $size Image size
   * @param bool $is_list Whether the field holds several
   * @return string
   */
  private function images($value, $size, $is_list) {
    $ids = $is_list || is_array($value) ? (array) $value : [$value];
    $size = sanitize_text_field((string) $size);

    $out = '';
    foreach ($ids as $id) {
      $id = (int) $id;
      if ($id > 0) {
        $out .= wp_get_attachment_image($id, $size);
      }
    }

    return $out;
  }

  /**
   * A link to one file, or to several
   *
   * @param mixed $value Attachment ID or IDs
   * @param bool $is_list Whether the field holds several
   * @return string
   */
  private function files($value, $is_list) {
    $ids = $is_list || is_array($value) ? (array) $value : [$value];

    $links = [];
    foreach ($ids as $id) {
      $id = (int) $id;
      $url = $id > 0 ? wp_get_attachment_url($id) : '';
      if (!$url) {
        continue;
      }

      $links[] = sprintf(
        '<a href="%s">%s</a>',
        esc_url($url),
        esc_html(get_the_title($id) ?: basename($url))
      );
    }

    return implode(', ', $links);
  }

  /**
   * The labels of the chosen collection items
   *
   * The stored value is an item ID such as entry_65a1…, which means nothing
   * on a page. The label is what the editor picked it by.
   *
   * @param mixed $value Entry ID or IDs
   * @param array $definition Field definition
   * @return string
   */
  private function collectionItems($value, $definition) {
    $object_type = isset($definition['options']['object_type']) && is_string($definition['options']['object_type'])
      ? $definition['options']['object_type']
      : '';
    if ($object_type === '') {
      return '';
    }

    $labels = \YamlCF\Core\Plugin::getInstance()->get('data_object_repository')->getLabels($object_type);

    $names = [];
    foreach ((array) $value as $entry_id) {
      if (is_scalar($entry_id) && isset($labels[(string) $entry_id])) {
        $names[] = $labels[(string) $entry_id];
      }
    }

    return esc_html(implode(', ', $names));
  }

  /**
   * The names of the chosen categories or tags
   *
   * @param mixed $value Term ID or IDs
   * @return string
   */
  private function terms($value) {
    $names = [];
    foreach ((array) $value as $term_id) {
      $term = get_term((int) $term_id);
      if ($term && !is_wp_error($term)) {
        $names[] = $term->name;
      }
    }

    return esc_html(implode(', ', $names));
  }
}
