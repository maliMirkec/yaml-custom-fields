<?php
/**
 * Integration: the Custom fields box in the post editor
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Tests\WPTestCase;

class MetaBoxRenderTest extends WPTestCase {
	public function setUp(): void {
		parent::setUp();
		update_option('yaml_cf_template_settings', ['single.php' => true]);
		update_option('yaml_cf_schemas', ['single.php' => "fields:\n  - name: headline\n    label: Headline\n    type: string\n    description: Keep it short.\n  - name: featured\n    label: Featured\n    type: boolean\n"]);
	}

	private function render($post_id) {
		ob_start();
		\YAML_Custom_Fields::get_instance()->render_schema_meta_box(get_post($post_id));
		return ob_get_clean();
	}

	/**
	 * The box is registered with add_meta_box() so that it also appears in the
	 * block editor, which never fires edit_form_after_title.
	 */
	public function testBoxIsRegisteredForAPostWithASchema() {
		$this->actingAs($this->createTestUser('administrator'));
		$post = get_post($this->createTestPost());

		global $wp_meta_boxes;
		$wp_meta_boxes = [];
		\YAML_Custom_Fields::get_instance()->register_meta_box($post->post_type, $post);

		$this->assertArrayHasKey('yaml-cf-meta-box', $wp_meta_boxes[$post->post_type]['normal']['high']);
	}

	public function testBoxIsNotRegisteredWhenTheTemplateHasNoSchema() {
		update_option('yaml_cf_schemas', []);
		$this->actingAs($this->createTestUser('administrator'));
		$post = get_post($this->createTestPost());

		global $wp_meta_boxes;
		$wp_meta_boxes = [];
		\YAML_Custom_Fields::get_instance()->register_meta_box($post->post_type, $post);

		$this->assertSame([], $wp_meta_boxes);
	}

	/**
	 * Core prints the .postbox wrapper and heading for a registered meta box;
	 * printing our own too would nest one postbox inside another.
	 */
	public function testRenderDoesNotPrintItsOwnPostboxWrapper() {
		$this->actingAs($this->createTestUser('administrator'));
		$html = $this->render($this->createTestPost());

		$this->assertStringNotContainsString('class="postbox"', $html);
		$this->assertStringNotContainsString('postbox-header', $html);
		$this->assertStringContainsString('yaml_cf_meta_box_nonce', $html);
	}

	public function testWriterSeesHelpTextButNoDeveloperTools() {
		$user_id = $this->createTestUser('author');
		$this->actingAs($user_id);
		$html = $this->render($this->createTestPost(['post_author' => $user_id]));

		$this->assertStringContainsString('<p class="yaml-cf-field-help">Keep it short.</p>', $html);
		$this->assertStringContainsString('Fill in the fields below. Click Update to save them.', $html);
		$this->assertStringContainsString('yaml-cf-import-trigger', $html);
		$this->assertStringNotContainsString('yaml-cf-copy-snippet', $html);
		$this->assertStringNotContainsString('Edit schema', $html);
	}

	public function testAdministratorSeesCopyCodeAndEditSchema() {
		$this->actingAs($this->createTestUser('administrator'));
		$html = $this->render($this->createTestPost());

		$this->assertStringContainsString('yaml-cf-copy-snippet', $html);
		$this->assertStringContainsString('Edit schema', $html);
		$this->assertStringContainsString('data-type="boolean"', $html);
	}
}
