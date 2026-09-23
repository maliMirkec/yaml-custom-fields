<?php
/**
 * Export & Import Page Template
 * File: templates/export-data-page.php
 *
 * Variables received from controller via extract():
 * - $yaml_cf_catalog : array ['setup' => item[], 'lists' => item[]] - what this site can export
 */

if (!defined('ABSPATH')) {
  exit;
}

$yaml_cf_setup_groups = isset($yaml_cf_catalog['setup']) ? $yaml_cf_catalog['setup'] : [];
$yaml_cf_list_items = isset($yaml_cf_catalog['lists']) ? $yaml_cf_catalog['lists'] : [];

/**
 * Print one tick box for a single thing that can be exported
 */
$yaml_cf_export_row = function ($item, $checked = true) {
  ?>
  <label class="yaml-cf-pick-row">
    <input type="checkbox" class="yaml-cf-pick" value="<?php echo esc_attr($item['id']); ?>" <?php checked($checked); ?> />
    <span class="yaml-cf-pick-label"><?php echo esc_html($item['label']); ?></span>
    <?php if (!empty($item['meta'])) : ?>
      <span class="yaml-cf-pick-meta"><?php echo esc_html($item['meta']); ?></span>
    <?php endif; ?>
  </label>
  <?php
};

/**
 * Print a template and its pieces: one row, with the pieces hidden until opened
 */
$yaml_cf_export_branch = function ($group) use ($yaml_cf_export_row) {
  $single = count($group['children']) === 1;
  $panel_id = 'yaml-cf-branch-' . sanitize_html_class($group['key']);
  ?>
  <div class="yaml-cf-pick-node">
    <div class="yaml-cf-pick-row is-branch">
      <input type="checkbox" class="yaml-cf-pick-branch" checked
        aria-label="<?php echo esc_attr($group['label']); ?>" />
      <span class="yaml-cf-pick-label"><?php echo esc_html($group['label']); ?></span>
      <?php if (!empty($group['summary'])) : ?>
        <span class="yaml-cf-pick-meta"><?php echo esc_html($group['summary']); ?></span>
      <?php endif; ?>

      <?php if (!$single) : ?>
        <button type="button" class="yaml-cf-pick-expand" aria-expanded="false" aria-controls="<?php echo esc_attr($panel_id); ?>">
          <span class="screen-reader-text">
            <?php
            /* translators: %s: template name */
            echo esc_html(sprintf(__('Show what is in %s', 'yaml-custom-fields'), $group['label']));
            ?>
          </span>
          <span class="yaml-cf-pick-caret" aria-hidden="true"></span>
        </button>
      <?php endif; ?>
    </div>

    <div class="yaml-cf-pick-children" id="<?php echo esc_attr($panel_id); ?>" hidden>
      <?php foreach ($group['children'] as $yaml_cf_child) {
        $yaml_cf_export_row($yaml_cf_child);
      } ?>
    </div>
  </div>
  <?php
};
?>

<div class="wrap">
  <div class="yaml-cf-admin-container">
    <div class="yaml-cf-header">
      <div class="yaml-cf-header-content">
        <img src="<?php echo esc_url(YAML_CF_PLUGIN_URL . 'assets/icon-256x256.png'); ?>" alt="<?php esc_attr_e('YAML Custom Fields', 'yaml-custom-fields'); ?>" class="yaml-cf-logo" />
        <div class="yaml-cf-header-text">
          <h1><?php esc_html_e('Export & Import', 'yaml-custom-fields'); ?></h1>
          <p class="yaml-cf-tagline"><?php esc_html_e('Back up the setup and content, or move it to another website', 'yaml-custom-fields'); ?></p>
        </div>
      </div>
    </div>
    <hr class="wp-header-end">

    <div class="yaml-cf-callout is-warning yaml-cf-spaced-bottom">
      <p>
        <strong><?php esc_html_e('Good to know:', 'yaml-custom-fields'); ?></strong>
        <?php esc_html_e('Images and files are not inside the export file, only links to them. Copy the media library to the other website too. Images and files that are missing there are left empty.', 'yaml-custom-fields'); ?>
      </p>
    </div>

    <!-- ================= Export ================= -->
    <section class="yaml-cf-card yaml-cf-card-wide" aria-labelledby="yaml-cf-export-title">
      <h2 id="yaml-cf-export-title">
        <span class="dashicons dashicons-download" aria-hidden="true"></span>
        <?php esc_html_e('Export', 'yaml-custom-fields'); ?>
      </h2>

      <ol class="yaml-cf-wizard-steps" id="yaml-cf-wizard-steps">
        <li data-step="1" class="is-current"><?php esc_html_e('What to export', 'yaml-custom-fields'); ?></li>
        <li data-step="2"><?php esc_html_e('Choose pages and posts', 'yaml-custom-fields'); ?></li>
        <li data-step="3"><?php esc_html_e('Review', 'yaml-custom-fields'); ?></li>
      </ol>

      <!-- Step 1: what -->
      <div class="yaml-cf-wizard-panel" data-panel="1">
        <?php if (empty($yaml_cf_setup_groups) && empty($yaml_cf_list_items)) : ?>
          <div class="yaml-cf-empty-state">
            <p><?php esc_html_e('There is nothing to export yet. Create a schema first.', 'yaml-custom-fields'); ?></p>
          </div>
        <?php endif; ?>

        <?php if (!empty($yaml_cf_setup_groups)) : ?>
          <div class="yaml-cf-pick-group" data-group="setup">
            <label class="yaml-cf-pick-row is-group">
              <input type="checkbox" class="yaml-cf-pick-all" checked />
              <span class="yaml-cf-pick-label"><?php esc_html_e('Setup', 'yaml-custom-fields'); ?></span>
              <span class="yaml-cf-pick-meta"><?php esc_html_e('Schemas, shared content and site details', 'yaml-custom-fields'); ?></span>
            </label>
            <div class="yaml-cf-pick-list">
              <?php foreach ($yaml_cf_setup_groups as $yaml_cf_group) {
                $yaml_cf_export_branch($yaml_cf_group);
              } ?>
            </div>
          </div>
        <?php endif; ?>

        <div class="yaml-cf-pick-group" data-group="posts">
          <label class="yaml-cf-pick-row is-group">
            <input type="checkbox" id="yaml-cf-want-posts" />
            <span class="yaml-cf-pick-label"><?php esc_html_e('Pages and posts', 'yaml-custom-fields'); ?></span>
            <span class="yaml-cf-pick-meta"><?php esc_html_e('The fields filled in on each one, chosen in the next step', 'yaml-custom-fields'); ?></span>
          </label>
        </div>

        <?php if (!empty($yaml_cf_list_items)) : ?>
          <div class="yaml-cf-pick-group" data-group="lists">
            <label class="yaml-cf-pick-row is-group">
              <input type="checkbox" class="yaml-cf-pick-all" />
              <span class="yaml-cf-pick-label"><?php esc_html_e('Collections', 'yaml-custom-fields'); ?></span>
              <span class="yaml-cf-pick-meta"><?php esc_html_e('Collections and their items', 'yaml-custom-fields'); ?></span>
            </label>
            <div class="yaml-cf-pick-list">
              <?php foreach ($yaml_cf_list_items as $yaml_cf_item) {
                // Collections start off, like post content
                $yaml_cf_export_row($yaml_cf_item, false);
              } ?>
            </div>
          </div>
        <?php endif; ?>

        <div class="yaml-cf-wizard-actions">
          <button type="button" class="button button-primary" id="yaml-cf-step1-next">
            <?php esc_html_e('Next', 'yaml-custom-fields'); ?>
          </button>
        </div>
      </div>

      <!-- Step 2: which posts -->
      <div class="yaml-cf-wizard-panel yaml-cf-hidden" data-panel="2">
        <div class="yaml-cf-panel yaml-cf-spaced-bottom">
          <h3><?php esc_html_e('How should the other website find each page?', 'yaml-custom-fields'); ?></h3>
          <label class="yaml-cf-radio-label">
            <input type="radio" name="match_by" value="slug" checked>
            <strong><?php esc_html_e('By web address', 'yaml-custom-fields'); ?></strong>
            <span class="yaml-cf-radio-hint">(<?php esc_html_e('Best choice, works on any website', 'yaml-custom-fields'); ?>)</span>
          </label>
          <label class="yaml-cf-radio-label">
            <input type="radio" name="match_by" value="id">
            <strong><?php esc_html_e('By post ID', 'yaml-custom-fields'); ?></strong>
            <span class="yaml-cf-radio-hint">(<?php esc_html_e('Only for the same website', 'yaml-custom-fields'); ?>)</span>
          </label>
        </div>

        <div id="yaml-cf-posts-loading" class="yaml-cf-loading">
          <span class="spinner is-active"></span>
          <p><?php esc_html_e('Loading pages and posts…', 'yaml-custom-fields'); ?></p>
        </div>

        <div id="yaml-cf-posts-list" class="yaml-cf-hidden">
          <div class="yaml-cf-posts-header yaml-cf-card-header">
            <div>
              <button type="button" class="button" id="yaml-cf-select-all"><?php esc_html_e('Select all on this page', 'yaml-custom-fields'); ?></button>
              <button type="button" class="button" id="yaml-cf-deselect-all"><?php esc_html_e('Clear selection', 'yaml-custom-fields'); ?></button>
            </div>
            <div>
              <span id="yaml-cf-selected-count">0</span> <?php esc_html_e('selected', 'yaml-custom-fields'); ?>
            </div>
          </div>

          <table class="wp-list-table widefat striped">
            <thead>
              <tr>
                <td class="check-column"><input type="checkbox" id="yaml-cf-select-all-checkbox" aria-label="<?php esc_attr_e('Select all on this page', 'yaml-custom-fields'); ?>"></td>
                <th><?php esc_html_e('Title', 'yaml-custom-fields'); ?></th>
                <th><?php esc_html_e('Short name', 'yaml-custom-fields'); ?></th>
                <th><?php esc_html_e('Type', 'yaml-custom-fields'); ?></th>
                <th><?php esc_html_e('Template', 'yaml-custom-fields'); ?></th>
                <th><?php esc_html_e('Status', 'yaml-custom-fields'); ?></th>
                <th><?php esc_html_e('ID', 'yaml-custom-fields'); ?></th>
              </tr>
            </thead>
            <tbody id="yaml-cf-posts-tbody"></tbody>
          </table>

          <div class="tablenav bottom">
            <div class="tablenav-pages" id="yaml-cf-posts-pagination">
              <span class="displaying-num" id="yaml-cf-posts-total"></span>
              <span class="pagination-links">
                <button type="button" class="button" id="yaml-cf-posts-prev" aria-label="<?php esc_attr_e('Previous page', 'yaml-custom-fields'); ?>">&lsaquo;</button>
                <span class="paging-input" id="yaml-cf-posts-page-info"></span>
                <button type="button" class="button" id="yaml-cf-posts-next" aria-label="<?php esc_attr_e('Next page', 'yaml-custom-fields'); ?>">&rsaquo;</button>
              </span>
            </div>
          </div>
        </div>

        <div id="yaml-cf-no-posts" class="yaml-cf-hidden yaml-cf-empty-state">
          <p><?php esc_html_e('No pages or posts have field content yet.', 'yaml-custom-fields'); ?></p>
        </div>

        <div class="yaml-cf-wizard-actions">
          <button type="button" class="button" data-wizard-back="1"><?php esc_html_e('Back', 'yaml-custom-fields'); ?></button>
          <button type="button" class="button button-primary" id="yaml-cf-step2-next"><?php esc_html_e('Next', 'yaml-custom-fields'); ?></button>
        </div>
      </div>

      <!-- Step 3: review -->
      <div class="yaml-cf-wizard-panel yaml-cf-hidden" data-panel="3">
        <div class="yaml-cf-panel" id="yaml-cf-export-summary"></div>

        <div class="yaml-cf-wizard-actions">
          <button type="button" class="button" id="yaml-cf-step3-back"><?php esc_html_e('Back', 'yaml-custom-fields'); ?></button>
          <button type="button" class="button button-primary" id="yaml-cf-do-export">
            <span class="dashicons dashicons-download" aria-hidden="true"></span>
            <?php esc_html_e('Export file', 'yaml-custom-fields'); ?>
          </button>
          <span id="yaml-cf-export-message" class="yaml-cf-inline-message"></span>
        </div>
      </div>
    </section>

    <!-- ================= Import ================= -->
    <section class="yaml-cf-card yaml-cf-card-wide" aria-labelledby="yaml-cf-import-title">
      <h2 id="yaml-cf-import-title">
        <span class="dashicons dashicons-upload" aria-hidden="true"></span>
        <?php esc_html_e('Import', 'yaml-custom-fields'); ?>
      </h2>
      <p><?php esc_html_e('Choose an export file. Nothing is changed until the list below is confirmed.', 'yaml-custom-fields'); ?></p>

      <div class="yaml-cf-button-row">
        <input type="file" id="yaml-cf-bundle-file" accept=".json" class="yaml-cf-file-input">
        <span id="yaml-cf-inspect-message" class="yaml-cf-inline-message"></span>
      </div>

      <div id="yaml-cf-import-preview" class="yaml-cf-hidden yaml-cf-spaced-top">
        <p class="yaml-cf-import-source" id="yaml-cf-import-source"></p>

        <div id="yaml-cf-import-groups"></div>

        <div class="yaml-cf-wizard-actions">
          <button type="button" class="button button-primary" id="yaml-cf-do-import"></button>
          <span id="yaml-cf-overwrite-count" class="yaml-cf-overwrite-count"></span>
          <span id="yaml-cf-import-message" class="yaml-cf-inline-message"></span>
        </div>
      </div>

      <div id="yaml-cf-import-results" class="yaml-cf-hidden yaml-cf-panel yaml-cf-spaced-top">
        <h3><?php esc_html_e('Result', 'yaml-custom-fields'); ?></h3>
        <div id="yaml-cf-import-results-content"></div>
      </div>
    </section>
  </div>
</div>
