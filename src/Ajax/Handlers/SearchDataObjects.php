<?php

namespace YamlCF\Ajax\Handlers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Ajax\AjaxHandler;

/**
 * Search the entries of one collection, for the picker a data_object field
 * shows when the collection is too big to list in full.
 *
 * Action: wp_ajax_yaml_cf_search_data_objects
 */
class SearchDataObjects extends AjaxHandler {
  const DEFAULT_LIMIT = 30;
  const MAX_LIMIT = 100;

  private $repository;
  private $schemaParser;

  public function __construct($repository, $schemaParser) {
    $this->repository = $repository;
    $this->schemaParser = $schemaParser;
  }

  public function handle() {
    // Writers use this picker in the post editor
    $this->verifyRequest('edit_posts');

    $object_type = $this->postText('object_type');
    $type = $object_type !== '' ? $this->repository->getType($object_type) : null;

    if ($type === null) {
      $this->sendError(__('That collection does not exist.', 'yaml-custom-fields'));
    }

    $search = $this->postText('search');
    $limit = absint($this->postText('limit', (string) self::DEFAULT_LIMIT));
    $limit = $limit > 0 ? min($limit, self::MAX_LIMIT) : self::DEFAULT_LIMIT;

    $label_field = $this->getLabelField($type);
    $entries = $this->repository->getEntries($object_type);

    $results = [];
    $matched = 0;
    foreach ($entries as $entry_id => $entry) {
      $label = $this->getEntryLabel($entry, $label_field, $entry_id);

      if ($search !== '' && stripos($label, $search) === false) {
        continue;
      }

      $matched++;
      if (count($results) < $limit) {
        $results[] = ['id' => (string) $entry_id, 'label' => $label];
      }
    }

    $this->sendSuccess([
      'results' => $results,
      'truncated' => $matched > count($results),
    ]);
  }

  /**
   * The collection's first field doubles as the label for its entries
   *
   * @param array $type Collection definition
   * @return string Field name, or '' when the schema has no fields
   */
  private function getLabelField($type) {
    if (empty($type['schema'])) {
      return '';
    }

    $schema = $this->schemaParser->parse($type['schema']);
    return !empty($schema['fields'][0]['name']) ? $schema['fields'][0]['name'] : '';
  }

  /**
   * @param array $entry Entry data
   * @param string $label_field Field to read the label from
   * @param string $entry_id Fallback when the label is missing or not text
   * @return string
   */
  private function getEntryLabel($entry, $label_field, $entry_id) {
    if ($label_field === '' || !isset($entry[$label_field])) {
      return (string) $entry_id;
    }

    $label = $entry[$label_field];
    return is_string($label) && $label !== '' ? $label : (string) $entry_id;
  }
}
