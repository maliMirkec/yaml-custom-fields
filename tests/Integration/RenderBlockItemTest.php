<?php
/**
 * Integration: adding a section renders it on the server
 *
 * The admin script used to build this markup in JavaScript, in parallel with
 * the PHP that renders sections (now YamlCF\Rendering\BlockItemRenderer). The two drifted, which is what made
 * taxonomy, post_type and data_object fields come out wrong in a section added
 * on the page. These tests pin down that both paths now produce the same fields.
 */

namespace YamlCF\Tests\Integration;

use WP_Ajax_UnitTestCase;
use WPAjaxDieContinueException;

class RenderBlockItemTest extends WP_Ajax_UnitTestCase {
	const SCHEMA = <<<'YAML'
fields:
  - name: sections
    label: Sections
    type: block
    list: true
    blocks:
      - name: feature
        label: Feature
        fields:
          - name: title
            label: Title
            type: string
          - name: topic
            label: Topic
            type: taxonomy
            options:
              taxonomy: category
          - name: kind
            label: Kind
            type: post_type
          - name: gallery
            label: Gallery
            type: image
            list: true
YAML;

	public function setUp(): void {
		parent::setUp();
		update_option('yaml_cf_schemas', ['single.php' => self::SCHEMA]);
		update_option('yaml_cf_template_settings', ['single.php' => true]);
	}

	public function tearDown(): void {
		$_POST = [];
		parent::tearDown();
	}

	private function callAjax(array $params) {
		$_POST = wp_slash(array_merge(['nonce' => wp_create_nonce('yaml_cf_nonce')], $params));
		try {
			$this->_handleAjax('yaml_cf_render_block_item');
		} catch (WPAjaxDieContinueException $e) {
			// Expected: wp_send_json_* ends the request
		}
		$response = json_decode($this->_last_response, true);
		$this->_last_response = '';
		return $response;
	}

	private function render(array $overrides = []) {
		return $this->callAjax(array_merge([
			'source' => 'template',
			'key' => 'single.php',
			'field_name' => 'sections',
			'block_type' => 'feature',
			'index' => '0',
		], $overrides));
	}

	/**
	 * The field types that used to be missing or wrong in a JavaScript-built
	 * section are the point of this test.
	 */
	public function testRenderedSectionCarriesEveryFieldType() {
		$this->_setRole('administrator');
		self::factory()->term->create(['taxonomy' => 'category', 'name' => 'Topic one']);

		$response = $this->render();

		$this->assertTrue($response['success']);
		$html = $response['data']['html'];

		$this->assertStringContainsString('yaml-cf-block-item', $html);
		$this->assertStringContainsString('data-block-type="feature"', $html);
		// The block type marker the save path reads
		$this->assertStringContainsString('name="yaml_cf[sections][0][type]"', $html);
		$this->assertStringContainsString('name="yaml_cf[sections][0][title]"', $html);
		// taxonomy, post_type and gallery all render, which is what regressed before
		$this->assertStringContainsString('Topic one', $html);
		$this->assertStringContainsString('name="yaml_cf[sections][0][kind]"', $html);
		$this->assertStringContainsString('yaml-cf-image-list', $html);
	}

	public function testIndexIsUsedInTheFieldNames() {
		$this->_setRole('administrator');

		$response = $this->render(['index' => '3']);

		$this->assertTrue($response['success']);
		$this->assertStringContainsString('name="yaml_cf[sections][3][title]"', $response['data']['html']);
		$this->assertSame(3, $response['data']['index']);
	}

	public function testUnknownBlockTypeIsRejected() {
		$this->_setRole('administrator');

		$response = $this->render(['block_type' => 'not_a_block']);

		$this->assertFalse($response['success']);
	}

	public function testUnknownFieldIsRejected() {
		$this->_setRole('administrator');

		$response = $this->render(['field_name' => 'not_a_field']);

		$this->assertFalse($response['success']);
	}

	public function testUnknownSourceIsRejected() {
		$this->_setRole('administrator');

		$response = $this->render(['source' => 'somewhere_else']);

		$this->assertFalse($response['success']);
	}

	public function testMissingSchemaIsRejected() {
		$this->_setRole('administrator');

		$response = $this->render(['key' => 'no-such-template.php']);

		$this->assertFalse($response['success']);
	}

	/**
	 * Writers add sections while editing a post, so this cannot require
	 * manage_options.
	 */
	public function testAuthorCanAddASection() {
		$this->_setRole('author');

		$response = $this->render();

		$this->assertTrue($response['success']);
	}

	public function testSubscriberCannotAddASection() {
		$this->_setRole('subscriber');

		$response = $this->render();

		$this->assertFalse($response['success']);
	}

	/**
	 * A section added on the page has to save through exactly the same path as
	 * one that came with it.
	 */
	public function testAddedSectionSavesLikeAnyOther() {
		$this->_setRole('administrator');
		$post_id = self::factory()->post->create(['post_type' => 'post']);

		$_POST = wp_slash([
			'yaml_cf_meta_box_nonce' => wp_create_nonce('yaml_cf_meta_box'),
			'yaml_cf' => [
				'sections' => [
					['type' => 'feature', 'title' => 'Added on the page'],
				],
			],
		]);
		\YAML_Custom_Fields::get_instance()->save_schema_data($post_id);

		$data = get_post_meta($post_id, '_yaml_cf_data', true);
		$this->assertSame('feature', $data['sections'][0]['type']);
		$this->assertSame('Added on the page', $data['sections'][0]['title']);
	}

	public function testGlobalSchemaIsAValidSource() {
		$this->_setRole('administrator');
		update_option('yaml_cf_global_schema', self::SCHEMA);

		$response = $this->render(['source' => 'global', 'key' => '']);

		$this->assertTrue($response['success']);
		$this->assertStringContainsString('name="yaml_cf[sections][0][title]"', $response['data']['html']);
	}

	public function testCollectionSchemaIsAValidSource() {
		$this->_setRole('administrator');
		update_option('yaml_cf_data_object_types', [
			'unis' => ['name' => 'Universities', 'schema' => self::SCHEMA],
		]);

		$response = $this->render(['source' => 'data_object', 'key' => 'unis']);

		$this->assertTrue($response['success']);
		$this->assertStringContainsString('name="yaml_cf[sections][0][title]"', $response['data']['html']);
	}
}
