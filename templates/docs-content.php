<?php
/**
 * Documentation Content (HTML)
 * File: templates/docs-content.php
 */

if (!defined('ABSPATH')) {
  exit;
}
?>

<p>A WordPress plugin for managing YAML frontmatter schemas in theme templates and partials. YAML Custom Fields allows you to define structured content schemas with an intuitive interface and ACF-like template functions.</p>

<blockquote>Vibe-coded with Claude.</blockquote>

<h2>Features</h2>

<ul>
  <li>🎨 <strong>Define YAML schemas</strong> for page templates and template partials</li>
  <li>📝 <strong>15+ field types</strong> including string, rich-text, images, blocks, taxonomies, post types, data objects, and more</li>
  <li>🔧 <strong>Beautiful admin interface</strong> with branded header and intuitive controls</li>
  <li>🎯 <strong>Three-level data hierarchy:</strong>
    <ul>
      <li>Per-page data for individual customization (stored in post meta)</li>
      <li>Per-template global data shared across all posts using the same template</li>
      <li>Site-wide global data for partials like headers and footers</li>
    </ul>
  </li>
  <li>🔀 <strong>Per-field global/local toggle</strong> - Each field can independently use template global data or page-specific data</li>
  <li>👀 <strong>Visual dual-field interface</strong> - See both template global and page-specific values side-by-side</li>
  <li>📦 <strong>Data Objects</strong> - Manage structured, reusable data (universities, companies, team members, etc.)</li>
  <li>🚀 <strong>Simple template functions</strong> with ACF-like syntax and auto-merge behavior</li>
  <li>🗑️ <strong>Clear buttons</strong> for image and file fields</li>
  <li>🔄 <strong>Reset all data</strong> button with confirmation</li>
  <li>🔒 <strong>Administrator-only access</strong> for security</li>
  <li>🧹 <strong>Clean uninstall</strong> removes all database records</li>
  <li>⚡ <strong>WordPress Coding Standards compliant</strong> with optimized performance</li>
</ul>

<h2>Screenshots</h2>

<h3>1. Main Admin Page</h3>
<p><img src="<?php echo esc_url(YAML_CF_PLUGIN_URL . 'screenshot-1.png'); ?>" alt="Main YAML Custom Fields admin page" style="max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 4px; margin: 10px 0;" /></p>
<p><em>Main admin page showing page templates and template partials with enable/disable toggles</em></p>

<h3>2. Schema Editor - Page Templates</h3>
<p><img src="<?php echo esc_url(YAML_CF_PLUGIN_URL . 'screenshot-2.png'); ?>" alt="Schema editor for page templates" style="max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 4px; margin: 10px 0;" /></p>
<p><em>Schema editor for main page templates with YAML syntax for defining custom fields</em></p>

<h3>3. Schema Editor - Partial Templates</h3>
<p><img src="<?php echo esc_url(YAML_CF_PLUGIN_URL . 'screenshot-3.png'); ?>" alt="Schema editor for partials" style="max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 4px; margin: 10px 0;" /></p>
<p><em>Schema editor for partial templates (headers, footers, etc.)</em></p>

<h3>4. Partial Data Editor</h3>
<p><img src="<?php echo esc_url(YAML_CF_PLUGIN_URL . 'screenshot-4.png'); ?>" alt="Partial data editor" style="max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 4px; margin: 10px 0;" /></p>
<p><em>Partial data editor for managing global content in template partials</em></p>

<h3>5. Documentation Page</h3>
<p><img src="<?php echo esc_url(YAML_CF_PLUGIN_URL . 'screenshot-5.png'); ?>" alt="Documentation page" style="max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 4px; margin: 10px 0;" /></p>
<p><em>Comprehensive documentation with guides and examples</em></p>

<h2>Installation</h2>

<h3>From WordPress Plugin Directory (Recommended)</h3>

<ol>
  <li>Log in to your WordPress admin dashboard</li>
  <li>Navigate to <strong>Plugins → Add New</strong></li>
  <li>Search for "YAML Custom Fields"</li>
  <li>Click <strong>Install Now</strong> next to the YAML Custom Fields plugin</li>
  <li>Click <strong>Activate</strong> after installation completes</li>
  <li>Go to <strong>YAML Custom Fields</strong> in the admin menu to configure your schemas</li>
</ol>

<h3>Manual Installation</h3>

<p>If you're installing from source or a ZIP file:</p>

<ol>
  <li>Upload the <code>yaml-custom-fields</code> folder to <code>/wp-content/plugins/</code></li>
  <li>If installing from source, navigate to the plugin directory and install dependencies:
    <pre><code>cd wp-content/plugins/yaml-custom-fields
composer install</code></pre>
    <strong>Note:</strong> If you downloaded from the WordPress plugin directory, dependencies are already included.
  </li>
  <li>Activate the plugin through the <strong>Plugins</strong> menu in WordPress</li>
  <li>Go to <strong>YAML Custom Fields</strong> in the admin menu to configure your schemas</li>
</ol>

<h2>Quick Start</h2>

<h3>1. Enable YAML for a Template</h3>

<ol>
  <li>Go to <strong>YAML Custom Fields</strong> in your WordPress admin</li>
  <li>Find your template in the "Page Templates" section</li>
  <li>Toggle the "Enable YAML" switch</li>
  <li>Click "Add Schema" or "Edit Schema"</li>
</ol>

<h3>2. Define a Schema</h3>

<p>Here's an example schema for a landing page template:</p>

<pre><code>fields:
  - name: hero_title
    label: Page Title
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
  - name: description
    label: Description
    type: text
    options:
      maxlength: 500
  - name: cta_button
    label: Call to Action Button
    type: object
    fields:
      - name: text
        label: Button Text
        type: string
      - name: url
        label: Button URL
        type: string
  - name: features
    label: Feature Sections
    type: block
    list: true
    blockKey: type
    blocks:
      - name: feature
        label: Feature Block
        fields:
          - name: title
            label: Feature Title
            type: string
          - name: description
            label: Feature Description
            type: text
          - name: icon
            label: Icon
            type: image</code></pre>

<h3>3. Edit Page Data</h3>

<ol>
  <li>Create or edit a page/post</li>
  <li>Select your template from the <strong>Template</strong> dropdown</li>
  <li>The <strong>YAML Custom Fields Schema</strong> meta box appears below the editor</li>
  <li>Fill in your custom fields</li>
  <li>Publish!</li>
</ol>

<h3>4. Use Fields in Your Template</h3>

<p>In your theme template file (e.g., <code>page-landing.php</code>):</p>

<pre><code>&lt;?php
// Get individual fields using short alias
$hero_title = ycf_get_field('hero_title');
$hero_image = ycf_get_image('hero_image');
$description = ycf_get_field('description');
$cta = ycf_get_field('cta_button');
$features = ycf_get_field('features');
?&gt;

&lt;div class="hero"&gt;
  &lt;?php if ($hero_image): ?&gt;
    &lt;img src="&lt;?php echo esc_url($hero_image['url']); ?&gt;"
         alt="&lt;?php echo esc_attr($hero_image['alt']); ?&gt;"
         width="&lt;?php echo esc_attr($hero_image['width']); ?&gt;"
         height="&lt;?php echo esc_attr($hero_image['height']); ?&gt;"&gt;
  &lt;?php endif; ?&gt;

  &lt;h1&gt;&lt;?php echo esc_html($hero_title); ?&gt;&lt;/h1&gt;
  &lt;p&gt;&lt;?php echo esc_html($description); ?&gt;&lt;/p&gt;

  &lt;?php if ($cta): ?&gt;
    &lt;a href="&lt;?php echo esc_url($cta['url']); ?&gt;" class="button"&gt;
      &lt;?php echo esc_html($cta['text']); ?&gt;
    &lt;/a&gt;
  &lt;?php endif; ?&gt;
&lt;/div&gt;

&lt;?php if ($features): ?&gt;
  &lt;div class="features"&gt;
    &lt;?php foreach ($features as $feature): ?&gt;
      &lt;div class="feature"&gt;
        &lt;?php if (!empty($feature['icon'])): ?&gt;
          &lt;img src="&lt;?php echo esc_url($feature['icon']); ?&gt;" alt=""&gt;
        &lt;?php endif; ?&gt;
        &lt;h3&gt;&lt;?php echo esc_html($feature['title']); ?&gt;&lt;/h3&gt;
        &lt;p&gt;&lt;?php echo esc_html($feature['description']); ?&gt;&lt;/p&gt;
      &lt;/div&gt;
    &lt;?php endforeach; ?&gt;
  &lt;/div&gt;
&lt;?php endif; ?&gt;</code></pre>

<h2>Template Functions</h2>

<p>YAML Custom Fields provides ACF-like template functions for retrieving your data:</p>

<h3><code>ycf_get_field($field_name, $post_id = null, $context_data = null)</code></h3>

<p>Get a single field value.</p>

<pre><code>// For current page/post
$title = ycf_get_field('hero_title');

// For specific post ID
$title = ycf_get_field('hero_title', 123);

// For partials
$logo = ycf_get_field('logo', 'partial:header.php');
$copyright = ycf_get_field('copyright', 'partial:footer.php');

// For partials in subdirectories
$menu = ycf_get_field('menu_items', 'partial:partials/navigation.php');

// For block fields (using context_data parameter)
$blocks = ycf_get_field('features');
foreach ($blocks as $block) {
  $title = ycf_get_field('title', null, $block);
  $description = ycf_get_field('description', null, $block);
}</code></pre>

<h3><code>ycf_get_image($field_name, $post_id = null, $size = 'full', $context_data = null)</code></h3>

<p>Get comprehensive image data including URL, alt text, dimensions, and more.</p>

<pre><code>// Basic usage
$image = ycf_get_image('hero_image');

// With custom size
$thumbnail = ycf_get_image('hero_image', null, 'thumbnail');

// In blocks
$blocks = ycf_get_field('features');
foreach ($blocks as $block) {
  $icon = ycf_get_image('icon', null, 'medium', $block);
  if ($icon) {
    echo '&lt;img src="' . esc_url($icon['url']) . '" alt="' . esc_attr($icon['alt']) . '"&gt;';
  }
}</code></pre>

<p><strong>Returns:</strong> Array with <code>id</code>, <code>url</code>, <code>alt</code>, <code>title</code>, <code>caption</code>, <code>description</code>, <code>width</code>, <code>height</code></p>

<h3><code>ycf_get_file($field_name, $post_id = null, $context_data = null)</code></h3>

<p>Get comprehensive file data including URL, path, size, and MIME type.</p>

<pre><code>// Basic usage
$pdf = ycf_get_file('brochure');

// In blocks
$blocks = ycf_get_field('downloads');
foreach ($blocks as $block) {
  $file = ycf_get_file('document', null, $block);
  if ($file) {
    echo '&lt;a href="' . esc_url($file['url']) . '"&gt;' . esc_html($file['filename']) . '&lt;/a&gt;';
  }
}</code></pre>

<p><strong>Returns:</strong> Array with <code>id</code>, <code>url</code>, <code>path</code>, <code>filename</code>, <code>filesize</code>, <code>mime_type</code>, <code>title</code></p>

<h3><code>ycf_get_term($field_name, $post_id = null, $context_data = null)</code></h3>

<p>Get WordPress taxonomy term(s) from a taxonomy field.</p>

<pre><code>// Single term
$category = ycf_get_term('category');
if ($category) {
  echo esc_html($category->name);
  echo '&lt;a href="' . esc_url(get_term_link($category)) . '"&gt;View category&lt;/a&gt;';
}

// Multiple terms
$tags = ycf_get_term('tags');
if ($tags) {
  foreach ($tags as $tag) {
    echo esc_html($tag->name) . ' ';
  }
}

// In blocks
$blocks = ycf_get_field('content_sections');
foreach ($blocks as $block) {
  $term = ycf_get_term('category', null, $block);
  if ($term) {
    echo esc_html($term->name);
  }
}</code></pre>

<p><strong>Returns:</strong> <code>WP_Term</code> object (single) or array of <code>WP_Term</code> objects (multiple), or <code>null</code> if not set</p>

<h3><code>ycf_get_fields($post_id = null)</code></h3>

<p>Get all fields at once.</p>

<pre><code>// For current page/post
$fields = ycf_get_fields();
// Returns: ['hero_title' => 'Welcome', 'description' => '...', ...]

// For partials
$header_data = ycf_get_fields('partial:header.php');</code></pre>

<h3><code>ycf_has_field($field_name, $post_id = null)</code></h3>

<p>Check if a field exists and has a value.</p>

<pre><code>if (ycf_has_field('hero_title')) {
  echo '&lt;h1&gt;' . esc_html(ycf_get_field('hero_title')) . '&lt;/h1&gt;';
}

// For partials
if (ycf_has_field('logo', 'partial:header.php')) {
  $logo = ycf_get_field('logo', 'partial:header.php');
}</code></pre>

<p><strong>Long-form aliases</strong> are also available:</p>
<ul>
  <li><code>yaml_cf_get_field()</code></li>
  <li><code>yaml_cf_get_fields()</code></li>
  <li><code>yaml_cf_has_field()</code></li>
</ul>

<h2>Working with Partials</h2>

<p>Partials (like <code>header.php</code>, <code>footer.php</code>, <code>sidebar.php</code>) have <strong>global, site-wide data</strong> that you manage from the YAML Custom Fields admin page.</p>

<h3>Partial Detection</h3>

<p>YAML Custom Fields automatically detects partials in two ways:</p>

<h4>Automatic Detection (Standard Partials)</h4>

<p>Common WordPress partials are detected automatically:</p>
<ul>
  <li><code>header.php</code>, <code>header-*.php</code></li>
  <li><code>footer.php</code>, <code>footer-*.php</code></li>
  <li><code>sidebar.php</code>, <code>sidebar-*.php</code></li>
  <li><code>content.php</code>, <code>content-*.php</code></li>
  <li><code>comments.php</code>, <code>searchform.php</code></li>
</ul>

<h4>Manual Detection (Custom Partials)</h4>

<p>For custom partials with non-standard names, add the <code>@ycf</code> marker in the file header:</p>

<pre><code>&lt;?php
/**
 * Custom Navigation Partial
 * @ycf
 */

// Your template code here</code></pre>

<p>The marker can appear anywhere in the <strong>first 30 lines</strong> of the file, in any comment style:</p>

<pre><code>&lt;?php
// @ycf - Enable YAML Custom Fields for this partial

/* @ycf */

/**
 * Some description
 * @ycf
 */</code></pre>

<p>After adding the marker, click the <strong>"Refresh Template List"</strong> button in the YAML Custom Fields admin page.</p>

<h3>Example: Header Partial</h3>

<p><strong>Schema</strong> for <code>header.php</code>:</p>

<pre><code>fields:
  - name: logo
    label: Site Logo
    type: image
  - name: site_title
    label: Site Title
    type: string
  - name: show_search
    label: Show Search Bar
    type: boolean
  - name: header_category
    label: Featured Category
    type: taxonomy
    options:
      taxonomy: category
  - name: menu_cta
    label: Menu CTA Button
    type: object
    fields:
      - name: text
        label: Button Text
        type: string
      - name: url
        label: Button URL
        type: string</code></pre>

<p><strong>Usage</strong> in <code>header.php</code>:</p>

<pre><code>&lt;?php
$logo = ycf_get_field('logo', 'partial:header.php');
$site_title = ycf_get_field('site_title', 'partial:header.php');
$show_search = ycf_get_field('show_search', 'partial:header.php');
$menu_cta = ycf_get_field('menu_cta', 'partial:header.php');
?&gt;

&lt;header class="site-header"&gt;
  &lt;div class="logo"&gt;
    &lt;?php if ($logo): ?&gt;
      &lt;img src="&lt;?php echo esc_url($logo); ?&gt;" alt="&lt;?php echo esc_attr($site_title); ?&gt;"&gt;
    &lt;?php else: ?&gt;
      &lt;h1&gt;&lt;?php echo esc_html($site_title); ?&gt;&lt;/h1&gt;
    &lt;?php endif; ?&gt;
  &lt;/div&gt;

  &lt;nav&gt;
    &lt;?php wp_nav_menu(['theme_location' => 'primary']); ?&gt;
  &lt;/nav&gt;

  &lt;?php if ($menu_cta): ?&gt;
    &lt;a href="&lt;?php echo esc_url($menu_cta['url']); ?&gt;" class="cta-button"&gt;
      &lt;?php echo esc_html($menu_cta['text']); ?&gt;
    &lt;/a&gt;
  &lt;?php endif; ?&gt;

  &lt;?php if ($show_search): ?&gt;
    &lt;?php get_search_form(); ?&gt;
  &lt;?php endif; ?&gt;
&lt;/header&gt;</code></pre>

<h2>Template Global Fields</h2>

<p>Template Global Fields allow you to define default values that are shared across all posts using the same template, while still allowing individual posts to override specific fields.</p>

<h3>Setting Up Template Global</h3>

<ol>
  <li>Go to <strong>YAML Custom Fields</strong> admin page</li>
  <li>Enable YAML for your template (e.g., <code>page.php</code>)</li>
  <li>Click <strong>Add Template Global</strong> to define the template global schema</li>
  <li>Define fields that should have shared default values</li>
  <li>Click <strong>Manage Template Global Data</strong> to set the default values</li>
</ol>

<h3>Using Template Global in Posts</h3>

<p>When editing a post that uses a template with Template Global fields, you'll see a dual-field interface for each field:</p>

<ul>
  <li><strong>Template Global (All Pages)</strong> - Read-only display showing the default value (with Edit link)</li>
  <li><strong>Page-Specific Value</strong> - Editable field for this post only</li>
  <li><strong>Checkbox</strong> - "Use template global for this field" - Toggle per field</li>
</ul>

<h3>Benefits</h3>

<ul>
  <li><strong>Consistency:</strong> Set default values once, use across all posts</li>
  <li><strong>Flexibility:</strong> Override any field on any post individually</li>
  <li><strong>Clarity:</strong> See both global and local values side-by-side</li>
  <li><strong>Efficiency:</strong> Update template global to affect all posts at once</li>
</ul>

<h3>Data Priority</h3>

<p>When a field uses template global, <code>ycf_get_field()</code> returns data in this priority order:</p>

<ol>
  <li>Page-specific value (if "use template global" is unchecked)</li>
  <li>Template global value (if "use template global" is checked)</li>
  <li>Site-wide global value (if template has site-wide global enabled)</li>
  <li><code>null</code> (if no value exists)</li>
</ol>

<p><strong>Example:</strong> If you set a default hero background image in Template Global, all pages using that template will show that image by default. Individual pages can override it by unchecking "Use template global for this field" and uploading their own image.</p>

<h2>Field Types</h2>

<p>YAML Custom Fields supports the following field types:</p>

<h3>String</h3>

<p>Single-line text input.</p>

<pre><code>- name: title
  label: Page Title
  type: string
  options:
    minlength: 3
    maxlength: 100</code></pre>

<p><strong>Options:</strong></p>
<ul>
  <li><code>minlength</code> - Minimum character length</li>
  <li><code>maxlength</code> - Maximum character length</li>
</ul>

<h3>Text</h3>

<p>Multi-line textarea.</p>

<pre><code>- name: description
  label: Description
  type: text
  options:
    maxlength: 500</code></pre>

<p><strong>Options:</strong></p>
<ul>
  <li><code>maxlength</code> - Maximum character length</li>
</ul>

<h3>Rich Text</h3>

<p>WordPress WYSIWYG editor with full formatting.</p>

<pre><code>- name: content
  label: Page Content
  type: rich-text</code></pre>

<h3>Code</h3>

<p>Code editor for storing HTML, CSS, or JavaScript code.</p>

<pre><code>- name: custom_css
  label: Custom CSS
  type: code
  options:
    language: css</code></pre>

<p><strong>Options:</strong></p>
<ul>
  <li><code>language</code> - Code language: html, css, javascript, js, php, python, etc.</li>
</ul>

<p><strong>Security &amp; Sanitization:</strong></p>
<ul>
  <li><strong>Administrators:</strong> Can store raw HTML, JavaScript, and forms. CSS is sanitized to remove dangerous patterns (e.g., <code>expression()</code>, <code>javascript:</code>).</li>
  <li><strong>Non-administrators:</strong> HTML is sanitized using <code>wp_kses_post()</code> (safe tags only), JavaScript is stripped completely.</li>
  <li>Uses WordPress's <code>unfiltered_html</code> capability - the same security model as Gutenberg's Custom HTML block.</li>
</ul>

<p><strong>Usage in templates:</strong></p>
<pre><code>&lt;?php
// Output code field (don't escape for HTML/JS)
echo ycf_get_field('mailchimp_form', 'partial:footer.php');

// For CSS, wrap in style tags
echo '&lt;style&gt;' . esc_html(ycf_get_field('custom_css')) . '&lt;/style&gt;';
?&gt;</code></pre>

<p><strong>⚠️ Important:</strong> Only use unescaped output for code fields where you (the administrator) control the content. Never use for untrusted user-generated content.</p>

<h3>Boolean</h3>

<p>Checkbox for true/false values.</p>

<pre><code>- name: featured
  label: Featured Post
  type: boolean
  default: false</code></pre>

<h3>Number</h3>

<p>Number input with optional constraints.</p>

<pre><code>- name: price
  label: Price
  type: number
  options:
    min: 0
    max: 9999</code></pre>

<p><strong>Options:</strong></p>
<ul>
  <li><code>min</code> - Minimum value</li>
  <li><code>max</code> - Maximum value</li>
</ul>

<h3>Date</h3>

<p>Date picker with optional time.</p>

<pre><code>- name: event_date
  label: Event Date
  type: date
  options:
    time: true</code></pre>

<p><strong>Options:</strong></p>
<ul>
  <li><code>time</code> - Set to <code>true</code> to include time selection</li>
</ul>

<h3>Select</h3>

<p>Dropdown selection.</p>

<pre><code>- name: category
  label: Category
  type: select
  options:
    multiple: false
    values:
      - value: news
        label: News
      - value: blog
        label: Blog Posts
      - value: events
        label: Events</code></pre>

<p><strong>Options:</strong></p>
<ul>
  <li><code>multiple</code> - Allow multiple selections</li>
  <li><code>values</code> - Array of options with <code>value</code> and <code>label</code> keys</li>
</ul>

<h3>Taxonomy</h3>

<p>WordPress taxonomy selector for categories, tags, or custom taxonomies.</p>

<pre><code>- name: category
  label: Post Category
  type: taxonomy
  options:
    taxonomy: category

- name: tags
  label: Tags
  type: taxonomy
  multiple: true
  options:
    taxonomy: post_tag

- name: custom_tax
  label: Custom Taxonomy
  type: taxonomy
  options:
    taxonomy: your_custom_taxonomy</code></pre>

<p><strong>Options:</strong></p>
<ul>
  <li><code>taxonomy</code> - WordPress taxonomy name (category, post_tag, or any custom taxonomy)</li>
  <li><code>multiple</code> - Set to <code>true</code> to allow multiple term selection</li>
</ul>

<p><strong>Usage in templates:</strong></p>

<pre><code>&lt;?php
// Get single term
$category = ycf_get_term('category');
if ($category) {
  echo '&lt;span&gt;' . esc_html($category->name) . '&lt;/span&gt;';
  echo '&lt;a href="' . esc_url(get_term_link($category)) . '"&gt;View more&lt;/a&gt;';
}

// Get multiple terms
$tags = ycf_get_term('tags');
if ($tags) {
  foreach ($tags as $tag) {
    echo '&lt;a href="' . esc_url(get_term_link($tag)) . '"&gt;';
    echo esc_html($tag->name);
    echo '&lt;/a&gt; ';
  }
}

// In blocks - use context_data parameter
$blocks = ycf_get_field('content_blocks');
foreach ($blocks as $block) {
  $block_category = ycf_get_term('category', null, $block);
  if ($block_category) {
    echo esc_html($block_category->name);
  }
}
?&gt;</code></pre>

<p><strong>Returns:</strong> <code>WP_Term</code> object (single selection) or array of <code>WP_Term</code> objects (multiple selection), or <code>null</code> if not set.</p>

<h3>Post Type</h3>

<p>Dropdown selector for registered WordPress post types (Post, Page, and custom post types).</p>

<pre><code>- name: content_type
  label: Content Type
  type: post_type

- name: archive_type
  label: Archive Type
  type: post_type</code></pre>

<p><strong>Usage in templates:</strong></p>

<pre><code>&lt;?php
// Get post type object
$post_type = ycf_get_post_type('content_type');
if ($post_type) {
  echo '&lt;h2&gt;' . esc_html($post_type->label) . '&lt;/h2&gt;';
  echo '&lt;p&gt;Slug: ' . esc_html($post_type->name) . '&lt;/p&gt;';

  // Query posts of this type
  $query = new WP_Query([
    'post_type' => $post_type->name,
    'posts_per_page' => 10
  ]);
}

// In blocks - use context_data parameter
$blocks = ycf_get_field('content_blocks');
foreach ($blocks as $block) {
  $block_post_type = ycf_get_post_type('type', null, $block);
  if ($block_post_type) {
    echo esc_html($block_post_type->label);
  }
}
?&gt;</code></pre>

<p><strong>Returns:</strong> <code>WP_Post_Type</code> object or <code>null</code> if not set.</p>

<h3>Data Object</h3>

<p>Reference to structured data objects that you define and manage independently of your templates. Perfect for reusable data like universities, companies, team members, or any other structured entities.</p>

<p><strong>How it works:</strong></p>
<ol>
  <li>Navigate to <strong>YAML CF &gt; Data Objects</strong> in the admin menu</li>
  <li>Create a new data object type (e.g., "Universities")</li>
  <li>Define its schema using YAML (e.g., name, logo, website, description)</li>
  <li>Add entries through the Manage Entries interface</li>
  <li>Reference these entries in your page schemas using the data_object field type</li>
</ol>

<p><strong>Example: Creating a Universities data object type</strong></p>

<p>First, create the type with this schema in Data Objects admin:</p>

<pre><code>fields:
  - name: name
    label: University Name
    type: string
    required: true
  - name: logo
    label: Logo
    type: image
  - name: website
    label: Website URL
    type: string
  - name: description
    label: Description
    type: text
  - name: location
    label: Location
    type: string
  - name: founded
    label: Founded Year
    type: number</code></pre>

<p><strong>Then reference it in your page schema:</strong></p>

<pre><code>- name: university
  label: University
  type: data_object
  options:
    object_type: universities

- name: partner_universities
  label: Partner Universities
  type: data_object
  list: true
  options:
    object_type: universities</code></pre>

<p><strong>Usage in templates:</strong></p>

<pre><code>&lt;?php
// Get single data object entry
$university = ycf_get_data_object('university');
if ($university) {
  echo '&lt;h2&gt;' . esc_html($university['name']) . '&lt;/h2&gt;';
  echo '&lt;p&gt;' . esc_html($university['location']) . '&lt;/p&gt;';

  // Get university logo
  if (!empty($university['logo'])) {
    $logo = wp_get_attachment_image($university['logo'], 'medium');
    echo $logo;
  }
}

// Get multiple data objects (list)
$partners = ycf_get_field('partner_universities');
if (!empty($partners)) {
  foreach ($partners as $partner_id) {
    $partner = ycf_get_data_object('partner_universities', null, ['entry_id' => $partner_id]);
    if ($partner) {
      echo '&lt;div class="partner"&gt;';
      echo '&lt;h3&gt;' . esc_html($partner['name']) . '&lt;/h3&gt;';
      echo '&lt;/div&gt;';
    }
  }
}

// In blocks - use context_data parameter
$blocks = ycf_get_field('content_blocks');
foreach ($blocks as $block) {
  $university = ycf_get_data_object('university', null, $block);
  if ($university) {
    echo esc_html($university['name']);
  }
}

// Get all entries of a data object type
$all_universities = ycf_get_data_objects('universities');
foreach ($all_universities as $entry_id => $university) {
  echo '&lt;li&gt;' . esc_html($university['name']) . '&lt;/li&gt;';
}
?&gt;</code></pre>

<p><strong>Returns:</strong> Array containing the data object entry fields, or <code>null</code> if not set.</p>

<p><strong>Helper functions:</strong></p>
<ul>
  <li><code>ycf_get_data_object($field_name, $post_id = null, $context_data = null)</code> - Get a single data object entry referenced by a field</li>
  <li><code>ycf_get_data_objects($object_type)</code> - Get all entries of a specific data object type</li>
</ul>

<h3>Image</h3>

<p>WordPress media uploader for images.</p>

<pre><code>- name: featured_image
  label: Featured Image
  type: image</code></pre>

<p>Returns the attachment ID. Use <code>ycf_get_image()</code> helper function to get full image data.</p>

<h3>File</h3>

<p>WordPress media uploader for any file type.</p>

<pre><code>- name: pdf_brochure
  label: PDF Brochure
  type: file</code></pre>

<p>Returns the attachment ID. Use <code>ycf_get_file()</code> helper function to get full file data.</p>

<h3>Object</h3>

<p>Nested group of fields.</p>

<pre><code>- name: author
  label: Author Information
  type: object
  fields:
    - name: name
      label: Author Name
      type: string
    - name: bio
      label: Biography
      type: text
    - name: photo
      label: Author Photo
      type: image</code></pre>

<p><strong>Access nested fields:</strong></p>

<pre><code>$author = ycf_get_field('author');
echo $author['name'];
echo $author['bio'];</code></pre>

<h3>Block</h3>

<p>Repeater field with multiple block types. Perfect for flexible page builders!</p>

<pre><code>- name: page_sections
  label: Page Sections
  type: block
  list: true
  blockKey: type
  blocks:
    - name: hero
      label: Hero Section
      fields:
        - name: title
          label: Hero Title
          type: string
        - name: background_image
          label: Background Image
          type: image
        - name: category
          label: Category
          type: taxonomy
          options:
            taxonomy: category
    - name: two_column
      label: Two Column Layout
      fields:
        - name: left_content
          label: Left Column
          type: rich-text
        - name: right_content
          label: Right Column
          type: rich-text</code></pre>

<p><strong>Properties:</strong></p>
<ul>
  <li><code>list: true</code> - Makes it repeatable</li>
  <li><code>blockKey</code> - Field name that identifies block type (usually "type")</li>
  <li><code>blocks</code> - Array of available block definitions</li>
</ul>

<p><strong>Usage in templates:</strong></p>

<pre><code>&lt;?php
$sections = ycf_get_field('page_sections');

if ($sections) {
  foreach ($sections as $section) {
    switch ($section['type']) {
      case 'hero':
        ?&gt;
        &lt;section class="hero"&gt;
          &lt;h1&gt;&lt;?php echo esc_html($section['title']); ?&gt;&lt;/h1&gt;
        &lt;/section&gt;
        &lt;?php
        break;

      case 'two_column':
        ?&gt;
        &lt;section class="two-column"&gt;
          &lt;div class="column"&gt;
            &lt;?php echo wp_kses_post($section['left_content']); ?&gt;
          &lt;/div&gt;
          &lt;div class="column"&gt;
            &lt;?php echo wp_kses_post($section['right_content']); ?&gt;
          &lt;/div&gt;
        &lt;/section&gt;
        &lt;?php
        break;
    }
  }
}
?&gt;</code></pre>

<h2>Helper Functions for Images and Files</h2>

<p>Since image and file fields store attachment IDs, YAML Custom Fields provides helper functions to retrieve full attachment data:</p>

<h3><code>ycf_get_image($field_name, $post_id = null, $size = 'full')</code></h3>

<p>Get comprehensive image data including URL, alt text, dimensions, and more.</p>

<pre><code>$image = ycf_get_image('featured_image');

if ($image) {
  echo '&lt;img src="' . esc_url($image['url']) . '"
        alt="' . esc_attr($image['alt']) . '"
        width="' . esc_attr($image['width']) . '"
        height="' . esc_attr($image['height']) . '" /&gt;';
}</code></pre>

<p><strong>Returns:</strong></p>
<ul>
  <li><code>id</code> - Attachment ID</li>
  <li><code>url</code> - Image URL at specified size</li>
  <li><code>alt</code> - Alt text</li>
  <li><code>title</code> - Image title</li>
  <li><code>caption</code> - Image caption</li>
  <li><code>description</code> - Image description</li>
  <li><code>width</code> - Image width</li>
  <li><code>height</code> - Image height</li>
</ul>

<h3><code>ycf_get_file($field_name, $post_id = null)</code></h3>

<p>Get comprehensive file data including URL, path, size, and MIME type.</p>

<pre><code>$pdf = ycf_get_file('pdf_brochure');

if ($pdf) {
  echo '&lt;a href="' . esc_url($pdf['url']) . '" download&gt;';
  echo 'Download ' . esc_html($pdf['filename']);
  echo ' (' . size_format($pdf['filesize']) . ')';
  echo '&lt;/a&gt;';
}</code></pre>

<p><strong>Returns:</strong></p>
<ul>
  <li><code>id</code> - Attachment ID</li>
  <li><code>url</code> - File URL</li>
  <li><code>path</code> - Server file path</li>
  <li><code>filename</code> - File name</li>
  <li><code>filesize</code> - File size in bytes</li>
  <li><code>mime_type</code> - MIME type</li>
  <li><code>title</code> - File title</li>
</ul>

<h2>Common Field Properties</h2>

<p>All field types support these properties:</p>

<pre><code>- name: field_name        # Required - Unique machine name
  label: Field Label      # Display name in admin
  type: string            # Required - Field type
  description: Help text  # Optional help text for editors
  default: Default value  # Default value for new entries
  required: true          # Make field required (not enforced yet)</code></pre>

<h2>Data Storage</h2>

<h3>Page Templates</h3>
<ul>
  <li><strong>Page/Post data</strong>: Post meta with key <code>_yaml_cf_data</code></li>
  <li><strong>Template Global preferences</strong>: Post meta with key <code>_yaml_cf_use_template_global_fields</code> (per-field array)</li>
  <li><strong>Scope</strong>: Per post/page</li>
  <li><strong>Editing</strong>: WordPress post/page editor</li>
</ul>

<h3>Template Global Fields</h3>
<ul>
  <li><strong>Template Global schemas</strong>: WordPress options with key <code>yaml_cf_template_global_schemas</code></li>
  <li><strong>Template Global data</strong>: WordPress options with key <code>yaml_cf_template_global_data</code></li>
  <li><strong>Scope</strong>: Per template (shared across all posts using the same template)</li>
  <li><strong>Editing</strong>: YAML Custom Fields admin page → "Manage Template Global Data" button</li>
</ul>

<h3>Site-Wide Global Schema</h3>
<ul>
  <li><strong>Global schema</strong>: WordPress options with key <code>yaml_cf_global_schema</code></li>
  <li><strong>Global data</strong>: WordPress options with key <code>yaml_cf_global_data</code></li>
  <li><strong>Scope</strong>: Site-wide (available to all templates)</li>
  <li><strong>Editing</strong>: YAML Custom Fields admin page → "Manage Global Data" button</li>
</ul>

<h3>Template Partials</h3>
<ul>
  <li><strong>Location</strong>: WordPress options with key <code>yaml_cf_partial_data</code></li>
  <li><strong>Scope</strong>: Global (site-wide)</li>
  <li><strong>Editing</strong>: YAML Custom Fields admin page → "Manage Data" button</li>
</ul>

<h3>Data Objects</h3>
<ul>
  <li><strong>Data Object Types</strong>: WordPress options with key <code>yaml_cf_data_object_types</code></li>
  <li><strong>Data Object Entries</strong>: WordPress options with keys <code>yaml_cf_data_object_entries_{type_slug}</code></li>
  <li><strong>Scope</strong>: Global (site-wide)</li>
  <li><strong>Editing</strong>: YAML Custom Fields admin page → "Data Objects"</li>
</ul>

<h3>Plugin Settings</h3>
<ul>
  <li><code>yaml_cf_template_settings</code> - Tracks which templates have YAML enabled</li>
  <li><code>yaml_cf_schemas</code> - Stores YAML schemas for each template/partial</li>
</ul>

<h2>Requirements</h2>

<ul>
  <li>WordPress 5.0 or higher</li>
  <li>PHP 7.4 or higher</li>
  <li>Composer (for installing dependencies from source)</li>
</ul>

<h2>Dependencies</h2>

<ul>
  <li><strong>Symfony YAML Component</strong> (v5.4) - YAML parsing
    <ul>
      <li>License: MIT (GPL-compatible)</li>
      <li>Homepage: <a href="https://symfony.com/components/Yaml" target="_blank">https://symfony.com/components/Yaml</a></li>
    </ul>
  </li>
</ul>

<h2>Security</h2>

<ul>
  <li>All admin functionality requires <code>manage_options</code> capability (administrator by default)</li>
  <li>AJAX requests protected with WordPress nonces</li>
  <li>Data sanitized and escaped appropriately</li>
  <li>Input validation on all fields</li>
</ul>

<h2>Uninstallation</h2>

<p>When you <strong>delete</strong> (not deactivate) the plugin, it automatically cleans up:</p>
<ul>
  <li>Template settings (<code>yaml_cf_template_settings</code>)</li>
  <li>Schemas (<code>yaml_cf_schemas</code>)</li>
  <li>Partial data (<code>yaml_cf_partial_data</code>)</li>
  <li>All post meta data (<code>_yaml_cf_data</code>)</li>
</ul>

<h2>Troubleshooting</h2>

<h3>Plugin activation error about Composer</h3>

<p><strong>Solution</strong>: Navigate to the plugin directory and run:</p>
<pre><code>composer install</code></pre>

<h3>Schema fields not appearing in post editor</h3>

<ol>
  <li>Ensure YAML is enabled for the template</li>
  <li>Verify you selected the correct page template</li>
  <li>Check YAML syntax (use 2 spaces for indentation)</li>
  <li>Clear browser cache and refresh</li>
</ol>

<h3>YAML parsing errors</h3>

<ol>
  <li>Validate YAML syntax with an online validator</li>
  <li>Use consistent 2-space indentation (no tabs)</li>
  <li>Check WordPress debug logs for detailed error messages</li>
</ol>

<h3>Changes not saving</h3>

<ol>
  <li>Check browser console for JavaScript errors</li>
  <li>Ensure you have permission to edit posts/options</li>
  <li>Verify WordPress AJAX is working</li>
</ol>

<h2>Contributing</h2>

<p>Contributions are welcome! If you'd like to contribute to YAML Custom Fields:</p>

<ol>
  <li>Fork the repository at <a href="https://github.com/maliMirkec/yaml-custom-fields" target="_blank">https://github.com/maliMirkec/yaml-custom-fields</a></li>
  <li>Create a feature branch (<code>git checkout -b feature/amazing-feature</code>)</li>
  <li>Commit your changes (<code>git commit -m 'Add amazing feature'</code>)</li>
  <li>Push to the branch (<code>git push origin feature/amazing-feature</code>)</li>
  <li>Open a Pull Request</li>
</ol>

<p>Please ensure your code follows WordPress coding standards and includes appropriate documentation.</p>

<h2>Reporting Issues</h2>

<p>Found a bug or have a feature request? Please report it on GitHub:</p>

<p><a href="https://github.com/maliMirkec/yaml-custom-fields/issues" target="_blank" class="button button-primary">Report an Issue on GitHub</a></p>

<p>When reporting issues, please include:</p>
<ul>
  <li>WordPress version</li>
  <li>PHP version</li>
  <li>Plugin version</li>
  <li>Steps to reproduce the issue</li>
  <li>Expected behavior vs actual behavior</li>
  <li>Any error messages or screenshots</li>
</ul>

<h2>Credits</h2>

<ul>
  <li><strong>Author</strong>: <a href="https://www.silvestar.codes" target="_blank">Silvestar Bistrović</a></li>
  <li><strong>Email</strong>: me@silvestar.codes</li>
  <li><strong>GitHub</strong>: <a href="https://github.com/maliMirkec/yaml-custom-fields" target="_blank">maliMirkec/yaml-custom-fields</a></li>
</ul>

<h2>License</h2>

<p>GPL v2 or later</p>

<h2>Changelog</h2>

<h3>Version 1.2.0</h3>
<ul>
  <li><strong>NEW: Template Global Fields</strong> - Define shared default values for all posts using the same template</li>
  <li><strong>NEW: Per-field global/local toggle</strong> - Each field can independently use template global or page-specific data</li>
  <li><strong>NEW: Dual-field interface</strong> - Visual side-by-side comparison of template global and page-specific values</li>
  <li><strong>NEW: Auto-merge data hierarchy</strong> - Intelligent data priority system (page > template global > site global)</li>
  <li>Enhanced post editor UI with clear visual indicators for global vs local data</li>
  <li>Improved field rendering system with unique IDs for dual fields</li>
  <li>Added per-field preferences storage for granular control</li>
  <li>Better reset functionality that preserves global data</li>
  <li>Enhanced documentation with Template Global Fields guide</li>
  <li>Improved admin interface organization for template management</li>
</ul>

<h3>Version 1.1.0</h3>
<ul>
  <li>Improved code quality and WordPress Coding Standards compliance</li>
  <li>Consolidated Export/Import functionality into single admin page</li>
  <li>Renamed "Export Page Data" to "Export/Import" for clarity</li>
  <li>Reorganized admin menu structure (Export/Import now positioned above Documentation)</li>
  <li>Enhanced database query performance with optimized caching strategy</li>
  <li>Implemented post tracking system for efficient cache management</li>
  <li>Improved input sanitization using <code>filter_input()</code> throughout the plugin</li>
  <li>Enhanced output escaping for better security</li>
  <li>Added production-safe logging system with WordPress hooks</li>
  <li>Better file upload validation and error handling</li>
  <li>Removed all <code>phpcs:ignore</code> suppressions in favor of proper WordPress coding practices</li>
  <li>Added <code>phpcs.xml.dist</code> configuration file for consistent code standards</li>
</ul>

<h3>Version 1.0.0</h3>
<ul>
  <li>Initial release</li>
  <li>Support for 15+ field types</li>
  <li>Template and partial support</li>
  <li>ACF-like template functions with <code>context_data</code> parameter for block fields</li>
  <li>Taxonomy field type for categories, tags, and custom taxonomies (single/multiple selection)</li>
  <li>Post Type field type for selecting registered WordPress post types</li>
  <li>Data Objects feature for managing structured, reusable data (universities, companies, etc.)</li>
  <li>Enhanced helper functions: <code>ycf_get_field()</code>, <code>ycf_get_image()</code>, <code>ycf_get_file()</code>, <code>ycf_get_term()</code>, <code>ycf_get_post_type()</code>, <code>ycf_get_data_object()</code>, <code>ycf_get_data_objects()</code></li>
  <li>Block/repeater functionality with context-aware field access</li>
  <li>WordPress media integration with attachment ID storage</li>
  <li>Administrator-only access</li>
  <li>Clean uninstall</li>
  <li>Clear buttons for image and file fields</li>
  <li>Reset All Data button for clearing all custom fields on a page</li>
  <li>Confirmation alerts for destructive actions</li>
  <li>Copy snippet buttons for all field types with complete function signatures</li>
</ul>

<hr>

<p><strong>Built with ❤️ for the WordPress community</strong></p>
