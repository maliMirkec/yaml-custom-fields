<?php
/**
 * Schema Reference Partial
 * File: templates/partials/schema-reference.php
 *
 * Collapsible help section with an example schema and the list of field types.
 * Shared by the main page and every schema editor screen.
 *
 * Optional variable:
 * - $yaml_cf_reference_example string Raw YAML shown as the example. Falls back
 *                                     to the general example below.
 */

if (!defined('ABSPATH')) {
  exit;
}

if (empty($yaml_cf_reference_example)) {
  $yaml_cf_reference_example = <<<'YAML'
fields:
  - section: Page basics
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
  - break: true
  - name: category_note
    label: Note for editors
    type: string
  - type: section
    label: Categories
    description: Used for filtering and listings
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
            type: rich-text
YAML;
}
?>

<details class="yaml-cf-reference">
  <summary>
    <span class="dashicons dashicons-book-alt" aria-hidden="true"></span>
    <?php esc_html_e('Help: example schema and field types', 'yaml-custom-fields'); ?>
  </summary>

  <div class="yaml-cf-reference-body">
    <div class="yaml-cf-schema-examples">
      <h3><?php esc_html_e('Example schema', 'yaml-custom-fields'); ?></h3>
      <pre><?php echo esc_html($yaml_cf_reference_example); ?></pre>
    </div>

    <div>
      <h3><?php esc_html_e('Field types', 'yaml-custom-fields'); ?></h3>
      <dl class="yaml-cf-field-types">
        <dt>boolean</dt><dd><?php esc_html_e('A tick box (yes or no).', 'yaml-custom-fields'); ?></dd>
        <dt>string</dt><dd><?php esc_html_e('One line of text.', 'yaml-custom-fields'); ?></dd>
        <dt>text</dt><dd><?php esc_html_e('A text box for a few lines.', 'yaml-custom-fields'); ?></dd>
        <dt>rich-text</dt><dd><?php esc_html_e('A text editor with bold, links and lists.', 'yaml-custom-fields'); ?></dd>
        <dt>code</dt><dd><?php esc_html_e('A box for code, like an embed or a form.', 'yaml-custom-fields'); ?></dd>
        <dt>number</dt><dd><?php esc_html_e('A number, with an optional lowest and highest value.', 'yaml-custom-fields'); ?></dd>
        <dt>date</dt><dd><?php esc_html_e('A date. Can include a time.', 'yaml-custom-fields'); ?></dd>
        <dt>select</dt><dd><?php esc_html_e('A dropdown with a fixed set of choices.', 'yaml-custom-fields'); ?></dd>
        <dt>taxonomy</dt><dd><?php esc_html_e('A choice of categories or tags.', 'yaml-custom-fields'); ?></dd>
        <dt>data_object</dt><dd><?php esc_html_e('A choice from a collection.', 'yaml-custom-fields'); ?></dd>
        <dt>image</dt><dd><?php esc_html_e('An image from the media library. Add "list: true" for a gallery of several images.', 'yaml-custom-fields'); ?></dd>
        <dt>file</dt><dd><?php esc_html_e('Any file from the media library, like a PDF. Add "list: true" for several files.', 'yaml-custom-fields'); ?></dd>
        <dt>object</dt><dd><?php esc_html_e('A group of fields that belong together.', 'yaml-custom-fields'); ?></dd>
        <dt>block</dt><dd><?php esc_html_e('Sections that can be added, removed and reordered.', 'yaml-custom-fields'); ?></dd>
      </dl>

      <h3 class="yaml-cf-reference-subhead"><?php esc_html_e('Naming a section in its header', 'yaml-custom-fields'); ?></h3>
      <p class="yaml-cf-reference-note">
        <?php esc_html_e('Each repeating section shows the text of its first field in its header, so a long page is easy to scan. To use a different field, add "summaryField: field_name" to the block.', 'yaml-custom-fields'); ?>
      </p>

      <h3 class="yaml-cf-reference-subhead"><?php esc_html_e('Splitting up a long form', 'yaml-custom-fields'); ?></h3>
      <p class="yaml-cf-reference-note">
        <?php esc_html_e('A section break groups the fields that follow it under a heading. Write "- section: Heading", or leave the heading out for a plain line. Use "- break: true" for a gap between fields without a heading or a line.', 'yaml-custom-fields'); ?>
      </p>
      <dl class="yaml-cf-field-types">
        <dt>section</dt><dd><?php esc_html_e('A heading that starts a new group of fields.', 'yaml-custom-fields'); ?></dd>
        <dt>break: true</dt><dd><?php esc_html_e('Adds a gap between fields, without a heading or a line.', 'yaml-custom-fields'); ?></dd>
        <dt>info</dt><dd><?php esc_html_e('A note for whoever fills in the form. It holds no content.', 'yaml-custom-fields'); ?></dd>
      </dl>
    </div>
  </div>
</details>
<?php unset($yaml_cf_reference_example); ?>
