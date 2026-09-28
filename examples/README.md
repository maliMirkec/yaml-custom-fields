# Examples

Two files that show every field type and how to print it in a theme.

## What's here

**`complete-schema-example.yaml`**: a field list (schema) with every field type.
- Each field has a comment that says what it does.
- It shows the options: required, min/max, multiple, and more.
- It shows sections, groups (objects) and collections.

**`complete-template-example.php`**: a page template that prints every field.
- It shows how to read each field type.
- It escapes everything safely.
- It shows loops for lists and sections.

---

## Try the field list

1. Go to **YAML CF** in the admin.
2. Turn on **Custom fields** for a template, for example `page.php`.
3. Click **Create schema** (or **Edit schema**).
4. Paste in `complete-schema-example.yaml`.
5. Save.

**You also need:**
- A collection called `universities`.
  - Go to **YAML CF → Collections**.
  - Add a collection named `universities`. Its fields are in the comments at the end of the YAML file.
  - Add a few items.
- The taxonomies `portfolio_cat` and `faq_category`.
  - Your theme (or another plugin) has to register them.
  - Or remove those two fields from the list.

## Try the template

1. Copy `complete-template-example.php` into your theme folder.
2. Edit a page.
3. Pick **"YAML Custom Fields - Complete Example"** in the Template box.
4. Fill in the fields and publish.
5. View the page.

You can also just read the file and copy the parts you need.

---

## Field types

| Type | What it is |
|---|---|
| `string` | One line of text |
| `text` | Several lines of text |
| `rich-text` | The WordPress text editor |
| `code` | Code (HTML, CSS, JS…) |
| `boolean` | A checkbox: yes or no |
| `number` | A number, with optional min and max |
| `date` | A date, with optional time |
| `select` | A dropdown. Can allow more than one choice. |
| `taxonomy` | Categories, tags or your own taxonomy |
| `post_type` | Pick a post type |
| `data_object` | Pick an item from a collection |
| `image` | An image. Add `list: true` for a gallery. |
| `file` | Any file from the media library |
| `object` | A group of fields |
| `block` | Sections the writer can add, remove and reorder |
| `info` | A note for the writer. It stores nothing. |
| `section` (or `break`) | A heading with a gap above it. It stores nothing. |

---

## Theme functions, quick list

Every function has a short `ycf_` name and a long `yaml_cf_` name. They do the same thing.

```php
// A field on the current page
$value = ycf_get_field('field_name');

// Does the field have a value? (empty text or an empty list = no)
if (ycf_has_field('field_name')) { … }

// All fields at once
$all = ycf_get_fields();

// An image, in a chosen size
$image = ycf_get_image('image_field', null, 'medium');

// A file
$file = ycf_get_file('file_field');

// A term (or terms)
$term = ycf_get_term('taxonomy_field');

// An item from a collection
$item = ycf_get_data_object('data_object_field');

// All items of a collection
$items = ycf_get_data_objects('universities');

// Site details
$phone = ycf_get_global_field('phone');

// A site part, like the header
$logo = ycf_get_field('logo', 'partial:header.php');

// Fields inside sections
$sections = ycf_get_field('page_sections');
foreach ($sections as $section) {
  $title = ycf_get_field('title', null, $section);
}
```

---

## Good to know

**Nesting**
- A section (`block`) can't hold another section.
- A section can hold groups (`object`).
- A group can hold other groups.

**Always escape what you print**
- `esc_html()`: plain text
- `esc_url()`: links
- `esc_attr()`: HTML attributes
- `wp_kses_post()`: rich text
- Code fields are meant to be printed as they are. Only people allowed to post raw HTML can save raw code.

**Where a value comes from**
- **The page's own value.** Filled in on each page.
- **Shared content.** One value for every page that uses the same template.
  - A writer can tick **"Use the shared value on this page"** on a field. Then the shared value wins.
  - A field that is only in the shared list always uses the shared value.
- **Site details.** One value for the whole site. A template can turn them on with **"Use site details on these pages"**. They are used when the page has no value of its own.
- If a field was cleared on purpose, it stays empty. It does not fall back.

`ycf_get_field()` picks the right value for you. The full order is in the [README](../README.md#which-value-wins).

---

## More help

- [Plugin README](../README.md)
- In the admin: **YAML CF → Documentation**
- [Testing guide](../TESTING.md)
- [Manual test guide](../MANUAL-TESTING.md)
- GitHub: https://github.com/maliMirkec/yaml-custom-fields
- Found a problem? [Open an issue](https://github.com/maliMirkec/yaml-custom-fields/issues).

---

**Last updated:** 2026-09-28
**Plugin version:** 2.0.0
**Maintained by:** Silvestar Bistrović
