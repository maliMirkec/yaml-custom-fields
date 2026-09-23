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
		\YAML_Custom_Fields::get_instance()->render_schema_meta_box_after_title(get_post($post_id));
		return ob_get_clean();
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
