# Release testing for 2.0.0

What to check by hand before publishing 2.0.0.

- [MANUAL-TESTING.md](MANUAL-TESTING.md) covers the whole plugin.
- This file covers **only what changed in 2.0.0**. The riskiest things come first.
- The automatic tests already cover all of this (263 unit, 579 integration). But they can't start from a site with real old data, and they can't see a button that stopped working.
- Tick the boxes as you go. **If it fails** says where to look.

> **You don't need to hunt for PHP warnings.** Three tests already do that, with every field type and every odd case:
> - `AdminScreenSmokeTest`: every admin screen and the Custom fields box, empty and filled, as an admin and as a writer.
> - `AjaxSmokeTest`: all 10 AJAX actions, with good, missing and nonsense input.
> - `PublicApiSmokeTest`: every theme function, with and without values, with deleted images and terms, inside sections and site parts.
>
> A warning from the plugin fails the test and names the file and line. They run on PHP 8.1, 8.3 and 8.4.
>
> Spend your time on **section 1, the update**. No test can reach it.

---

## Before you start

- [ ] Build: `./build-scoped.sh`
- [ ] Get **two** test sites:
  - **Site A (updated).** Install 1.2.9 first. Add real content (see below). *Then* replace it with 2.0.0. This one matters most.
  - **Site B (fresh).** A new install of 2.0.0. No history.
- [ ] On Site A, **before** updating, add:
  - two templates with fields, one of them with shared fields;
  - site details, filled in;
  - one collection with a few items;
  - three or four pages with fields filled in (an image, rich text and sections);
  - at least one page with **"Use the shared value on this page"** ticked.
- [ ] Write down what a few of those pages show on the website. You'll compare later.

> **Take a database backup of Site A before updating.** Section 1 is the only part that changes old data. You may want to run it more than once.

---

## 1. The update

**Why first:** `src/Core/Upgrader.php` is the only code that changes data already on a site. Every automatic test starts from an empty database. It runs once, on the first admin page an admin opens after the update.

- [ ] Update Site A. Open **any** admin page.
- [ ] `wp option get yaml_cf_db_version` says `5`.
- [ ] The pages you wrote down show exactly the same as before.
- [ ] Field lists, shared fields, site details and collections are all still there, unchanged.
- [ ] Open another admin page. The version is still `5`. Nothing else changed. It must not run twice.

**If it fails:** restore the backup first. A half-done update is worse than none.

### 1a. Settings aren't loaded on every page anymore

- [ ] Run `wp option list --search='yaml_cf_*' --fields=option_name,autoload`
- [ ] Every row says `off`, `no` or `auto-off`. **`auto` counts as on.** That was the bug.
- [ ] On Site B, save a field list. Run the command again. New rows must be off too.

**If it fails:** some code still calls `update_option()` without the third argument.

### 1b. Child theme site parts survive

Only if Site A uses a **child theme** that has its own site part with fields.

- [ ] That site part is still listed on the main screen, still on, and still has its content.
- [ ] `wp option get yaml_cf_schemas --format=json` lists it by its short name. No long file paths.

**If it fails:** the content is still in the database under the old name. Look at `repairChildThemePaths()` in `src/Core/Upgrader.php` before saving anything.

### 1c. Pages don't keep their own copy of the field list anymore

- [ ] `wp post meta get <id> _yaml_cf_schema` on an updated page finds nothing. That's right.
- [ ] **Content Check** still lists pages and still finds missing images.
- [ ] Add an image field to a field list. Open Content Check **without** saving any page. The new field is checked.

**If it fails:** `PostSchemaLocator` isn't finding the field list from the page's template.

### 1d. Collections moved to one row per item

**Why:** each item now has its own database row, plus a small index. This touches the most rows.

- [ ] On Site A, before updating, use a collection item in a page field.
- [ ] After updating: all items are listed, the page still shows its item, and the picker finds it.
- [ ] `wp option list --search='yaml_cf_do_*' --fields=option_name,autoload` shows one row per item and one index. None autoloaded.
- [ ] `yaml_cf_data_object_entries_*` rows are gone.

---

## 2. Every button still works

**Why:** admin-only actions now need a second, admin-only token. If one place was missed, its button quietly stops working.

Click each one. Check it does what it says:

- [ ] Switch a template's **Custom fields** on, then off.
- [ ] Tick and untick **Use site details on these pages**. The result shows next to it. The page doesn't jump.
- [ ] **Refresh list** on the main screen.
- [ ] **Export:** go through all steps and download the file.
- [ ] **Import:** drop that file back in. The preview lists what's in it. Finish the import.
- [ ] On a page: **Add section** in a sections field.
- [ ] On a page: a **taxonomy picker** with more than 50 terms. A search box shows. Search works.
- [ ] On a page: a **collection picker**, the same way.
- [ ] Save a field list.
- [ ] Save a site part, shared content and site details.
- [ ] Save each choice on **YAML CF → Settings**.

**If it fails:** open the browser console. A failed token shows as `-1` or `403`. Check that the call sends both `nonce` and `admin_nonce`.

### 2a. Rights still hold

- [ ] Log in as a **Contributor**. The YAML CF menu isn't there.
- [ ] As that Contributor, edit a post with fields. The fields show. **Add section** and pickers work.
- [ ] As that Contributor, the **Copy code** buttons don't show.

---

## 3. Values on the website

**Why:** `ycf_get_field()` and `ycf_get_fields()` used to follow different rules. Now they share one. Two things changed on purpose.

- [ ] A page with **"Use the shared value on this page"** ticked shows the **shared** value.
- [ ] If the theme uses `ycf_get_fields()`, that shows the shared value too. **This is a planned change.** Before, it ignored the tick.
- [ ] Untick it. The page's own value comes back.
- [ ] A field filled in on the page **and** in site details, with the same name, shows the **page's** value. (Second planned change.)
- [ ] A page never saved since a shared field was added shows the shared value.
- [ ] A field the writer cleared stays empty. It doesn't fall back to the shared value.
- [ ] Sections, images, galleries, files, rich text and code all still show.

**If it fails:** the order is in one place: `yaml_cf_resolve_post_fields()` in `yaml-custom-fields.php`.

---

## 4. Field lists (schemas)

**Why:** the plugin now checks types, repeated names and reserved names, at every level. Some lists that used to save are now refused. It must refuse the right ones.

- [ ] Every field list on Site A still opens and saves unchanged.
- [ ] `type: strng` is refused with *Did you mean "string"?*
- [ ] A bad type **inside a group** is refused. The message names the group.
- [ ] A bad type **inside sections** is refused the same way.
- [ ] The same name twice is refused.
- [ ] The same name at two levels (top and inside a group) is **allowed**.
- [ ] `- info:` and `- section:` shortcuts work, also inside a group and in shared fields.
- [ ] `type: break` works. It shows a gap and a heading.

**If it fails:** the list of types is `SchemaValidator::FIELD_TYPES`. It must match `FieldRenderer`. A unit test checks this.

### 4a. Old field lists with a bad type *(optional)*

Only possible on a site that saved one before the check. To make one:

```
wp option patch update yaml_cf_schemas page.php "fields:
  - name: headline
    type: string
  - name: subtitle
    type: strng
"
```

- [ ] Editing a page shows a **warning** where `subtitle` would be. Not an empty label.
- [ ] Put a value in `subtitle` (with `wp post meta`). Save the page. The value is **still there**. Before, it was lost.
- [ ] Importing a file with a bad type still imports the template, and says so.

---

## 5. Themes

**Why:** child themes and block themes used to show no templates.

- [ ] **Child theme:** activate a child of a classic theme. The main screen lists the **parent's** templates. A template in both is listed **once**.
- [ ] Give the child its own site part (`header-promo.php` with `@ycf` in its top comment). It's listed by its short name.
- [ ] Add fields to a parent template. Fill them in on a page. It works.
- [ ] **Block theme:** activate Twenty Twenty-Four or Twenty Twenty-Five. The main screen lists `page`, `single` and any Site Editor templates. `index` and `archive` are under **site parts**.
- [ ] Add fields to `page`. Fill them in. `[ycf field="…"]` in a Shortcode block shows the value.
- [ ] **No templates:** the message names the theme and says something useful.

---

## 6. The Custom fields box

### 6a. Where it shows

**Why:** the box is now a real meta box, so it shows in the block editor. The classic editor still gets it under the title. Only one of the two must print.

- [ ] **Classic editor:** the box is right under the title, above the content.
- [ ] It has a normal box header: title on the **left**, collapse arrow on the right. Clicking the arrow folds and unfolds it.
- [ ] It shows **once**, not twice.
- [ ] Fill it in, click **Update**. It saves.
- [ ] **Block editor:** the box is in a "Custom fields" panel below the content.
- [ ] A post whose template has no fields gets no empty box, in either editor.
- [ ] A template with **only shared fields** (no page fields) still gets the box, with the shared values.

**If it fails:** `MetaBox::register()` and `MetaBox::renderAfterTitle()` both ask `use_block_editor_for_post()`. Exactly one of them must act.

### 6b. How it looks and behaves

- [ ] **One field per row**, everywhere: the page editor, shared content, site details, collection items, site parts.
- [ ] **Collapse all / Expand all** is a small button with an icon, inside the section title row. The title row border goes all the way across.
- [ ] Section titles use the same icons as the side menu.
- [ ] Change a field. The **"not saved yet"** line shows. Works in the classic editor **and** the block editor (try Twenty Twenty-One).
- [ ] Save. The line goes away.
- [ ] Shared values and site details show read-only. Their **Copy code** buttons copy (paste somewhere to check).
- [ ] **Choose image**: the icon and text line up.

### 6c. Removing a field keeps its content

**Why:** the editor always promised this. It wasn't true. The next save threw the content away.

- [ ] Fill in two fields on a page. Remove one from the field list. Save the page. The removed field's content is still in the database.
- [ ] Put the field back with the same name. Its content is back.
- [ ] A field you clear stays cleared.
- [ ] A checkbox can be unticked and stays unticked.
- [ ] Same for site parts, site details and shared fields.
- [ ] Saving a site details or shared field list without a field says which fields lost their place.

### 6d. Content Check cleanup

- [ ] Content Check lists the kept values under **"Values with no field"**.
- [ ] **A page whose template has no fields** says "not checked". It must **not** list every field as left over. Check this carefully: getting it wrong would offer to wipe those pages.
- [ ] Removing takes exactly the listed values. Nothing else.
- [ ] Put a field back in the list. Then submit a removal form that was already open. That field is **not** removed.

---

## 7. Collections

- [ ] Add, edit and delete an item.
- [ ] **Save and add another**: saves the item, shows "Item saved. Fill in the next one below.", and opens an empty item.
- [ ] Delete a whole collection. Its items go with it.
- [ ] Export and import a collection. The items survive.
- [ ] A collection with more than 50 items has pages. Page two shows different items.

---

## 8. The REST API

**Why:** it's new, and the write part is the riskiest thing in this release.

- [ ] `/wp-json/wp/v2/pages/<id>` has a `yaml_cf` object with the values the theme shows, code fields decoded.
- [ ] A **draft** page and its fields can't be read when logged out.
- [ ] Logged out, `/wp-json/yaml-cf/v1/site-details` and `/wp-json/yaml-cf/v1/collections/<name>` are **refused**. That's the default.
- [ ] Tick **Let anyone read the site details and collections** under **YAML CF → Settings → REST API**. Now they can be read logged out.
- [ ] `/wp-json/yaml-cf/v1/collections/<name>?page=2&per_page=50` pages. `?search=` finds items.
- [ ] Logged out, sending a `yaml_cf` object changes nothing.
- [ ] As a Contributor, writing to someone else's page changes nothing.
- [ ] As an admin, sending **one** field leaves the rest alone.
- [ ] As an Editor (no `unfiltered_html`), `<script>` in a rich-text field is stripped, like in the editor.

---

## 9. The [ycf] shortcode

**Why:** it lets block themes show fields. Anyone who can edit a post can use it, so `post=` needs checking.

- [ ] On a **block theme**, `[ycf field="headline"]` in a Shortcode block shows the value.
- [ ] `source="site"` shows a site detail.
- [ ] An image field shows a picture. `size="thumbnail"` changes the size.
- [ ] A file shows a link. Rich text keeps bold and links. Code prints as it is.
- [ ] `default="…"` shows when the field is empty.
- [ ] A group prints nothing. Not the word "Array".
- [ ] **Logged out**, `post="<id of a draft>"` prints nothing.
- [ ] Logged out, `post="<id of a published page>"` prints that page's value.

---

## 10. Messages

**Why:** all messages now look and work the same. The old floating toasts are gone.

- [ ] After saving any screen, a message shows at the top, with an icon for its kind (success, error, warning, info).
- [ ] Messages can be closed. They don't vanish on their own.
- [ ] A screen reader reads each message, including its kind ("Error: …").
- [ ] Buttons that work in place (like **Use site details on these pages** or **Copy code**) show their result right next to the button. The page doesn't jump.
- [ ] Broken YAML keeps your text and shows a red message.
- [ ] In the block editor, messages use the editor's own notices.
- [ ] On Site B, before any fields exist, the main screen says **"Get started in four steps"**. Step four is **"Show it in the theme"**, with the `ycf_get_field()` line.
- [ ] The **Documentation** page's value order matches section 3.
- [ ] Look for typos in new texts.

---

## 11. Main screen and Settings

- [ ] Switching **Custom fields** on and off doesn't make the table jump. Columns keep their width.
- [ ] Buttons like **Add shared fields** sit under their label (like "Optional").
- [ ] **Edit schema** and **Edit site details** look like buttons. Icons line up with the text.
- [ ] Icons have space between them and the text.
- [ ] On a phone-size screen, the table still fits.
- [ ] **YAML CF → Settings** exists and has three choices: **Page caches**, **REST API**, **If this plugin is deleted**.
- [ ] Each choice saves and stays after a reload.

---

## 12. Deleting the plugin

**Why:** this can remove all content. It's now a choice. Use a **throwaway** site.

- [ ] **YAML CF → Settings** has an **"If this plugin is deleted"** part that says what gets removed.
- [ ] Tick **Keep the content if the plugin is deleted**. Save. It stays ticked after a reload.
- [ ] Delete the plugin (**Plugins → Delete**). Install it again and turn it on. Field lists and content are all back.
- [ ] Untick it. Save. Delete again. Everything is gone: settings, post meta, collections.

**If it fails with the box ticked:** stop. That's data loss. It must not ship.

---

## 13. Speed

- [ ] Edit a post whose template has **no** fields. View source: the plugin's `admin.js`, `admin.css` and the media library are **not** loaded.
- [ ] A post whose template **has** fields (or only shared fields) loads them.
- [ ] A page with many fields on the website isn't slower. `WP_DEBUG` shows no notices.
- [ ] Clear all fields on a page and save. `wp option get yaml_cf_tracked_posts` no longer lists it.

---

## Sign-off

- [ ] Sections 1–12 pass on Site A (updated).
- [ ] Sections 2–13 pass on Site B (fresh).
- [ ] `composer check:release` and `composer test:integration` pass.
- [ ] The checklist in [RELEASE.md](RELEASE.md) is done.
- [ ] Version and changelog in `readme.txt` are right.

Tested by: ____________________  Version: ____________  Date: ____________

---

## Known limits in 2.0.0

These are on purpose, not bugs:

- **Block themes need the shortcode.** There's no block yet, so you can't drag a field onto a page.
- **Collection item names are set when the item is saved.** If you reorder a collection's fields, old items get their new name the next time each is saved.
- **REST search in a collection returns one page.** It says how many matched in `total`, but doesn't page through them.
- **`ycf_get_fields()` reads every field on the page.** For one value, `ycf_get_field()` is faster.

The three limits from 1.2.9 are fixed: block themes can show fields, collections no longer share one row, and fields are in the REST API.
