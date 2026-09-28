=== YAML Custom Fields ===
Contributors: starbist
Tags: custom fields, fields, content, yaml, schema
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Custom fields for pages, posts and every part of the site. You set the fields once. Writers fill them in.

== Description ==

YAML Custom Fields adds boxes to fill in on pages, like "Headline", "Team photo" or "Event date". Writers fill them in. The website shows them in the right place.

You list the fields of each page template in a short text, called a schema. Each field takes a few lines.

= What it does =

* Gives each page template its own fields.
* Adds fields to the header, the footer and other site parts.
* Adds shared fields: one value for every page with the same template.
* Keeps site details, like a phone number, for the whole site.
* Lets writers build pages from sections they add, remove and reorder.
* Keeps collections, like team members, for any page to pick from.
* Exports and imports fields and content, for backups or moving to another site.
* Finds missing images and files, and content left behind by removed fields.
* Shows fields in block themes with a shortcode.
* Makes fields available over the REST API.

= Field types =

* One line of text
* Several lines of text
* Text with formatting (bold, links, lists)
* Code, like an embed or a form
* Yes or no (tick box)
* Number
* Date, with or without time
* Dropdown
* Category or tag
* Kind of content (posts, pages and more)
* Item from a collection
* Image, or a gallery
* File, or several files
* Group of fields
* Sections
* Info box: a note for writers
* Section heading: a title with a gap above it

= How it works =

1. Go to **YAML CF** and switch on **Custom fields** for a template.
2. Click **Create schema** and list the fields:

`fields:
  - name: headline
    label: Headline
    type: string
  - name: photo
    label: Photo
    type: image`

3. Open a page that uses the template. The fields are in a "Custom fields" box. In the block editor it's below the content. In the classic editor it's below the title.
4. Show each field in the theme with one line:

`<h1><?php echo esc_html( ycf_get_field( 'headline' ) ); ?></h1>`

The **Documentation** page in the plugin explains every step in plain words.

== Installation ==

1. In the WordPress admin, go to **Plugins → Add New**.
2. Search for "YAML Custom Fields".
3. Click **Install Now**, then **Activate**.
4. Go to **YAML CF** in the menu on the left.

For a ZIP file, use **Plugins → Add New → Upload Plugin**.

= Requirements =

* WordPress 6.4 or newer
* PHP 8.1 or newer

Everything else is included.

== Frequently Asked Questions ==

= What is a schema? =

A short list of the fields a page gets. Each field has a name, a label and a type. It's written in YAML, a simple text format with one setting per line.

= Do I need to code? =

Writers don't. They only fill in fields. To show fields on the website, the theme needs one line per field. The **Copy code** button next to each field copies that line for you.

= Does it work with any theme? =

Yes, classic and block themes. A child theme is scanned together with its parent, so the parent's templates are listed too.

Block themes have no template files to edit. Use the `[ycf field="name"]` shortcode in a Shortcode block.

= Does it work with the block editor? =

Yes. The fields are in a "Custom fields" box below the content. You don't need the Classic Editor plugin.

= Is there an API? =

Yes, the WordPress REST API. Every page and post has a `yaml_cf` object. There are also routes for site details and collections. The Documentation page has the details.

= Which content types are supported? =

Posts, pages and other public content types, like an "Events" type from a theme or plugin.

= Who can change the setup? =

Only administrators change schemas and settings. Writers fill in fields on posts they may edit.

= What happens when I turn the plugin off? =

Nothing is lost. The content stays.

= What happens when I delete the plugin? =

All its data is removed: schemas, site details, collections and page content. To keep the content, tick **Keep the content if the plugin is deleted** under **YAML CF → Settings** first.

= How do I move content to another site? =

Use **YAML CF → Export & Import** to save it to a file. Images and files aren't in that file, so copy the media library too. Then **Content Check** finds anything missing.

= Where do I report problems? =

On [GitHub](https://github.com/maliMirkec/yaml-custom-fields/issues). Please say what you did, what you expected and what happened.

== Screenshots ==

1. The YAML CF screen, with a switch per template and the status of its fields.
2. The schema editor for a template.
3. The schema editor for a site part, like the header.
4. The content of a site part.
5. The Documentation page, written in plain words.

== Changelog ==

= 2.0.0 =

**Changed**

* **Needs WordPress 6.4 or newer.** Older versions weren't tested. WordPress won't offer this update to older sites.
* **The shared value tick always wins.** When "Use the shared value on this page" is ticked, `ycf_get_fields()` now shows the shared value too. Before, it ignored the tick.
* **A page's own value beats a site detail with the same name.**
* **`ycf_has_field()` means "has a value".** An empty text or an empty list is no value. An unticked box or a zero still is.
* **Mistakes in a schema are refused when saved.** Unknown types (with a "Did you mean…?" hint), the same name twice, names with square brackets, and names the plugin uses itself. Schemas already saved keep working.

**New**

* **Works in the block editor.** The fields are a real box now. No Classic Editor plugin needed.
* **Block themes.** Their templates are listed and can have fields.
* **`[ycf]` shortcode.** Show a field without editing the theme. Works in a Shortcode block, a widget or the content.
* **REST API.** Every page and post has a `yaml_cf` object. There are routes for site details and collections. You can save through it too, with the same checks as the editor.
* **Settings screen.** **YAML CF → Settings** has three choices: page caches, REST API access, and what happens when the plugin is deleted.
* **Keep content when the plugin is deleted.** A choice on the Settings screen.
* **Galleries.** Image fields take `list: true`. Pick many images at once. Reorder by dragging or with the keyboard.
* **Search box for big lists.** A category or collection field with more than 50 choices becomes a search box. Change the limit with the `yaml_cf_picker_threshold` filter.
* **Sections fold away.** Each section shows its first field as its title. A small **Collapse all** button folds them all.
* **Save and add another.** For collection items. Saves and opens an empty item.
* **Content Check finds left-behind content.** Values of removed fields are listed under "Values with no field", with a button to remove them.
* **Warning when a field loses its place.** Removing or renaming a field in any schema says which fields are affected.
* **`yaml_cf_field_value` filter.** Change any value before the theme gets it.
* **Use site details on these pages.** The switch is back on the main screen.

**Better**

* **One kind of message.** Messages look the same everywhere, with the same icons. Nothing pops up in a corner. Nothing disappears on its own. Results of a switch or button show right next to it, so the page doesn't jump.
* **Accessible messages.** Screen readers read each message once, with its kind ("Error:", "Warning:"). Errors get keyboard focus. Pickers say how many results they found.
* **Icons.** Headings, columns and buttons have icons. The same thing always has the same icon.
* **One field per row** in every form.
* **The Custom fields box** can be folded in the classic editor too, and remembers it. The "not saved yet" line works in the block editor too.
* **Main screen.** The table doesn't jump when you switch templates on and off. It works on small screens.
* **Faster.** Plugin data isn't loaded on every page view. Values are worked out once per page. Editor scripts only load where there are fields. Editor pages are much smaller.
* **Collections scale.** Each item has its own database row. The item list has pages of 50.
* **No copy of the schema in every post.** Schema changes take effect at once.
* **"Add section" is drawn by the server,** so a new section is the same as one that was already there.
* **Clearer texts.** Onboarding says the theme needs a line of code. An empty template list says why. Permission messages say what's really needed.

**Fixed**

* Removing a field from a schema no longer deletes its content. Put the name back and the content comes back. The same on every screen.
* A tick box can be unticked again.
* Importing no longer empties fields the file doesn't mention.
* A template with only shared fields now shows the Custom fields box.
* Child themes showed no templates.
* Themes built on `singular.php` (like Twenty Twenty) couldn't get page fields.
* `ycf_get_field()` and `ycf_get_fields()` could give different values for the same field.
* A typo in a field type showed nothing and then lost the field's content.
* Two fields could share a name, and one replaced the other.
* Shortcuts like `- info:` and `- section:` caused warnings in shared fields and inside sections.
* Fields could be saved into another post when a plugin saved a second post at the same time.
* A form left open too long lost your changes without a word. Now it says so.
* Collection labels didn't update after a schema change.
* A collection field could show an item from another collection.
* Big collection imports could lose their lock.
* Imports kept image IDs from another site in site details, site parts and shared content.
* `ycf_get_image()` with a custom size like `[300, 200]` crashed the page.
* Fields named with digits only (like `2024`) could be renamed by some saves.
* The import preview couldn't tick pages from older export files.
* Content Check offered to delete fields still in use.
* "Empty all fields" left galleries, file lists and pickers filled.
* Import messages didn't show in the block editor.
* The active filter button in Content Check was unreadable.

**Security**

* Admin-only actions use their own security token.
* Writers can only get their own post's schema.
* The category picker checks the user may use that taxonomy.
* Site details no longer leak to visitors through a page's REST data.
* Picker labels are inserted as text, not HTML.
* CSS cleaning repeats until nothing is left to remove.
* Schemas over 256 KB are refused.

**Removed**

* Nine old background requests nothing used anymore.

**Updated**

* Tested with WordPress 7.1.

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
Back up first. Needs WordPress 6.4+. The shared value tick now wins in ycf_get_fields(), and a page value beats a site detail with the same name. Schemas with mistakes are refused. The database is tidied once, by itself.

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

The full guide is on the plugin's Documentation page. Here's the short version.

= Functions =

`ycf_get_field( 'name' )                        // One field
ycf_get_field( 'name', 42 )                    // A field of another post
ycf_get_field( 'name', 'partial:header.php' )  // A site part
ycf_get_field( 'title', null, $section )       // A field inside a section
ycf_get_fields()                               // All fields
ycf_has_field( 'name' )                        // Does it have a value?
ycf_get_image( 'name', null, 'large' )         // Image: id, url, alt, title, caption, width, height
ycf_get_file( 'name' )                         // File: id, url, path, filename, filesize, mime_type, title
ycf_get_term( 'name' )                         // Category or tag (WP_Term)
ycf_get_post_type( 'name' )                    // Content type (WP_Post_Type)
ycf_get_data_object( 'name' )                  // An item from a collection
ycf_get_data_objects( 'short-name' )           // Every item in a collection
ycf_get_global_field( 'name' )                 // One site detail
ycf_get_global_fields()                        // All site details`

Each function also has a long name starting with `yaml_cf_`.

Always escape what you print: `esc_html()` for text, `esc_url()` for links, `wp_kses_post()` for formatted text. Code fields print as they are.

= Shortcode =

`[ycf field="headline"]
[ycf field="hero" size="large"]
[ycf field="phone" source="site"]
[ycf field="subtitle" default="Coming soon"]`

= Where data is stored =

* Page fields: post meta `_yaml_cf_data`
* Schemas: option `yaml_cf_schemas`
* Site parts: option `yaml_cf_partial_data`
* Shared fields: options `yaml_cf_template_global_schemas` and `yaml_cf_template_global_data`
* Site details: options `yaml_cf_global_schema` and `yaml_cf_global_data`
* Collections: option `yaml_cf_data_object_types`. Each item has its own option, plus a small index per collection. Read them with `ycf_get_data_objects()`.

== Privacy Policy ==

YAML Custom Fields doesn't collect or send any data. Everything stays in your WordPress database.

== Third-Party Libraries ==

* **Symfony YAML** (6.4), MIT license (works with GPL)
  - Homepage: https://symfony.com/components/Yaml
  - License: https://github.com/symfony/yaml/blob/6.4/LICENSE

== Credits ==

* Made by [Studio Cita](https://www.cita.hr)
* Lead developer: [Silvestar Bistrović](https://www.silvestar.codes)

== Support ==

* [Plugin documentation](https://github.com/maliMirkec/yaml-custom-fields)
* [Report a problem](https://github.com/maliMirkec/yaml-custom-fields/issues)
