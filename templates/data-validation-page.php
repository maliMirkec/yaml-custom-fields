<?php
/**
 * Data Validation Template
 */

if (!defined('ABSPATH')) {
  exit;
}

// Variables received from controller via extract():
// - $validation_results : array - Array of validation results with 'post' and 'missing_attachments' keys
// - $total_posts : int - Total number of posts validated
// - $posts_with_issues : int - Number of posts with missing attachments
// - $total_missing_attachments : int - Total count of missing attachments across all posts
?>

<div class="wrap">
  <div class="yaml-cf-admin-container">
    <div class="yaml-cf-header">
      <div class="yaml-cf-header-content">
        <img src="<?php echo esc_url(YAML_CF_PLUGIN_URL . 'icon-256x256.png'); ?>" alt="YAML Custom Fields" class="yaml-cf-logo" />
        <div class="yaml-cf-header-text">
          <h1><?php esc_html_e('Data Validation', 'yaml-custom-fields'); ?></h1>
          <p class="yaml-cf-tagline"><?php esc_html_e('Validate custom field data and detect missing attachments', 'yaml-custom-fields'); ?></p>
        </div>
      </div>
    </div>

    <div class="yaml-cf-validation-container">
    <!-- Summary Card -->
    <div class="card" style="max-width: 100%; margin-top: 20px;">
      <h2><?php esc_html_e('Validation Summary', 'yaml-custom-fields'); ?></h2>

      <div class="yaml-cf-summary-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 20px;">
        <div class="yaml-cf-stat-box" style="padding: 20px; background: #f0f0f1; border-radius: 4px; text-align: center;">
          <div style="font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo esc_html($total_posts); ?></div>
          <div style="margin-top: 5px; color: #646970;"><?php esc_html_e('Total Posts', 'yaml-custom-fields'); ?></div>
        </div>

        <div class="yaml-cf-stat-box" style="padding: 20px; background: #f0f0f1; border-radius: 4px; text-align: center;">
          <div style="font-size: 32px; font-weight: bold; color: <?php echo $posts_with_issues > 0 ? '#d63638' : '#00a32a'; ?>;">
            <?php echo esc_html($posts_with_issues); ?>
          </div>
          <div style="margin-top: 5px; color: #646970;"><?php esc_html_e('Posts with Issues', 'yaml-custom-fields'); ?></div>
        </div>

        <div class="yaml-cf-stat-box" style="padding: 20px; background: #f0f0f1; border-radius: 4px; text-align: center;">
          <div style="font-size: 32px; font-weight: bold; color: <?php echo $total_missing_attachments > 0 ? '#d63638' : '#00a32a'; ?>;">
            <?php echo esc_html($total_missing_attachments); ?>
          </div>
          <div style="margin-top: 5px; color: #646970;"><?php esc_html_e('Missing Attachments', 'yaml-custom-fields'); ?></div>
        </div>

        <div class="yaml-cf-stat-box" style="padding: 20px; background: #f0f0f1; border-radius: 4px; text-align: center;">
          <div style="font-size: 32px; font-weight: bold; color: #00a32a;">
            <?php echo esc_html($total_posts - $posts_with_issues); ?>
          </div>
          <div style="margin-top: 5px; color: #646970;"><?php esc_html_e('Healthy Posts', 'yaml-custom-fields'); ?></div>
        </div>
      </div>

      <?php if ($posts_with_issues === 0): ?>
        <div class="notice notice-success inline" style="margin-top: 20px;">
          <p><strong><?php esc_html_e('All data is valid!', 'yaml-custom-fields'); ?></strong> <?php esc_html_e('No missing attachments found.', 'yaml-custom-fields'); ?></p>
        </div>
      <?php else: ?>
        <div class="notice notice-warning inline" style="margin-top: 20px;">
          <p><strong><?php esc_html_e('Validation Issues Found', 'yaml-custom-fields'); ?></strong> <?php
            echo esc_html(
              sprintf(
                /* translators: %1$d: number of posts with issues, %2$d: total missing attachments */
                _n(
                  '%1$d post has %2$d missing attachment.',
                  '%1$d posts have %2$d missing attachments.',
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
    <?php if ($posts_with_issues > 0): ?>
      <div class="card" style="max-width: 100%; margin-top: 20px;">
        <h2><?php esc_html_e('What to do about missing attachments?', 'yaml-custom-fields'); ?></h2>
        <p><?php esc_html_e('Missing attachments may occur after importing data from another site:', 'yaml-custom-fields'); ?></p>
        <ul style="margin-left: 20px; margin-top: 10px;">
          <li><?php esc_html_e('Edit each affected post and re-upload the images/files', 'yaml-custom-fields'); ?></li>
          <li><?php esc_html_e('Alternatively, import the media library from the source site first', 'yaml-custom-fields'); ?></li>
          <li><?php esc_html_e('You can also manually update attachment IDs if you know the mapping', 'yaml-custom-fields'); ?></li>
        </ul>
      </div>
    <?php endif; ?>

    <!-- Validation Results -->
    <div class="card" style="max-width: 100%; margin-top: 20px;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h2 style="margin: 0;"><?php esc_html_e('Validation Results', 'yaml-custom-fields'); ?></h2>
        <div>
          <button type="button" class="button yaml-cf-filter-btn active" data-filter="all">
            <?php esc_html_e('All', 'yaml-custom-fields'); ?> (<?php echo esc_html($total_posts); ?>)
          </button>
          <button type="button" class="button yaml-cf-filter-btn" data-filter="issues">
            <?php esc_html_e('Issues', 'yaml-custom-fields'); ?> (<?php echo esc_html($posts_with_issues); ?>)
          </button>
          <button type="button" class="button yaml-cf-filter-btn" data-filter="healthy">
            <?php esc_html_e('Healthy', 'yaml-custom-fields'); ?> (<?php echo esc_html($total_posts - $posts_with_issues); ?>)
          </button>
        </div>
      </div>

      <?php if (empty($validation_results)): ?>
        <div class="notice notice-info inline">
          <p><?php esc_html_e('No posts with custom field data found.', 'yaml-custom-fields'); ?></p>
        </div>
      <?php else: ?>
        <table class="wp-list-table widefat striped" id="yaml-cf-validation-table">
          <thead>
            <tr>
              <th style="width: 60px;"><?php esc_html_e('Type', 'yaml-custom-fields'); ?></th>
              <th><?php esc_html_e('Title', 'yaml-custom-fields'); ?></th>
              <th style="width: 100px;"><?php esc_html_e('Status', 'yaml-custom-fields'); ?></th>
              <th style="width: 120px;"><?php esc_html_e('Issues', 'yaml-custom-fields'); ?></th>
              <th style="width: 80px;"><?php esc_html_e('Actions', 'yaml-custom-fields'); ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($validation_results as $yaml_cf_result): ?>
              <?php
              $yaml_cf_post = $yaml_cf_result['post'];
              $yaml_cf_missing = $yaml_cf_result['missing_attachments'];
              $yaml_cf_has_issues = !empty($yaml_cf_missing);
              $yaml_cf_issue_count = count($yaml_cf_missing);
              ?>
              <tr class="yaml-cf-validation-row" data-status="<?php echo $yaml_cf_has_issues ? 'issues' : 'healthy'; ?>">
                <td>
                  <span class="dashicons <?php echo $yaml_cf_post->post_type === 'page' ? 'dashicons-admin-page' : 'dashicons-admin-post'; ?>"
                        style="color: #2271b1;"></span>
                </td>
                <td>
                  <strong>
                    <a href="<?php echo esc_url(get_edit_post_link($yaml_cf_post->ID)); ?>" target="_blank">
                      <?php echo esc_html($yaml_cf_post->post_title ?: __('(no title)', 'yaml-custom-fields')); ?>
                    </a>
                  </strong>
                  <div style="color: #646970; font-size: 12px; margin-top: 3px;">
                    ID: <?php echo esc_html($yaml_cf_post->ID); ?>
                  </div>
                </td>
                <td>
                  <?php if ($yaml_cf_has_issues): ?>
                    <span class="dashicons dashicons-warning" style="color: #d63638;"></span>
                    <span style="color: #d63638;"><?php esc_html_e('Has Issues', 'yaml-custom-fields'); ?></span>
                  <?php else: ?>
                    <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                    <span style="color: #00a32a;"><?php esc_html_e('Valid', 'yaml-custom-fields'); ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($yaml_cf_has_issues): ?>
                    <details>
                      <summary style="cursor: pointer; color: #2271b1;">
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
                      <ul style="margin: 10px 0 0 20px; font-size: 12px;">
                        <?php foreach ($yaml_cf_missing as $yaml_cf_item): ?>
                          <li>
                            <strong><?php echo esc_html($yaml_cf_item['field']); ?>:</strong>
                            ID <?php echo esc_html($yaml_cf_item['id']); ?>
                          </li>
                        <?php endforeach; ?>
                      </ul>
                    </details>
                  <?php else: ?>
                    <span style="color: #646970;">-</span>
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
      <?php endif; ?>
    </div>
    </div>
  </div>
</div>

<style>
.yaml-cf-validation-container {
  max-width: 100%;
}

.yaml-cf-filter-btn {
  margin-left: 5px;
}

.yaml-cf-filter-btn.active {
  background: #2271b1;
  color: #fff;
  border-color: #2271b1;
}

.yaml-cf-validation-row[data-status="hidden"] {
  display: none;
}

.yaml-cf-summary-grid {
  margin-bottom: 20px;
}

.yaml-cf-stat-box {
  transition: transform 0.2s;
}

.yaml-cf-stat-box:hover {
  transform: translateY(-2px);
}
</style>

<script>
(function() {
  // Filter functionality
  const filterBtns = document.querySelectorAll('.yaml-cf-filter-btn');
  const rows = document.querySelectorAll('.yaml-cf-validation-row');

  filterBtns.forEach(btn => {
    btn.addEventListener('click', function() {
      // Update active state
      filterBtns.forEach(b => b.classList.remove('active'));
      this.classList.add('active');

      const filter = this.dataset.filter;

      // Show/hide rows
      rows.forEach(row => {
        const status = row.dataset.status;
        if (filter === 'all') {
          row.style.display = '';
        } else if (filter === 'issues' && status === 'issues') {
          row.style.display = '';
        } else if (filter === 'healthy' && status === 'healthy') {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    });
  });
})();
</script>
