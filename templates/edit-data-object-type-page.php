<?php
/**
 * Edit Data Object Type Page
 * Create or edit a data object type and its schema
 */

if (!defined('ABSPATH')) {
  exit;
}

// Variables passed from controller:
// - $type_id: The data object type slug (from URL)
// - $type_name: The data object type name
// - $schema_yaml: The YAML schema
// - $is_editing: Whether we're editing an existing type
// - $success_message: Success message to display (if any)
// - $error_message: Error message to display (if any)

$yaml_cf_type_slug = $type_id;
$yaml_cf_is_editing = $is_editing;
$yaml_cf_type_name = $type_name;
$yaml_cf_schema_yaml = $schema_yaml;
?>

<div class="wrap">
  <div class="yaml-cf-admin-container">
    <div class="yaml-cf-header">
      <div class="yaml-cf-header-content">
        <img src="<?php echo esc_url(YAML_CF_PLUGIN_URL . 'assets/icon-256x256.png'); ?>" alt="<?php esc_attr_e('YAML Custom Fields', 'yaml-custom-fields'); ?>" class="yaml-cf-logo" />
        <div class="yaml-cf-header-text">
          <h1><?php echo $yaml_cf_is_editing ? esc_html($yaml_cf_type_name) : esc_html__('New collection', 'yaml-custom-fields'); ?></h1>
          <p class="yaml-cf-tagline"><?php esc_html_e('Choose the fields for this collection', 'yaml-custom-fields'); ?></p>
        </div>
      </div>
    </div>
    <hr class="wp-header-end">

    <div class="yaml-cf-intro">
      <p>
        <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-data-objects')); ?>" class="button">
          <span class="dashicons dashicons-arrow-left-alt2"></span>
          <?php esc_html_e('Back to Collections', 'yaml-custom-fields'); ?>
        </a>
        <?php if ($yaml_cf_is_editing) : ?>
          <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-manage-data-object-entries&type_id=' . urlencode($yaml_cf_type_slug))); ?>" class="button button-secondary yaml-cf-button-gap">
            <span class="dashicons dashicons-edit"></span>
            <?php esc_html_e('Edit items', 'yaml-custom-fields'); ?>
          </a>
        <?php endif; ?>
      </p>
      <?php if ($yaml_cf_is_editing) : ?>
        <p><strong><?php esc_html_e('Short name:', 'yaml-custom-fields'); ?></strong> <code><?php echo esc_html($yaml_cf_type_slug); ?></code></p>
      <?php endif; ?>
      <p><?php esc_html_e('The schema says which fields every item in this collection has. For example: a name, a logo and a website.', 'yaml-custom-fields'); ?></p>
    </div>

    <form method="post" action="">
      <?php wp_nonce_field('yaml_cf_save_data_object_type', 'yaml_cf_save_data_object_type_nonce'); ?>

      <div class="yaml-cf-surface yaml-cf-spaced-bottom">
        <h2><?php esc_html_e('About this collection', 'yaml-custom-fields'); ?></h2>

        <table class="form-table">
          <tr>
            <th scope="row">
              <label for="type_name"><?php esc_html_e('Collection name', 'yaml-custom-fields'); ?></label>
            </th>
            <td>
              <input type="text" name="type_name" id="type_name" value="<?php echo esc_attr($yaml_cf_type_name); ?>" class="regular-text" required />
              <p class="description"><?php esc_html_e('For example: Team members, Partners, Locations.', 'yaml-custom-fields'); ?></p>
            </td>
          </tr>
          <tr>
            <th scope="row">
              <label for="type_slug"><?php esc_html_e('Short name', 'yaml-custom-fields'); ?></label>
            </th>
            <td>
              <input type="text" name="type_slug" id="type_slug" value="<?php echo esc_attr($yaml_cf_type_slug); ?>" class="regular-text" <?php echo $yaml_cf_is_editing ? 'readonly' : ''; ?> required />
              <p class="description"><?php esc_html_e('Used in the schema and the theme. Lowercase letters and dashes only, like team-members. It cannot be changed later.', 'yaml-custom-fields'); ?></p>
            </td>
          </tr>
        </table>
      </div>

      <div class="yaml-cf-surface">
        <h2><?php esc_html_e('Schema', 'yaml-custom-fields'); ?></h2>
        <p><?php esc_html_e('Write the schema below:', 'yaml-custom-fields'); ?></p>
        <textarea name="schema" id="yaml-cf-schema-editor" rows="20" class="large-text code yaml-cf-code-editor"><?php echo esc_textarea($yaml_cf_schema_yaml); ?></textarea>
      </div>

      <p class="submit yaml-cf-spaced-top">
        <button type="submit" class="button button-primary button-large">
          <?php esc_html_e('Save collection', 'yaml-custom-fields'); ?>
        </button>
        <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-data-objects')); ?>" class="button button-large">
          <?php esc_html_e('Cancel', 'yaml-custom-fields'); ?>
        </a>
      </p>
    </form>

    <?php
    $yaml_cf_reference_example = <<<'YAML'
fields:
  - section: Basics
  - name: name
    label: University Name
    type: string
    required: true
  - name: logo
    label: Logo
    type: image
  - name: website
    label: Website URL
    type: string
  - break: true
  - name: description
    label: Description
    type: text
  - section: Details
  - name: location
    label: Location
    type: string
  - name: founded
    label: Founded Year
    type: number
YAML;
    include YAML_CF_PLUGIN_DIR . 'templates/partials/schema-reference.php';
    ?>

    <?php if ($yaml_cf_is_editing) : ?>
    <div class="yaml-cf-schema-examples yaml-cf-examples-spaced">
      <h2><?php esc_html_e('Use it on a page', 'yaml-custom-fields'); ?></h2>
      <p><?php esc_html_e('To let a page pick an item from this collection, add this to the page\'s schema:', 'yaml-custom-fields'); ?></p>
      <div class="yaml-cf-relative">
        <button type="button" class="yaml-cf-copy-snippet button button-small yaml-cf-copy-corner" data-snippet="- name: <?php echo esc_attr($yaml_cf_type_slug); ?>&#10;  label: <?php echo esc_attr($yaml_cf_type_name); ?>&#10;  type: data_object&#10;  options:&#10;    object_type: <?php echo esc_attr($yaml_cf_type_slug); ?>">
          <span class="dashicons dashicons-editor-code"></span>
          <?php esc_html_e('Copy', 'yaml-custom-fields'); ?>
        </button>
        <pre>- name: <?php echo esc_html($yaml_cf_type_slug); ?>
  label: <?php echo esc_html($yaml_cf_type_name); ?>
  type: data_object
  options:
    object_type: <?php echo esc_html($yaml_cf_type_slug); ?></pre>
      </div>
    </div>

    <div class="yaml-cf-schema-examples yaml-cf-examples-spaced">
      <h2><?php esc_html_e('Show it on the website', 'yaml-custom-fields'); ?></h2>
      <p><?php esc_html_e('To show items on the website, add this to the theme:', 'yaml-custom-fields'); ?></p>

      <h3 class="yaml-cf-spaced-top"><?php esc_html_e('Show the chosen item', 'yaml-custom-fields'); ?></h3>
      <div class="yaml-cf-relative">
        <button type="button" class="yaml-cf-copy-snippet button button-small yaml-cf-copy-corner" data-snippet="&lt;?php&#10;$<?php echo esc_attr($yaml_cf_type_slug); ?> = ycf_get_data_object('<?php echo esc_attr($yaml_cf_type_slug); ?>');&#10;if ($<?php echo esc_attr($yaml_cf_type_slug); ?>) {&#10;  echo '&lt;h2&gt;' . esc_html($<?php echo esc_attr($yaml_cf_type_slug); ?>['name']) . '&lt;/h2&gt;';&#10;  // Access other fields: $<?php echo esc_attr($yaml_cf_type_slug); ?>['field_name']&#10;}&#10;?&gt;">
          <span class="dashicons dashicons-editor-code"></span>
          <?php esc_html_e('Copy', 'yaml-custom-fields'); ?>
        </button>
        <pre>&lt;?php
$<?php echo esc_html($yaml_cf_type_slug); ?> = ycf_get_data_object('<?php echo esc_html($yaml_cf_type_slug); ?>');
if ($<?php echo esc_html($yaml_cf_type_slug); ?>) {
  echo '&lt;h2&gt;' . esc_html($<?php echo esc_html($yaml_cf_type_slug); ?>['name']) . '&lt;/h2&gt;';
  // Access other fields: $<?php echo esc_html($yaml_cf_type_slug); ?>['field_name']
}
?&gt;</pre>
      </div>

      <h3 class="yaml-cf-spaced-top"><?php esc_html_e('Show every item', 'yaml-custom-fields'); ?></h3>
      <div class="yaml-cf-relative">
        <button type="button" class="yaml-cf-copy-snippet button button-small yaml-cf-copy-corner" data-snippet="&lt;?php&#10;$all_<?php echo esc_attr($yaml_cf_type_slug); ?> = ycf_get_data_objects('<?php echo esc_attr($yaml_cf_type_slug); ?>');&#10;foreach ($all_<?php echo esc_attr($yaml_cf_type_slug); ?> as $entry_id => $entry) {&#10;  echo '&lt;h3&gt;' . esc_html($entry['name']) . '&lt;/h3&gt;';&#10;}&#10;?&gt;">
          <span class="dashicons dashicons-editor-code"></span>
          <?php esc_html_e('Copy', 'yaml-custom-fields'); ?>
        </button>
        <pre>&lt;?php
$all_<?php echo esc_html($yaml_cf_type_slug); ?> = ycf_get_data_objects('<?php echo esc_html($yaml_cf_type_slug); ?>');
foreach ($all_<?php echo esc_html($yaml_cf_type_slug); ?> as $entry_id => $entry) {
  echo '&lt;h3&gt;' . esc_html($entry['name']) . '&lt;/h3&gt;';
}
?&gt;</pre>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
