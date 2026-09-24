<?php
/**
 * Documentation Content (HTML)
 * File: templates/docs-content.php
 *
 * Plain language, neutral tone: describe what things are and what happens.
 * Developer details are at the end, in "For developers".
 */

if (!defined('ABSPATH')) {
  exit;
}
?>

<p class="yaml-cf-docs-lead">YAML Custom Fields adds custom fields to pages. A field is a box to fill in, like "Headline" or "Team photo". The website then shows that content in the right place.</p>

<nav class="yaml-cf-docs-toc" aria-label="On this page">
  <strong>On this page</strong>
  <ul>
    <li><a href="#words">What the words mean</a></li>
    <li><a href="#writers">Filling in fields</a></li>
    <li><a href="#setup">Setting up fields</a></li>
    <li><a href="#field-types">Field types</a></li>
    <li><a href="#site-parts">Site parts: header, footer and more</a></li>
    <li><a href="#shared">Shared fields</a></li>
    <li><a href="#site-details">Site details</a></li>
    <li><a href="#lists">Collections</a></li>
    <li><a href="#theme">Showing fields on the website</a></li>
    <li><a href="#moving">Backups and moving to another website</a></li>
    <li><a href="#problems">When something does not work</a></li>
    <li><a href="#safety">Safety</a></li>
    <li><a href="#developers">For developers</a></li>
  </ul>
</nav>

<h2 id="words">What the words mean</h2>

<dl class="yaml-cf-docs-glossary">
  <dt>Field</dt>
  <dd>A box to fill in. For example, a title, a photo or a date.</dd>

  <dt>Template</dt>
  <dd>A layout file in the theme. It decides how a page looks. A "Homepage" template, for example.</dd>

  <dt>Schema</dt>
  <dd>A short list of the fields a template gets. It is written in YAML, a simple text format. Each field has a name, a label and a type.</dd>

  <dt>Site parts</dt>
  <dd>Parts that repeat on many pages, like the header and the footer. Also pages that list posts, like a blog or a category page.</dd>

  <dt>Shared fields</dt>
  <dd>Fields with one value for every page that uses the same template. Filled in once.</dd>

  <dt>Site details</dt>
  <dd>Details used across the whole website, like a phone number. Filled in once, under Site details.</dd>

  <dt>Collection</dt>
  <dd>A set of items filled in once, like the team members. Pages can pick items from it.</dd>
</dl>

<h2 id="writers">Filling in fields</h2>

<ol>
  <li>Open a page or post, or create a new one.</li>
  <li>Check the page template on the right. The fields depend on it.</li>
  <li>Scroll down to the <strong>Custom fields</strong> box, below the title.</li>
  <li>Fill in the fields.</li>
  <li>Click <strong>Update</strong> or <strong>Publish</strong> to save.</li>
</ol>

<p><strong>Good to know:</strong> the fields appear in the classic editor. On a site that uses the block editor, the Classic Editor plugin brings them back.</p>

<h3>Images and files</h3>
<p><strong>Choose image</strong> and <strong>Choose file</strong> open the media library. <strong>Remove</strong> empties the field.</p>

<h3>Sections</h3>
<p>Some pages are built from sections, like a banner, a text block or a gallery. <strong>Add section</strong> adds one. The arrows change the order. The trash icon removes a section.</p>

<h3>Shared values</h3>
<p>Some fields show two boxes. The top box holds the <strong>shared value</strong>, the same on every page with this template. The second box is for <strong>this page only</strong>.</p>
<p>The tick box <strong>Use the shared value on this page</strong> switches between them.</p>

<h3>Saving a copy of the fields</h3>
<p>Three links sit at the top of the Custom fields box:</p>
<ul>
  <li><strong>Export fields</strong> saves this page's fields to a file.</li>
  <li><strong>Import fields</strong> fills the fields from such a file, replacing what is there now.</li>
  <li><strong>Empty all fields</strong> clears every field on this page. The change is saved with Update.</li>
</ul>

<h2 id="setup">Setting up fields</h2>

<p>This part is for whoever sets up the website. It is done once per template.</p>

<h3>Step 1: Switch on a template</h3>
<p>Open <strong>YAML Custom Fields</strong> in the menu on the left, find the template in the table and switch it <strong>On</strong>.</p>

<h3>Step 2: Write the schema</h3>
<p><strong>Create schema</strong> opens the editor. Each field is one entry. Here is a schema with three fields:</p>

<pre><code>fields:
  - name: headline
    label: Headline
    type: string
  - name: photo
    label: Photo
    type: image
  - name: intro
    label: Introduction
    type: text</code></pre>

<p>Each field needs three lines:</p>
<ul>
  <li><code>name</code> is the field's code name, in lowercase letters and underscores, like <code>team_photo</code>. The theme uses this name.</li>
  <li><code>label</code> is the text above the box in the editor.</li>
  <li><code>type</code> is the kind of field. See <a href="#field-types">Field types</a>.</li>
</ul>

<p><strong>Tips for writing a schema:</strong></p>
<ul>
  <li>The first line is <code>fields:</code>.</li>
  <li>Each field starts with a dash: <code>- name:</code>.</li>
  <li>Spaces only, no tabs. The lines of one field line up.</li>
  <li>If something is wrong, a message says what to fix. The text stays in the editor.</li>
</ul>

<h3>Step 3: Fill in the content</h3>
<p>The fields appear below the title on any page that uses the template. See <a href="#writers">Filling in fields</a>.</p>

<h3>Step 4: Show the fields on the website</h3>
<p>The theme needs one short line per field. See <a href="#theme">Showing fields on the website</a>.</p>

<h2 id="field-types">Field types</h2>

<p>Every field has a type. The type decides what the box looks like.</p>

<h3>One line of text: <code>string</code></h3>
<pre><code>- name: headline
  label: Headline
  type: string
  options:
    maxlength: 100</code></pre>
<p><code>minlength</code> and <code>maxlength</code> set the shortest and longest text. Both are optional.</p>

<h3>A few lines of text: <code>text</code></h3>
<pre><code>- name: intro
  label: Introduction
  type: text
  options:
    maxlength: 500</code></pre>

<h3>Text with formatting: <code>rich-text</code></h3>
<p>A text editor with bold, italic, links and lists.</p>
<pre><code>- name: body
  label: Main text
  type: rich-text</code></pre>

<h3>Code: <code>code</code></h3>
<p>For embed codes, forms or small pieces of CSS.</p>
<pre><code>- name: newsletter_form
  label: Newsletter form
  type: code
  options:
    language: html</code></pre>
<p>Scripts are kept only when an administrator saves the page. For everyone else, unsafe code is removed on save. See <a href="#safety">Safety</a>.</p>

<h3>Yes or no: <code>boolean</code></h3>
<p>A tick box.</p>
<pre><code>- name: show_banner
  label: Show the banner
  type: boolean
  default: false</code></pre>

<h3>Number: <code>number</code></h3>
<pre><code>- name: price
  label: Price
  type: number
  options:
    min: 0
    max: 9999</code></pre>

<h3>Date: <code>date</code></h3>
<pre><code>- name: event_date
  label: Event date
  type: date
  options:
    time: true</code></pre>
<p><code>time: true</code> adds a time next to the date.</p>

<h3>Dropdown: <code>select</code></h3>
<pre><code>- name: color
  label: Background color
  type: select
  options:
    values:
      - value: light
        label: Light
      - value: dark
        label: Dark</code></pre>
<p><code>multiple: true</code> (next to <code>type</code>) allows more than one choice.</p>

<h3>Category or tag: <code>taxonomy</code></h3>
<pre><code>- name: topic
  label: Topic
  type: taxonomy
  options:
    taxonomy: category</code></pre>
<p><code>category</code> covers categories and <code>post_tag</code> covers tags. <code>multiple: true</code> allows more than one.</p>

<h3>Kind of content: <code>post_type</code></h3>
<p>A dropdown with the kinds of content on the site, like Posts and Pages.</p>
<pre><code>- name: show_type
  label: What to show
  type: post_type</code></pre>

<h3>Item from a collection: <code>data_object</code></h3>
<pre><code>- name: speaker
  label: Speaker
  type: data_object
  options:
    object_type: team-members</code></pre>
<p><code>object_type</code> is the short name of the collection. <code>list: true</code> allows more than one item. See <a href="#lists">Collections</a>.</p>

<h3>Image: <code>image</code></h3>
<pre><code>- name: photo
  label: Photo
  type: image</code></pre>
<p><code>list: true</code> turns the field into a gallery: several images picked in one go, shown as thumbnails and reordered by dragging.</p>
<pre><code>- name: gallery
  label: Gallery
  type: image
  list: true</code></pre>

<h3>File: <code>file</code></h3>
<pre><code>- name: brochure
  label: Brochure (PDF)
  type: file</code></pre>
<p><code>list: true</code> allows several files.</p>

<h3>Group of fields: <code>object</code></h3>
<p>Fields that belong together, like a button's text and link.</p>
<pre><code>- name: button
  label: Button
  type: object
  fields:
    - name: text
      label: Button text
      type: string
    - name: link
      label: Button link
      type: string</code></pre>

<h3>Sections: <code>block</code></h3>
<p>Sections that can be added, removed and reordered in the editor. Each section type has its own fields.</p>
<pre><code>- name: sections
  label: Page sections
  type: block
  list: true
  blockKey: type
  blocks:
    - name: banner
      label: Banner
      fields:
        - name: title
          label: Title
          type: string
        - name: image
          label: Image
          type: image
    - name: text
      label: Text block
      fields:
        - name: body
          label: Text
          type: rich-text</code></pre>
<ul>
  <li><code>list: true</code> allows many sections.</li>
  <li><code>blocks</code> lists the section types.</li>
  <li><code>blockKey</code> is the name that stores the section type. <code>type</code> is the usual choice.</li>
</ul>

<h3>Info box: <code>info</code></h3>
<p>A note in the editor with nothing to fill in. It works on page and post templates.</p>
<pre><code>- info: "Use a photo that is **at least 1200 pixels wide**."</code></pre>
<p><code>**bold**</code>, <code>_italic_</code> and <code>[links](https://example.com)</code> work inside the note.</p>

<h3>Section break: <code>section</code></h3>
<p>A section break splits a long form into segments. The fields after it are grouped under its heading, until the next break.</p>
<pre><code>fields:
  - section: Page basics
  - name: title
    label: Page title
    type: string

  - section: Contact details
  - name: email
    label: Email
    type: string
  - name: phone
    label: Phone
    type: string</code></pre>
<p>The longer form takes a note under the heading:</p>
<pre><code>- type: section
  label: Opening hours
  description: Shown in the footer of every page</code></pre>
<p>Leave the heading out for a plain break with no title:</p>
<pre><code>- section:</code></pre>
<p>On a wide screen short fields sit two or three to a row. To start a new row without drawing anything &mdash; no heading, no line &mdash; use a row break:</p>
<pre><code>- break: true</code></pre>
<p>It is the quiet version of a section break, for when fields belong together but the form reads better with them on their own row.</p>
<p>Section breaks hold no content, so they change nothing about how fields are saved or read in a theme. Fields written before the first break stay where they are, outside any group.</p>

<h3>Extra settings for every field</h3>
<pre><code>- name: headline      # Code name (needed)
  label: Headline     # Text above the box
  type: string        # Kind of field (needed)
  description: Keep it under 60 characters.  # Help text under the label
  default: Welcome    # Filled in on new pages</code></pre>
<p><code>description</code> shows in small text under the label. It is the place for a hint about what belongs in the field.</p>

<h3>Removing or renaming a field</h3>
<p>Taking a field out of a schema, or giving it a different name, does not throw away what was typed into it. The field stops showing, and the content stays where it is.</p>
<p>Putting the field back under the same name brings its content back with it. That is deliberate: a field that disappears from a schema is usually a typo or a rename, and content should not be lost to either.</p>
<p>The same is true for a field inside a group. Fields inside a repeating section are the exception: those sections are stored as a numbered list, so a field removed from one does lose its content.</p>
<p>When content is being kept this way, <strong>Content Check</strong> lists it under "Values with no field", and can remove it when it really is not wanted. Nothing else ever removes it.</p>

<h2 id="site-parts">Site parts: header, footer and more</h2>

<p>Site parts appear on many pages. Their content is filled in once, on the YAML Custom Fields page, not on each post.</p>

<p>These files are found automatically:</p>
<ul>
  <li>Header, footer and sidebar: <code>header.php</code>, <code>footer.php</code>, <code>sidebar.php</code> and versions like <code>footer-shop.php</code></li>
  <li>Content parts: <code>content.php</code>, <code>content-*.php</code>, <code>comments.php</code>, <code>searchform.php</code></li>
  <li>Pages that list posts: <code>index.php</code>, <code>home.php</code>, <code>front-page.php</code>, <code>archive.php</code>, <code>category.php</code>, <code>tag.php</code>, <code>search.php</code>, <code>404.php</code> and similar</li>
</ul>

<p>Other files need a marker. Add <code>@ycf</code> in a comment in the first 30 lines:</p>
<pre><code>&lt;?php
/**
 * Newsletter box
 * @ycf
 */</code></pre>
<p><strong>Refresh list</strong> on the YAML Custom Fields page then picks it up.</p>

<p>A site part works like a template: switch it on, create its schema, then use <strong>Edit content</strong>.</p>

<h3>Block themes</h3>
<p>A block theme keeps its templates as blocks rather than as <code>.php</code> files, and names them without the <code>.php</code>: <code>page</code>, <code>single</code>, <code>single-event</code>. They are listed on the YAML Custom Fields page the same way, and fields are read in the theme with the same functions.</p>
<p>Templates made in the Site Editor appear after they are saved. Use <strong>Refresh templates</strong> if a new one is not in the list yet.</p>
<p>Templates that show a list of posts, like <code>index</code> or <code>archive</code>, are treated as site parts, because their content is not tied to one page.</p>

<h3>Child themes</h3>
<p>A child theme is scanned together with the theme it builds on, so the parent's templates are listed too. When both have a file of the same name, the child's is the one that runs, and there is one entry for it.</p>

<h2 id="shared">Shared fields</h2>

<p>Shared fields have one value for every page with the same template. Useful for something like a banner on all event pages.</p>

<ol>
  <li>Find the template on the YAML Custom Fields page.</li>
  <li><strong>Add shared fields</strong> opens the schema editor for them.</li>
  <li><strong>Edit content</strong> in the Shared fields column opens the boxes to fill in.</li>
</ol>

<p>When a page's schema has a field with the same name, the editor shows both values, and each page can use the shared one or its own.</p>

<p>The value that shows up comes from this order, top one first:</p>
<ol>
  <li>The shared value, when "Use the shared value on this page" is ticked.</li>
  <li>The shared value, for fields that only exist as shared fields.</li>
  <li>The value on the page.</li>
  <li>The shared value, for a page that has never been saved since the field was added. A page saved with the field left empty stays empty: clearing a field is a decision.</li>
  <li>The site detail of the same name, when the template uses site details.</li>
</ol>
<p><code>ycf_get_field('name')</code> and <code>ycf_get_fields()</code> both follow this order, so the same field is the same value whichever one the theme asks with.</p>

<h2 id="site-details">Site details</h2>

<p>Site details are used across the whole website. Good for a phone number, an address or social links.</p>
<ol>
  <li><strong>Create site details</strong> on the YAML Custom Fields page opens the schema editor.</li>
  <li>Save the schema.</li>
  <li><strong>Edit site details</strong> opens the boxes to fill in.</li>
</ol>

<h2 id="lists">Collections</h2>

<p>A collection holds items used on many pages: team members, partners or locations. Each item is filled in once, and pages pick items from the collection.</p>

<ol>
  <li>Open <strong>YAML Custom Fields → Collections</strong>.</li>
  <li><strong>Add a collection</strong> asks for a name, like "Team members", and a short name, like <code>team-members</code>.</li>
  <li>The schema describes one item:
<pre><code>fields:
  - name: name
    label: Name
    type: string
  - name: photo
    label: Photo
    type: image
  - name: role
    label: Role
    type: string</code></pre>
  </li>
  <li><strong>Edit items</strong> opens the list to fill in.</li>
  <li>A <code>data_object</code> field in a page's schema points at the list:
<pre><code>- name: speaker
  label: Speaker
  type: data_object
  options:
    object_type: team-members</code></pre>
  </li>
</ol>

<h2 id="theme">Showing fields on the website</h2>

<p>The theme decides where each field appears. This part is for whoever edits the theme files. Next to each field in the editor, the <strong>Copy code</strong> button copies the line for that field.</p>

<h3>Text and numbers</h3>
<pre><code>&lt;h1&gt;&lt;?php echo esc_html(ycf_get_field('headline')); ?&gt;&lt;/h1&gt;</code></pre>
<p><code>esc_html()</code> keeps the text safe. Text fields always need it.</p>

<h3>Text with formatting</h3>
<pre><code>&lt;?php echo wp_kses_post(ycf_get_field('body')); ?&gt;</code></pre>

<h3>Images</h3>
<pre><code>&lt;?php $photo = ycf_get_image('photo', null, 'large'); ?&gt;
&lt;?php if ($photo) : ?&gt;
  &lt;img src="&lt;?php echo esc_url($photo['url']); ?&gt;" alt="&lt;?php echo esc_attr($photo['alt']); ?&gt;"&gt;
&lt;?php endif; ?&gt;</code></pre>
<p>The size can be <code>thumbnail</code>, <code>medium</code>, <code>large</code> or <code>full</code>. The result also holds <code>width</code>, <code>height</code>, <code>title</code> and <code>caption</code>.</p>

<h3>Galleries</h3>
<p>With <code>list: true</code>, the result is a list of images in the order they were arranged, shown with <code>foreach</code>.</p>
<pre><code>&lt;?php $gallery = ycf_get_image('gallery', null, 'medium'); ?&gt;
&lt;?php if ($gallery) : ?&gt;
  &lt;ul class="gallery"&gt;
    &lt;?php foreach ($gallery as $image) : ?&gt;
      &lt;li&gt;&lt;img src="&lt;?php echo esc_url($image['url']); ?&gt;" alt="&lt;?php echo esc_attr($image['alt']); ?&gt;"&gt;&lt;/li&gt;
    &lt;?php endforeach; ?&gt;
  &lt;/ul&gt;
&lt;?php endif; ?&gt;</code></pre>

<h3>Files</h3>
<pre><code>&lt;?php $file = ycf_get_file('brochure'); ?&gt;
&lt;?php if ($file) : ?&gt;
  &lt;a href="&lt;?php echo esc_url($file['url']); ?&gt;"&gt;Download &lt;?php echo esc_html($file['filename']); ?&gt;&lt;/a&gt;
&lt;?php endif; ?&gt;</code></pre>
<p>With <code>list: true</code>, the result is a list of files, shown with <code>foreach</code>.</p>

<h3>Groups</h3>
<pre><code>&lt;?php $button = ycf_get_field('button'); ?&gt;
&lt;a href="&lt;?php echo esc_url($button['link']); ?&gt;"&gt;&lt;?php echo esc_html($button['text']); ?&gt;&lt;/a&gt;</code></pre>

<h3>Sections</h3>
<pre><code>&lt;?php foreach ((array) ycf_get_field('sections') as $section) : ?&gt;
  &lt;?php if ($section['type'] === 'banner') : ?&gt;
    &lt;?php $image = ycf_get_image('image', null, 'full', $section); ?&gt;
    &lt;h2&gt;&lt;?php echo esc_html(ycf_get_field('title', null, $section)); ?&gt;&lt;/h2&gt;
  &lt;?php elseif ($section['type'] === 'text') : ?&gt;
    &lt;?php echo wp_kses_post(ycf_get_field('body', null, $section)); ?&gt;
  &lt;?php endif; ?&gt;
&lt;?php endforeach; ?&gt;</code></pre>
<p>Inside a section, the section goes in as the last value: <code>ycf_get_field('title', null, $section)</code>.</p>

<h3>Categories and tags</h3>
<pre><code>&lt;?php $topic = ycf_get_term('topic'); ?&gt;
&lt;?php if ($topic) : ?&gt;
  &lt;a href="&lt;?php echo esc_url(get_term_link($topic)); ?&gt;"&gt;&lt;?php echo esc_html($topic-&gt;name); ?&gt;&lt;/a&gt;
&lt;?php endif; ?&gt;</code></pre>

<h3>Items from a collection</h3>
<pre><code>&lt;?php $speaker = ycf_get_data_object('speaker'); ?&gt;
&lt;?php if ($speaker) : ?&gt;
  &lt;p&gt;&lt;?php echo esc_html($speaker['name']); ?&gt;&lt;/p&gt;
&lt;?php endif; ?&gt;

&lt;?php // Every item in a list ?&gt;
&lt;?php foreach (ycf_get_data_objects('team-members') as $member) : ?&gt;
  &lt;li&gt;&lt;?php echo esc_html($member['name']); ?&gt;&lt;/li&gt;
&lt;?php endforeach; ?&gt;</code></pre>

<h3>Site parts</h3>
<p>The file name goes after <code>partial:</code>:</p>
<pre><code>&lt;?php echo esc_html(ycf_get_field('phone', 'partial:header.php')); ?&gt;</code></pre>

<h3>Site details</h3>
<pre><code>&lt;?php echo esc_html(ycf_get_global_field('phone')); ?&gt;</code></pre>

<h3>Code fields</h3>
<p>Code fields print as they are, so the code works:</p>
<pre><code>&lt;?php echo ycf_get_field('newsletter_form'); ?&gt;</code></pre>
<p>CSS needs its tags removed first:</p>
<pre><code>&lt;style&gt;&lt;?php echo wp_strip_all_tags(ycf_get_field('custom_css')); ?&gt;&lt;/style&gt;</code></pre>

<h3>All the functions</h3>
<table class="widefat striped yaml-cf-docs-table">
  <thead>
    <tr><th>Function</th><th>What it returns</th></tr>
  </thead>
  <tbody>
    <tr><td><code>ycf_get_field('name')</code></td><td>The value of one field</td></tr>
    <tr><td><code>ycf_get_fields()</code></td><td>All fields of the page at once</td></tr>
    <tr><td><code>ycf_has_field('name')</code></td><td>Yes, when the field has a value</td></tr>
    <tr><td><code>ycf_get_image('name', null, 'large')</code></td><td>An image with its link, size and alt text</td></tr>
    <tr><td><code>ycf_get_file('name')</code></td><td>A file with its link, name and size</td></tr>
    <tr><td><code>ycf_get_term('name')</code></td><td>The chosen category or tag</td></tr>
    <tr><td><code>ycf_get_post_type('name')</code></td><td>The chosen kind of content</td></tr>
    <tr><td><code>ycf_get_data_object('name')</code></td><td>The chosen item from a collection</td></tr>
    <tr><td><code>ycf_get_data_objects('short-name')</code></td><td>Every item in a collection</td></tr>
    <tr><td><code>ycf_get_global_field('name')</code></td><td>One of the site details</td></tr>
    <tr><td><code>ycf_get_global_fields()</code></td><td>All site details</td></tr>
  </tbody>
</table>
<p>A post ID as the second value reads another page: <code>ycf_get_field('headline', 42)</code>. Each function also has a long name that starts with <code>yaml_cf_</code>, like <code>yaml_cf_get_field()</code>.</p>

<h2 id="moving">Backups and moving to another website</h2>

<p><strong>YAML Custom Fields → Export &amp; Import</strong> writes one file holding whatever is chosen:</p>
<ul>
  <li><strong>Setup</strong>: each template schema, shared fields and content, site details, and which templates are switched on. Every piece is ticked on its own, so a single template can be exported by itself.</li>
  <li><strong>Pages and posts</strong>: the fields filled in on each of the chosen pages and posts.</li>
  <li><strong>Collections</strong>: the chosen collections and their items.</li>
</ul>

<h3>Exporting</h3>
<p>The export runs in three steps: tick what to include, choose the pages and posts (only if they were ticked), then review and save the file.</p>

<h3>Importing</h3>
<p>Choose the file on the same page of the other website. <strong>Nothing changes when the file is chosen.</strong> The page first lists everything the file holds and marks each row:</p>
<ul>
  <li><strong>new</strong>: this website does not have it yet.</li>
  <li><strong>exists here - will overwrite</strong>: this website already has it, and importing replaces it.</li>
  <li><strong>not on this website</strong>: a post in the file that has no match here. It cannot be imported and is greyed out.</li>
</ul>
<p>Untick anything that should be left alone, then import. Whatever is not ticked stays exactly as it is, so importing one template's schema never disturbs the others.</p>

<p>Files exported by older versions of the plugin still import, and so do the separate setup, post and list files.</p>

<p><strong>Images and files are not inside the export file.</strong> Only links to them are, so the media library has to be copied as well. <strong>Content Check</strong> then lists posts with missing images or files.</p>

<h2 id="problems">When something does not work</h2>

<h3>The fields do not appear on a page</h3>
<ol>
  <li>The template has to be switched <strong>On</strong>.</li>
  <li>The page has to use that template. "Template" on the right side of the editor shows which one.</li>
  <li>The schema has to show a number of fields, not "Fix schema".</li>
  <li>Reload the page.</li>
</ol>

<h3>The schema does not save</h3>
<p>The message at the top says what is wrong. Common mistakes:</p>
<ul>
  <li>A tab instead of spaces. Spaces only.</li>
  <li>Lines of one field that do not line up.</li>
  <li>A missing <code>name</code> or <code>type</code>.</li>
  <li>A colon inside a label. Quotes fix it: <code>label: "Note: read this"</code>.</li>
</ul>

<h3>A new template is not in the list</h3>
<p><strong>Refresh list</strong> on the YAML Custom Fields page scans the theme again.</p>

<h3>The field shows in the editor, but not on the website</h3>
<p>The theme needs a line for that field. See <a href="#theme">Showing fields on the website</a>.</p>

<h3>Still stuck?</h3>
<p><a href="https://github.com/maliMirkec/yaml-custom-fields/issues" target="_blank" rel="noopener">Open an issue on GitHub</a>. Helpful details: what was done, what was expected, what happened, and a screenshot.</p>

<h2 id="safety">Safety</h2>
<ul>
  <li>Only administrators can change schemas, settings and site details.</li>
  <li>Writers fill in fields on the posts they are allowed to edit.</li>
  <li>Everything typed into a field is cleaned before it is saved. Scripts are removed.</li>
  <li>Code fields keep scripts only when an administrator saves the page. This is the same rule WordPress uses for its Custom HTML block.</li>
  <li>Deleting the plugin removes all its settings and field content.</li>
</ul>

<details class="yaml-cf-docs-developers" id="developers">
  <summary><h2>For developers</h2></summary>

  <h3>Requirements</h3>
  <ul>
    <li>WordPress 5.0 or newer</li>
    <li>PHP 8.1 or newer</li>
    <li>Includes the Symfony YAML component 6.4 (MIT license), scoped to avoid conflicts</li>
  </ul>

  <h3>Where data is stored</h3>
  <ul>
    <li>Page fields: post meta <code>_yaml_cf_data</code>. The "use shared value" choices: <code>_yaml_cf_use_template_global_fields</code>.</li>
    <li>Schemas: option <code>yaml_cf_schemas</code>. Switched-on templates: <code>yaml_cf_template_settings</code>.</li>
    <li>Site parts: option <code>yaml_cf_partial_data</code>.</li>
    <li>Shared fields: options <code>yaml_cf_template_global_schemas</code> and <code>yaml_cf_template_global_data</code>.</li>
    <li>Site details: options <code>yaml_cf_global_schema</code> and <code>yaml_cf_global_data</code>.</li>
    <li>Collections: option <code>yaml_cf_data_object_types</code>, items in <code>yaml_cf_data_object_entries_{short-name}</code>.</li>
  </ul>
  <p>Image and file fields store attachment IDs. Code fields are stored base64-encoded and decoded by the functions above.</p>
  <p>None of these options are loaded on every page view. They are read when something asks for them.</p>

  <h3>Deleting the plugin</h3>
  <p>Deactivating the plugin changes nothing. <strong>Deleting</strong> it removes the schemas, the site details, the collections and the content filled into the fields on every page and post, and that cannot be undone.</p>
  <p>To keep the content, tick <strong>Keep the content if the plugin is deleted</strong> at the bottom of the YAML Custom Fields page before deleting. The content then stays in the database and comes back if the plugin is installed again.</p>

  <h3>Function signatures</h3>
  <pre><code>ycf_get_field($field_name, $post_id = null, $context_data = null)
ycf_get_fields($post_id = null)
ycf_has_field($field_name, $post_id = null)
ycf_get_image($field_name, $post_id = null, $size = 'full', $context_data = null)
ycf_get_file($field_name, $post_id = null, $context_data = null)
ycf_get_term($field_name, $post_id = null, $context_data = null)
ycf_get_post_type($field_name, $post_id = null, $context_data = null)
ycf_get_data_object($field_name, $post_id = null, $context_data = null)
ycf_get_data_objects($object_type)
ycf_get_global_field($field_name)
ycf_get_global_fields()</code></pre>
  <p><code>$post_id</code> takes a post ID or <code>'partial:header.php'</code>. <code>$context_data</code> takes a section (block item) array.</p>

  <h3>Uninstall</h3>
  <p>Deleting the plugin runs <code>uninstall.php</code>. It removes all <code>yaml_cf_</code> options and transients and all <code>_yaml_cf_</code> post meta, on every site of a multisite network.</p>

  <h3>Contributing</h3>
  <p>The code is on <a href="https://github.com/maliMirkec/yaml-custom-fields" target="_blank" rel="noopener">GitHub</a>. Pull requests are welcome. The code follows the WordPress coding standards, and <code>composer test</code> runs the tests.</p>
</details>

<hr>

<p>YAML Custom Fields is made by <a href="https://www.cita.hr" target="_blank" rel="noopener">Studio Cita</a>. It is free software under the GPL v2 or later license.</p>
