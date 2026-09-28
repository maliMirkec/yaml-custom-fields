<?php
/**
 * Integration: the import preview says what the import will actually do
 *
 * The preview marks each post in a file as "here" or "missing", and a missing
 * one cannot be ticked. It used to disagree with the importer twice over: a
 * file from an older version, which names no post type, had its pages marked
 * missing (the preview assumed "post"), and a post matched by ID was marked
 * "here" whatever its type, although the importer skips the wrong type.
 *
 * @package YamlCF
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Tests\WPTestCase;

class BundleCatalogTest extends WPTestCase {
	private function previewPosts(array $file) {
		$items = \YamlCF\Core\Plugin::getInstance()->get('bundle_catalog')->fromBundle(array_merge(['plugin' => 'yaml-custom-fields'], $file));

		return isset($items['posts']) ? $items['posts'] : [];
	}

	public function testAPageInAFileWithNoTypesIsFound() {
		$this->createTestPost(['post_type' => 'page', 'post_name' => 'about']);

		$posts = $this->previewPosts([
			'match_by' => 'slug',
			'posts' => [['slug' => 'about', 'title' => 'About', 'data' => ['headline' => 'x']]],
		]);

		$this->assertTrue($posts[0]['exists'], 'The importer searches every type when the file names none, and finds the page.');
	}

	public function testAnIdOfTheWrongTypeIsMissing() {
		$page = $this->createTestPost(['post_type' => 'page']);

		$posts = $this->previewPosts([
			'match_by' => 'id',
			'posts' => [['id' => $page, 'type' => 'post', 'slug' => 'x', 'data' => ['headline' => 'x']]],
		]);

		$this->assertFalse($posts[0]['exists'], 'The importer skips a post of another type than the file names.');
	}

	public function testAnIdOfTheRightTypeIsFound() {
		$page = $this->createTestPost(['post_type' => 'page']);

		$posts = $this->previewPosts([
			'match_by' => 'id',
			'posts' => [['id' => $page, 'type' => 'page', 'slug' => 'x', 'data' => ['headline' => 'x']]],
		]);

		$this->assertTrue($posts[0]['exists']);
	}

	public function testAnIdThatIsAnAttachmentIsMissing() {
		$attachment = self::factory()->attachment->create_object(['file' => 'a.jpg', 'post_mime_type' => 'image/jpeg']);

		$posts = $this->previewPosts([
			'match_by' => 'id',
			'posts' => [['id' => $attachment, 'slug' => 'x', 'data' => ['headline' => 'x']]],
		]);

		$this->assertFalse($posts[0]['exists'], 'An attachment cannot hold fields, so the importer would skip it.');
	}
}
