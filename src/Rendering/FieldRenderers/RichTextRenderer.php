<?php

/**
 * Rich Text Renderer
 * WordPress's own editor, with a short toolbar
 */

namespace YamlCF\Rendering\FieldRenderers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Rendering\BaseFieldRenderer;
use YamlCF\Rendering\BlockFieldRendererInterface;

class RichTextRenderer extends BaseFieldRenderer implements BlockFieldRendererInterface {
  public function render($field, $value, $state) {
    wp_editor($value, $state['field_id'], [
      'textarea_name' => 'yaml_cf[' . $state['field_name'] . ']',
      'textarea_rows' => 10,
      'media_buttons' => true,
      'tinymce' => [
        'toolbar1' => 'formatselect,bold,italic,bullist,numlist,link,unlink',
      ],
      '_content_editor_dfw' => false
    ]);
  }

  public function renderInBlock($field, $value, $state) {
    $readonly = $state['readonly'];
    $editor_settings = [
      'textarea_name' => $this->blockInputName($state),
      'textarea_rows' => 10,
      'media_buttons' => !$readonly,
      'wpautop' => true,
      'tinymce' => [
        'toolbar1' => 'formatselect,bold,italic,bullist,numlist,link,unlink',
        'wp_autoresize_on' => true,
      ],
      'quicktags' => true,
    ];
    if ($readonly) {
      $editor_settings['tinymce']['readonly'] = 1;
      $editor_settings['quicktags'] = false;
    }
    wp_editor($value, $state['field_id'], $editor_settings);
  }
}
