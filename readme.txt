=== YAML Custom Fields ===
Contributors: starbist
Tags: yaml, frontmatter, custom-fields, cms, page-builder
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A WordPress plugin for managing YAML frontmatter schemas in theme templates and partials.

== Description ==

YAML Custom Fields allows you to define structured content schemas with an intuitive interface and ACF-like template functions. Perfect for theme developers who want flexible, schema-based content management without the complexity.

= Features =

* Define YAML schemas for page templates and template partials
* 15+ field types including string, rich-text, images, blocks, taxonomies, data objects, and more
* Easy-to-use admin interface for managing schemas and data
* **Three-level data hierarchy:**
  * Per-page data for individual customization (stored in post meta)
  * Per-template global data shared across all posts using the same template
  * Site-wide global data for partials like headers and footers
* **Per-field global/local toggle:** Each field can independently use template global data or page-specific data
* **Visual dual-field interface:** See both template global and page-specific values side-by-side
* Data Objects for managing structured, reusable data (universities, companies, etc.)
* Data Validation page for reviewing imported content
* Consolidated Export/Import page for all data types (settings, page data, data objects)
* Simple template functions with ACF-like syntax and auto-merge behavior
* Administrator-only access for security
* Clean uninstall removes all database records
* WordPress Coding Standards compliant

= Supported Field Types =

* **String** - Single-line text with min/max length
* **Text** - Multi-line textarea
* **Rich Text** - WordPress WYSIWYG editor
* **Code** - Code editor with syntax highlighting
* **Boolean** - Checkbox for true/false values
* **Number** - Number input with min/max constraints
* **Date** - Date picker with optional time
* **Select** - Dropdown with single/multiple selection
* **Taxonomy** - WordPress categories, tags, or custom taxonomies with single/multiple selection
* **Post Type** - Dropdown to select registered post types (Post, Page, custom post types)
* **Data Object** - Reference to structured data objects managed independently (universities, companies, team members, etc.)
* **Image** - WordPress media uploader for images
* **File** - WordPress media uploader for any file
* **Object** - Nested group of fields
* **Block** - Repeatable blocks for flexible page builders

= Usage Example =

In your theme template:

`<?php
$hero_title = ycf_get_field('hero_title');
$hero_image = ycf_get_image('hero_image', null, 'full');
$category = ycf_get_term('category');
$post_type = ycf_get_post_type('content_type');
$university = ycf_get_data_object('university');
$features = ycf_get_field('features');
?>

<div class="hero">
  <?php if ($hero_image): ?>
    <img src="<?php echo esc_url($hero_image['url']); ?>" alt="<?php echo esc_attr($hero_image['alt']); ?>">
  <?php endif; ?>
  <h1><?php echo esc_html($hero_title); ?></h1>
  <?php if ($category): ?>
    <span class="category"><?php echo esc_html($category->name); ?></span>
  <?php endif; ?>
  <?php if ($university): ?>
    <p><?php echo esc_html($university['name']); ?></p>
  <?php endif; ?>
</div>`

== Installation ==

= From WordPress Plugin Directory =

1. Log in to your WordPress admin dashboard
2. Navigate to **Plugins → Add New**
3. Search for "YAML Custom Fields"
4. Click **Install Now** next to the YAML Custom Fields plugin
5. Click **Activate** after installation completes
6. Go to **YAML Custom Fields** in the admin menu to configure your schemas

= Manual Installation =

1. Download the plugin ZIP file
2. Log in to your WordPress admin dashboard
3. Navigate to **Plugins → Add New → Upload Plugin**
4. Choose the ZIP file and click **Install Now**
5. Click **Activate** after installation completes
6. Go to **YAML Custom Fields** in the admin menu to configure your schemas

= Requirements =

* WordPress 5.0 or higher
* PHP 8.1 or higher
* The plugin includes all necessary dependencies

== Frequently Asked Questions ==

= What is YAML frontmatter? =

YAML frontmatter is a structured way to define metadata for content. It's commonly used in static site generators and headless CMS systems. YAML Custom Fields brings this approach to WordPress themes.

= How is this different from ACF? =

While ACF is a comprehensive custom fields solution, YAML Custom Fields focuses on YAML-based schemas that are portable and version-controllable. It's ideal for developers who prefer code-first approaches and want simpler, more predictable data structures.

= Can I use this with my existing theme? =

Yes! YAML Custom Fields works with any WordPress theme. You define schemas for your templates and use simple PHP functions to retrieve the data in your template files.

= Does this work with Gutenberg? =

Yes, YAML Custom Fields is compatible with both the Classic and Block (Gutenberg) editors. The custom fields appear below the editor regardless of which editor you're using.

= What happens to my data if I deactivate the plugin? =

Your data remains in the database. Only when you **delete** the plugin (not just deactivate) will it clean up all settings, schemas, and custom field data.

= Can I use this for WooCommerce products? =

Currently, YAML Custom Fields supports pages and posts only. Support for custom post types including WooCommerce products may be added in future versions.

= How do I report bugs or request features? =

Please visit the [GitHub repository](https://github.com/maliMirkec/yaml-custom-fields) to report issues or request features.

== Screenshots ==

1. Main YAML Custom Fields admin page showing page templates and template partials with enable/disable toggles
2. Schema editor for main page templates with YAML syntax for defining custom fields
3. Schema editor for partial templates (headers, footers, etc.)
4. Partial data editor for managing global content in template partials
5. Data Validation page for reviewing imported content
6. Data Objects page for managing structured, reusable data types
7. Export/Import page with consolidated export/import functionality for settings, page data, and data objects
8. Documentation page with comprehensive guides and examples

== Changelog ==

= 1.2.1 =
* **FIX: Export/Import** - Template global schemas and data now properly exported and imported
* **FIX: Page Data Export** - Schema is now included in page data exports (form-based and AJAX)
* **FIX: Page Data Import** - Now correctly handles both single-post and multi-post export formats
* **NEW: Template Global Readonly Display** - Template-global-only fields now display as readonly in post editor
* **NEW: Auto-fallback for Template Global Fields** - `ycf_get_field()` now automatically retrieves template global data
* Fixed browser autocomplete issues with template global form fields

= 1.2.0 =
* **NEW: Template Global Fields** - Define shared default values for all posts using the same template
* **NEW: Per-field global/local toggle** - Each field can independently use template global or page-specific data
* **NEW: Dual-field interface** - Visual side-by-side comparison of template global and page-specific values
* **NEW: Auto-merge data hierarchy** - Intelligent data priority system (page > template global > site global)
* Enhanced post editor UI with clear visual indicators for global vs local data
* Improved field rendering system with unique IDs for dual fields
* Added per-field preferences storage for granular control
* Better reset functionality that preserves global data
* Enhanced documentation with Template Global Fields guide
* Improved admin interface organization for template management

= 1.1.0 =
* Improved code quality and WordPress Coding Standards compliance
* Consolidated Export/Import functionality into single admin page
* Renamed "Export Page Data" to "Export/Import" for clarity
* Reorganized admin menu structure (Export/Import now positioned above Documentation)
* Enhanced database query performance with optimized caching strategy
* Implemented post tracking system for efficient cache management
* Improved input sanitization using filter_input() throughout the plugin
* Enhanced output escaping for better security
* Added production-safe logging system with WordPress hooks
* Better file upload validation and error handling
* Removed all phpcs:ignore suppressions in favor of proper WordPress coding practices
* Added phpcs.xml.dist configuration file for consistent code standards

= 1.0.0 =
* Initial release
* Support for 15+ field types
* Template and partial support
* ACF-like template functions with context_data parameter for block fields
* Taxonomy field type for categories, tags, and custom taxonomies (single/multiple selection)
* Post Type field type for selecting registered WordPress post types
* Data Objects feature for managing structured, reusable data (universities, companies, etc.)
* Enhanced helper functions: ycf_get_field(), ycf_get_image(), ycf_get_file(), ycf_get_term(), ycf_get_post_type(), ycf_get_data_object(), ycf_get_data_objects()
* Block/repeater functionality with context-aware field access
* WordPress media integration
* Administrator-only access
* Clean uninstall
* Clear buttons for image and file fields
* Reset All Data button for clearing all custom fields
* Confirmation alerts for destructive actions
* Copy snippet buttons for all field types with complete function signatures

== Upgrade Notice ==

= 1.1.0 =
Code quality improvements, consolidated Export/Import page, and enhanced security. All functionality remains backwards compatible.

= 1.0.0 =
Initial release of YAML Custom Fields.

== Developer Documentation ==

= Template Functions =

**Get a single field value:**

`$value = ycf_get_field('field_name');
$value = ycf_get_field('field_name', 123); // Specific post ID
$value = ycf_get_field('logo', 'partial:header.php'); // From partial
$value = ycf_get_field('title', null, $block); // From block context`

**Get image field with details:**

`$image = ycf_get_image('field_name', null, 'full');
$image = ycf_get_image('field_name', 123, 'thumbnail'); // Specific post ID
$image = ycf_get_image('icon', null, 'medium', $block); // From block context

// Returns: array('id', 'url', 'alt', 'title', 'caption', 'description', 'width', 'height')`

**Get file field with details:**

`$file = ycf_get_file('field_name', null);
$file = ycf_get_file('field_name', 123); // Specific post ID
$file = ycf_get_file('document', null, $block); // From block context

// Returns: array('id', 'url', 'path', 'filename', 'filesize', 'mime_type', 'title')`

**Get taxonomy field (term or terms):**

`$term = ycf_get_term('field_name', null);
$term = ycf_get_term('field_name', 123); // Specific post ID
$term = ycf_get_term('category', null, $block); // From block context

// Returns: WP_Term object or array of WP_Term objects (for multiple selection)`

**Get post type field:**

`$post_type = ycf_get_post_type('field_name', null);
$post_type = ycf_get_post_type('field_name', 123); // Specific post ID
$post_type = ycf_get_post_type('content_type', null, $block); // From block context

// Returns: WP_Post_Type object or null`

**Get data object field:**

`$university = ycf_get_data_object('field_name', null);
$university = ycf_get_data_object('field_name', 123); // Specific post ID
$university = ycf_get_data_object('university', null, $block); // From block context

// Returns: Array with data object entry fields or null`

**Get all entries of a data object type:**

`$all_universities = ycf_get_data_objects('universities');
foreach ($all_universities as $entry_id => $university) {
    echo esc_html($university['name']);
}

// Returns: Array of all entries for the specified data object type`

**Get all fields:**

`$fields = ycf_get_fields();
$fields = ycf_get_fields(123); // Specific post ID
$fields = ycf_get_fields('partial:footer.php'); // From partial`

**Check if field exists:**

`if (ycf_has_field('hero_title')) {
    echo ycf_get_field('hero_title');
}`

**Working with Block fields:**

`$blocks = ycf_get_field('features');

if (!empty($blocks)) {
    foreach ($blocks as $block) {
        // Access nested fields using context_data parameter
        $title = ycf_get_field('title', null, $block);
        $icon = ycf_get_image('icon', null, 'thumbnail', $block);
        $category = ycf_get_term('category', null, $block);

        echo '<h3>' . esc_html($title) . '</h3>';
        if ($icon) {
            echo '<img src="' . esc_url($icon['url']) . '">';
        }
        if ($category) {
            echo '<span>' . esc_html($category->name) . '</span>';
        }
    }
}`

= Sample YAML Schema =

`fields:
  - name: hero_title
    label: Hero Title
    type: string
    required: true
    options:
      maxlength: 100
  - name: hero_image
    label: Hero Image
    type: image
  - name: category
    label: Category
    type: taxonomy
    options:
      taxonomy: category
  - name: tags
    label: Tags
    type: taxonomy
    multiple: true
    options:
      taxonomy: post_tag
  - name: content_type
    label: Content Type
    type: post_type
  - name: university
    label: University
    type: data_object
    options:
      object_type: universities
  - name: features
    label: Features
    type: block
    list: true
    blockKey: type
    blocks:
      - name: feature
        label: Feature Block
        fields:
          - name: title
            label: Title
            type: string
          - name: icon
            label: Icon
            type: image
          - name: description
            label: Description
            type: text`

= Working with Partials =

For custom partials, add the @ycf marker in the file header:

`<?php
/**
 * Custom Navigation Partial
 * @ycf
 */`

Then click "Refresh Template List" in the YAML Custom Fields admin page.

= Template Global Fields =

Template Global Fields allow you to define default values that are shared across all posts using the same template, while still allowing individual posts to override specific fields.

**Setting up Template Global:**

1. Go to **YAML Custom Fields** admin page
2. Enable YAML for your template (e.g., page.php)
3. Click **Add Template Global** to define the template global schema
4. Define fields that should have shared default values
5. Click **Manage Template Global Data** to set the default values

**Using Template Global in Posts:**

When editing a post that uses a template with Template Global fields, you'll see a dual-field interface for each field:

* **Template Global (All Pages)** - Read-only display showing the default value (with Edit link)
* **Page-Specific Value** - Editable field for this post only
* **Checkbox** - "Use template global for this field" - Toggle per field

**Benefits:**

* **Consistency:** Set default values once, use across all posts
* **Flexibility:** Override any field on any post individually
* **Clarity:** See both global and local values side-by-side
* **Efficiency:** Update template global to affect all posts at once

**Data Priority (when using template functions):**

When a field uses template global, `ycf_get_field()` returns data in this priority order:

1. Page-specific value (if "use template global" is unchecked)
2. Template global value (if "use template global" is checked)
3. Site-wide global value (if template has site-wide global enabled)
4. null (if no value exists)

= Data Storage =

* **Page/Post data:** Stored in post meta with key `_yaml_cf_data`
* **Template Global preferences:** Stored in post meta with key `_yaml_cf_use_template_global_fields` (per-field array)
* **Template Global schemas:** Stored in options table with key `yaml_cf_template_global_schemas`
* **Template Global data:** Stored in options table with key `yaml_cf_template_global_data`
* **Site-wide global schema:** Stored in options table with key `yaml_cf_global_schema`
* **Site-wide global data:** Stored in options table with key `yaml_cf_global_data`
* **Partial data:** Stored in options table with key `yaml_cf_partial_data`
* **Schemas:** Stored in options table with key `yaml_cf_schemas`
* **Data Object Types:** Stored in options table with key `yaml_cf_data_object_types`
* **Data Object Entries:** Stored in options table with keys `yaml_cf_data_object_entries_{type_slug}`

== Privacy Policy ==

YAML Custom Fields does not collect, store, or transmit any user data outside of your WordPress installation. All data is stored locally in your WordPress database.

== Third-Party Libraries ==

This plugin includes the following third-party libraries:

* **Symfony YAML Component** (v7.3) - Licensed under MIT License (GPL-compatible)
  - Homepage: https://symfony.com/components/Yaml
  - License: https://github.com/symfony/yaml/blob/7.3/LICENSE

== Credits ==

* Author: [Silvestar Bistrovic](https://www.silvestar.codes)

== Support ==

For documentation, examples, and support, visit:
* [Plugin Documentation](https://github.com/maliMirkec/yaml-custom-fields)
* [Report Issues](https://github.com/maliMirkec/yaml-custom-fields/issues)
