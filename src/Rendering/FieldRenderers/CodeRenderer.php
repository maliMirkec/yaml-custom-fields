<?php
namespace YamlCF\Rendering\FieldRenderers;
use YamlCF\Rendering\BaseFieldRenderer;

class CodeRenderer extends BaseFieldRenderer {
  public function render($field, $value, $context) {
    $field_id = $this->getFieldId($field['name'], $context);
    $this->renderLabel($field, $context);
    $options = isset($field['options']) ? $field['options'] : [];
    $language = isset($options['language']) ? $options['language'] : 'html';
    echo '<textarea name="yaml_cf[' . esc_attr($field['name']) . ']" id="' . esc_attr($field_id) . '" rows="10" class="large-text code" data-language="' . esc_attr($language) . '">' . esc_textarea($value) . '</textarea>';
  }
}
