# Manual testing guide

A step-by-step click-through of every feature of YAML Custom Fields 2.0.0.

- Each section is one feature. Do the steps in order; each ✅ line is what you should see.
- If a ✅ line is not what happens, that is a bug. Note the section number and step.
- For the upgrade from 1.2.9, use `RELEASE-TESTING-2.0.0.md` as well. This guide starts from a clean site.

---

## 0. Before you start

- Use a test site, never a live one. Take a database backup first.
- Install and activate the plugin.
- Use a classic theme with `page.php` and `single.php` (for example Twenty Twenty-One) unless a section says otherwise.
- Create three extra users: an **Editor**, an **Author** and a **Contributor**.
- Upload to the media library: three images and one PDF.
- Create 60 tags (needed for the search picker in section 6). A quick way: `wp term generate post_tag --count=60`.
- Keep this test schema at hand. Sections below refer to it as **the test schema**:

```yaml
fields:
  - section: Basics
  - name: headline
    label: Headline
    type: string
  - name: intro
    label: Intro
    type: text
  - name: body
    label: Body
    type: rich-text
  - name: embed
    label: Embed
    type: code
    options:
      language: html
  - name: count
    label: Count
    type: number
  - name: event_date
    label: Event date
    type: date
  - name: featured
    label: Featured
    type: boolean
  - name: color
    label: Colour
    type: select
    options:
      values:
        - red
        - blue
  - info: "This is an info box for writers."
  - section: Media
  - name: hero
    label: Hero image
    type: image
  - name: gallery
    label: Gallery
    type: image
    list: true
  - name: brochure
    label: Brochure
    type: file
  - name: downloads
    label: Downloads
    type: file
    list: true
  - section: Links
  - name: topic
    label: Topic
    type: taxonomy
    options:
      taxonomy: category
  - name: tags
    label: Tags
    type: taxonomy
    multiple: true
    options:
      taxonomy: post_tag
  - name: kind
    label: Post type
    type: post_type
  - name: contact
    label: Contact
    type: object
    fields:
      - name: phone
        label: Phone
        type: string
      - name: email
        label: Email
        type: string
  - name: sections
    label: Sections
    type: block
    list: true
    blocks:
      - name: banner
        label: Banner
        fields:
          - name: title
            label: Title
            type: string
          - name: photo
            label: Photo
            type: image
      - name: quote
        label: Quote
        fields:
          - name: text
            label: Text
            type: text
```

---

## 1. Main screen (YAML CF menu)

- Go to **YAML CF** in the admin menu.
- ✅ The menu lists **Settings** between **Content Check** and **Documentation**.
- ✅ Each section heading and each button has an icon, with a little space between icon and text. The same thing always has the same icon (for example site details, shared fields, Edit schema).
- ✅ First visit shows **Get started in four steps**.
- ✅ **Page & post templates** lists `page.php` and `single.php` first, then the rest A–Z.
- ✅ **Site parts & archive pages** lists `header.php`, `footer.php`, `index.php`, `archive.php` and so on.
- Switch **Custom fields** on for `page.php`.
- ✅ "✓ Saved" appears under the switch, without a page reload. The page does not move.
- ✅ The row shows **No fields yet** and, on the line below it, a **Create schema** button. Under **Shared fields**: **Optional**, with **Add shared fields** below it.
- Switch several templates on and off, quickly.
- ✅ **Nothing in the table moves**: columns keep their width and rows keep their height. Only the content inside the switched row changes.
- Narrow the browser window below about 780px.
- ✅ Each template becomes a card, with the column names above each part.
- Reload the page.
- ✅ The switch is still on.
- Switch it off.
- ✅ The row shows **Switch on to add fields**.
- Switch it back on.
- Add a new template file to the theme (for example `page-landing.php` with a `Template Name:` header).
- Click **Refresh list**.
- ✅ The new template appears.

### 1a. Empty table messages
- Switch to a block theme (for example Twenty Twenty-Four).
- ✅ The tables list block templates such as `page` and `single`.
- ✅ If a table is empty, it names the theme and says Site Editor templates appear once saved.
- Switch back to the classic theme.

---

## 1b. Settings screen

- Go to **YAML CF → Settings**.
- ✅ Three cards: **Page caches**, **REST API** and **If this plugin is deleted**, each with its own icon.
- ✅ The main **YAML CF** screen no longer shows them.
- Change each tick box and click its **Save this choice**.
- ✅ The page comes back to **Settings** with a green notice saying what changed, and the box keeps its new state.
- Sections 18, 20 and 21 test what each choice does.

---

## 2. Template schema editor

- On the main screen, click **Create schema** for `page.php`.
- ✅ The editor opens with the theme file name shown.
- Paste the test schema and click **Save schema**.
- ✅ Success message. The main screen row shows a green badge with the field count.

### 2a. Mistakes are refused, and the text is kept
- Replace `type: string` on `headline` with `type: strng` and save.
- ✅ Error names the field and asks "Did you mean "string"?". Your text is still in the editor.
- Give two fields the same name and save.
- ✅ Error: there are two fields with that name.
- Name a field `_template_global_override` and save.
- ✅ Error: it is a name the plugin uses itself.
- Name a field `links[0]` and save.
- ✅ Error: field names cannot contain square brackets.
- Write `name: [a, b]` and save.
- ✅ Error: the field needs a proper name.
- Break the YAML (for example an unclosed `[`) and save.
- ✅ Error: the schema has a typing mistake, with details.
- Name a field `2024` (digits only) and save.
- ✅ Saved. Digit-only names are allowed.
- Put the test schema back and save.

### 2b. Removing a field keeps its content
- Fill in **Headline** on a page (see section 3) and update it.
- In the schema, delete the `headline` field and save.
- ✅ A notice says which field lost its place and that its content is still saved.
- Open the page and click **Update**.
- Put `headline` back in the schema and save.
- Reopen the page.
- ✅ The old headline is back.

---

## 3. Post editor – the "Custom fields" box

### 3a. The box appears
- Create a new page (template: default).
- ✅ Block editor: a **Custom fields** box appears below the content.
- ✅ Classic editor (install the Classic Editor plugin to check): the box sits under the title.
- ✅ The box title has the same icon as **YAML CF** in the admin menu.
- ✅ The box shows a **Template: page.php** chip, and **Edit schema**, **Export fields**, **Import fields** and **Empty all fields** buttons, each with an icon.
- In the **classic editor**, click the arrow at the right of the box's title bar.
- ✅ The box folds away. Reload the page – ✅ it is still folded. Click the arrow again – ✅ it opens, and stays open after a reload.
- ✅ In the block editor the same arrow folds the box away too.
- Log in as the Author and open one of their pages.
- ✅ **Edit schema** is not shown to the Author.
- Switch `page.php` off on the main screen.
- ✅ The box is gone from the page editor. Switch it back on.

### 3b. Every field type saves and comes back
Fill in each field, click **Update** or **Publish**, then reload the editor. Check each line.
- **Headline** with `& < > " '` – ✅ comes back exactly as typed.
- **Intro** with several lines – ✅ line breaks kept.
- **Body** with bold, italic and a link – ✅ formatting kept.
- **Embed** with `<div class="x">Hi</div>` – ✅ kept exactly as an Administrator.
- **Count** – ✅ letters are refused; a number is kept.
- **Event date** – ✅ a date picker opens; the date is kept.
- **Featured** ticked, then unticked on a second save – ✅ both states are kept (unticking really saves).
- **Colour** – ✅ the chosen option is kept.
- ✅ The info box shows its text and has no input.
- ✅ **Basics**, **Media** and **Links** show as headed groups.
- **Hero image**: click **Choose image**, pick one – ✅ preview appears. Click **Remove** – ✅ preview goes; after Update it stays empty.
- **Brochure**: click **Choose file**, pick the PDF – ✅ file name shows; it is kept.
- **Topic** (short list) – ✅ the chosen category is kept.
- **Post type** – ✅ the choice is kept.
- **Contact → Phone / Email** – ✅ both kept inside the group.

### 3c. Unsaved changes warning
- Change any field.
- ✅ The line at the top of the Custom fields box says there are changes not saved yet.
- Try to leave the page.
- ✅ The browser asks to confirm.
- Click **Update**.
- ✅ After saving, that line is back to normal.

### 3d. Writers cannot slip in code
- Log in as the Author (no `unfiltered_html`) and put `<script>alert(1)</script><p>ok</p>` into **Embed** and **Body**, then update.
- ✅ Reload: the script is gone and `<p>ok</p>` remains.
- As the Author, set **Embed**'s language to `js` in the schema (as admin), then save `alert(1)` as the Author.
- ✅ The JavaScript is emptied for the Author. An Administrator can save it.

---

## 4. Repeating sections

- In **Sections**, pick **Banner** and click the add button.
- ✅ A Banner item appears with **Title** and **Photo**. It appears without reloading.
- Add a **Quote** item.
- Type a title in the Banner.
- ✅ The Banner's header shows that title as its summary.
- Use the arrow buttons to move the Quote above the Banner.
- ✅ The order changes. After Update and reload, the order is kept.
- With two or more items, click **Collapse all**.
- ✅ All items fold away. Click the caret on one – ✅ it opens.
- Click **Remove** on one item, then Update.
- ✅ After reload the removed item is gone.
- Add a Banner, choose an image in it, Update and reload.
- ✅ The image is kept inside the item.

---

## 5. Galleries and file lists

- In **Gallery**, click **Add images** and pick all three images in one go.
- ✅ Three thumbnails appear.
- Drag one to another position.
- ✅ The order changes. After Update and reload, the order is kept.
- Use the keyboard: tab to a thumbnail's **Move earlier / Move later** buttons and press Enter.
- ✅ The image moves.
- Remove one image and Update.
- ✅ Two remain after reload.
- Remove all images and Update.
- ✅ The gallery is empty after reload (it does not bring the old images back).
- Delete one of the used images from the media library, then reopen the page.
- ✅ The tile says the image is missing instead of showing a broken picture.
- In **Downloads**, click **Add files**, pick two files, remove one, then Update.
- ✅ One file remains after reload. Removing the last one and updating leaves it empty.

---

## 6. Search pickers (big lists)

- Open a page with the **Tags** field (60 tags exist).
- ✅ Instead of a long list, a search box shows "60 choices, so search for the one you want".
- Type part of a tag name.
- ✅ Matching tags appear. With many matches, a line says to keep typing.
- Click one.
- ✅ It appears as a chip. Choose two more.
- Use the arrow keys and Enter in the results list.
- ✅ Keyboard choice works.
- Click the × on one chip, then Update.
- ✅ After reload, only the remaining chips are shown.
- Remove all chips and Update.
- ✅ The field is empty after reload.
- Create a tag named `R&D <team>` and pick it.
- ✅ The chip shows the name as text, exactly `R&D <team>`.

---

## 7. Empty all fields

- Fill in every field on a page, including Gallery, Downloads, Tags, Topic and Sections. Update.
- Click **Empty all fields** and confirm.
- ✅ Every field on the page empties: text, rich text, images, the gallery, file lists, picker chips and repeating sections.
- ✅ Shared values and site details on the page are not touched.
- ✅ A message says to click Update.
- Click **Update** and reload.
- ✅ Everything stays empty.
- Click **Empty all fields** and **Cancel**.
- ✅ Nothing changes.

---

## 8. Shared fields (same on every page of a template)

- On the main screen, for `page.php`, click **Add shared fields**.
- Save this schema:

```yaml
fields:
  - name: headline
    label: Headline
    type: string
  - name: disclaimer
    label: Disclaimer
    type: text
```

- Click **Edit content** for shared fields, fill in both, click **Save content**.
- Open a page on `page.php`.
- ✅ **Headline** shows the shared value (read-only, with **Edit**), a **This page only** box, and a **Use the shared value on this page** tick box.
- ✅ **Disclaimer** is shown read-only under **Shared fields**, with an **Edit shared content** link.
- Tick **Use the shared value on this page**, Update.
- ✅ The page's own box is greyed out. In the theme (section 16), `ycf_get_field('headline')` shows the shared value.
- Untick it, type a page value, Update.
- ✅ The theme shows the page's own value.

---

## 9. Site details (same on the whole site)

- On the main screen, click **Create site details**, save a schema with `phone` and `email`.
- ✅ **Site details** now appears in the menu.
- Click **Edit site details**, fill in both, click **Save content**.
- ✅ Success message.
- On the main screen, in the `page.php` row, tick **Use site details on these pages**.
- ✅ "✓ Saved" appears under the checkbox. Reload – ✅ still ticked.
- Open a page on `page.php`.
- ✅ A read-only **Site details** part is shown, with an **Edit site details** link.
- ✅ In the theme, `ycf_get_field('phone')` on that page returns the site detail.
- Untick the switch.
- ✅ The page no longer shows site details, and `ycf_get_field('phone')` returns nothing.
- ✅ `ycf_get_global_field('phone')` works anywhere, switch or not.

---

## 10. Site parts (header, footer and your own)

- Switch **Custom fields** on for `header.php` and click **Create schema**. Save a schema with a `tagline` field.
- Click **Edit content**, fill in the tagline, **Save content**.
- ✅ In `header.php`: `ycf_get_field('tagline', 'partial:header.php')` shows it.
- Add `@ycf` in a comment at the top of a new theme file (for example `parts/promo.php`) and click **Refresh list**.
- ✅ It appears under **Site parts & archive pages** as `parts/promo.php`.

---

## 11. Collections

- Go to **YAML CF → Collections** and click **Add a collection**.
- Name: `Team`, Short name: `team`. Schema:

```yaml
fields:
  - name: name
    label: Name
    type: string
  - name: role
    label: Role
    type: string
```

- Click **Save collection**.
- ✅ The collection is listed with **Edit schema**, **Edit items**, **Delete**.
- Try to add another collection with short name `team`.
- ✅ Refused: the short name is taken. The form keeps what you typed.
- Try a short name longer than 64 characters.
- ✅ Refused as too long.
- Edit the Team collection and change the short name field in the page's HTML (developer tools), then save.
- ✅ The short name does not change.

### 11a. Items
- Click **Edit items → Add an item**, fill in Ada / Engineer, **Save item**. Add two more.
- ✅ Items are listed by name.
- Edit one, change the role, save.
- ✅ The change is kept.
- Delete one and confirm.
- ✅ It is gone.
- Add more than 50 items (or import them, section 13).
- ✅ The list is paged; saving an item on page 2 brings you back to page 2.

### 11b. Labels follow the schema
- Edit the Team schema and move `role` above `name`. Save.
- ✅ Items are now listed and picked by their role, not their name (without saving each item again).

### 11c. Using a collection on a page
- Add to the page schema:

```yaml
  - name: member
    label: Team member
    type: data_object
    options:
      object_type: team
```

- ✅ The page editor lets you pick an item (a search picker if the collection is big).
- Pick one and Update.
- ✅ `ycf_get_data_object('member')` returns that item in the theme.
- Add `multiple: true` and pick three.
- ✅ `ycf_get_data_object('member')` returns the three items in the chosen order.

### 11d. Deleting a collection
- Delete the collection and confirm.
- ✅ It and all its items are gone.

---

## 12. Per-page export and import (in the page editor)

- On a filled-in page, click **Export fields**.
- ✅ A `.json` file downloads.
- On another page with the same template, click **Import fields** and choose that file.
- ✅ A message says the import worked. In the **block editor** it appears in the editor's own notice area at the top.
- ✅ The fields are filled in.
- Edit the file so it only contains `headline`, and import it onto a page whose other fields are filled.
- ✅ Only the headline changes. The other fields keep their content.
- Import a file made for a page with a different web address (when the file matches by address).
- ✅ A clear error says the file has no content for this page.
- Try a `.txt` file, a broken `.json`, and a file over 5 MB.
- ✅ Each gives a clear error and changes nothing.
- As the Contributor, try the export link of someone else's page (copy it from an admin's editor).
- ✅ Refused with "You are not allowed to edit this page…".

---

## 13. Export & Import wizard

### 13a. Export
- Go to **YAML CF → Export & Import**.
- ✅ Step 1 lists **Setup** (grouped per template), **Pages and posts**, and **Collections**.
- Click the caret next to a template.
- ✅ Its pieces (schema, shared fields, content) show. Untick one piece.
- Tick **Pages and posts**, click **Next**.
- ✅ Step 2 lists pages that have field content, paged, with select-all buttons.
- Choose **By web address**, pick some pages, click **Next**.
- ✅ Step 3 summarises what the file will hold.
- Click **Export file**.
- ✅ A `.json` file downloads.

### 13b. Import
- On a second test site (or after changing things), choose the file under **Import**.
- ✅ A preview lists everything in the file. Items that already exist say **exists here – will overwrite**; new ones say **new**.
- ✅ Pages not on this site are marked missing and cannot be ticked.
- ✅ Pages from an **older export file** (one without post types) are still found, not marked missing.
- Untick a few items, click **Import N selected**, confirm the overwrite warning.
- ✅ A result lists what was imported and any problems.
- ✅ A site part's content from the file is merged in: fields the file does not mention keep their content.
- ✅ Images whose IDs do not exist on this site are left empty. Use **Check images and files** (section 14).
- Choose a random `.json` file.
- ✅ Error: not a YAML Custom Fields export file.

---

## 14. Content Check

- Go to **YAML CF → Content Check**.
- ✅ The summary shows pages with fields, missing images and files, and values with no field.
- Delete an image used on a page from the media library, reload.
- ✅ That page is listed under **Needs attention** with the missing image.
- Remove a field from a page schema (section 2b), reload.
- ✅ The page shows **No field for:** with the field name.
- ✅ A field moved into the template's **shared fields** is not listed. That page copy is kept on purpose.
- ✅ A field named with digits only (`2024`) that is still in the schema is not listed.
- ✅ A page whose template has no schema says **not checked**.
- Tick a listed value, click remove, and confirm.
- ✅ A message says how many values were removed, and the value is gone.
- ✅ With a page cache plugin active, that page's cache is cleared.
- Use the **All / Issues** filter.
- ✅ The list filters; the active button reads clearly.

---

## 15. Expired forms

- Open **Site details**, type something, then wait until your login session's nonce expires (or edit the hidden `_nonce` field in developer tools to a wrong value), and click **Save content**.
- ✅ A page says "Nothing was saved: this page was open for too long…" with a back link. Nothing is saved.
- Repeat on: a schema editor, **Edit content** for a site part, shared content, and **Import fields**.
- ✅ Same message each time.

---

## 16. Showing fields in the theme

Put these lines in `page.php` (or use the **Copy code** buttons next to each field) and view the page on the front end.

- `<?php echo esc_html(ycf_get_field('headline')); ?>` – ✅ shows the headline.
- `<?php print_r(ycf_get_fields()); ?>` – ✅ shows all values; the same values `ycf_get_field()` returns.
- `<?php var_dump(ycf_has_field('headline')); ?>` – ✅ `true` when filled, `false` when the field is cleared or missing. An unticked **Featured** box still gives `true`.
- `<?php print_r(ycf_get_image('hero', null, 'medium')); ?>` – ✅ image data with url, alt, width, height.
- `<?php print_r(ycf_get_image('hero', null, [300, 200])); ?>` – ✅ works (no fatal error).
- `<?php print_r(ycf_get_image('gallery')); ?>` – ✅ a list of images in gallery order.
- `<?php print_r(ycf_get_file('brochure')); ?>` – ✅ file data; for **Downloads**, a list.
- `<?php print_r(ycf_get_term('tags')); ?>` – ✅ term objects.
- `<?php print_r(ycf_get_post_type('kind')); ?>` – ✅ the post type.
- `<?php echo ycf_get_field('embed'); ?>` – ✅ the code runs as HTML (not encoded text).
- `<?php echo esc_html(ycf_get_field('tagline', 'partial:header.php')); ?>` – ✅ the site part value.
- `<?php print_r(ycf_get_data_objects('team')); ?>` – ✅ every item of the collection.
- Add a filter in the theme's `functions.php`:
  `add_filter('yaml_cf_field_value', fn($v, $name) => $name === 'headline' ? strtoupper((string) $v) : $v, 10, 2);`
- ✅ The headline shows in capitals.

### 16a. Shortcode (works in block themes)
- Add a **Shortcode** block with `[ycf field="headline"]`.
- ✅ Shows the headline, escaped.
- `[ycf field="hero" size="large"]` – ✅ shows the image.
- `[ycf field="phone" source="site"]` – ✅ shows the site detail.
- `[ycf field="missing" default="Nothing here"]` – ✅ shows "Nothing here".
- `[ycf field="headline" post="123"]` with a **draft** page's ID, viewed while logged out – ✅ shows nothing.
- Same with a **published** page's ID – ✅ shows its headline.
- A page behind a password – ✅ its fields stay hidden until the password is entered.

---

## 17. Which template a page uses

- Set a page's template to `page-landing.php` in the editor.
- ✅ The box shows **Template: page-landing.php** and that template's fields.
- Create `page-about.php` in the theme, give it a schema (switched on), and create a page with the address `about`.
- ✅ That page uses `page-about.php`'s fields. Before that template has a schema, it uses `page.php`'s.
- Switch to a theme that has `singular.php` but no `page.php` or `single.php` (for example Twenty Twenty).
- ✅ `singular.php` is listed; pages and posts use it.
- On a block theme, create a `page-about` template in the Site Editor and give `page-about` a schema.
- ✅ The page with address `about` uses it.
- Use a child theme that only has `style.css`.
- ✅ The parent theme's templates are listed and work.

---

## 18. REST API

Use a browser or a REST client. Replace `ID` with a page ID.

- Logged out, open `/wp-json/wp/v2/pages/ID`.
- ✅ A `yaml_cf` field holds the page's values.
- ✅ With **Use site details on these pages** ticked, the site details are **not** in it while REST reading is closed (default).
- Logged out, open `/wp-json/yaml-cf/v1/site-details` and `/wp-json/yaml-cf/v1/collections/team`.
- ✅ Both are refused (401).
- Go to **YAML CF → Settings** and tick **Let anyone read the site details and collections, without logging in**, then **Save this choice**.
- ✅ Both routes now return data; the page's `yaml_cf` field now includes site details.
- `/wp-json/yaml-cf/v1/collections/team?per_page=2&page=2` – ✅ paged, with `X-WP-Total` headers.
- `/wp-json/yaml-cf/v1/collections/team?search=ada` – ✅ only matches.
- As an Editor (application password), send `POST /wp-json/wp/v2/pages/ID` with body `{"yaml_cf": {"headline": "From REST"}}`.
- ✅ The headline changes; other fields keep their values.
- Send a `<script>` in a rich-text field.
- ✅ It is removed, as in the editor.
- As the Contributor, write to someone else's page.
- ✅ Refused.
- A draft or password-protected page, logged out.
- ✅ Not readable (draft) or `yaml_cf` is empty (password).

---

## 19. Permissions

- As the Editor, Author and Contributor, open **YAML CF**.
- ✅ The menu is not there. The plugin's admin URLs say permission is needed.
- As the Author and the Contributor, edit their own page.
- ✅ The Custom fields box works, including **Add section** and the search pickers.
- As the Contributor, open a page of another user in the editor.
- ✅ WordPress refuses, as for any page they cannot edit.

---

## 20. Page caches

- With a page cache plugin active, edit **Site details** and save.
- ✅ Pages with fields show the new value straight away.
- Go to **YAML CF → Settings** and untick **Clear the cache of every page with fields when site-wide content is saved**, **Save this choice**.
- ✅ Saving site details is faster; cached pages keep the old value until the cache plugin purges them.

---

## 21. Deleting the plugin

- Go to **YAML CF → Settings** and tick **Keep the content if the plugin is deleted**, **Save this choice**.
- Deactivate and delete the plugin, then install and activate it again.
- ✅ Schemas, site details, collections and page content are all back.
- Untick the choice, save, delete the plugin again.
- ✅ The database has no `yaml_cf_` options, no `yaml_cf_do_` rows and no `_yaml_cf_` post meta left.
- On a multisite network, repeat for two sites.
- ✅ Each site follows its own choice.

---

## 22. Messages and notices

The plugin tells you how things went in two ways, and never with pop-ups in a screen corner:

- **Status next to the control** for anything done without leaving the page (a switch, adding a section, unsaved changes). It appears in space that is already there, so **nothing on the page moves**.
- **A notice under the page heading** for the result of a save that reloads the page. It is there before you start reading, so nothing moves either.

Both use the same icons: ✓ done, ⓘ note, ⚠ warning, ⊗ error.

### Status next to the control
- On the main screen, switch a template's **Custom fields** off and on.
- ✅ "✓ Saved" appears just under that switch. **The page does not move.** No notice appears at the top.
- Tick **Use site details on these pages**.
- ✅ "✓ Saved" appears under the checkbox. The page does not move. The "Saved" under the other switch disappears (only the latest is shown).
- Disconnect from the network (developer tools → Offline) and use a switch.
- ✅ "⊗ Not saved. Try again." appears in the same place, and the switch goes back to how it was.
- In the page editor, change a field. Do this in the **block editor** (the default, e.g. with Twenty Twenty-One) and in the classic editor.
- ✅ The line under **Template: …** in the Custom fields box changes to "⚠ Changes not saved yet. Save to keep them." Nothing moves. Keep typing – it does not flash or repeat.
- Undo your change (type the old value back).
- ✅ The line goes back to "Fill in the fields below…".
- Click **Remove** on a section.
- ✅ The section goes, focus moves to the next section, and the box's line says there are changes to save. No notice at the top.
- Choose no section type and click **Add section**.
- ✅ "⚠ Choose a section type first." appears next to the button, and focus goes to the section type list.
- On **Site details**, change a field without saving.
- ✅ "⚠ There are changes that are not saved yet." appears next to the **Save content** button. It goes away when you save.
- Click a **Copy code** button.
- ✅ The button itself says **Copied** for a moment. Nothing else appears.

### Notices after a save
- Save **Site details**.
- ✅ After the page reloads, a green notice with ✓ is under the page heading, with a close button.
- Wait a minute.
- ✅ It is still there. Nothing disappears by itself.
- Save a schema with a mistake.
- ✅ A red notice with ⊗, and keyboard focus is on it (press Tab: the next thing after it is focused).
- With the keyboard only: Tab to a notice's close button and press Enter.
- ✅ The notice closes and focus moves to the next notice (or the page heading), not the top of the page.
- In the **block editor**, use **Import fields**.
- ✅ The result appears in the editor's own notice bar at the top.

### Other notices
- On **Export & Import**, export a file and import one.
- ✅ The result appears as a notice directly under the buttons; nothing above it moves. Import problems are listed in an amber notice.
- On **Content Check** and **Collections**, look at the grey information notes.
- ✅ Same shape as the other notices, with an ⓘ icon.

### With a screen reader (VoiceOver on macOS: Cmd+F5; NVDA on Windows)
- Switch a template on.
- ✅ You hear "Done: Custom fields turned on for …" once.
- Remove a section in the page editor.
- ✅ You hear "Section removed. Save to keep the change.", then that there are changes not saved yet.
- Cause an error (for example a failed save or a broken import file).
- ✅ You hear "Error: …" straight away, interrupting other speech.
- Search in a big picker (Tags).
- ✅ You hear how many results were found, or "Nothing matched."
- Click **Copy code**.
- ✅ You hear "Code copied. Paste it into the theme file."
- Move through a notice with the reading keys.
- ✅ The notice starts with its kind ("Error:", "Warning:", "Done:", "Note:"); the icon itself is not read.

---

## 23. Accessibility and browsers (quick pass)

- Tab through the main screen and the Custom fields box using only the keyboard.
- ✅ Every switch, button, picker and gallery control can be reached and used.
- ✅ Notices are announced by a screen reader (VoiceOver or NVDA).
- Repeat sections 3, 4, 5 and 6 in Chrome, Firefox and Safari.
- ✅ Same behaviour, and no errors in the browser console.

---

## Sign-off

- Tested by:
- Date:
- WordPress version:
- PHP version:
- Theme(s):
- Browsers:
- Problems found (section and step):
