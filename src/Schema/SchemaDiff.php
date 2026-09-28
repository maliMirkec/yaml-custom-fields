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
   * The names a schema defines at its top level only
   *
   * Deliberately not collectFieldNames(): that flattens an object's children and
   * a block's fields into the same list, and values are keyed by depth. Moving a
   * field into a group is an ordinary edit, and it strands the old top-level
   * value — but the flat list still contains the name, one level down, so the
   * stranded value would look like a field that is still in use.
   *
   * @param array|null $schema Parsed schema
   * @return string[] Top-level field names, without duplicates
   */
  public function topLevelFieldNames($schema) {
    if (!is_array($schema) || empty($schema['fields']) || !is_array($schema['fields'])) {
      return [];
    }

    $names = [];
    foreach ($schema['fields'] as $field) {
      // is_scalar(), not is_string(): YAML reads "name: 2024" as a number, and
      // that field's value is as live as any other
      if (is_array($field) && !empty($field['name']) && is_scalar($field['name'])) {
        $names[] = (string) $field['name'];
      }
    }

    return array_values(array_unique($names));
  }

  /**
   * Put back the values that no field describes any more
   *
   * Saving rebuilds stored data from what a form sent. A field deleted or
   * renamed in the schema renders no input, so it sends nothing, and its content
   * used to be destroyed on the next save. The editor promises the opposite:
   * that the content is still saved and that putting the name back brings it
   * back. This is what makes that true, for every screen that saves fields.
   *
   * Only names at the level being looked at count as still in use. A group's
   * children live inside the group's own value, so a name reused at another
   * depth says nothing about a stranded value one level up.
   *
   * Recurses into groups, because a field removed from inside a group strands
   * its value just as surely as one removed from the top. Repeating sections are
   * left alone on purpose: their items are positional and each can be a
   * different section type, so there is no single field list to measure an item
   * against.
   *
   * @param array $submitted What the form sent for this level
   * @param array $stored What is already saved for this level
   * @param array|null $schema Schema node holding this level's 'fields'
   * @param string[] $reserved Names the plugin owns, which are never put back
   * @return array The submitted values, with stranded ones restored
   */
  public function restoreStranded($submitted, $stored, $schema, array $reserved = []) {
    if (!is_array($stored) || empty($stored)) {
      return is_array($submitted) ? $submitted : [];
    }

    if (!is_array($submitted)) {
      $submitted = [];
    }

    $known = [];
    if (is_array($schema) && !empty($schema['fields']) && is_array($schema['fields'])) {
      foreach ($schema['fields'] as $field) {
        if (is_array($field) && !empty($field['name']) && is_scalar($field['name'])) {
          $known[(string) $field['name']] = $field;
        }
      }
    }

    foreach ($stored as $key => $value) {
      $key = (string) $key;

      if (!isset($known[$key])) {
        // Bookkeeping keys are not content, so they are allowed to go
        if (in_array($key, $reserved, true)) {
          continue;
        }

        // The form sent something for it, even an empty string: that wins
        if (array_key_exists($key, $submitted)) {
          continue;
        }

        $submitted[$key] = $value;
        continue;
      }

      // Still a field here. If it groups other fields, look inside it too.
      $type = isset($known[$key]['type']) ? $known[$key]['type'] : '';
      if ($type === 'object' && is_array($value) && isset($submitted[$key]) && is_array($submitted[$key])) {
        $submitted[$key] = $this->restoreStranded(
          $submitted[$key],
          $value,
          ['fields' => isset($known[$key]['fields']) && is_array($known[$key]['fields']) ? $known[$key]['fields'] : []]
        );
      }
    }

    return $submitted;
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
      if (empty($field['name']) || !is_scalar($field['name'])) {
        continue;
      }

      $names[] = (string) $field['name'];

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
