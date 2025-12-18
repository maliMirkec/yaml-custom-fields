<?php
/**
 * Admin Page Template
 * File: templates/admin-page.php
 */

if (!defined('ABSPATH')) {
  exit;
}
?>

<div class="wrap">
  <div class="yaml-cf-admin-container">
    <div class="yaml-cf-header">
      <div class="yaml-cf-header-content">
        <img src="<?php echo esc_url(YAML_CF_PLUGIN_URL . 'icon-256x256.png'); ?>" alt="YAML Custom Fields" class="yaml-cf-logo" />
        <div class="yaml-cf-header-text">
          <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
          <p class="yaml-cf-tagline"><?php esc_html_e('YAML-powered content schemas for WordPress themes', 'yaml-custom-fields'); ?></p>
        </div>
      </div>
    </div>

    <div class="yaml-cf-intro">
    <p><?php esc_html_e('YAML Custom Fields allows you to define YAML frontmatter schemas for your theme templates. Enable YAML for templates, define schemas, and manage structured content directly in the WordPress editor.', 'yaml-custom-fields'); ?></p>
    <p>
      <button type="button" id="yaml-cf-refresh-templates" class="button">
        <span class="dashicons dashicons-update"></span>
        <?php esc_html_e('Refresh Template List', 'yaml-custom-fields'); ?>
      </button>
    </p>
    <p class="description">
      <?php esc_html_e('Scan theme files for new templates and partials with @ycf markers', 'yaml-custom-fields'); ?>
    </p>
    </div>

    <div class="yaml-cf-global-schema-section" style="background: #f9f9f9; padding: 20px; border-left: 4px solid #2271b1; margin: 30px 0;">
    <h2><?php esc_html_e('Global Schema & Data', 'yaml-custom-fields'); ?></h2>
    <p><?php esc_html_e('Define a global schema with fields that can be reused across templates. Global data is shared everywhere (not per-post).', 'yaml-custom-fields'); ?></p>
    <p>
      <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-edit-global-schema')); ?>" class="button button-primary">
        <span class="dashicons dashicons-admin-generic"></span>
        <?php esc_html_e('Edit Global Schema', 'yaml-custom-fields'); ?>
      </a>
      <?php if (!empty($global_schema_parsed) && !empty($global_schema_parsed['fields'])) : ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-manage-global-data')); ?>" class="button">
          <span class="dashicons dashicons-edit"></span>
          <?php esc_html_e('Manage Global Data', 'yaml-custom-fields'); ?>
        </a>
        <span>
          <span class="dashicons dashicons-yes-alt" style="color: #46b450; margin-top: 3px; margin-left: 10px;"></span>
          <span class="description"><?php esc_html_e('Global schema configured', 'yaml-custom-fields'); ?></span>
        </span>
      <?php else : ?>
        <span class="description" style="margin-left: 10px;"><?php esc_html_e('No global schema defined yet', 'yaml-custom-fields'); ?></span>
      <?php endif; ?>
    </p>
    </div>

    <h2><?php esc_html_e('Page & Post Templates', 'yaml-custom-fields'); ?></h2>
    <p><?php esc_html_e('Configure YAML schemas for individual pages and posts. Data is stored per post/page and editable in the post editor.', 'yaml-custom-fields'); ?></p>

    <?php if (empty($templates)) : ?>
    <p><?php esc_html_e('No templates found in the current theme.', 'yaml-custom-fields'); ?></p>
    <?php else : ?>
    <div class="wp-table-wrap">
      <table class="wp-list-table widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Template Name', 'yaml-custom-fields'); ?></th>
                <th><?php esc_html_e('File', 'yaml-custom-fields'); ?></th>
                <th><?php esc_html_e('Enable YAML', 'yaml-custom-fields'); ?></th>
                <th><?php esc_html_e('Schema', 'yaml-custom-fields'); ?></th>
                <th><?php esc_html_e('Template Global Schema', 'yaml-custom-fields'); ?></th>
                <th><?php esc_html_e('Template Global Data', 'yaml-custom-fields'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($templates as $yaml_cf_template) : ?>
                <?php
                $yaml_cf_is_enabled = isset($template_settings[$yaml_cf_template['file']]) && $template_settings[$yaml_cf_template['file']];
                $yaml_cf_has_schema = isset($schemas[$yaml_cf_template['file']]) && !empty($schemas[$yaml_cf_template['file']]);
                $yaml_cf_has_template_global_schema = isset($template_global_schemas[$yaml_cf_template['file']]) && !empty($template_global_schemas[$yaml_cf_template['file']]);
                ?>
                <tr>
                    <td><strong><?php echo esc_html($yaml_cf_template['name']); ?></strong></td>
                    <td><code><?php echo esc_html($yaml_cf_template['file']); ?></code></td>
                    <td>
                        <label class="yaml-cf-switch">
                            <input type="checkbox"
                                    class="yaml-cf-enable-yaml"
                                    name="enable-yaml"
                                    data-template="<?php echo esc_attr($yaml_cf_template['file']); ?>"
                                    <?php checked($yaml_cf_is_enabled); ?> />
                            <span class="yaml-cf-slider"></span>
                        </label>
                    </td>
                    <td>
                        <?php if ($yaml_cf_is_enabled) : ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-edit-schema&template=' . urlencode($yaml_cf_template['file']))); ?>"
                              class="button">
                                <?php echo $yaml_cf_has_schema ? esc_html__('Edit Schema', 'yaml-custom-fields') : esc_html__('Add Schema', 'yaml-custom-fields'); ?>
                            </a>
                            <?php if ($yaml_cf_has_schema) : ?>
                                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                            <?php endif; ?>
                        <?php else : ?>
                            <span class="description"><?php esc_html_e('Enable YAML first', 'yaml-custom-fields'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($yaml_cf_is_enabled) : ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-edit-template-global&template=' . urlencode($yaml_cf_template['file']))); ?>"
                              class="button">
                                <?php echo $yaml_cf_has_template_global_schema ? esc_html__('Edit Template Global', 'yaml-custom-fields') : esc_html__('Add Template Global', 'yaml-custom-fields'); ?>
                            </a>
                            <?php if ($yaml_cf_has_template_global_schema) : ?>
                                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                            <?php endif; ?>
                        <?php else : ?>
                            <span class="description"><?php esc_html_e('Enable YAML first', 'yaml-custom-fields'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($yaml_cf_is_enabled && $yaml_cf_has_template_global_schema) : ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-manage-template-global&template=' . urlencode($yaml_cf_template['file']))); ?>"
                              class="button">
                                <?php esc_html_e('Manage Template Global Data', 'yaml-custom-fields'); ?>
                            </a>
                        <?php else : ?>
                            <span class="description"><?php esc_html_e('Add template global schema first', 'yaml-custom-fields'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <h2 style="margin-top: 40px;"><?php esc_html_e('Template Partials & Archives', 'yaml-custom-fields'); ?></h2>
    <p><?php esc_html_e('Configure YAML schemas for template partials (header, footer, sidebar, etc.) and archive pages (archive-events.php, category.php, etc.). Data is stored globally and can be managed below.', 'yaml-custom-fields'); ?></p>

    <?php if (empty($partials)) : ?>
    <p><?php esc_html_e('No partials found in the current theme.', 'yaml-custom-fields'); ?></p>
    <?php else : ?>
    <div class="wp-table-wrap">
      <table class="wp-list-table widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Template Name', 'yaml-custom-fields'); ?></th>
                <th><?php esc_html_e('File', 'yaml-custom-fields'); ?></th>
                <th><?php esc_html_e('Enable YAML', 'yaml-custom-fields'); ?></th>
                <th><?php esc_html_e('Schema', 'yaml-custom-fields'); ?></th>
                <th><?php esc_html_e('Data', 'yaml-custom-fields'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($partials as $yaml_cf_partial) : ?>
                <?php
                $yaml_cf_is_enabled = isset($template_settings[$yaml_cf_partial['file']]) && $template_settings[$yaml_cf_partial['file']];
                $yaml_cf_has_schema = isset($schemas[$yaml_cf_partial['file']]) && !empty($schemas[$yaml_cf_partial['file']]);
                ?>
                <tr>
                    <td><strong><?php echo esc_html($yaml_cf_partial['name']); ?></strong></td>
                    <td><code><?php echo esc_html($yaml_cf_partial['file']); ?></code></td>
                    <td>
                        <label class="yaml-cf-switch">
                            <input type="checkbox"
                                    class="yaml-cf-enable-yaml"
                                    name="enable-yaml"
                                    data-template="<?php echo esc_attr($yaml_cf_partial['file']); ?>"
                                    <?php checked($yaml_cf_is_enabled); ?> />
                            <span class="yaml-cf-slider"></span>
                        </label>
                    </td>
                    <td>
                        <?php if ($yaml_cf_is_enabled) : ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-edit-schema&template=' . urlencode($yaml_cf_partial['file']))); ?>"
                              class="button">
                                <?php echo $yaml_cf_has_schema ? esc_html__('Edit Schema', 'yaml-custom-fields') : esc_html__('Add Schema', 'yaml-custom-fields'); ?>
                            </a>
                            <?php if ($yaml_cf_has_schema) : ?>
                                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                            <?php endif; ?>
                        <?php else : ?>
                            <span class="description"><?php esc_html_e('Enable YAML first', 'yaml-custom-fields'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($yaml_cf_is_enabled && $yaml_cf_has_schema) : ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-edit-partial&template=' . urlencode($yaml_cf_partial['file']))); ?>"
                              class="button">
                                <?php esc_html_e('Manage Data', 'yaml-custom-fields'); ?>
                            </a>
                        <?php else : ?>
                            <span class="description"><?php esc_html_e('Add schema first', 'yaml-custom-fields'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <div class="yaml-cf-schema-examples">
    <h2><?php esc_html_e('Schema Example', 'yaml-custom-fields'); ?></h2>
    <p><?php esc_html_e('Here\'s an example schema in YAML format:', 'yaml-custom-fields'); ?></p>
    <pre>fields:
  - name: title
    label: Page Title
    type: string
    options:
      maxlength: 100
  - name: description
    label: Description
    type: text
    options:
      maxlength: 160
  - name: featured_image
    label: Featured Image
    type: image
  - name: category
    label: Category
    type: taxonomy
    options:
      taxonomy: category
  - name: university
    label: University
    type: data_object
    options:
      object_type: universities
  - name: sponsors
    label: Sponsors
    type: data_object
    multiple: true
    options:
      object_type: companies
  - name: tags
    label: Tags
    type: taxonomy
    multiple: true
    options:
      taxonomy: post_tag
  - name: sections
    label: Page Sections
    type: block
    list: true
    blockKey: type
    blocks:
      - name: hero
        label: Hero Section
        fields:
          - name: hero-title
            label: Hero Title
            type: string
          - name: content
            label: Hero Content
            type: rich-text
      - name: text
        label: Text Block
        fields:
          - name: content
            label: Content
            type: rich-text</pre>

    <h3><?php esc_html_e('Supported Field Types', 'yaml-custom-fields'); ?></h3>
    <ul>
      <li><strong>boolean</strong> - Checkbox</li>
      <li><strong>string</strong> - Single line text (supports minlength, maxlength)</li>
      <li><strong>text</strong> - Multi-line textarea (supports maxlength)</li>
      <li><strong>rich-text</strong> - WordPress WYSIWYG editor</li>
      <li><strong>code</strong> - Code editor (supports language option)</li>
      <li><strong>number</strong> - Number input (supports min, max)</li>
      <li><strong>date</strong> - Date picker (supports time option for datetime)</li>
      <li><strong>select</strong> - Dropdown (supports multiple and values options)</li>
      <li><strong>taxonomy</strong> - WordPress categories, tags, or custom taxonomies (supports multiple and taxonomy options)</li>
      <li><strong>data_object</strong> - Reference to data object entries (supports multiple and object_type options)</li>
      <li><strong>image</strong> - WordPress media uploader for images</li>
      <li><strong>file</strong> - WordPress media uploader for any file</li>
      <li><strong>object</strong> - Nested fields group</li>
      <li><strong>block</strong> - Repeater field with multiple block types (list: true for repeatable)</li>
    </ul>
    </div>
  </div>
</div>


<script>
jQuery(document).ready(function($) {
  <?php if (!empty($notification)) : ?>
  // Display notification
  if (typeof yamlCF !== 'undefined' && yamlCF.showMessage) {
    yamlCF.showMessage('<?php echo esc_js($notification['message']); ?>', '<?php echo esc_js($notification['type']); ?>');
  }
  <?php endif; ?>

  // Handle refresh button click
  $('#yaml-cf-refresh-templates').on('click', function(e) {
    e.preventDefault();
    var $btn = $(this);
    var originalHtml = $btn.html();

    // Disable button and show loading state
    $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> <?php esc_html_e('Refreshing...', 'yaml-custom-fields'); ?>');

    $.ajax({
      url: ajaxurl,
      type: 'POST',
      data: {
        action: 'yaml_cf_refresh_templates',
        nonce: yamlCF.nonce
      },
      success: function(response) {
        if (response.success) {
          // Reload the page to show updated template list
          window.location.reload();
        } else {
          $btn.prop('disabled', false).html(originalHtml);
          if (typeof yamlCF !== 'undefined' && yamlCF.showMessage) {
            yamlCF.showMessage(response.data || '<?php esc_html_e('Failed to refresh template list', 'yaml-custom-fields'); ?>', 'error');
          }
        }
      },
      error: function() {
        $btn.prop('disabled', false).html(originalHtml);
        if (typeof yamlCF !== 'undefined' && yamlCF.showMessage) {
          yamlCF.showMessage('<?php esc_html_e('Failed to refresh template list', 'yaml-custom-fields'); ?>', 'error');
        }
      }
    });
  });
});
</script>
