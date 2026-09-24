<?php

/**
 * Data Object Repository
 * Handle all data object types and entries
 */

namespace YamlCF\Data\Repositories;


// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
class DataObjectRepository {
  /**
   * Where the items are kept
   *
   * @var \YamlCF\Data\EntryStore|null
   */
  private $store = null;

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
    return $this->store()->all($type_slug);
  }

  /**
   * The id => label index for a collection, without reading the items
   *
   * @param string $type_slug Type slug
   * @return array<string, string>
   */
  public function getEntryIndex($type_slug) {
    return $this->store()->index($type_slug);
  }

  /**
   * How many items a collection holds
   *
   * @param string $type_slug Type slug
   * @return int
   */
  public function countEntries($type_slug) {
    return $this->store()->count($type_slug);
  }

  /**
   * One page of items, read without loading the rest
   *
   * @param string $type_slug Type slug
   * @param int $paged Page number, from 1
   * @param int $per_page Items per page
   * @return array{entries: array<string, array>, total: int, total_pages: int, paged: int}
   */
  public function getEntryPage($type_slug, $paged = 1, $per_page = 50) {
    $index = $this->store()->index($type_slug);
    $total = count($index);
    $per_page = max(1, (int) $per_page);
    $total_pages = max(1, (int) ceil($total / $per_page));
    $paged = min(max(1, (int) $paged), $total_pages);

    $ids = array_slice(array_keys($index), ($paged - 1) * $per_page, $per_page);

    return [
      'entries' => $this->store()->getMany($type_slug, $ids),
      'total' => $total,
      'total_pages' => $total_pages,
      'paged' => $paged,
    ];
  }

  /**
   * Items whose label matches, found from the index rather than by reading all
   *
   * @param string $type_slug Type slug
   * @param string $search Text to look for
   * @param int $limit Most items to return
   * @return array{entries: array<string, array>, labels: array<string, string>, total: int}
   */
  public function searchEntries($type_slug, $search, $limit) {
    $found = $this->store()->search($type_slug, $search, $limit);
    $index = $this->store()->index($type_slug);

    $labels = [];
    foreach ($found['ids'] as $entry_id) {
      $labels[$entry_id] = isset($index[$entry_id]) ? $index[$entry_id] : $entry_id;
    }

    return [
      'entries' => $this->store()->getMany($type_slug, $found['ids']),
      'labels' => $labels,
      'total' => $found['total'],
    ];
  }

  /**
   * Replace every item of a collection, as an import does
   *
   * @param string $type_slug Type slug
   * @param array<string, array> $entries Items keyed by entry id
   * @param string $label_field Field whose value labels an item
   * @return bool
   */
  public function replaceEntries($type_slug, array $entries, $label_field = '') {
    return $this->store()->replaceAll($type_slug, $entries, $label_field);
  }

  /**
   * Where the items are kept
   *
   * @return \YamlCF\Data\EntryStore
   */
  private function store() {
    if ($this->store === null) {
      $this->store = new \YamlCF\Data\EntryStore();
    }

    return $this->store;
  }

  /**
   * Get a specific entry
   *
   * @param string $type_slug Type slug
   * @param string $entry_id Entry ID
   * @return array|null Entry data or null if not found
   */
  public function getEntry($type_slug, $entry_id) {
    return $this->store()->get($type_slug, $entry_id);
  }

  /**
   * Save an entry
   *
   * @param string $type_slug Type slug
   * @param string $entry_id Entry ID
   * @param array $data Entry data
   * @return bool Success
   */
  public function saveEntry($type_slug, $entry_id, $data, $label_field = '') {
    return $this->store()->save($type_slug, $entry_id, $data, $label_field);
  }

  /**
   * Delete an entry
   *
   * @param string $type_slug Type slug
   * @param string $entry_id Entry ID
   * @return bool Success
   */
  public function deleteEntry($type_slug, $entry_id) {
    return $this->store()->delete($type_slug, $entry_id);
  }

  /**
   * Delete all entries for a type
   *
   * @param string $type_slug Type slug
   * @return bool Success
   */
  public function deleteAllEntries($type_slug) {
    return $this->store()->deleteAll($type_slug);
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
