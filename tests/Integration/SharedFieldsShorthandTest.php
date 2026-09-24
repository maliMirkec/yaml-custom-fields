<?php
/**
 * Integration: shorthand in a shared fields schema
 *
 * The post's own schema is expanded before the meta box renders it, so
 * "- info:" and "- section:" become real fields with a name. The shared fields
 * schema was parsed but never expanded, so those entries reached the meta box
 * with no 'name' key and the editor filled with "Undefined array key" warnings.
 *
 * WP_DEBUG is on in this suite and warnings are turned into exceptions, so a
 * warning fails these tests rather than hiding in the output.
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Tests\WPTestCase;

class SharedFieldsShorthandTest extends WPTestCase {
	const POST_SCHEMA = "fields:\n  - name: headline\n    label: Headline\n    type: string\n";

	public function setUp(): void {
		parent::setUp();
		update_option('yaml_cf_schemas', ['single.php' => self::POST_SCHEMA], false);
		update_option('yaml_cf_template_settings', ['single.php' => true], false);
		set_current_screen('post');
	}

	/**
	 * Render the meta box for a post and hand back the markup
	 */
	private function renderMetaBox($post_id) {
		ob_start();
		try {
			\YAML_Custom_Fields::get_instance()->render_schema_meta_box(get_post($post_id));
		} finally {
			$html = ob_get_clean();
		}

		return $html;
	}

	private function givenSharedSchema($yaml) {
		update_option('yaml_cf_template_global_schemas', ['single.php' => $yaml], false);
	}

	/**
	 * The report that started this: a fresh site, a shared schema written with
	 * the documented shorthand, and the post editor full of warnings.
	 */
	public function testAnInfoShorthandInSharedFieldsDoesNotWarn() {
		$this->givenSharedSchema("fields:\n  - info: \"Fill these in once\"\n  - name: phone\n    label: Phone\n    type: string\n");

		$this->actingAs($this->createTestUser('administrator'));
		$post_id = $this->createTestPost();

		$html = $this->renderMetaBox($post_id);

		$this->assertStringContainsString('Shared fields', $html);
		$this->assertStringContainsString('Phone', $html);
	}

	public function testASectionShorthandInSharedFieldsDoesNotWarn() {
		$this->givenSharedSchema("fields:\n  - section: \"Contact details\"\n  - name: phone\n    label: Phone\n    type: string\n");

		$this->actingAs($this->createTestUser('administrator'));
		$post_id = $this->createTestPost();

		$html = $this->renderMetaBox($post_id);

		$this->assertStringContainsString('Phone', $html);
	}

	public function testABreakShorthandInSharedFieldsDoesNotWarn() {
		$this->givenSharedSchema("fields:\n  - name: phone\n    label: Phone\n    type: string\n  - type: break\n  - name: email\n    label: Email\n    type: string\n");

		$this->actingAs($this->createTestUser('administrator'));
		$post_id = $this->createTestPost();

		$html = $this->renderMetaBox($post_id);

		$this->assertStringContainsString('Phone', $html);
		$this->assertStringContainsString('Email', $html);
	}

	/**
	 * A long-form section with no name of its own is given one when expanded,
	 * and that has to happen for the shared schema too.
	 */
	public function testALongFormSectionWithoutANameDoesNotWarn() {
		$this->givenSharedSchema("fields:\n  - type: section\n    label: Contact\n  - name: phone\n    label: Phone\n    type: string\n");

		$this->actingAs($this->createTestUser('administrator'));
		$post_id = $this->createTestPost();

		$html = $this->renderMetaBox($post_id);

		$this->assertStringContainsString('Phone', $html);
	}

	/**
	 * Shorthand on both sides at once: the post schema's generated names and the
	 * shared schema's are both positional, so they can be identical.
	 */
	public function testShorthandInBothSchemasDoesNotWarn() {
		update_option('yaml_cf_schemas', [
			'single.php' => "fields:\n  - info: \"About this page\"\n  - name: headline\n    label: Headline\n    type: string\n",
		], false);
		$this->givenSharedSchema("fields:\n  - info: \"Shared note\"\n  - name: phone\n    label: Phone\n    type: string\n");

		$this->actingAs($this->createTestUser('administrator'));
		$post_id = $this->createTestPost();

		$html = $this->renderMetaBox($post_id);

		$this->assertStringContainsString('Headline', $html);
		$this->assertStringContainsString('Phone', $html);
	}

	/**
	 * A field in both schemas still gets the two boxes and the tick, which is
	 * what the shared fields feature is for.
	 */
	public function testAFieldInBothSchemasStillRendersAsADualField() {
		$this->givenSharedSchema("fields:\n  - info: \"Shared note\"\n  - name: headline\n    label: Headline\n    type: string\n");

		$this->actingAs($this->createTestUser('administrator'));
		$post_id = $this->createTestPost();

		$html = $this->renderMetaBox($post_id);

		$this->assertStringContainsString('Use the shared value on this page', $html);
	}
}
