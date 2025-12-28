# YAML Custom Fields - Examples

This directory contains comprehensive examples demonstrating all features and field types available in the YAML Custom Fields WordPress plugin.

## What's Included

### 1. complete-schema-example.yaml
A comprehensive YAML schema showcasing **all 15+ field types** with:
- Inline comments explaining each field type
- All available options (min/max, required, multiple, validation, etc.)
- Nested objects and data object references
- Block/repeater fields with all possible inner field types
- Taxonomy and post type selectors
- Code fields with different languages

### 2. complete-template-example.php
A complete WordPress page template demonstrating:
- How to retrieve all field types using `ycf_*` functions
- Proper escaping for security
- Handling arrays, loops, and nested data
- Block field patterns with switch/case
- Data object retrieval
- WordPress helper function integration

---

## How to Use These Examples

### Using the Schema Example

1. **Navigate to YAML Custom Fields** in WordPress admin
2. **Enable YAML** for a page template (e.g., `page.php`)
3. **Click "Add Schema" or "Edit Schema"**
4. **Copy the contents** of `complete-schema-example.yaml`
5. **Paste into the schema editor**
6. **Save the schema**

**Important Prerequisites:**
- **Data Objects**: This schema references a "universities" data object type
  - Go to **YAML CF > Data Objects**
  - Create a new type called "universities"
  - Use the schema from `complete-schema-example.yaml` comments
  - Add some test entries

- **Custom Taxonomies**: The schema uses custom taxonomies:
  - `portfolio_cat` (Portfolio Category)
  - `faq_category` (FAQ Category)
  - Create these taxonomies in your theme or ensure they exist before using

### Using the Template Example

**Option 1: As a Page Template**
1. **Copy** `complete-template-example.php` to your active theme directory
2. **Rename** (optional) to match WordPress naming: `page-example.php`
3. **Edit a page** in WordPress
4. **Select Template** dropdown
5. **Choose** "YAML Custom Fields - Complete Example"
6. **Publish** and view the page

**Option 2: As a Reference**
- Use the code as a reference guide when building your own templates
- Copy specific sections for the field types you're using
- Adapt the patterns to your theme's structure

---

## Field Types Reference

The examples demonstrate all available field types:

1. **string** - Single-line text with min/max length
2. **text** - Multi-line textarea
3. **rich-text** - WordPress WYSIWYG editor
4. **code** - Code editor with syntax highlighting
5. **boolean** - Checkbox for true/false
6. **number** - Number input with min/max
7. **date** - Date picker with optional time
8. **select** - Dropdown with single/multiple selection
9. **taxonomy** - WordPress categories, tags, custom taxonomies
10. **post_type** - Dropdown to select registered post types
11. **data_object** - Reference to structured data objects
12. **image** - WordPress media uploader for images
13. **file** - WordPress media uploader for any file
14. **object** - Nested group of fields
15. **block** - Repeatable blocks for flexible page builders

---

## Template Functions Quick Reference

```php
// Get a field value
$value = ycf_get_field('field_name');

// Get image data with size
$image = ycf_get_image('image_field', null, 'medium');

// Get file data
$file = ycf_get_file('file_field');

// Get taxonomy term(s)
$term = ycf_get_term('taxonomy_field');

// Get data object
$object = ycf_get_data_object('data_object_field');

// Get all fields at once
$all_fields = ycf_get_fields();

// Check if field exists and has value
if (ycf_has_field('field_name')) {
  // Field exists
}

// For partials
$logo = ycf_get_field('logo', 'partial:header.php');

// For blocks with context
$blocks = ycf_get_field('page_sections');
foreach ($blocks as $block) {
  $title = ycf_get_field('title', null, $block);
}
```

---

## Important Notes

### Nested Blocks Not Supported
- Block fields **cannot** contain other block fields
- Objects **can** be nested inside blocks
- Objects **can** contain other objects (multi-level nesting)

### Escaping Functions
Always use proper escaping:
- `esc_html()` - Plain text output
- `esc_url()` - URLs
- `esc_attr()` - HTML attributes
- `wp_kses_post()` - Rich-text content (safe HTML)
- Code fields for admin use can be output unescaped (use with caution)

### Data Hierarchy
YAML Custom Fields supports a three-level data hierarchy:
1. **Page-specific data** (stored in post meta)
2. **Template global data** (shared across all posts using same template)
3. **Site-wide global data** (for partials like headers and footers)

Use the "Use template global" checkbox per field to toggle between page and template global data.

---

## Additional Resources

- **Main Documentation**: [Plugin README](../README.md)
- **In-Admin Docs**: WordPress Admin > YAML Custom Fields > Documentation
- **Testing Guide**: [README-TESTING.md](../README-TESTING.md)
- **Manual Testing**: [MANUAL-TESTING.md](../MANUAL-TESTING.md)
- **GitHub Repository**: https://github.com/maliMirkec/yaml-custom-fields

---

## Contributing

Found an issue with the examples or have suggestions? Please report it on [GitHub Issues](https://github.com/maliMirkec/yaml-custom-fields/issues).

---

**Built with ❤️ for the WordPress community**

---

**Last Updated:** 2025-12-28
**Plugin Version:** 1.2.2
**Maintained By:** Silvestar Bistrović
