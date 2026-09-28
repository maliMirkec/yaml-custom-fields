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

  /**
   * @param \YamlCF\Data\Repositories\DataObjectRepository $repository Collections
   */
  public function __construct($repository) {
    $this->repository = $repository;
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

    // Matched against the collection's index of labels, so a search reads that
    // and the handful of items it is about — not every item in the collection.
    $found = $this->repository->searchEntries($object_type, $search, $limit);

    $results = [];
    foreach ($found['labels'] as $entry_id => $label) {
      $results[] = ['id' => (string) $entry_id, 'label' => (string) $label];
    }

    $this->sendSuccess([
      'results' => $results,
      'truncated' => $found['total'] > count($results),
    ]);
  }
}
