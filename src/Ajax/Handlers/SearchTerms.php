<?php

namespace YamlCF\Ajax\Handlers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Ajax\AjaxHandler;

/**
 * Search the terms of one taxonomy, for the picker a taxonomy field shows when
 * the taxonomy is too big to list in full.
 *
 * Action: wp_ajax_yaml_cf_search_terms
 */
class SearchTerms extends AjaxHandler {
  const DEFAULT_LIMIT = 30;
  const MAX_LIMIT = 100;

  public function handle() {
    // Writers use this picker in the post editor, so it cannot require
    // manage_options like the settings screens do.
    $this->verifyRequest('edit_posts');

    $taxonomy = $this->postText('taxonomy');
    if ($taxonomy === '' || !taxonomy_exists($taxonomy)) {
      $this->sendError(__('That taxonomy does not exist.', 'yaml-custom-fields'));
    }

    // Existing is not the same as allowed. Another plugin may register a
    // taxonomy that is nobody's business to read, and this picker would have
    // listed its terms to anyone who can edit a post.
    if (!$this->userMayBrowse($taxonomy)) {
      $this->sendError(__('You do not have permission to browse that taxonomy.', 'yaml-custom-fields'));
    }

    $search = $this->postText('search');
    $limit = absint($this->postText('limit', (string) self::DEFAULT_LIMIT));
    $limit = $limit > 0 ? min($limit, self::MAX_LIMIT) : self::DEFAULT_LIMIT;

    $terms = get_terms([
      'taxonomy' => $taxonomy,
      'hide_empty' => false,
      'search' => $search,
      'number' => $limit,
      'orderby' => 'name',
      'order' => 'ASC',
    ]);

    if (is_wp_error($terms)) {
      $this->sendError($terms->get_error_message());
    }

    $results = [];
    foreach ($terms as $term) {
      $results[] = ['id' => $term->term_id, 'label' => $term->name];
    }

    $this->sendSuccess([
      'results' => $results,
      // The picker says "keep typing" rather than implying these are all of them
      'truncated' => count($results) >= $limit,
    ]);
  }

  /**
   * Whether the current user is allowed to see a taxonomy's terms
   *
   * A taxonomy that is neither public nor shown in the admin is internal to
   * whatever registered it. For the rest, the taxonomy's own assign_terms
   * capability decides, which for categories and tags is edit_posts, so writers
   * keep the picker they have always had.
   *
   * @param string $taxonomy Taxonomy name
   * @return bool
   */
  private function userMayBrowse($taxonomy) {
    $details = get_taxonomy($taxonomy);
    if (!$details) {
      return false;
    }

    if (empty($details->public) && empty($details->show_ui)) {
      return false;
    }

    $capability = isset($details->cap->assign_terms) ? $details->cap->assign_terms : 'edit_posts';

    return current_user_can($capability);
  }
}
