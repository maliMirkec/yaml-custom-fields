<?php
/**
 * Export Page Data Template
 */

if (!defined('ABSPATH')) {
  exit;
}
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

    <div class="yaml-cf-export-data-container">

    <!-- Settings Export/Import -->
    <div class="card yaml-cf-card-wide">
      <h2><?php esc_html_e('Setup', 'yaml-custom-fields'); ?></h2>
      <p><?php esc_html_e('Save the setup to a file, or load it from a file. Useful for backups, or for copying a setup to another website.', 'yaml-custom-fields'); ?></p>

      <div class="yaml-cf-button-row">
        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?yaml_cf_export_settings=1'), 'yaml_cf_export_settings')); ?>" class="button button-primary">
          <span class="dashicons dashicons-download"></span>
          <?php esc_html_e('Export setup', 'yaml-custom-fields'); ?>
        </a>
        <button type="button" class="button yaml-cf-import-settings-trigger">
          <span class="dashicons dashicons-upload"></span>
          <?php esc_html_e('Import setup', 'yaml-custom-fields'); ?>
        </button>
        <input type="file" id="yaml-cf-import-settings-file" accept=".json" class="yaml-cf-hidden" />
      </div>

      <div class="yaml-cf-callout is-info yaml-cf-spaced-top">
        <p><strong><?php esc_html_e('What is in the file:', 'yaml-custom-fields'); ?></strong></p>
        <ul>
          <li><?php esc_html_e('The schemas of the page templates', 'yaml-custom-fields'); ?></li>
          <li><?php esc_html_e('The schemas of the site parts', 'yaml-custom-fields'); ?></li>
          <li><?php esc_html_e('Which templates are switched on', 'yaml-custom-fields'); ?></li>
        </ul>
      </div>
    </div>

    <!-- Page Data Export -->
    <div class="card yaml-cf-card-wide">
      <h2><?php esc_html_e('Post content', 'yaml-custom-fields'); ?></h2>
      <p><?php esc_html_e('Save the field content of posts and pages to a file.', 'yaml-custom-fields'); ?></p>

      <h3 class="yaml-cf-spaced-top"><?php esc_html_e('Choose posts and pages', 'yaml-custom-fields'); ?></h3>

      <div class="yaml-cf-callout is-warning yaml-cf-spaced-bottom">
        <p>
          <strong><?php esc_html_e('Good to know:', 'yaml-custom-fields'); ?></strong>
          <?php esc_html_e('Images and files are not inside the file, only links to them. The media library has to be copied to the other website too. Images and files that are missing there are left empty.', 'yaml-custom-fields'); ?>
        </p>
      </div>

      <div class="yaml-cf-export-options yaml-cf-panel">
        <h3><?php esc_html_e('How should the other website find each post?', 'yaml-custom-fields'); ?></h3>
        <p>
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
        </p>
      </div>

      <div id="yaml-cf-posts-loading" class="yaml-cf-loading">
        <span class="spinner is-active"></span>
        <p><?php esc_html_e('Loading posts…', 'yaml-custom-fields'); ?></p>
      </div>

      <form method="post" id="yaml-cf-export-form">
        <?php wp_nonce_field('yaml_cf_export_page_data', 'yaml_cf_export_page_data_nonce'); ?>
        <input type="hidden" name="match_by" id="yaml-cf-export-match-by" value="slug">

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
          <tbody id="yaml-cf-posts-tbody">
          </tbody>
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

        <div id="yaml-cf-export-post-ids"></div>

        <div class="yaml-cf-spaced-top">
          <button type="submit" class="button button-primary" id="yaml-cf-export-selected" disabled>
            <?php esc_html_e('Export selected', 'yaml-custom-fields'); ?>
          </button>
        </div>
      </div>
      </form>

      <div id="yaml-cf-no-posts" class="yaml-cf-hidden yaml-cf-empty-state">
        <p><?php esc_html_e('No posts or pages have field content yet.', 'yaml-custom-fields'); ?></p>
      </div>
    </div>

    <div class="card yaml-cf-card-wide">
      <h3><?php esc_html_e('Import post content', 'yaml-custom-fields'); ?></h3>
      <p><?php esc_html_e('Load post and page content from an export file.', 'yaml-custom-fields'); ?></p>

      <div class="yaml-cf-callout is-info yaml-cf-spaced-bottom">
        <p><strong><?php esc_html_e('How it works:', 'yaml-custom-fields'); ?></strong></p>
        <ul>
          <li><?php esc_html_e('Choose a .json file exported from YAML Custom Fields.', 'yaml-custom-fields'); ?></li>
          <li><?php esc_html_e('Each post is matched by its web address or ID, as chosen when exporting.', 'yaml-custom-fields'); ?></li>
          <li><?php esc_html_e('Images and files that do not exist on this website are left empty.', 'yaml-custom-fields'); ?></li>
          <li><?php esc_html_e('Afterwards, open Content Check to find missing images or files.', 'yaml-custom-fields'); ?></li>
        </ul>
      </div>

      <div>
        <input type="file" id="yaml-cf-import-file" accept=".json" class="yaml-cf-file-input">
        <br>
        <button type="button" class="button button-primary" id="yaml-cf-import-data">
          <?php esc_html_e('Import post content', 'yaml-custom-fields'); ?>
        </button>
        <span id="yaml-cf-import-message" class="yaml-cf-inline-message"></span>
      </div>

      <div id="yaml-cf-import-results" class="yaml-cf-hidden yaml-cf-panel yaml-cf-spaced-top">
        <h3><?php esc_html_e('Result', 'yaml-custom-fields'); ?></h3>
        <div id="yaml-cf-import-results-content"></div>
      </div>
    </div>

    <!-- Data Objects Export -->
    <div class="card yaml-cf-card-wide yaml-cf-section-gap">
      <h2><?php esc_html_e('Reusable lists', 'yaml-custom-fields'); ?></h2>
      <p><?php esc_html_e('Save the reusable lists and their items to a file.', 'yaml-custom-fields'); ?></p>

      <h3 class="yaml-cf-spaced-top"><?php esc_html_e('Export fields', 'yaml-custom-fields'); ?></h3>

      <div class="yaml-cf-callout is-warning yaml-cf-spaced-bottom">
        <p>
          <strong><?php esc_html_e('Good to know:', 'yaml-custom-fields'); ?></strong>
          <?php esc_html_e('This saves every list and its items. Images and files are saved as links only.', 'yaml-custom-fields'); ?>
        </p>
      </div>

      <?php
      $yaml_cf_data_object_types = get_option('yaml_cf_data_object_types', []);
      if (empty($yaml_cf_data_object_types)) :
      ?>
        <div class="notice notice-info inline">
          <p><?php esc_html_e('No lists yet.', 'yaml-custom-fields'); ?> <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-data-objects')); ?>"><?php esc_html_e('Make the first list', 'yaml-custom-fields'); ?></a></p>
        </div>
      <?php else : ?>
        <form method="post" id="yaml-cf-export-data-objects-form">
          <?php wp_nonce_field('yaml_cf_export_data_objects', 'yaml_cf_export_data_objects_nonce'); ?>
          <button type="submit" class="button button-primary">
            <?php esc_html_e('Export all lists', 'yaml-custom-fields'); ?>
          </button>
          <p class="description yaml-cf-description-spaced">
            <?php
            $yaml_cf_total_types = count($yaml_cf_data_object_types);
            $yaml_cf_total_entries = 0;
            foreach ($yaml_cf_data_object_types as $yaml_cf_type_slug => $yaml_cf_type_data) {
              $yaml_cf_entries = get_option('yaml_cf_data_object_entries_' . $yaml_cf_type_slug, []);
              $yaml_cf_total_entries += count($yaml_cf_entries);
            }
            /* translators: 1: number of types, 2: number of entries */
            printf(esc_html__('This exports %1$d lists with %2$d items.', 'yaml-custom-fields'), absint($yaml_cf_total_types), absint($yaml_cf_total_entries));
            ?>
          </p>
        </form>
      <?php
      endif;
      ?>
    </div>

    <div class="card yaml-cf-card-wide">
      <h3><?php esc_html_e('Import lists', 'yaml-custom-fields'); ?></h3>
      <p><?php esc_html_e('Load reusable lists from an export file.', 'yaml-custom-fields'); ?></p>

      <div class="yaml-cf-callout is-info yaml-cf-spaced-bottom">
        <p><strong><?php esc_html_e('How it works:', 'yaml-custom-fields'); ?></strong></p>
        <ul>
          <li><?php esc_html_e('Choose a .json file exported from YAML Custom Fields.', 'yaml-custom-fields'); ?></li>
          <li><?php esc_html_e('A list with the same short name is replaced.', 'yaml-custom-fields'); ?></li>
          <li><?php esc_html_e('Images and files that do not exist on this website are left empty.', 'yaml-custom-fields'); ?></li>
          <li><?php esc_html_e('All items in each list are imported.', 'yaml-custom-fields'); ?></li>
        </ul>
      </div>

      <div>
        <input type="file" id="yaml-cf-import-data-objects-file" accept=".json" class="yaml-cf-file-input">
        <br>
        <button type="button" class="button button-primary" id="yaml-cf-import-data-objects">
          <?php esc_html_e('Import lists', 'yaml-custom-fields'); ?>
        </button>
        <span id="yaml-cf-import-data-objects-message" class="yaml-cf-inline-message"></span>
      </div>

      <div id="yaml-cf-import-data-objects-results" class="yaml-cf-hidden yaml-cf-panel yaml-cf-spaced-top">
        <h3><?php esc_html_e('Result', 'yaml-custom-fields'); ?></h3>
        <div id="yaml-cf-import-data-objects-results-content"></div>
      </div>
    </div>
  </div>
  </div>
</div>
