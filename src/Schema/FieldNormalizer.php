<?php

/**
 * Field Normalizer
 * Normalize shorthand field syntax to standard structure
 */

namespace YamlCF\Schema;


// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
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
   * Normalize shorthand section field syntax to standard field structure
   *
   * Transforms: - section: "Contact details" → - type: section, name: section_0, label: "Contact details"
   * A bare "- section:" has no label and renders as a plain rule.
   * "- break: true" becomes a section that draws nothing; it only starts a new row.
   *
   * @param array $fields The fields array from parsed YAML
   * @return array Normalized fields array
   */
  public function normalizeSectionFields($fields) {
    $normalized = [];
    $section_counter = 0;

    foreach ($fields as $field) {
      if (!is_array($field)) {
        $normalized[] = $field;
        continue;
      }

      // Shorthand is "- section: Title" or a bare "- section:" (value null)
      $is_shorthand = array_key_exists('section', $field)
        && !isset($field['type'])
        && !isset($field['name']);

      if ($is_shorthand) {
        $section = [
          'type' => 'section',
          'name' => 'section_' . $section_counter++,
        ];
        if (is_string($field['section']) && $field['section'] !== '') {
          $section['label'] = $field['section'];
        }
        $normalized[] = $section;
        continue;
      }

      // "- break: true" starts a new row without drawing anything. It is a
      // section that renders nothing, so everything downstream treats it the same.
      $is_break_shorthand = array_key_exists('break', $field)
        && !isset($field['type'])
        && !isset($field['name']);

      if ($is_break_shorthand) {
        // "break: false" says explicitly that nothing should happen here
        if ($field['break'] === false) {
          continue;
        }
        $normalized[] = [
          'type' => 'section',
          'name' => 'section_' . $section_counter++,
          'break' => true,
        ];
        continue;
      }

      if (isset($field['type']) && $field['type'] === 'break') {
        $normalized[] = [
          'type' => 'section',
          'name' => 'section_' . $section_counter++,
          'break' => true,
        ];
        continue;
      }

      // Long form still needs a name: sections are addressed by position, not by name
      if (isset($field['type']) && $field['type'] === 'section' && !isset($field['name'])) {
        $field['name'] = 'section_' . $section_counter++;
      }

      $normalized[] = $field;
    }

    return $normalized;
  }

  /**
   * Normalize all shorthand syntax in fields array
   *
   * @param array $fields The fields array from parsed YAML
   * @return array Normalized fields array
   */
  public function normalizeAll($fields) {
    return $this->normalizeSectionFields($this->normalizeInfoFields($fields));
  }
}
