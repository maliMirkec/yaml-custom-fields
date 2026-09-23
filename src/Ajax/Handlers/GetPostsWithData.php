<?php

namespace YamlCF\Ajax\Handlers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Ajax\AjaxHandler;

/**
 * List posts that have field data, one page at a time
 * Action: wp_ajax_yaml_cf_get_posts_with_data
 */
class GetPostsWithData extends AjaxHandler {
  const DEFAULT_PER_PAGE = 20;
  const MAX_PER_PAGE = 100;

  private $repository;
  private $templateResolver;

  public function __construct($repository, $templateResolver) {
    $this->repository = $repository;
    $this->templateResolver = $templateResolver;
  }

  public function handle() {
    $this->verifyRequest();

    $page = max(1, absint($this->postText('page', '1')));
    $per_page = absint($this->postText('per_page', (string) self::DEFAULT_PER_PAGE));
    $per_page = $per_page > 0 ? min($per_page, self::MAX_PER_PAGE) : self::DEFAULT_PER_PAGE;

    $result = $this->repository->queryPostsWithData([
      'paged' => $page,
      'per_page' => $per_page,
    ]);

    $posts = [];
    foreach ($result['posts'] as $post) {
      $posts[] = [
        'id' => $post->ID,
        'title' => $post->post_title,
        'slug' => $post->post_name,
        'type' => $post->post_type,
        'status' => $post->post_status,
        'template' => $this->templateResolver->getTemplateForPost($post),
        'edit_url' => get_edit_post_link($post->ID, 'raw'),
      ];
    }

    $this->sendSuccess([
      'posts' => $posts,
      'page' => $result['paged'],
      'per_page' => $per_page,
      'total' => $result['total'],
      'total_pages' => $result['total_pages'],
    ]);
  }
}
