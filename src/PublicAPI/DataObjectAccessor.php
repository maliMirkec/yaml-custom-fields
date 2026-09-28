<?php

/**
 * Data Object Accessor
 * yaml_cf_get_data_object() and yaml_cf_get_data_objects()
 */

namespace YamlCF\PublicAPI;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class DataObjectAccessor {
  /** @var FieldAccessor */
  private $fields;

  /** @var \YamlCF\Data\Repositories\DataObjectRepository */
  private $repository;

  /** @var FieldResolver|null */
  private $resolver;

  /**
   * @param FieldAccessor $fields Reads the chosen item's ID
   * @param \YamlCF\Data\Repositories\DataObjectRepository $repository Collection items
   * @param FieldResolver|null $resolver Decodes code fields against the collection's schema
   */
  public function __construct(FieldAccessor $fields, $repository, $resolver = null) {
    $this->fields = $fields;
    $this->repository = $repository;
    $this->resolver = $resolver;
  }

  /**
   * An item as a theme should see it: code fields decoded
   *
   * Code fields are stored encoded, like everywhere else in the plugin. Pages
   * and site details are decoded on the way out; collection items were not, so
   * a theme read "__YAMLCF_B64__…" out of a collection's code field.
   *
   * @param string $type_slug Collection slug
   * @param array $entry Item as stored
   * @return array
   */
  public function decodeEntry($type_slug, $entry) {
    if (!is_array($entry)) {
      return $entry;
    }

    $resolver = $this->resolver ?: \YamlCF\Core\Plugin::getInstance()->get('field_resolver');
    $type = $this->repository->getType($type_slug);
    $schema = is_array($type) && isset($type['schema']) && is_string($type['schema']) ? $type['schema'] : null;

    return $resolver->decodeAll($entry, $schema);
  }

  /**
   * The collection item a data_object field points at, or its items
   *
   * A field with list: true holds several items and returns a list of them,
   * in the order they were chosen, the way yaml_cf_get_image() returns a
   * gallery. It used to return null, so the documented list could not be read.
   *
   * @param string $field_name The field's name
   * @param int|string|null $post_id Post ID, 'partial:<file>', or null for the current post
   * @param array|null $context_data Values of a block item to read from instead
   * @return array|null The item's values, a list of items, or null
   */
  public function getDataObject($field_name, $post_id = null, $context_data = null) {
    $entry_id = $this->fields->getField($field_name, $post_id, $context_data);
    // A block item's fields are not looked up; the old search stands in there
    $collection = is_array($context_data) ? '' : $this->collectionOf($field_name, $post_id);

    if (is_array($entry_id)) {
      $entries = [];
      foreach ($entry_id as $one_id) {
        $entry = is_string($one_id) && $one_id !== '' ? $this->findEntry($one_id, $collection) : null;
        if ($entry !== null) {
          $entries[] = $entry;
        }
      }

      return !empty($entries) ? $entries : null;
    }

    if (!$entry_id || !is_string($entry_id)) {
      return null;
    }

    return $this->findEntry($entry_id, $collection);
  }

  /**
   * The collection a data_object field's schema says it picks from
   *
   * @param string $field_name The field's name
   * @param int|string|null $post_id Post ID, 'partial:<file>', or null for the current post
   * @return string Collection slug, or '' when no schema says
   */
  private function collectionOf($field_name, $post_id) {
    $resolver = $this->resolver ?: \YamlCF\Core\Plugin::getInstance()->get('field_resolver');
    $definition = $resolver->fieldDefinitionFor($field_name, $post_id);

    return isset($definition['options']['object_type']) && is_string($definition['options']['object_type'])
      ? $definition['options']['object_type']
      : '';
  }

  /**
   * One item, from the collection the field picks from, or from any
   *
   * Item IDs made on this site are unique across collections, but an import
   * keeps the IDs in its file, so two collections can both hold "entry_1".
   * Searching every collection then returned the first one's item for a field
   * that picks from the second. The field's own collection is used whenever
   * its schema names one that exists.
   *
   * @param string $entry_id Entry ID
   * @param string $collection Collection the field picks from, or ''
   * @return array|null
   */
  private function findEntry($entry_id, $collection = '') {
    $data_object_types = get_option('yaml_cf_data_object_types', []);
    if (!is_array($data_object_types)) {
      return null;
    }

    if ($collection !== '' && isset($data_object_types[$collection])) {
      $entry = $this->repository->getEntry($collection, $entry_id);

      return $entry !== null ? $this->decodeEntry($collection, $entry) : null;
    }

    // No schema to say which collection, so every collection is asked for that
    // one id. Asked for the id rather than for all of its items: since 2.0.0
    // each item is an option of its own, so reading a whole collection to look
    // one id up would read every item of every collection on a page view.
    foreach (array_keys($data_object_types) as $type_slug) {
      $entry = $this->repository->getEntry($type_slug, $entry_id);
      if ($entry !== null) {
        return $this->decodeEntry((string) $type_slug, $entry);
      }
    }

    return null;
  }

  /**
   * Every item of a collection
   *
   * @param string $object_type The collection's short name
   * @return array Items keyed by entry ID
   */
  public function getDataObjects($object_type) {
    if (empty($object_type) || !is_string($object_type)) {
      return [];
    }

    $entries = $this->repository->getEntries($object_type);
    if (!is_array($entries)) {
      return [];
    }

    foreach ($entries as $entry_id => $entry) {
      $entries[$entry_id] = $this->decodeEntry($object_type, $entry);
    }

    return $entries;
  }
}
