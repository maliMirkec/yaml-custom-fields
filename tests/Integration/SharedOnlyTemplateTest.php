<?php
/**
 * Integration: a template with shared fields and no page fields of its own
 *
 * The theme reads shared values whether or not the template has page fields,
 * but the post editor only showed the Custom fields box when there were page
 * fields. A template set up with shared fields alone showed writers nothing.
 *
 * @package YamlCF
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Tests\WPTestCase;

class SharedOnlyTemplateTest extends WPTestCase {
	const SHARED = "fields:\n  - name: disclaimer\n    label: Disclaimer\n    type: text\n";

	public function setUp(): void {
		parent::setUp();
		$this->actingAs($this->createTestUser('administrator'));
		update_option('yaml_cf_schemas', [], false);
		update_option('yaml_cf_template_settings', ['page.php' => true], false);
		update_option('yaml_cf_template_global_schemas', ['page.php' => self::SHARED], false);
		update_option('yaml_cf_template_global_data', ['page.php' => ['disclaimer' => 'Shared disclaimer']], false);
	}

	private function box() {
		return \YamlCF\Core\Plugin::getInstance()->get('meta_box');
	}

	public function testThePageGetsTheBoxWithTheSharedContent() {
		$page = get_post($this->createTestPost(['post_type' => 'page']));

		$this->assertNotNull($this->box()->schemaForPost($page));

		ob_start();
		$this->box()->render($page);
		$html = ob_get_clean();

		$this->assertStringContainsString('yaml-cf-template-global-only-fields', $html);
		$this->assertStringContainsString('Shared disclaimer', $html);
		$this->assertSame('Shared disclaimer', yaml_cf_get_field('disclaimer', $page->ID), 'What the theme reads is what the box shows.');
	}

	public function testASwitchedOffTemplateStillGetsNoBox() {
		update_option('yaml_cf_template_settings', [], false);
		$page = get_post($this->createTestPost(['post_type' => 'page']));

		$this->assertNull($this->box()->schemaForPost($page));
	}

	public function testAnEmptySharedSchemaGetsNoBox() {
		update_option('yaml_cf_template_global_schemas', ['page.php' => ''], false);
		$page = get_post($this->createTestPost(['post_type' => 'page']));

		$this->assertNull($this->box()->schemaForPost($page));
	}
}
