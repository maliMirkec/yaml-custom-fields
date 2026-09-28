<?php
/**
 * Integration: a gallery, meaning an image field with "list: true"
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Tests\WPTestCase;

class GalleryFieldTest extends WPTestCase {
	const SCHEMA = <<<'YAML'
fields:
  - name: hero
    label: Hero
    type: image
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

	private function submit($post_id, array $fields) {
		$_POST = wp_slash([
			'yaml_cf_meta_box_nonce' => wp_create_nonce('yaml_cf_meta_box'),
			'yaml_cf' => $fields,
		]);
		\YAML_Custom_Fields::get_instance()->save_schema_data($post_id);
	}

	private function render($post_id) {
		ob_start();
		\YAML_Custom_Fields::get_instance()->render_schema_meta_box(get_post($post_id));
		return ob_get_clean();
	}

	/**
	 * Make a real attachment so the thumbnail lookup has something to find
	 */
	private function createImage() {
		return self::factory()->attachment->create_object([
			'file' => 'gallery-image.jpg',
			'post_mime_type' => 'image/jpeg',
		], 0, ['post_title' => 'Gallery image']);
	}

	public function testGalleryRendersATileForEachImageAndKeepsOrder() {
		$this->actingAs($this->createTestUser('administrator'));
		$first = $this->createImage();
		$second = $this->createImage();

		$post_id = $this->createTestPost();
		update_post_meta($post_id, '_yaml_cf_data', ['gallery' => [$second, $first]]);

		$html = $this->render($post_id);

		$this->assertStringContainsString('yaml-cf-image-list', $html);
		$this->assertStringContainsString('data-id="' . $second . '"', $html);
		$this->assertStringContainsString('data-id="' . $first . '"', $html);

		// The stored order is the rendered order
		$this->assertLessThan(
			strpos($html, 'data-id="' . $first . '"'),
			strpos($html, 'data-id="' . $second . '"')
		);
	}

	public function testGalleryRoundTripsThroughTheSavePath() {
		$this->actingAs($this->createTestUser('administrator'));
		$first = $this->createImage();
		$second = $this->createImage();
		$post_id = $this->createTestPost();

		// The leading '' is the placeholder input that keeps the field clearable
		$this->submit($post_id, ['gallery' => ['', (string) $first, (string) $second]]);

		$data = get_post_meta($post_id, '_yaml_cf_data', true);
		$this->assertSame([(string) $first, (string) $second], $data['gallery']);
	}

	public function testEmptyingAGallerySavesItEmpty() {
		$this->actingAs($this->createTestUser('administrator'));
		$post_id = $this->createTestPost();
		update_post_meta($post_id, '_yaml_cf_data', ['gallery' => ['5', '6']]);

		// Every tile removed: only the placeholder is submitted
		$this->submit($post_id, ['gallery' => ['']]);

		$data = get_post_meta($post_id, '_yaml_cf_data', true);
		$this->assertSame([], $data['gallery']);
	}

	public function testGetImageReturnsAListForAGalleryAndAnArrayForASingleImage() {
		$first = $this->createImage();
		$second = $this->createImage();
		$post_id = $this->createTestPost();
		update_post_meta($post_id, '_yaml_cf_data', [
			'hero' => $first,
			'gallery' => [$first, $second],
		]);

		$gallery = yaml_cf_get_image('gallery', $post_id, 'medium');
		$this->assertIsArray($gallery);
		$this->assertCount(2, $gallery);
		$this->assertSame($first, $gallery[0]['id']);
		$this->assertArrayHasKey('url', $gallery[0]);

		// A single image field still returns one image, not a list of them
		$hero = yaml_cf_get_image('hero', $post_id);
		$this->assertArrayHasKey('url', $hero);
		$this->assertSame($first, $hero['id']);
	}

	/**
	 * An imported gallery can point at attachments that do not exist here. Those
	 * are skipped rather than returned as entries with a false url.
	 */
	public function testMissingAttachmentsAreSkippedAndFlaggedInTheEditor() {
		$this->actingAs($this->createTestUser('administrator'));
		$real = $this->createImage();
		$post_id = $this->createTestPost();
		update_post_meta($post_id, '_yaml_cf_data', ['gallery' => [$real, '999999']]);

		$gallery = yaml_cf_get_image('gallery', $post_id);
		$this->assertCount(1, $gallery);
		$this->assertSame($real, $gallery[0]['id']);

		$html = $this->render($post_id);
		$this->assertStringContainsString('Image 999999 is missing', $html);
	}

	/**
	 * An ID that is not an attachment is not an image: its title and content
	 * must not come back as the image's
	 */
	public function testAnIdOfAnotherPostIsNotAnImage() {
		$draft = $this->createTestPost(['post_status' => 'draft', 'post_title' => 'Secret draft', 'post_content' => 'Unpublished']);
		$post_id = $this->createTestPost();
		update_post_meta($post_id, '_yaml_cf_data', ['hero' => (string) $draft]);

		$this->assertNull(yaml_cf_get_image('hero', $post_id));
	}
}
