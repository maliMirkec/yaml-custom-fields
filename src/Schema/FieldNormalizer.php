<?php
/**
 * Field Normalizer
 * Normalize shorthand field syntax to standard structure
 */

namespace YamlCF\Schema;

class FieldNormalizer {
  /**
   * Normalize shorthand info field syntax to standard field structure
   * Transforms: - info: "text" → - type: info, name: info_0, text: "text"
   *
   * @param array $fields The fields array from parsed YAML
   * @return array Normalized fields array
   */
  public function normalizeInfoFields($fields) {
    $normalized = [];
    $info_counter = 0;

    foreach ($fields as $field) {
      // Check if this is shorthand info syntax (single key 'info')
      if (isset($field['info']) && !isset($field['type']) && !isset($field['name'])) {
        // This is shorthand: - info: "text"
        $normalized[] = [
          'type' => 'info',
          'name' => 'info_' . $info_counter++,
          'text' => $field['info']
        ];
      } else {
        // Standard field structure, keep as-is
        $normalized[] = $field;
      }
    }

    return $normalized;
  }

  /**
   * Normalize all shorthand syntax in fields array
   * Currently only handles info fields, but can be extended
   *
   * @param array $fields The fields array from parsed YAML
   * @return array Normalized fields array
   */
  public function normalizeAll($fields) {
    return $this->normalizeInfoFields($fields);
  }
}
