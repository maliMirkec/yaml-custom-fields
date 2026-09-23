<?php

/**
 * Section Grouper
 * Split a flat list of fields into the segments marked by section fields
 */

namespace YamlCF\Schema;


// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
class SectionGrouper {
  /**
   * Group fields by the section breaks between them
   *
   * A section field starts a new group and is removed from the field list; the
   * fields after it belong to that group until the next section. Fields before
   * the first section form a leading group with no header, which renders exactly
   * as it did before sections existed.
   *
   * @param array $fields Normalized fields array
   * @return array List of ['section' => array|null, 'fields' => array]
   */
  public function group($fields) {
    if (!is_array($fields)) {
      return [];
    }

    $groups = [];
    $current = ['section' => null, 'fields' => []];

    foreach ($fields as $field) {
      if ($this->isSection($field)) {
        // Keep the leading group only if it holds something
        if (!empty($current['fields']) || $current['section'] !== null) {
          $groups[] = $current;
        }
        $current = ['section' => $field, 'fields' => []];
        continue;
      }

      $current['fields'][] = $field;
    }

    if (!empty($current['fields']) || $current['section'] !== null) {
      $groups[] = $current;
    }

    return $groups;
  }

  /**
   * Check whether a field list contains any section break
   *
   * @param array $fields Normalized fields array
   * @return bool
   */
  public function hasSections($fields) {
    if (!is_array($fields)) {
      return false;
    }

    foreach ($fields as $field) {
      if ($this->isSection($field)) {
        return true;
      }
    }

    return false;
  }

  /**
   * Remove section fields from a list, leaving only fields that hold data
   *
   * @param array $fields Normalized fields array
   * @return array Fields without section breaks
   */
  public function stripSections($fields) {
    if (!is_array($fields)) {
      return [];
    }

    return array_values(array_filter($fields, function ($field) {
      return !$this->isSection($field);
    }));
  }

  /**
   * Check if a field is a section break
   *
   * @param mixed $field Field definition
   * @return bool
   */
  public function isSection($field) {
    return is_array($field) && isset($field['type']) && $field['type'] === 'section';
  }
}
