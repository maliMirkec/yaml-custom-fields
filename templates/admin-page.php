<?php
/**
 * Admin Page Template (dashboard)
 * File: templates/admin-page.php
 */

if (!defined('ABSPATH')) {
  exit;
}

// Variables received from controller via extract():
// - $template_rows : array - Page/post templates: file, name, enabled, has_schema, field_count, has_shared_schema, shared_field_count
// - $partial_rows : array - Partials and archives (same keys)
// - $global_field_count : int - Number of fields in the global schema
// - $show_getting_started : bool - Show first-run guidance
// - $theme_kind : array - name, is_block, is_child, parent of the active theme
// - $keep_data_on_uninstall : bool - Whether deleting the plugin keeps the content

$yaml_cf_admin_url = function ($page, $template = null) {
  $args = ['page' => $page];
  if ($template !== null) {
    $args['template'] = $template;
  }
  return add_query_arg(array_map('rawurlencode', $args), admin_url('admin.php'));
};

/**
 * Print the status badge for a schema
 */
$yaml_cf_schema_badge = function ($has_schema, $field_count) {
  if ($has_schema && $field_count > 0) {
    echo '<span class="yaml-cf-badge is-ready"><span class="dashicons dashicons-yes" aria-hidden="true"></span>';
    /* translators: %d: number of fields */
    echo esc_html(sprintf(_n('%d field', '%d fields', $field_count, 'yaml-custom-fields'), $field_count));
    echo '</span>';
  } elseif ($has_schema) {
    echo '<span class="yaml-cf-badge is-warning" title="' . esc_attr__('The schema has a mistake. Open it to fix it.', 'yaml-custom-fields') . '"><span class="dashicons dashicons-warning" aria-hidden="true"></span>' . esc_html__('Fix schema', 'yaml-custom-fields') . '</span>';
  } else {
    echo '<span class="yaml-cf-badge is-empty">' . esc_html__('No fields yet', 'yaml-custom-fields') . '</span>';
  }
};

/**
 * Print the on/off switch for a template row
 */
$yaml_cf_toggle = function ($row) {
  ?>
  <label class="yaml-cf-toggle">
    <input type="checkbox"
           class="yaml-cf-enable-yaml"
           data-template="<?php echo esc_attr($row['file']); ?>"
           data-template-name="<?php echo esc_attr($row['name']); ?>"
           data-has-schema="<?php echo $row['has_schema'] ? '1' : '0'; ?>"
           <?php checked($row['enabled']); ?> />
    <span class="yaml-cf-toggle-track" aria-hidden="true"></span>
    <span class="yaml-cf-toggle-state" aria-hidden="true">
      <span class="is-on"><?php esc_html_e('On', 'yaml-custom-fields'); ?></span>
      <span class="is-off"><?php esc_html_e('Off', 'yaml-custom-fields'); ?></span>
    </span>
    <span class="screen-reader-text">
      <?php
      /* translators: %s: template name */
      echo esc_html(sprintf(__('Custom fields for %s', 'yaml-custom-fields'), $row['name']));
      ?>
    </span>
  </label>
  <?php
};

/**
 * Say why a table is empty, in terms of the theme that is switched on
 */
$yaml_cf_empty_reason = function ($kind, $what) {
  if (!empty($kind['is_block'])) {
    echo '<p>';
    echo esc_html(sprintf(
      /* translators: 1: theme name, 2: what is missing, e.g. "page or post templates" */
      __('%1$s builds its pages out of blocks, and this plugin found no %2$s in it.', 'yaml-custom-fields'),
      $kind['name'],
      $what
    ));
    echo '</p>';
    echo '<p>' . esc_html__('Templates made in the Site Editor show up here once they are saved. Use Refresh templates after adding one.', 'yaml-custom-fields') . '</p>';
    return;
  }

  if (!empty($kind['is_child'])) {
    echo '<p>';
    echo esc_html(sprintf(
      /* translators: 1: child theme name, 2: parent theme name, 3: what is missing */
      __('%1$s and the theme it builds on, %2$s, have no %3$s between them.', 'yaml-custom-fields'),
      $kind['name'],
      $kind['parent'],
      $what
    ));
    echo '</p>';
    return;
  }

  echo '<p>';
  echo esc_html(sprintf(
    /* translators: 1: theme name, 2: what is missing */
    __('%1$s has no %2$s.', 'yaml-custom-fields'),
    $kind['name'],
    $what
  ));
  echo '</p>';
};

?>

<div class="wrap yaml-cf-dashboard">
  <div class="yaml-cf-admin-container">
    <div class="yaml-cf-header">
      <div class="yaml-cf-header-content">
        <img src="<?php echo esc_url(YAML_CF_PLUGIN_URL . 'assets/icon-256x256.png'); ?>" alt="<?php esc_attr_e('YAML Custom Fields', 'yaml-custom-fields'); ?>" class="yaml-cf-logo" />
        <div class="yaml-cf-header-text">
          <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
          <p class="yaml-cf-tagline"><?php esc_html_e('Custom fields for pages, posts and every part of the site.', 'yaml-custom-fields'); ?></p>
        </div>
      </div>
    </div>
    <hr class="wp-header-end">

    <?php if ($show_getting_started) : ?>
      <section class="yaml-cf-onboarding" aria-labelledby="yaml-cf-onboarding-title">
        <h2 id="yaml-cf-onboarding-title"><?php esc_html_e('Get started in four steps', 'yaml-custom-fields'); ?></h2>
        <ol class="yaml-cf-steps">
          <li>
            <strong><?php esc_html_e('Turn on a template', 'yaml-custom-fields'); ?></strong>
            <span><?php esc_html_e('Find a template in the tables below and switch it on.', 'yaml-custom-fields'); ?></span>
          </li>
          <li>
            <strong><?php esc_html_e('Create its schema', 'yaml-custom-fields'); ?></strong>
            <span><?php esc_html_e('A schema is a short list of the fields a page gets, like a title or a photo. It is written in a simple text format. See the example at the bottom of this page.', 'yaml-custom-fields'); ?></span>
          </li>
          <li>
            <strong><?php esc_html_e('Fill in the content', 'yaml-custom-fields'); ?></strong>
            <span><?php esc_html_e('The fields appear below the title on every page or post that uses the template. Writers only ever see this step.', 'yaml-custom-fields'); ?></span>
          </li>
          <li>
            <strong><?php esc_html_e('Show it in the theme', 'yaml-custom-fields'); ?></strong>
            <span>
              <?php esc_html_e('A field shows up on the website once the theme asks for it. That is one line per field, and the Copy code button next to each field copies the exact line.', 'yaml-custom-fields'); ?>
              <code>&lt;?php echo esc_html( ycf_get_field( 'headline' ) ); ?&gt;</code>
            </span>
          </li>
        </ol>
        <p class="yaml-cf-onboarding-more">
          <a href="<?php echo esc_url($yaml_cf_admin_url('yaml-cf-docs')); ?>"><?php esc_html_e('Read the documentation', 'yaml-custom-fields'); ?></a>
        </p>
      </section>
    <?php endif; ?>

    <!-- Site details -->
    <section class="yaml-cf-card yaml-cf-global-card" aria-labelledby="yaml-cf-global-title">
      <div class="yaml-cf-card-title-row">
        <h2 id="yaml-cf-global-title">
          <span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span>
          <?php esc_html_e('Site details', 'yaml-custom-fields'); ?>
        </h2>
        <?php if ($global_field_count > 0) : ?>
          <span class="yaml-cf-badge is-ready"><span class="dashicons dashicons-yes" aria-hidden="true"></span>
            <?php
            /* translators: %d: number of fields */
            echo esc_html(sprintf(_n('%d field', '%d fields', $global_field_count, 'yaml-custom-fields'), $global_field_count));
            ?>
          </span>
        <?php else : ?>
          <span class="yaml-cf-badge is-empty"><?php esc_html_e('Not set up', 'yaml-custom-fields'); ?></span>
        <?php endif; ?>
      </div>

      <p class="yaml-cf-card-text">
        <?php esc_html_e('Details used across the whole website, like a phone number or social links.', 'yaml-custom-fields'); ?><br>
        <?php esc_html_e('Fill them in once and show them on any page.', 'yaml-custom-fields'); ?>
      </p>

      <div class="yaml-cf-card-actions">
        <?php if ($global_field_count > 0) : ?>
          <a href="<?php echo esc_url($yaml_cf_admin_url('yaml-cf-manage-global-data')); ?>" class="button button-primary">
            <span class="dashicons dashicons-edit" aria-hidden="true"></span>
            <?php esc_html_e('Edit site details', 'yaml-custom-fields'); ?>
          </a>
          <a href="<?php echo esc_url($yaml_cf_admin_url('yaml-cf-edit-global-schema')); ?>" class="button">
            <span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
            <?php esc_html_e('Edit schema', 'yaml-custom-fields'); ?>
          </a>
        <?php else : ?>
          <a href="<?php echo esc_url($yaml_cf_admin_url('yaml-cf-edit-global-schema')); ?>" class="button button-primary">
            <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
            <?php esc_html_e('Create site details', 'yaml-custom-fields'); ?>
          </a>
          <span class="yaml-cf-hint"><?php esc_html_e('Optional. Skip this if everything is different on each page.', 'yaml-custom-fields'); ?></span>
        <?php endif; ?>
      </div>
    </section>

    <!-- Page & post templates -->
    <section class="yaml-cf-section" aria-labelledby="yaml-cf-templates-title">
      <div class="yaml-cf-section-header">
        <div>
          <h2 id="yaml-cf-templates-title">
            <?php esc_html_e('Page & post templates', 'yaml-custom-fields'); ?>
          </h2>
          <p class="yaml-cf-section-text"><?php esc_html_e('These fields are filled in on each page or post, below the title.', 'yaml-custom-fields'); ?></p>
        </div>
        <div class="yaml-cf-section-tools">
          <button type="button" id="yaml-cf-refresh-templates" class="button" aria-describedby="yaml-cf-refresh-help">
            <span class="dashicons dashicons-update" aria-hidden="true"></span>
            <?php esc_html_e('Refresh list', 'yaml-custom-fields'); ?>
          </button>
          <span id="yaml-cf-refresh-help" class="yaml-cf-hint"><?php esc_html_e('Refresh to see newly added templates.', 'yaml-custom-fields'); ?></span>
        </div>
      </div>

      <?php if (empty($template_rows)) : ?>
        <div class="yaml-cf-empty">
          <?php $yaml_cf_empty_reason($theme_kind, __('page or post templates', 'yaml-custom-fields')); ?>
        </div>
      <?php else : ?>
        <table class="yaml-cf-table">
          <thead>
            <tr>
              <th scope="col"><?php esc_html_e('Template', 'yaml-custom-fields'); ?></th>
              <th scope="col" class="yaml-cf-col-toggle"><?php esc_html_e('Custom fields', 'yaml-custom-fields'); ?></th>
              <th scope="col">
                <?php esc_html_e('Page fields', 'yaml-custom-fields'); ?>
                <span class="yaml-cf-th-help"><?php esc_html_e('Different on each page', 'yaml-custom-fields'); ?></span>
              </th>
              <th scope="col">
                <?php esc_html_e('Shared fields', 'yaml-custom-fields'); ?>
                <span class="yaml-cf-th-help"><?php esc_html_e('Same on every page with this template', 'yaml-custom-fields'); ?></span>
              </th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($template_rows as $yaml_cf_row) : ?>
              <tr class="yaml-cf-row <?php echo $yaml_cf_row['enabled'] ? 'is-enabled' : 'is-disabled'; ?>">
                <td class="yaml-cf-cell-template" data-label="<?php esc_attr_e('Template', 'yaml-custom-fields'); ?>">
                  <strong><?php echo esc_html($yaml_cf_row['name']); ?></strong>
                  <code class="yaml-cf-file"><?php echo esc_html($yaml_cf_row['file']); ?></code>
                </td>
                <td data-label="<?php esc_attr_e('Custom fields', 'yaml-custom-fields'); ?>">
                  <?php $yaml_cf_toggle($yaml_cf_row); ?>
                </td>
                <td data-label="<?php esc_attr_e('Page fields', 'yaml-custom-fields'); ?>">
                  <div class="yaml-cf-when-enabled yaml-cf-cell-status">
                    <?php $yaml_cf_schema_badge($yaml_cf_row['has_schema'], $yaml_cf_row['field_count']); ?>
                    <a href="<?php echo esc_url($yaml_cf_admin_url('yaml-cf-edit-schema', $yaml_cf_row['file'])); ?>" class="button button-small">
                      <?php echo $yaml_cf_row['has_schema'] ? esc_html__('Edit schema', 'yaml-custom-fields') : esc_html__('Create schema', 'yaml-custom-fields'); ?>
                    </a>
                  </div>
                  <span class="yaml-cf-when-disabled yaml-cf-hint"><?php esc_html_e('Switch on to add fields', 'yaml-custom-fields'); ?></span>
                </td>
                <td data-label="<?php esc_attr_e('Shared fields', 'yaml-custom-fields'); ?>">
                  <div class="yaml-cf-when-enabled yaml-cf-cell-status">
                    <?php if ($yaml_cf_row['has_shared_schema']) : ?>
                      <?php $yaml_cf_schema_badge(true, $yaml_cf_row['shared_field_count']); ?>
                      <?php if ($yaml_cf_row['shared_field_count'] > 0) : ?>
                        <a href="<?php echo esc_url($yaml_cf_admin_url('yaml-cf-manage-template-global', $yaml_cf_row['file'])); ?>" class="button button-small">
                          <?php esc_html_e('Edit content', 'yaml-custom-fields'); ?>
                        </a>
                      <?php endif; ?>
                      <a href="<?php echo esc_url($yaml_cf_admin_url('yaml-cf-edit-template-global', $yaml_cf_row['file'])); ?>" class="button button-small button-link yaml-cf-link-button">
                        <?php esc_html_e('Edit schema', 'yaml-custom-fields'); ?>
                      </a>
                    <?php else : ?>
                      <span class="yaml-cf-hint"><?php esc_html_e('Optional', 'yaml-custom-fields'); ?></span>
                      <a href="<?php echo esc_url($yaml_cf_admin_url('yaml-cf-edit-template-global', $yaml_cf_row['file'])); ?>" class="button button-small">
                        <?php esc_html_e('Add shared fields', 'yaml-custom-fields'); ?>
                      </a>
                    <?php endif; ?>
                  </div>
                  <span class="yaml-cf-when-disabled yaml-cf-hint">&mdash;</span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>

    <!-- Partials & archives -->
    <section class="yaml-cf-section" aria-labelledby="yaml-cf-partials-title">
      <div class="yaml-cf-section-header">
        <div>
          <h2 id="yaml-cf-partials-title">
            <?php esc_html_e('Site parts & archive pages', 'yaml-custom-fields'); ?>
          </h2>
          <p class="yaml-cf-section-text"><?php esc_html_e('Parts that repeat on many pages, like the header and footer, and pages that list posts. Their content is filled in once, here.', 'yaml-custom-fields'); ?></p>
        </div>
      </div>

      <?php if (empty($partial_rows)) : ?>
        <div class="yaml-cf-empty">
          <?php $yaml_cf_empty_reason($theme_kind, __('site parts', 'yaml-custom-fields')); ?>
          <?php if (empty($theme_kind['is_block'])) : ?>
            <p><?php esc_html_e('To add one, put @ycf in a comment at the top of the template file, then refresh.', 'yaml-custom-fields'); ?></p>
          <?php endif; ?>
        </div>
      <?php else : ?>
        <table class="yaml-cf-table">
          <thead>
            <tr>
              <th scope="col"><?php esc_html_e('Template', 'yaml-custom-fields'); ?></th>
              <th scope="col" class="yaml-cf-col-toggle"><?php esc_html_e('Custom fields', 'yaml-custom-fields'); ?></th>
              <th scope="col"><?php esc_html_e('Fields', 'yaml-custom-fields'); ?></th>
              <th scope="col"><?php esc_html_e('Content', 'yaml-custom-fields'); ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($partial_rows as $yaml_cf_row) : ?>
              <tr class="yaml-cf-row <?php echo $yaml_cf_row['enabled'] ? 'is-enabled' : 'is-disabled'; ?>">
                <td class="yaml-cf-cell-template" data-label="<?php esc_attr_e('Template', 'yaml-custom-fields'); ?>">
                  <strong><?php echo esc_html($yaml_cf_row['name']); ?></strong>
                  <code class="yaml-cf-file"><?php echo esc_html($yaml_cf_row['file']); ?></code>
                </td>
                <td data-label="<?php esc_attr_e('Custom fields', 'yaml-custom-fields'); ?>">
                  <?php $yaml_cf_toggle($yaml_cf_row); ?>
                </td>
                <td data-label="<?php esc_attr_e('Fields', 'yaml-custom-fields'); ?>">
                  <div class="yaml-cf-when-enabled yaml-cf-cell-status">
                    <?php $yaml_cf_schema_badge($yaml_cf_row['has_schema'], $yaml_cf_row['field_count']); ?>
                    <a href="<?php echo esc_url($yaml_cf_admin_url('yaml-cf-edit-schema', $yaml_cf_row['file'])); ?>" class="button button-small">
                      <?php echo $yaml_cf_row['has_schema'] ? esc_html__('Edit schema', 'yaml-custom-fields') : esc_html__('Create schema', 'yaml-custom-fields'); ?>
                    </a>
                  </div>
                  <span class="yaml-cf-when-disabled yaml-cf-hint"><?php esc_html_e('Switch on to add fields', 'yaml-custom-fields'); ?></span>
                </td>
                <td data-label="<?php esc_attr_e('Content', 'yaml-custom-fields'); ?>">
                  <div class="yaml-cf-when-enabled yaml-cf-cell-status">
                    <?php if ($yaml_cf_row['field_count'] > 0) : ?>
                      <a href="<?php echo esc_url($yaml_cf_admin_url('yaml-cf-edit-partial', $yaml_cf_row['file'])); ?>" class="button button-small button-primary">
                        <?php esc_html_e('Edit content', 'yaml-custom-fields'); ?>
                      </a>
                    <?php else : ?>
                      <span class="yaml-cf-hint"><?php esc_html_e('Create the schema first.', 'yaml-custom-fields'); ?></span>
                    <?php endif; ?>
                  </div>
                  <span class="yaml-cf-when-disabled yaml-cf-hint">&mdash;</span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>

    <!-- What happens if the plugin is deleted -->
    <section class="yaml-cf-card yaml-cf-uninstall-card" aria-labelledby="yaml-cf-uninstall-title">
      <div class="yaml-cf-card-title-row">
        <h2 id="yaml-cf-uninstall-title">
          <span class="dashicons dashicons-trash" aria-hidden="true"></span>
          <?php esc_html_e('If this plugin is deleted', 'yaml-custom-fields'); ?>
        </h2>
      </div>
      <div class="yaml-cf-card-body">
        <p>
          <?php esc_html_e('Deleting the plugin removes the schemas, the site details, the collections, and the content filled into the fields on every page and post. Deactivating it changes nothing.', 'yaml-custom-fields'); ?>
        </p>
        <form method="post" action="">
          <?php wp_nonce_field('yaml_cf_keep_data', 'yaml_cf_keep_data_nonce'); ?>
          <p>
            <label>
              <input type="checkbox" name="yaml_cf_keep_data" value="1" <?php checked($keep_data_on_uninstall); ?> />
              <?php esc_html_e('Keep the content if the plugin is deleted', 'yaml-custom-fields'); ?>
            </label>
          </p>
          <p class="yaml-cf-hint">
            <?php esc_html_e('With this ticked, the content stays in the database and comes back if the plugin is installed again. Untick it to have everything removed.', 'yaml-custom-fields'); ?>
          </p>
          <p>
            <button type="submit" class="button"><?php esc_html_e('Save this choice', 'yaml-custom-fields'); ?></button>
          </p>
        </form>
      </div>
    </section>

    <!-- Schema reference -->
    <?php include YAML_CF_PLUGIN_DIR . 'templates/partials/schema-reference.php'; ?>
  </div>
</div>
