=== YAML Custom Fields ===
Contributors: starbist
Tags: custom fields, fields, content, yaml, schema
Requires at least: 5.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Custom fields for pages, posts and every part of the site. Set the fields once; writers fill them in.

== Description ==

YAML Custom Fields adds boxes to pages, like "Headline", "Team photo" or "Event date". Writers fill them in, and the website shows that content in the right place.

The fields of each page template are set in a short list, called a schema. It takes a few lines per field.

= What it does =

* Gives each page template its own fields.
* Holds the content of the header, footer and other site parts, filled in once.
* Adds shared fields, the same on every page with one template.
* Keeps site details, like a phone number or social links, used across the whole website.
* Builds pages from sections that writers add, remove and reorder.
* Keeps reusable lists, like team members or partners, for any page to pick from.
* Exports and imports the setup and content, for backups or a move to another website.
* Finds imported posts with missing images or files.

= Field types =

* One line of text
* A few lines of text
* Text with formatting (bold, links, lists)
* Code, like an embed or a form
* Yes or no (tick box)
* Number
* Date, with or without time
* Dropdown
* Category or tag
* Kind of content (posts, pages and more)
* Item from a reusable list
* Image, or a gallery of several images
* File, or several files
* Group of fields
* Sections
* Info box with a note for writers

= How it works =

1. Go to **YAML Custom Fields** and switch on a template.
2. Click **Create schema** and list the fields:

`fields:
  - name: headline
    label: Headline
    type: string
  - name: photo
    label: Photo
    type: image`

3. Open a page that uses the template. The fields sit in a "Custom fields" panel, below the content in the block editor and below the title in the classic editor.
4. The theme shows each field with one line:

`<h1><?php echo esc_html( ycf_get_field( 'headline' ) ); ?></h1>`

The Documentation page inside the plugin explains every step in plain words.

== Installation ==

1. In the WordPress admin, go to **Plugins → Add New**.
2. Search for "YAML Custom Fields".
3. Click **Install Now**, then **Activate**.
4. Go to **YAML Custom Fields** in the menu on the left.

For a ZIP file, use **Plugins → Add New → Upload Plugin** instead.

= Requirements =

* WordPress 5.0 or newer
* PHP 8.1 or newer

Everything else the plugin needs is included.

== Frequently Asked Questions ==

= What is a schema? =

A short list of the fields a page gets. Each field has a name, a label and a type. It is written in YAML, a simple text format with one setting per line.

= Is coding needed? =

Not for writers, who only fill in fields. Setting up the fields and showing them on the website needs a few lines in the theme. The **Copy code** button next to each field copies the exact line.

= Does it work with any theme? =

Yes, classic themes and block themes alike. The theme decides where each field shows up. A child theme is scanned together with the theme it builds on, so the parent's templates are listed too.

= Does it work with the block editor? =

Yes. The fields appear in a "Custom fields" panel, below the content in the block editor and below the title in the classic editor. The Classic Editor plugin is not needed.

= Which content types are supported? =

Posts, pages and other public content types, such as an "Events" type added by a theme or plugin.

= Who can change the setup? =

Only administrators change schemas and settings. Writers fill in fields on the posts they are allowed to edit.

= What happens when the plugin is switched off? =

The content stays. It is only removed when the plugin is **deleted**.

= How does content move to another website? =

**YAML Custom Fields → Export & Import** saves it to a file. Images and files are not inside that file, so the media library has to be copied as well. **Content Check** then finds anything missing.

= Where are problems reported? =

On [GitHub](https://github.com/maliMirkec/yaml-custom-fields/issues). Helpful details: what was done, what was expected and what happened.

== Screenshots ==

1. The YAML Custom Fields page, with a switch per template and the status of its fields.
2. The schema editor for a template.
3. The schema editor for a site part, like the header.
4. The content of a site part.
5. The Documentation page, written in plain words.

== Changelog ==

= 2.0.0 =
* **NEW: Works in the Block Editor** - The fields are now a real meta box, so they appear in the block editor as well as the classic one. The Classic Editor plugin is no longer needed
* **NEW: Galleries** - Image fields support `list: true`: pick many images in one trip to the media library, see them as a grid of thumbnails, and reorder them by dragging or with the keyboard
* **NEW: Search Instead of Endless Lists** - A taxonomy or collection field with more than 50 choices becomes a search box, so a page with thousands of tags or collection items no longer carries every one of them. The cut-off can be changed with the `yaml_cf_picker_threshold` filter
* **NEW: Sections Fold Away** - Each repeating section can be collapsed, shows the text of its first field in its header instead of repeating the block name, and a "Collapse all" button folds them all at once
* **NEW: yaml_cf_field_value Filter** - The filter the documentation has always described now actually exists, and runs for every route a value can arrive by
* **NEW: Warning When a Field Loses Its Content** - Renaming or deleting a field in a schema now says which fields are affected, and that their content is still saved
* **IMPROVED: Faster Field Reads** - Resolving a post's template no longer touches the filesystem on every `ycf_get_field()` call, which mattered most in archive loops
* **IMPROVED: Added Sections Render on the Server** - "Add section" now asks the server for the markup instead of rebuilding every field type in JavaScript. A section added on the page is now identical to one that came with it, which is the class of bug behind the 1.2.3 fixes
* **IMPROVED: Much Smaller Editor Pages** - Every taxonomy term and collection item used to be written into the page for the old JavaScript field builder. That is gone, so a post editor on a site with thousands of tags or collection items loads a fraction of the markup
* **FIXED: Unreadable Active Filter Button** - The selected filter on the Content Check page was purple text on a purple background, and now reads white. It also reports its state to screen readers
* **FIXED: Nested Field Names in the New Renderers** - The unfinished renderers in `src/Rendering` ignored their nesting prefix and now refuse to render rather than silently dropping a value
* **FIXED: ycf_get_field() and ycf_get_fields() Could Disagree** - The two functions worked out values by different rules, so the same field on the same page could come back with two different values. In particular `ycf_get_fields()` ignored the "Use the shared value on this page" ticks entirely. Both now read from one place, in the order the documentation describes
* **FIXED: A Typo in a Field Type Passed Unnoticed** - `type: strng` used to save with "The schema looks good.", render nothing at all, and then drop the field's content the next time the page was saved. Types are now checked when the schema is saved, inside groups and sections too, and the message suggests the type that was probably meant. Content belonging to a field with an unknown type is left alone rather than dropped
* **IMPROVED: Fewer Repeated Lookups Per Page** - A page's field values are worked out once per request instead of once per `ycf_get_field()` call, which re-read the same options for every field on the page
* **FIXED: Child Themes Showed No Templates** - The theme scan asked for the active theme's files without asking for the parent's, so a child theme that overrides only style.css reported an empty template list although the parent had page.php and single.php. A partial belonging to the child was also stored under its full path instead of its name
* **NEW: Block Themes Are Supported** - A block theme's templates (`page`, `single`, `single-event` and templates made in the Site Editor) are now listed and can hold fields, and templates that show a list of posts are sorted into site parts. Before this, a block theme showed an empty table
* **IMPROVED: An Empty Template List Says Why** - Instead of "The active theme has no page or post templates yet", the page now names the theme it looked in and, for a block theme, says that Site Editor templates appear once saved
* **IMPROVED: Plugin Data No Longer Loads on Every Page View** - Schemas, shared content and site details were stored as autoloaded options, so WordPress read all of them into memory on every request, front end included. They are now read only when something asks for them, and an existing site is corrected once, automatically
* **IMPROVED: No More Copy of the Schema in Every Post** - Every save wrote the whole parsed schema into the post, although it can be worked out from the post's template. It is worked out now, so the database stays smaller and editing a schema takes effect at once instead of when each post is next saved
* **IMPROVED: Editor Scripts Stay Off Screens That Have No Fields** - The post editor no longer loads this plugin's scripts or the media library when the post's template has no schema
* **NEW: Choose What Happens to the Content if the Plugin Is Deleted** - Deleting the plugin removes the content filled into every field, which cannot be undone. The YAML Custom Fields page now explains this and offers to keep the content
* **IMPROVED: Onboarding Says the Theme Needs a Line of Code** - "The theme shows what was typed" read as though nothing else was needed. The steps now include showing a field in the theme, with the line to copy
* **FIXED: Two Fields Could Share a Name** - A schema with the same name twice saved happily, and one field then quietly replaced the other in the stored data. Duplicate names are now refused, as is the name the plugin uses for its own bookkeeping
* **SECURITY: Administrator Actions Have Their Own Token** - The token in a writer's post editor was the same one that saved schemas and imported bundles. Administrator-only actions now use a separate token that only administrators are given
* **SECURITY: The Taxonomy Picker Checks Permissions** - It confirmed only that a taxonomy existed, so a writer could list the terms of a private taxonomy belonging to another plugin. It now checks that the taxonomy is visible and that the user may assign its terms
* **SECURITY: Writers Cannot Ask for Administrator-Only Schemas** - The "Add section" request accepted site details, shared field and collection schemas from the post editor. Those now need administrator permissions
* **SECURITY: Stricter CSS Cleaning** - Cleaning ran once per pattern, so a value could close back up into the very thing that was removed. It now repeats until nothing changes, and strips comments first
* **SECURITY: A Size Limit on Schemas** - A schema is a short list of fields, and is now refused above 256 KB rather than being handed to the parser whole
* **FIXED: Warnings in the Editor from Shorthand in Shared Fields** - A shared fields schema written with `- info:` or `- section:` shorthand filled the post editor with "Undefined array key" warnings, because only the page's own schema was expanded before being drawn. Every schema is expanded the same way now, wherever it is used
* **FIXED: Shorthand Inside a Repeating Section** - The same problem inside a section's own fields, where a field with no label of its own also went unhandled

= 1.2.9 =
* **UPDATED: WordPress 7.0 Compatibility** - Confirmed compatibility and updated "Tested up to"
* **FIX: Import JSON Parsing** - Fixed "Invalid JSON: Syntax error" when importing settings, page data, or data objects whose field values contain HTML/rich-text content, by removing an errant sanitize_textarea_field() call that corrupted the JSON payload before parsing

= 1.2.8 =
* **NEW: Multiple File Uploads** - File fields now support `list: true` to attach multiple files, with a dedicated admin UI for adding and removing files
* **UPDATED: ycf_get_file() for Lists** - When `list: true` is set, `ycf_get_file()` returns an array of file-data arrays instead of a single one
* **IMPROVED: Attachment Validation** - Validation now checks each attachment ID in file/image list fields individually

= 1.2.7 =
* **FIX: Data Object Fields in Blocks/Objects** - Fixed data_object fields not rendering correctly when nested inside block or object fields by adding recursive data object collection in AssetManager and DataObjectController
* **FIX: Attachment Data Validation** - Fixed attachment validation logic to correctly handle the case where a schema exists but has no image/file fields, preventing numeric values from being incorrectly cleared
* **FIX: Import Schema Parsing** - Fixed data object import to parse YAML schema before validating attachment data, ensuring attachment IDs are preserved correctly during import

= 1.2.6 =
* **FIX: Code Field HTML Preservation** - Code fields now use base64 encoding with a marker prefix to preserve HTML/JavaScript/CSS code exactly as entered, preventing WordPress sanitization from stripping tags
* **FIX: Code Field Re-save Protection** - Already-encoded code fields are now preserved on re-save even if schema detection fails, preventing data loss on page refresh
* **NEW: Auto-decode on Frontend** - Code field values are automatically decoded when retrieved via yaml_cf_get_field() and related functions

= 1.2.5 =
* **FIX: Code Field Sanitization** - Changed filter from FILTER_SANITIZE_FULL_SPECIAL_CHARS to FILTER_UNSAFE_RAW in postRaw() to preserve raw data for schema-aware sanitization, fixing HTML entities being encoded before code fields could be properly identified

= 1.2.4 =
* **REFACTOR: Assets Folder Structure** - Reorganized assets for WordPress.org compliance
* **IMPROVED: File Organization** - Separated admin assets (CSS/JS) into 'admin-assets' folder
* **IMPROVED: Plugin Assets** - Moved WordPress.org assets (icons, banners, screenshots) to 'assets' folder
* **UPDATED: File References** - Updated all asset paths in templates and AssetManager

= 1.2.3 =
* **FIX: Dynamic Block Fields** - Fixed taxonomy, post_type, and data_object fields not rendering correctly in dynamically added blocks
* **NEW: JavaScript Field Handlers** - Added missing post_type and data_object field support to JavaScript block rendering
* **IMPROVED: Field Type Parity** - All field types now work identically in both static (PHP) and dynamic (JavaScript) rendering
* **IMPROVED: Data Localization** - Enhanced controllers to pass taxonomy terms, post types, and data objects to JavaScript

= 1.2.2 =
* **UPDATED: Symfony Libraries** - Updated Symfony YAML Component to 6.4 and Deprecation Contracts to 3.6.0 for PHP 8.1+ compatibility
* **SECURITY: Nonce Verification** - Fixed GET parameter access to verify nonces before accessing other parameters
* **IMPROVED: Script Enqueuing** - Converted all inline scripts to proper wp_enqueue_script usage with wp_localize_script
* **REQUIREMENT: PHP 8.1+** - Minimum PHP version requirement (Symfony 6.4 LTS supports PHP 8.1+)

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

= 2.0.0 =
Read this before updating. Two things change what the website shows. Pages where "Use the shared value on this page" is ticked now really do show the shared value through `ycf_get_fields()`, which used to ignore the tick. Where a page field and a site detail share a name, the page's own value now wins. Schemas with a mistake in them - an unknown field type, or the same field name twice - are refused when saved from now on; schemas already saved keep working, and the editor says what is wrong. The update tidies the database once, by itself, the first time an administrator opens a page: it stops the plugin's options being read on every page view, removes a copy of the schema it used to keep in every post, and repairs site parts of a child theme that were filed under their full path. Back up before updating, as with any update.

= 1.2.6 =
Important fix for code fields. HTML/JavaScript/CSS code is now properly preserved using base64 encoding. Re-save any code fields that previously had content stripped.

= 1.2.5 =
Bug fix for code field sanitization. Code fields now preserve raw content correctly without premature HTML entity encoding.

= 1.2.4 =
Asset folder reorganization for better WordPress.org compliance. All functionality remains unchanged.

= 1.2.3 =
Bug fix for dynamic block fields. Taxonomy, post type, and data object fields now render correctly when adding blocks dynamically.

= 1.2.2 =
Important security update with nonce verification fixes. Updated to PHP 8.1+ requirement with Symfony 6.4 compatibility.

= 1.2.1 =
Fixes for export/import functionality and template global field handling. Recommended update for better data portability.

= 1.2.0 =
Major feature update introducing Template Global Fields for shared default values across posts using the same template.

= 1.1.0 =
Code quality improvements, consolidated Export/Import page, and enhanced security. All functionality remains backwards compatible.

= 1.0.0 =
Initial release of YAML Custom Fields.

== Developer Documentation ==

The full guide is on the plugin's Documentation page. A short summary follows.

= Functions =

`ycf_get_field( 'name' )                 // One field
ycf_get_field( 'name', 42 )             // One field of another post
ycf_get_field( 'name', 'partial:header.php' )  // A site part
ycf_get_field( 'title', null, $section ) // A field inside a section
ycf_get_fields()                        // All fields
ycf_has_field( 'name' )                 // Does the field have a value?
ycf_get_image( 'name', null, 'large' )  // Image: id, url, alt, title, caption, width, height
ycf_get_file( 'name' )                  // File: id, url, path, filename, filesize, mime_type, title
ycf_get_term( 'name' )                  // Category or tag (WP_Term)
ycf_get_post_type( 'name' )             // Content type (WP_Post_Type)
ycf_get_data_object( 'name' )           // Item from a reusable list
ycf_get_data_objects( 'short-name' )    // Every item in a reusable list
ycf_get_global_field( 'name' )          // One of the site details
ycf_get_global_fields()                 // All site details`

Each function also has a long name that starts with `yaml_cf_`.

Output is always escaped: `esc_html()` for text, `esc_url()` for links, `wp_kses_post()` for formatted text. Code fields print as they are.

= Where data is stored =

* Page fields: post meta `_yaml_cf_data`
* Schemas: option `yaml_cf_schemas`
* Site parts: option `yaml_cf_partial_data`
* Shared fields: options `yaml_cf_template_global_schemas` and `yaml_cf_template_global_data`
* Site details: options `yaml_cf_global_schema` and `yaml_cf_global_data`
* Reusable lists: option `yaml_cf_data_object_types`, items in `yaml_cf_data_object_entries_{short-name}`

== Privacy Policy ==

YAML Custom Fields does not collect or send any data. Everything stays in the site's own WordPress database.

== Third-Party Libraries ==

This plugin includes the following third-party libraries:

* **Symfony YAML Component** (v6.4) - Licensed under MIT License (GPL-compatible)
  - Homepage: https://symfony.com/components/Yaml
  - License: https://github.com/symfony/yaml/blob/7.3/LICENSE

== Credits ==

* Developed by [Studio Cita](https://www.cita.hr)
* Lead developer: [Silvestar Bistrović](https://www.silvestar.codes)

== Support ==

Help and examples:
* [Plugin Documentation](https://github.com/maliMirkec/yaml-custom-fields)
* [Report Issues](https://github.com/maliMirkec/yaml-custom-fields/issues)
