<?php

/**
 * Schema Diff
 * Compare two versions of a schema to find the fields that went away
 */

namespace YamlCF\Schema;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renaming or deleting a field in a schema leaves whatever was typed into it
 * stranded in the database: the value is still there, but nothing renders it and
 * nothing points at it. From the editor's chair the text simply disappeared.
 *
 * This finds those fields so that saving a schema can say what is about to
 * happen instead of doing it quietly.
 */
class SchemaDiff {
  /**
   * Every field name a schema defines, including nested and block fields
   *
   * Names are flat, matching how values are keyed in the stored data: an
   * object's children and a block's fields are collected under their own names.
   *
   * @param array|null $schema Parsed schema
   * @return string[] Field names, without duplicates
   */
  public function collectFieldNames($schema) {
    if (!is_array($schema) || empty($schema['fields']) || !is_array($schema['fields'])) {
      return [];
    }

    return array_values(array_unique($this->walk($schema['fields'])));
  }

  /**
   * @param array $fields Schema field definitions
   * @return string[] Field names, possibly with duplicates
   */
  private function walk($fields) {
    $names = [];

    foreach ($fields as $field) {
      if (!is_array($field)) {
        continue;
      }

      // "- section: Hero" and "- break: true" carry no data of their own
      if (empty($field['name']) || !is_string($field['name'])) {
        continue;
      }

      $names[] = $field['name'];

      if (!empty($field['fields']) && is_array($field['fields'])) {
        $names = array_merge($names, $this->walk($field['fields']));
      }

      if (!empty($field['blocks']) && is_array($field['blocks'])) {
        foreach ($field['blocks'] as $block) {
          if (!empty($block['fields']) && is_array($block['fields'])) {
            $names = array_merge($names, $this->walk($block['fields']));
          }
        }
      }
    }

    return $names;
  }

  /**
   * The fields that exist in the old schema but not the new one
   *
   * A rename shows up here as the old name: there is no way to tell a rename
   * from a deletion, and both strand the same content.
   *
   * @param array|null $old_schema Parsed schema being replaced
   * @param array|null $new_schema Parsed schema being saved
   * @return string[] Field names that are about to lose their content
   */
  public function removedFieldNames($old_schema, $new_schema) {
    $old = $this->collectFieldNames($old_schema);
    if (empty($old)) {
      return [];
    }

    return array_values(array_diff($old, $this->collectFieldNames($new_schema)));
  }
}
