<?php
/**
 * Data Validation Template
 */

if (!defined('ABSPATH')) {
  exit;
}

// Variables received from controller via extract():
// - $validation_results : array - Validation results for this page ('post' and 'missing_attachments' keys)
// - $total_posts : int - Total number of imported posts with custom field data (all pages)
// - $page_posts : int - Number of posts validated on this page
// - $posts_with_issues : int - Posts on this page with missing attachments
// - $total_missing_attachments : int - Missing attachments on this page
// - $paged : int - Current page
// - $total_pages : int - Number of pages
// - $per_page : int - Posts per page

$yaml_cf_healthy = $page_posts - $posts_with_issues;
$yaml_cf_first = $total_posts > 0 ? (($paged - 1) * $per_page) + 1 : 0;
$yaml_cf_last = ($paged - 1) * $per_page + $page_posts;
$yaml_cf_pagination = paginate_links([
  'base' => add_query_arg('paged', '%#%'),
  'format' => '',
  'current' => $paged,
  'total' => $total_pages,
  'prev_text' => __('&laquo; Previous', 'yaml-custom-fields'),
  'next_text' => __('Next &raquo;', 'yaml-custom-fields'),
]);
?>

<div class="wrap">
  <div class="yaml-cf-admin-container">
    <div class="yaml-cf-header">
      <div class="yaml-cf-header-content">
        <img src="<?php echo esc_url(YAML_CF_PLUGIN_URL . 'assets/icon-256x256.png'); ?>" alt="<?php esc_attr_e('YAML Custom Fields', 'yaml-custom-fields'); ?>" class="yaml-cf-logo" />
        <div class="yaml-cf-header-text">
          <h1><?php esc_html_e('Content Check', 'yaml-custom-fields'); ?></h1>
          <p class="yaml-cf-tagline"><?php esc_html_e('Find imported posts with missing images or files', 'yaml-custom-fields'); ?></p>
        </div>
      </div>
    </div>
    <hr class="wp-header-end">

    <div class="yaml-cf-validation-container">
    <!-- Summary Card -->
    <div class="card yaml-cf-card-wide">
      <h2><?php esc_html_e('Summary', 'yaml-custom-fields'); ?></h2>

      <?php if ($total_pages > 1) : ?>
        <p class="description">
          <?php
          echo esc_html(
            sprintf(
              /* translators: 1: first post number, 2: last post number, 3: total number of posts */
              __('Showing posts %1$d–%2$d of %3$d. The numbers above are for this page.', 'yaml-custom-fields'),
              $yaml_cf_first,
              $yaml_cf_last,
              $total_posts
            )
          );
          ?>
        </p>
      <?php endif; ?>

      <div class="yaml-cf-summary-grid">
        <div class="yaml-cf-stat-box">
          <div class="yaml-cf-stat-value"><?php echo esc_html($total_posts); ?></div>
          <div class="yaml-cf-stat-label"><?php esc_html_e('Imported posts', 'yaml-custom-fields'); ?></div>
        </div>

        <div class="yaml-cf-stat-box">
          <div class="yaml-cf-stat-value <?php echo $posts_with_issues > 0 ? 'is-bad' : 'is-good'; ?>">
            <?php echo esc_html($posts_with_issues); ?>
          </div>
          <div class="yaml-cf-stat-label"><?php esc_html_e('Posts that need attention', 'yaml-custom-fields'); ?></div>
        </div>

        <div class="yaml-cf-stat-box">
          <div class="yaml-cf-stat-value <?php echo $total_missing_attachments > 0 ? 'is-bad' : 'is-good'; ?>">
            <?php echo esc_html($total_missing_attachments); ?>
          </div>
          <div class="yaml-cf-stat-label"><?php esc_html_e('Missing images and files', 'yaml-custom-fields'); ?></div>
        </div>

        <div class="yaml-cf-stat-box">
          <div class="yaml-cf-stat-value is-good">
            <?php echo esc_html($yaml_cf_healthy); ?>
          </div>
          <div class="yaml-cf-stat-label"><?php esc_html_e('Posts that are OK', 'yaml-custom-fields'); ?></div>
        </div>
      </div>

      <?php if ($posts_with_issues === 0) : ?>
        <div class="notice notice-success inline yaml-cf-notice-spaced">
          <p><strong><?php esc_html_e('All good.', 'yaml-custom-fields'); ?></strong> <?php esc_html_e('Every image and file is in place.', 'yaml-custom-fields'); ?></p>
        </div>
      <?php else : ?>
        <div class="notice notice-warning inline yaml-cf-notice-spaced">
          <p><strong><?php esc_html_e('Some images or files are missing.', 'yaml-custom-fields'); ?></strong> <?php
            echo esc_html(
              sprintf(
                /* translators: %1$d: number of posts with issues, %2$d: total missing attachments */
                _n(
                  '%1$d post has %2$d missing image or file.',
                  '%1$d posts have %2$d missing images or files.',
                  $posts_with_issues,
                  'yaml-custom-fields'
                ),
                $posts_with_issues,
                $total_missing_attachments
              )
            );
          ?></p>
        </div>
      <?php endif; ?>
    </div>

    <!-- Help Section -->
    <?php if ($posts_with_issues > 0) : ?>
      <div class="card yaml-cf-card-wide">
        <h2><?php esc_html_e('How to fix missing images and files', 'yaml-custom-fields'); ?></h2>
        <p><?php esc_html_e('This happens when content is imported from another website, but its images and files are not.', 'yaml-custom-fields'); ?></p>
        <ul class="yaml-cf-help-list">
          <li><?php esc_html_e('Open each post below and choose the image or file again.', 'yaml-custom-fields'); ?></li>
          <li><?php esc_html_e('Or copy the media library from the old website first, then import again.', 'yaml-custom-fields'); ?></li>
          <li><?php esc_html_e('If the matching new image is known, choose it in the post.', 'yaml-custom-fields'); ?></li>
        </ul>
      </div>
    <?php endif; ?>

    <!-- Validation Results -->
    <div class="card yaml-cf-card-wide">
      <div class="yaml-cf-card-header">
        <h2><?php esc_html_e('Posts', 'yaml-custom-fields'); ?></h2>
        <div>
          <button type="button" class="button yaml-cf-filter-btn active" data-filter="all">
            <?php esc_html_e('All', 'yaml-custom-fields'); ?> (<?php echo esc_html($page_posts); ?>)
          </button>
          <button type="button" class="button yaml-cf-filter-btn" data-filter="issues">
            <?php esc_html_e('Issues', 'yaml-custom-fields'); ?> (<?php echo esc_html($posts_with_issues); ?>)
          </button>
          <button type="button" class="button yaml-cf-filter-btn" data-filter="healthy">
            <?php esc_html_e('OK', 'yaml-custom-fields'); ?> (<?php echo esc_html($yaml_cf_healthy); ?>)
          </button>
        </div>
      </div>

      <?php if (empty($validation_results)) : ?>
        <div class="notice notice-info inline">
          <p><?php esc_html_e('No imported posts to check yet.', 'yaml-custom-fields'); ?></p>
        </div>
      <?php else : ?>
        <table class="wp-list-table widefat striped" id="yaml-cf-validation-table">
          <thead>
            <tr>
              <th class="yaml-cf-col-type"><?php esc_html_e('Type', 'yaml-custom-fields'); ?></th>
              <th><?php esc_html_e('Title', 'yaml-custom-fields'); ?></th>
              <th class="yaml-cf-col-status"><?php esc_html_e('Status', 'yaml-custom-fields'); ?></th>
              <th class="yaml-cf-col-issues"><?php esc_html_e('Issues', 'yaml-custom-fields'); ?></th>
              <th class="yaml-cf-col-actions"><?php esc_html_e('Actions', 'yaml-custom-fields'); ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($validation_results as $yaml_cf_result) : ?>
              <?php
              $yaml_cf_post = $yaml_cf_result['post'];
              $yaml_cf_missing = $yaml_cf_result['missing_attachments'];
              $yaml_cf_has_issues = !empty($yaml_cf_missing);
              $yaml_cf_issue_count = count($yaml_cf_missing);
              ?>
              <tr class="yaml-cf-validation-row" data-status="<?php echo $yaml_cf_has_issues ? 'issues' : 'healthy'; ?>">
                <td>
                  <span class="dashicons yaml-cf-type-icon <?php echo $yaml_cf_post->post_type === 'page' ? 'dashicons-admin-page' : 'dashicons-admin-post'; ?>"></span>
                </td>
                <td>
                  <strong>
                    <a href="<?php echo esc_url(get_edit_post_link($yaml_cf_post->ID)); ?>" target="_blank">
                      <?php echo esc_html($yaml_cf_post->post_title ?: __('(no title)', 'yaml-custom-fields')); ?>
                    </a>
                  </strong>
                  <div class="yaml-cf-muted-small">
                    <?php
                    /* translators: %d: post ID */
                    echo esc_html(sprintf(__('ID: %d', 'yaml-custom-fields'), $yaml_cf_post->ID));
                    ?>
                  </div>
                </td>
                <td>
                  <?php if ($yaml_cf_has_issues) : ?>
                    <span class="dashicons dashicons-warning yaml-cf-status-bad"></span>
                    <span class="yaml-cf-status-bad"><?php esc_html_e('Needs attention', 'yaml-custom-fields'); ?></span>
                  <?php else : ?>
                    <span class="dashicons dashicons-yes-alt yaml-cf-status-good"></span>
                    <span class="yaml-cf-status-good"><?php esc_html_e('OK', 'yaml-custom-fields'); ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($yaml_cf_has_issues) : ?>
                    <details>
                      <summary class="yaml-cf-summary-toggle">
                        <?php
                          echo esc_html(
                            sprintf(
                              /* translators: %d: number of missing attachments */
                              _n('%d missing', '%d missing', $yaml_cf_issue_count, 'yaml-custom-fields'),
                              $yaml_cf_issue_count
                            )
                          );
                        ?>
                      </summary>
                      <ul class="yaml-cf-missing-list">
                        <?php foreach ($yaml_cf_missing as $yaml_cf_item) : ?>
                          <li>
                            <strong><?php echo esc_html($yaml_cf_item['field']); ?>:</strong>
                            <?php
                            /* translators: %d: attachment ID */
                            echo esc_html(sprintf(__('ID %d', 'yaml-custom-fields'), $yaml_cf_item['id']));
                            ?>
                          </li>
                        <?php endforeach; ?>
                      </ul>
                    </details>
                  <?php else : ?>
                    <span class="yaml-cf-muted">-</span>
                  <?php endif; ?>
                </td>
                <td>
                  <a href="<?php echo esc_url(get_edit_post_link($yaml_cf_post->ID)); ?>"
                     class="button button-small"
                     target="_blank">
                    <?php esc_html_e('Edit', 'yaml-custom-fields'); ?>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <?php if ($yaml_cf_pagination) : ?>
          <div class="tablenav bottom">
            <div class="tablenav-pages"><?php echo wp_kses_post($yaml_cf_pagination); ?></div>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    </div>
  </div>
</div>
