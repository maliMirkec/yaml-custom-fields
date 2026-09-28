<?php

/**
 * Snippet Builder
 * The "Copy code" button beside each field, with the PHP that reads it
 */

namespace YamlCF\Rendering;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SnippetBuilder {
  /**
   * The snippet for a top-level or nested field
   *
   * Nothing for a collection item (it is read through its collection), a block
   * field (it prints its own, see forBlockList()) or a section break.
   *
   * @param array $field Field definition
   * @param array|null $context Render context
   * @return string PHP code, or '' when the field has no snippet
   */
  public function forField($field, $context) {
    if (!($context && is_array($context) && isset($context['type']) && $context['type'] !== 'data_object' && $field['type'] !== 'block' && $field['type'] !== 'section')) {
      return '';
    }

    // Site details have a function of their own. Through ycf_get_field()
    // they only show on templates with site details switched on, and never
    // in header.php or footer.php, which is where they are usually wanted.
    if ($context['type'] === 'global') {
      return $this->forSiteDetail($field);
    }

    // Determine the function name and parameters based on field type
    $function_name = 'ycf_get_field';
    $extra_params = '';
    $post_id_param = '';

    if (isset($field['type'])) {
      if ($context['type'] === 'partial' && isset($context['template'])) {
        $post_id_param = ", 'partial:" . esc_js($context['template']) . "'";
      } else {
        $post_id_param = ", null";
      }

      if ($field['type'] === 'image') {
        $function_name = 'ycf_get_image';
        $extra_params = ", 'thumbnail'"; // Size options: thumbnail, medium, large, full
      } elseif ($field['type'] === 'file') {
        $function_name = 'ycf_get_file';
      } elseif ($field['type'] === 'taxonomy') {
        $function_name = 'ycf_get_term';
      } elseif ($field['type'] === 'post_type') {
        $function_name = 'ycf_get_post_type';
      }
    }

    $is_file_list_field = ($field['type'] === 'file') && isset($field['list']) && $field['list'];
    $is_image_list_field = ($field['type'] === 'image') && isset($field['list']) && $field['list'];

    if ($is_file_list_field) {
      return "\$files = ycf_get_file('" . esc_js($field['name']) . "'" . $post_id_param . ");\nif (!empty(\$files)) {\n  foreach (\$files as \$file) {\n    echo '<a href=\"' . esc_url(\$file['url']) . '\">' . esc_html(\$file['filename']) . '</a>';\n  }\n}";
    }

    if ($is_image_list_field) {
      return "\$images = ycf_get_image('" . esc_js($field['name']) . "'" . $post_id_param . ", 'medium');\nif (!empty(\$images)) {\n  foreach (\$images as \$image) {\n    echo '<img src=\"' . esc_url(\$image['url']) . '\" alt=\"' . esc_attr(\$image['alt']) . '\" />';\n  }\n}";
    }

    return $function_name . "('" . esc_js($field['name']) . "'" . $post_id_param . $extra_params . ")";
  }

  /**
   * The snippet for a site details field
   *
   * ycf_get_global_field() hands back what is stored, so an image or a file
   * comes back as an attachment ID and is turned into markup here.
   *
   * @param array $field Field definition
   * @return string PHP code
   */
  public function forSiteDetail($field) {
    $read = "ycf_get_global_field('" . esc_js($field['name']) . "')";
    $is_list = !empty($field['list']);

    if ($field['type'] === 'image') {
      return $is_list
        ? "foreach ((array) " . $read . " as \$image_id) {\n  echo wp_get_attachment_image(\$image_id, 'medium');\n}"
        : "echo wp_get_attachment_image(" . $read . ", 'medium');";
    }

    if ($field['type'] === 'file') {
      return $is_list
        ? "foreach ((array) " . $read . " as \$file_id) {\n  echo '<a href=\"' . esc_url(wp_get_attachment_url(\$file_id)) . '\">' . esc_html(get_the_title(\$file_id)) . '</a>';\n}"
        : "echo esc_url(wp_get_attachment_url(" . $read . "));";
    }

    return $read;
  }

  /**
   * The snippet for a list of blocks: a loop, with the calls for reading each
   * kind of field inside a block
   *
   * @param array $field Block field definition
   * @return string PHP code
   */
  public function forBlockList($field) {
    return "<?php\n// Get all blocks\n\$blocks = ycf_get_field('" . esc_js($field['name']) . "', null);\n\nif (!empty(\$blocks)) {\n  foreach (\$blocks as \$block) {\n    // Access block fields using context parameter:\n    // \$value = ycf_get_field('field_name', null, \$block);\n    // \$image = ycf_get_image('image_field', null, 'thumbnail', \$block);\n    // \$file = ycf_get_file('file_field', null, \$block);\n    // \$term = ycf_get_term('taxonomy_field', null, \$block);\n    // \$post_type = ycf_get_post_type('post_type_field', null, \$block);\n    // \$data_object = ycf_get_data_object('data_object_field', null, \$block);\n  }\n}\n?>";
  }

  /**
   * The snippet for a field inside a block, read from the loop's $block
   *
   * @param array $block_field Field definition
   * @param string $block_field_type The field's type ('string' when it has none)
   * @return string PHP code
   */
  public function forBlockField($block_field, $block_field_type) {
    $function_name = 'ycf_get_field';
    $extra_snippet_params = '';
    if ($block_field_type === 'image') {
      $function_name = 'ycf_get_image';
      $extra_snippet_params = "'thumbnail', ";
    } elseif ($block_field_type === 'file') {
      $function_name = 'ycf_get_file';
    } elseif ($block_field_type === 'taxonomy') {
      $function_name = 'ycf_get_term';
    } elseif ($block_field_type === 'post_type') {
      $function_name = 'ycf_get_post_type';
    } elseif ($block_field_type === 'data_object') {
      $function_name = 'ycf_get_data_object';
    }

    if (($block_field_type === 'file') && isset($block_field['list']) && $block_field['list']) {
      return "\$files = ycf_get_file('" . esc_js($block_field['name']) . "', null, \$block);\nif (!empty(\$files)) {\n  foreach (\$files as \$file) {\n    echo '<a href=\"' . esc_url(\$file['url']) . '\">' . esc_html(\$file['filename']) . '</a>';\n  }\n}";
    }

    return $function_name . "('" . esc_js($block_field['name']) . "', null, " . $extra_snippet_params . "\$block)";
  }

  /**
   * Print the "Copy code" button and its popover
   *
   * Only for administrators: writers do not edit theme files.
   *
   * @param string $snippet PHP code
   * @param string $popover_id Popover element ID
   * @param string $code_class Class for the <code> element, if any
   * @return void
   */
  public function render($snippet, $popover_id, $code_class = '') {
    if (!current_user_can('manage_options')) {
      return;
    }

    echo '<span class="yaml-cf-snippet-wrapper">';
    echo '<button type="button" class="yaml-cf-copy-snippet" data-snippet="' . esc_attr($snippet) . '" data-popover="' . esc_attr($popover_id) . '">';
    echo '<span class="dashicons dashicons-editor-code"></span>';
    echo '<span class="snippet-text">' . esc_html__('Copy code', 'yaml-custom-fields') . '</span>';
    echo '</button>';
    echo '<span class="yaml-cf-snippet-popover" id="' . esc_attr($popover_id) . '" role="tooltip">';
    echo $code_class !== ''
      ? '<code class="' . esc_attr($code_class) . '">' . esc_html($snippet) . '</code>'
      : '<code>' . esc_html($snippet) . '</code>';
    echo '<span class="snippet-hint">' . esc_html__('Click to copy', 'yaml-custom-fields') . '</span>';
    echo '</span>';
    echo '</span>';
  }
}
