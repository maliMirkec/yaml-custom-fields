<?php
namespace YamlCF\Rendering\FieldRenderers;
use YamlCF\Rendering\BaseFieldRenderer;

class RichTextRenderer extends BaseFieldRenderer {
  public function render($field, $value, $context) {
    $field_id = $this->getFieldId($field['name'], $context);
    $this->renderLabel($field, $context);
    wp_editor($value, $field_id, [
      'textarea_name' => 'yaml_cf[' . $field['name'] . ']',
      'textarea_rows' => 10,
      'media_buttons' => true,
      'tinymce' => ['toolbar1' => 'formatselect,bold,italic,bullist,numlist,link,unlink'],
    ]);
  }
}
