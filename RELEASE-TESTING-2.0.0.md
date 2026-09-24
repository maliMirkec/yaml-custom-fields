# Release testing for 2.0.0

What to click through by hand before publishing, and why each thing is on the list.

`MANUAL-TESTING.md` covers the plugin as a whole. This file covers only what
changed in 2.0.0, ordered by how much damage it would do if it were wrong.
Everything here is already covered by automated tests (245 unit, 136
integration) — what those tests cannot do is start from a site that already has
real data in it, or notice that a button stopped responding.

Tick the boxes as you go. If something fails, the **If it fails** line says what
it points at.

> **What you do not have to test by hand.** Three smoke tests run the same
> fourteen-schema matrix — every field type, every shorthand form, nesting, and
> the awkward cases — across the three surfaces a PHP warning can surface on:
>
> - `AdminScreenSmokeTest` — every admin screen and the post editor meta box,
>   empty, filled in, as an administrator and as a writer.
> - `AjaxSmokeTest` — all nineteen AJAX handlers, as an administrator and as a
>   writer, and again with missing and with nonsense parameters.
> - `PublicApiSmokeTest` — every theme-facing function for every field name, with
>   values, without values, with deleted attachments and terms, inside repeating
>   sections, on site parts, and with arguments a theme can get wrong.
>
> A warning from the plugin's own code fails the build and names the file and
> line. All three were checked by deliberately introducing a fault and confirming
> each one caught it. They run in CI on PHP 8.1, 8.3 and 8.4.
>
> So do not spend time clicking through schema variations hunting for warnings.
> Spend it on section 1, the upgrade path, which is the part no test can reach.

---

## Before you start

- [ ] Build the scoped dependencies: `./build-scoped.sh`
- [ ] Have **two** test sites ready:
  - **Site A — upgraded.** Install the currently published version (1.2.9)
    first, add real content (see below), *then* replace it with the new build.
    This is the one that matters.
  - **Site B — clean.** New install of the new version, no history.
- [ ] On Site A, before upgrading, set up: two templates with schemas, one with
      shared fields, site details filled in, one collection with a few entries,
      three or four pages with fields filled in (including an image, a rich-text
      field and a repeating section), and at least one page where **"Use the
      shared value on this page"** is ticked.
- [ ] Note down what a few of those pages show on the front end. You will be
      comparing against this later.

> Take a database snapshot of Site A before upgrading. Section 1 is the only
> part of this release that rewrites existing data, and you will want to be able
> to run it more than once.

---

## 1. The upgrade path

**Why it is first:** `src/Core/Upgrader.php` is the only new code that changes
data on a site that already exists. Every automated test starts from an empty
database, so this is the one thing they genuinely cannot cover. It runs once, on
the first admin page load after the update, and is tracked by the
`yaml_cf_db_version` option.

- [ ] Upgrade Site A and load **any** admin page.
- [ ] `wp option get yaml_cf_db_version` returns `4`.
- [ ] Every page you noted down still shows exactly what it showed before.
- [ ] Schemas, shared fields, site details and collections are all still there
      and unchanged.
- [ ] Load another admin page. `yaml_cf_db_version` is still `4` and nothing
      else changed — it must not run twice.

**If it fails:** restore the snapshot before doing anything else. A partial
migration is worse than none, and you will want the original to compare against.

### 1a. Options are no longer read on every page view

- [ ] `wp option list --search='yaml_cf_*' --fields=option_name,autoload`
- [ ] Every row reads `off`, `no` or `auto-off`. **`auto` counts as on** — that
      is the value an option gets when it is saved without saying either way, and
      it was the whole bug.
- [ ] On Site B, save a schema, then check the same command. Newly created
      options must be off as well, not just migrated ones.

**If it fails:** a write path somewhere is still calling `update_option()`
without its third argument.

### 1b. Child theme site parts survive

Only applies if Site A runs a **child theme** and has a site part of its own
(one in the child, not the parent) with a schema saved against it. Those were
filed under their full path before this release.

- [ ] That site part is still listed on the dashboard, still switched on, and
      **Edit content** still shows what was filled in.
- [ ] `wp option get yaml_cf_schemas --format=json` shows it keyed by its plain
      name, with no absolute path left anywhere in the list.

**If it fails:** the content is still in the database under the old key — check
`repairChildThemePaths()` in `src/Core/Upgrader.php` before re-saving anything.

### 1c. The per-post schema copy is gone

- [ ] `wp post meta get <id> _yaml_cf_schema` on an upgraded page returns
      nothing (it errors with "could not be found", which is the right answer).
- [ ] The **Content Check** page still lists pages and still reports missing
      images, as it did before the upgrade.
- [ ] Edit a schema to add an image field, then open Content Check **without**
      re-saving any page. The new field is taken into account.

**If it fails:** `PostSchemaLocator` is not finding the schema from the post's
template. Check that the template name it resolves to matches the key the schema
is stored under.

---

## 2. The admin still works at all

**Why:** administrator-only actions moved to their own nonce
(`yaml_cf_admin_nonce`), and every AJAX call in the admin now sends two tokens
instead of one. If any call site was missed, that button silently stops working.
This is the change most likely to break something visible.

Click each of these and confirm it does what it says:

- [ ] Switch a template **on**, then **off** again (`save_template_settings`)
- [ ] Toggle **site details** on a template (`toggle_use_global`)
- [ ] **Refresh templates** on the dashboard (`refresh_templates`)
- [ ] **Export** — run the wizard all the way through and download a file
      (`export_bundle`, `get_posts_with_data`)
- [ ] **Import** — drop that file back in, check the preview lists its contents,
      and complete the import (`inspect_bundle`, `import_bundle`)
- [ ] In a post, **Add section** on a repeating field (`render_block_item`)
- [ ] In a post, use a **taxonomy picker** on a taxonomy with more than 50 terms,
      so the search box appears (`search_terms`)
- [ ] In a post, use a **collection picker** the same way (`search_data_objects`)
- [ ] Save a schema in the schema editor
- [ ] Save content on a site part, on shared fields, and on site details

**If it fails:** open the browser console. A failed nonce comes back as `-1` or
`403`. Check that call site sends both `nonce` and `admin_nonce`.

### 2a. Permissions still hold

- [ ] Log in as a **Contributor**. The plugin's own menu is not visible.
- [ ] As that Contributor, edit a post with fields: the fields show, **Add
      section** works, and the pickers work.
- [ ] As that Contributor, the **Copy code** snippets do *not* appear.

---

## 3. Field values on the front end

**Why:** `ycf_get_field()` and `ycf_get_fields()` used to resolve values by
different rules and could disagree about the same field. They now share one
resolver. Two behaviours deliberately changed, and both only show up on sites
that use these features.

- [ ] A page where **"Use the shared value on this page"** is ticked shows the
      **shared** value on the front end.
- [ ] If your theme uses `ycf_get_fields()` anywhere, that page shows the shared
      value there too. **This is the deliberate change** — before, `get_fields()`
      ignored the tick completely. If a page's output changed here, that is the
      fix working.
- [ ] Untick it on that page. The page's own value comes back.
- [ ] A field with a value on the page **and** a site detail of the same name
      shows the **page's** value. (Second deliberate change; only applies if you
      have a name used in both places.)
- [ ] A page never saved since a shared field was added shows the shared value.
- [ ] A page saved with a field left **empty** stays empty — it does not fall
      back to the shared value.
- [ ] Repeating sections, images, galleries, files, rich text and code fields all
      still render.

**If it fails:** the order lives in one place, `yaml_cf_resolve_post_fields()` in
`yaml-custom-fields.php`, and is written out in the docblock above it.

---

## 4. Schemas

**Why:** the validator now checks field types, duplicate names and reserved
names, recursively. Schemas that used to save may now be refused — that is the
point, but it needs to refuse the right things.

- [ ] Every schema already on Site A still opens and still saves unchanged.
- [ ] `type: strng` is refused with *"Did you mean "string"?"*
- [ ] A bad type **inside a group** is refused, and the message names the group.
- [ ] A bad type **inside a repeating section** is refused the same way.
- [ ] The same field name twice is refused.
- [ ] The same name at two different depths (top level and inside a group) is
      **accepted** — those are stored separately.
- [ ] `- info:` and `- section:` shorthand still work, including inside a group.
- [ ] `type: break` still works.

**If it fails:** the list of valid types is `SchemaValidator::FIELD_TYPES`, and
it is meant to match the `switch` in `render_fields_flat()` exactly.

### 4a. Legacy schemas with a bad type *(advanced, optional)*

Only reachable on a site that saved one before the check existed. To set one up:

```
wp option patch update yaml_cf_schemas page.php "fields:
  - name: headline
    type: string
  - name: subtitle
    type: strng
"
```

- [ ] Editing a page on that template shows a **warning** where `subtitle` would
      be, not an empty label.
- [ ] Put a value in `subtitle` (via `wp post meta`), save the page in the
      editor, and the value is **still there** afterwards. This is the data-loss
      fix: the field posts nothing, so it used to be dropped.
- [ ] Importing a bundle whose schema has a bad type still imports the template,
      and says so in the result rather than skipping it.

---

## 5. Themes

**Why:** child themes reported no templates at all, and block themes reported
none either.

- [ ] **Child theme.** Activate a child of a classic theme where the child
      overrides little or nothing. The dashboard lists the **parent's**
      templates. A template both themes have is listed **once**.
- [ ] Give the child a partial of its own (`header-promo.php` with `@ycf` in a
      comment). It is listed under its plain name, not a long file path.
- [ ] Save a schema against a parent template and fill it in on a page. It works
      end to end.
- [ ] **Block theme.** Activate Twenty Twenty-Four or Twenty Twenty-Five. The
      dashboard lists `page`, `single` and any Site Editor templates. `index` and
      `archive` appear under **site parts**, not templates.
- [ ] Save a schema against `page`, fill it in on a page, and confirm
      `ycf_get_field()` returns it. (Displaying it in a block theme still needs a
      PHP call site — that is a known limitation, not a bug.)
- [ ] **Empty state.** On a theme with no templates, the message names the theme
      and says something useful. It must not still read *"The active theme has no
      page or post templates yet."*

---

## 6. Messaging

- [ ] On Site B, before creating any schema, the dashboard reads **"Get started
      in four steps"** and the fourth is **"Show it in the theme"** with the
      `ycf_get_field()` line.
- [ ] It no longer says *"The theme shows what was typed."*
- [ ] The **Documentation** page's value-order list matches what you saw in
      section 3.
- [ ] Spot-check new wording for typos: the empty-state messages, the unknown
      type message, the deletion card.

---

## 7. Deleting the plugin

**Why:** this removes the content filled into every field, which cannot be
undone. It is now a choice. Test it on a **throwaway** site.

- [ ] The bottom of the YAML Custom Fields page has an **"If this plugin is
      deleted"** card explaining what deletion removes.
- [ ] Tick **Keep the content if the plugin is deleted** and save. The confirmation
      appears and the box stays ticked after a reload.
- [ ] Delete the plugin through **Plugins → Delete**. Re-install and activate it:
      the schemas and the page content are all back.
- [ ] Untick it, save, delete again. Everything is gone — options, post meta,
      collections.

**If it fails with the box ticked:** stop. That is unrecoverable data loss and
must not ship.

---

## 8. Performance, lightly

- [ ] On a post whose template has **no** schema, view source in the editor: this
      plugin's `admin.js` and `admin.css` are **not** loaded, and neither is the
      media library.
- [ ] On a post whose template **does** have a schema, they are all loaded.
- [ ] Load a front-end page with a good number of fields. Nothing is slower than
      before, and `WP_DEBUG` produces no notices.
- [ ] Clear all the fields on a page and save. `wp option get yaml_cf_tracked_posts`
      no longer lists that page.

---

## Sign-off

- [ ] Sections 1–7 pass on Site A (upgraded)
- [ ] Sections 2–8 pass on Site B (clean)
- [ ] `composer test` and `composer lint` pass
- [ ] `RELEASE.md`'s own pre-release checklist is done
- [ ] Version and changelog in `readme.txt` are correct

Tested by: ____________________  Version: ____________  Date: ____________

---

## Known limitations in this release

These are deliberate, not defects. They are worth knowing before someone reports
them as bugs:

- **Block themes can hold fields but cannot display them on their own.** There is
  no `.php` template to call `ycf_get_field()` from. A shortcode or a block would
  close this.
- **Collections keep every entry in one option.** Fine for hundreds, slow for
  thousands. Fixing it means moving entries to a custom post type or table.
- **Fields are not in the REST API.** No `register_meta()`, so headless front
  ends and most integrations cannot see them.
