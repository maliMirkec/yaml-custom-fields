<?php
/**
 * Integration: every admin screen survives every shape of schema
 *
 * The bug this file exists to prevent: a shared fields schema written with
 * "- info:" shorthand filled the post editor with "Undefined array key" warnings
 * on a fresh site. Nothing caught it, because no test had ever rendered that
 * screen with that schema.
 *
 * Rather than add one test per bug found, this renders **every** admin screen,
 * and the post editor meta box, against a set of schemas covering every field
 * type, every shorthand form, nesting, and the awkward cases. WP_DEBUG is on in
 * this suite and phpunit-integration.xml.dist turns notices and warnings into
 * exceptions, so any one of them fails the test that provoked it.
 *
 * Each schema is used as a page schema, a shared fields schema, a site details
 * schema, a site part schema and a collection schema at once, because the bug
 * was that one of those five paths handled the schema differently from the rest.
 *
 * When a new field type or shorthand is added, add it to schemas() and this
 * whole grid covers it.
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Tests\WPTestCase;

class AdminScreenSmokeTest extends WPTestCase {
	const TEMPLATE = 'single.php';
	const PARTIAL = 'header.php';
	const COLLECTION = 'people';

	public function tearDown(): void {
		$_GET = [];
		$_POST = [];
		parent::tearDown();
	}

	/**
	 * Schemas that between them cover everything the editor can be asked to draw
	 *
	 * @return array<string, array{0: string}>
	 */
	public function schemas() {
		return [
			'every field type' => ["fields:\n"
				. "  - name: a_string\n    label: String\n    type: string\n"
				. "  - name: a_text\n    label: Text\n    type: text\n"
				. "  - name: a_rich_text\n    label: Rich text\n    type: rich-text\n"
				. "  - name: a_code\n    label: Code\n    type: code\n"
				. "  - name: a_boolean\n    label: Boolean\n    type: boolean\n"
				. "  - name: a_number\n    label: Number\n    type: number\n"
				. "  - name: a_date\n    label: Date\n    type: date\n"
				. "  - name: a_select\n    label: Select\n    type: select\n    options:\n      choices:\n        - one\n        - two\n"
				. "  - name: a_taxonomy\n    label: Taxonomy\n    type: taxonomy\n    taxonomy: category\n"
				. "  - name: a_post_type\n    label: Post type\n    type: post_type\n    post_type: post\n"
				. "  - name: an_image\n    label: Image\n    type: image\n"
				. "  - name: a_file\n    label: File\n    type: file\n"],

			'info shorthand' => ["fields:\n"
				. "  - info: \"A note for whoever fills this in\"\n"
				. "  - name: headline\n    label: Headline\n    type: string\n"],

			'section shorthand' => ["fields:\n"
				. "  - section: \"Contact details\"\n"
				. "  - name: phone\n    label: Phone\n    type: string\n"],

			'break shorthand' => ["fields:\n"
				. "  - name: phone\n    label: Phone\n    type: string\n"
				. "  - type: break\n"
				. "  - name: email\n    label: Email\n    type: string\n"],

			'long form section with no name' => ["fields:\n"
				. "  - type: section\n    label: Contact\n"
				. "  - name: phone\n    label: Phone\n    type: string\n"],

			'shorthand of every kind at once' => ["fields:\n"
				. "  - info: \"Top note\"\n"
				. "  - section: \"First\"\n"
				. "  - name: one\n    label: One\n    type: string\n"
				. "  - type: break\n"
				. "  - info: \"Another note\"\n"
				. "  - name: two\n    label: Two\n    type: string\n"],

			'group of fields' => ["fields:\n"
				. "  - name: button\n    label: Button\n    type: object\n    fields:\n"
				. "      - name: label\n        label: Label\n        type: string\n"
				. "      - name: url\n        label: URL\n        type: string\n"],

			'group containing shorthand' => ["fields:\n"
				. "  - name: details\n    label: Details\n    type: object\n    fields:\n"
				. "      - info: \"Fill both of these in\"\n"
				. "      - name: label\n        label: Label\n        type: string\n"],

			'repeating sections' => ["fields:\n"
				. "  - name: sections\n    label: Sections\n    type: block\n    list: true\n    blocks:\n"
				. "      - name: banner\n        label: Banner\n        fields:\n"
				. "          - name: image\n            label: Image\n            type: image\n"
				. "          - name: title\n            label: Title\n            type: string\n"
				. "      - name: quote\n        label: Quote\n        fields:\n"
				. "          - name: body\n            label: Body\n            type: rich-text\n"],

			'repeating sections containing shorthand' => ["fields:\n"
				. "  - name: sections\n    label: Sections\n    type: block\n    list: true\n    blocks:\n"
				. "      - name: banner\n        label: Banner\n        fields:\n"
				. "          - info: \"A note inside a section\"\n"
				. "          - name: title\n            label: Title\n            type: string\n"],

			'lists and galleries' => ["fields:\n"
				. "  - name: gallery\n    label: Gallery\n    type: image\n    list: true\n"
				. "  - name: downloads\n    label: Downloads\n    type: file\n    list: true\n"
				. "  - name: topics\n    label: Topics\n    type: taxonomy\n    taxonomy: category\n    multiple: true\n"],

			'fields with no label' => ["fields:\n"
				. "  - name: headline\n    type: string\n"
				. "  - name: body\n    type: text\n"],

			'a single field' => ["fields:\n  - name: only\n    label: Only\n    type: string\n"],

			'no fields at all' => ["fields: []\n"],
		];
	}

	/**
	 * Put the schema everywhere a schema can live, so every screen has to cope
	 */
	private function applyEverywhere($yaml) {
		update_option('yaml_cf_schemas', [
			self::TEMPLATE => $yaml,
			self::PARTIAL => $yaml,
		], false);
		update_option('yaml_cf_template_settings', [
			self::TEMPLATE => true,
			self::PARTIAL => true,
			self::TEMPLATE . '_use_global' => true,
		], false);
		update_option('yaml_cf_template_global_schemas', [self::TEMPLATE => $yaml], false);
		update_option('yaml_cf_global_schema', $yaml, false);
		update_option('yaml_cf_data_object_types', [
			self::COLLECTION => ['name' => 'People', 'schema' => $yaml],
		], false);

		// A cached theme scan, so the screens list something to act on
		set_transient('yaml_cf_templates_' . get_stylesheet(), [
			'templates' => [['file' => self::TEMPLATE, 'name' => 'Single', 'path' => '']],
			'partials' => [['file' => self::PARTIAL, 'name' => 'Header', 'path' => '']],
		], HOUR_IN_SECONDS);
	}

	/**
	 * Render something, failing the test on any notice, warning or error
	 *
	 * @param string $what Name used in the failure message
	 * @param callable $render What to run
	 */
	private function renderQuietly($what, callable $render) {
		ob_start();
		try {
			$render();
		} catch (\WPDieException $e) {
			ob_end_clean();
			$this->fail($what . ' called wp_die(): ' . $e->getMessage());
		} catch (\Throwable $e) {
			ob_end_clean();
			// Warnings and notices arrive here, converted by the PHPUnit config
			// The file and line are the point of this: they name the code that
			// warned, not the test that happened to reach it.
			$this->fail($what . ' raised ' . get_class($e) . ': ' . $e->getMessage()
				. ' at ' . $e->getFile() . ':' . $e->getLine());
		}

		$html = ob_get_clean();

		$this->assertIsString($html);
	}

	/**
	 * @dataProvider schemas
	 */
	public function testEveryAdminScreenRendersCleanly($yaml) {
		$this->actingAs($this->createTestUser('administrator'));
		$this->applyEverywhere($yaml);

		$plugin = \YamlCF\Core\Plugin::getInstance();

		$screens = [
			'Dashboard' => ['template_schema_controller', 'render', []],
			'Schema editor' => ['schema_editor_controller', 'render', ['template' => self::TEMPLATE]],
			'Site part editor' => ['partial_controller', 'render', ['template' => self::PARTIAL]],
			'Site details schema' => ['global_schema_controller', 'render', []],
			'Site details content' => ['global_data_controller', 'render', []],
			'Shared fields schema' => ['template_global_controller', 'renderSchemaEditor', ['template' => self::TEMPLATE]],
			'Shared fields content' => ['template_global_controller', 'renderDataManager', ['template' => self::TEMPLATE]],
			'Content check' => ['validation_controller', 'render', []],
			'Collections list' => ['data_object_controller', 'renderList', []],
			'Collection editor' => ['data_object_controller', 'renderEdit', ['type_id' => self::COLLECTION]],
			'Collection entries' => ['data_object_controller', 'renderEntries', ['type_id' => self::COLLECTION]],
			'Export and import' => ['export_import_controller', 'render', []],
			'Settings' => ['settings_controller', 'render', []],
			'Documentation' => ['docs_controller', 'render', []],
		];

		foreach ($screens as $name => $screen) {
			list($service, $method, $query) = $screen;

			$_GET = $query;
			$_REQUEST = $query;

			$controller = $plugin->get($service);
			$this->renderQuietly($name, function () use ($controller, $method) {
				$controller->$method();
			});
		}
	}

	/**
	 * The post editor is where the reported bug showed up, and it is the one
	 * screen a writer sees, so it gets its own pass.
	 *
	 * @dataProvider schemas
	 */
	public function testThePostEditorMetaBoxRendersCleanly($yaml) {
		$this->actingAs($this->createTestUser('administrator'));
		$this->applyEverywhere($yaml);
		set_current_screen('post');

		$post_id = $this->createTestPost();

		$this->renderQuietly('Post editor meta box', function () use ($post_id) {
			\YAML_Custom_Fields::get_instance()->render_schema_meta_box(get_post($post_id));
		});
	}

	/**
	 * The same again for a post that already holds values, since rendering a
	 * filled-in field goes down different branches from rendering an empty one.
	 *
	 * @dataProvider schemas
	 */
	public function testThePostEditorRendersCleanlyWithSavedValues($yaml) {
		$this->actingAs($this->createTestUser('administrator'));
		$this->applyEverywhere($yaml);
		set_current_screen('post');

		$attachment_id = self::factory()->attachment->create();
		$term_id = self::factory()->term->create(['taxonomy' => 'category']);

		// Something plausible for every field type at once; fields the schema
		// does not have are simply ignored
		$post_id = $this->createTestPost([], [
			'a_string' => 'Text',
			'a_text' => "Two\nlines",
			'a_rich_text' => '<p>Rich</p>',
			'a_code' => \YAML_Custom_Fields::CODE_FIELD_MARKER . base64_encode('<b>x</b>'),
			'a_boolean' => '1',
			'a_number' => '42',
			'a_date' => '2026-01-01',
			'a_select' => 'one',
			'a_taxonomy' => (string) $term_id,
			'a_post_type' => 'post',
			'an_image' => (string) $attachment_id,
			'a_file' => (string) $attachment_id,
			'gallery' => [(string) $attachment_id],
			'downloads' => [(string) $attachment_id],
			'topics' => [(string) $term_id],
			'headline' => 'Headline',
			'body' => 'Body',
			'phone' => '555-0100',
			'email' => 'a@example.org',
			'one' => 'One',
			'two' => 'Two',
			'only' => 'Only',
			'button' => ['label' => 'Go', 'url' => 'https://example.org'],
			'details' => ['label' => 'Detail'],
			'sections' => [
				['type' => 'banner', 'image' => (string) $attachment_id, 'title' => 'Banner title'],
				['type' => 'quote', 'body' => '<p>Quoted</p>'],
			],
		]);

		// Shared values and the ticks that switch to them, which is the branch
		// the reported bug was hiding in
		update_option('yaml_cf_template_global_data', [
			self::TEMPLATE => ['headline' => 'Shared headline', 'phone' => '555-0199', 'a_string' => 'Shared'],
		], false);
		update_post_meta($post_id, '_yaml_cf_use_template_global_fields', [
			'headline' => '1',
			'a_string' => '1',
		]);

		$this->renderQuietly('Post editor meta box with values', function () use ($post_id) {
			\YAML_Custom_Fields::get_instance()->render_schema_meta_box(get_post($post_id));
		});
	}

	/**
	 * A writer sees a different meta box from an administrator: no snippets, no
	 * schema link. That branch needs covering too.
	 *
	 * @dataProvider schemas
	 */
	public function testThePostEditorRendersCleanlyForAWriter($yaml) {
		$this->applyEverywhere($yaml);
		$this->actingAs($this->createTestUser('contributor'));
		set_current_screen('post');

		$post_id = $this->createTestPost();

		$this->renderQuietly('Post editor meta box as a writer', function () use ($post_id) {
			\YAML_Custom_Fields::get_instance()->render_schema_meta_box(get_post($post_id));
		});
	}
}
