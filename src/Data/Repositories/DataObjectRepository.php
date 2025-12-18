<?php
/**
 * Data Object Repository
 * Handle all data object types and entries
 */

namespace YamlCF\Data\Repositories;

class DataObjectRepository {
  /**
   * Get all data object types
   *
   * @return array Data object types indexed by slug
   */
  public function getTypes() {
    $types = get_option('yaml_cf_data_object_types', []);
    return is_array($types) ? $types : [];
  }

  /**
   * Get a specific data object type
   *
   * @param string $slug Type slug
   * @return array|null Type data or null if not found
   */
  public function getType($slug) {
    $types = $this->getTypes();
    return isset($types[$slug]) ? $types[$slug] : null;
  }

  /**
   * Save a data object type
   *
   * @param string $slug Type slug
   * @param array $data Type data (name, schema, etc.)
   * @return bool Success
   */
  public function saveType($slug, $data) {
    $types = $this->getTypes();
    $types[$slug] = $data;
    return update_option('yaml_cf_data_object_types', $types, false);
  }

  /**
   * Delete a data object type
   *
   * @param string $slug Type slug
   * @return bool Success
   */
  public function deleteType($slug) {
    $types = $this->getTypes();
    if (isset($types[$slug])) {
      unset($types[$slug]);
      // Also delete all entries for this type
      $this->deleteAllEntries($slug);
      return update_option('yaml_cf_data_object_types', $types, false);
    }
    return true;
  }

  /**
   * Get all entries for a data object type
   *
   * @param string $type_slug Type slug
   * @return array Entries indexed by entry ID
   */
  public function getEntries($type_slug) {
    $entries = get_option('yaml_cf_data_object_entries_' . $type_slug, []);
    return is_array($entries) ? $entries : [];
  }

  /**
   * Get a specific entry
   *
   * @param string $type_slug Type slug
   * @param string $entry_id Entry ID
   * @return array|null Entry data or null if not found
   */
  public function getEntry($type_slug, $entry_id) {
    $entries = $this->getEntries($type_slug);
    return isset($entries[$entry_id]) ? $entries[$entry_id] : null;
  }

  /**
   * Save an entry
   *
   * @param string $type_slug Type slug
   * @param string $entry_id Entry ID
   * @param array $data Entry data
   * @return bool Success
   */
  public function saveEntry($type_slug, $entry_id, $data) {
    $entries = $this->getEntries($type_slug);
    $entries[$entry_id] = $data;
    return update_option('yaml_cf_data_object_entries_' . $type_slug, $entries, false);
  }

  /**
   * Delete an entry
   *
   * @param string $type_slug Type slug
   * @param string $entry_id Entry ID
   * @return bool Success
   */
  public function deleteEntry($type_slug, $entry_id) {
    $entries = $this->getEntries($type_slug);
    if (isset($entries[$entry_id])) {
      unset($entries[$entry_id]);
      return update_option('yaml_cf_data_object_entries_' . $type_slug, $entries, false);
    }
    return true;
  }

  /**
   * Delete all entries for a type
   *
   * @param string $type_slug Type slug
   * @return bool Success
   */
  public function deleteAllEntries($type_slug) {
    return delete_option('yaml_cf_data_object_entries_' . $type_slug);
  }

  /**
   * Find entry by ID across all types
   *
   * @param string $entry_id Entry ID
   * @return array|null Entry data with 'type' key, or null if not found
   */
  public function findEntryById($entry_id) {
    $types = $this->getTypes();
    foreach ($types as $type_slug => $type_data) {
      $entry = $this->getEntry($type_slug, $entry_id);
      if ($entry !== null) {
        $entry['_type'] = $type_slug;
        return $entry;
      }
    }
    return null;
  }
}
