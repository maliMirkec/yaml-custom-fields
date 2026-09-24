<?php
/**
 * Integration: saving the post editor meta box and reading values back
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Tests\WPTestCase;

class MetaBoxSaveTest extends WPTestCase {
	const MARKER = '__YAMLCF_B64__';

	const SCHEMA = <<<'YAML'
fields:
  - name: title
    type: string
  - name: body
    type: rich-text
  - name: embed
    type: code
  - name: sections
    type: block
    list: true
    blocks:
      - name: widget
        fields:
          - name: html
            type: code
          - name: caption
            type: string
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

	/**
	 * Submit the meta box form as the current user (values are slashed like real requests)
	 */
	private function submit($post_id, array $fields, array $extra = []) {
		$_POST = wp_slash(array_merge([
			'yaml_cf_meta_box_nonce' => wp_create_nonce('yaml_cf_meta_box'),
			'yaml_cf' => $fields,
		], $extra));
		\YAML_Custom_Fields::get_instance()->save_schema_data($post_id);
	}

	private function encoded($code) {
		return self::MARKER . base64_encode($code);
	}

	public function testContributorCannotSmuggleHtmlThroughTheMarker() {
		$user_id = $this->createTestUser('contributor');
		$this->actingAs($user_id);
		$post_id = $this->createTestPost(['post_author' => $user_id, 'post_status' => 'draft']);

		$this->submit($post_id, ['title' => $this->encoded('<script>alert(1)</script>')]);

		$stored = get_post_meta($post_id, '_yaml_cf_data', true);
		$this->assertStringNotContainsString(self::MARKER, $stored['title']);
		$this->assertStringNotContainsString('<script>', (string) ycf_get_field('title', $post_id));
	}

	public function testCodeFieldIsFilteredForUsersWithoutUnfilteredHtml() {
		$user_id = $this->createTestUser('contributor');
		$this->actingAs($user_id);
		$post_id = $this->createTestPost(['post_author' => $user_id, 'post_status' => 'draft']);

		$this->submit($post_id, [
			'embed' => '<script>steal()</script><p>Form</p>',
			'sections' => [
				['type' => 'widget', 'html' => '<script>x()</script><div>Widget</div>', 'caption' => '<b>Hi</b>'],
			],
		]);

		// wp_kses_post() removes the script element (its text content stays, as plain text)
		$embed = ycf_get_field('embed', $post_id);
		$this->assertStringNotContainsString('<script', $embed);
		$this->assertStringContainsString('<p>Form</p>', $embed);

		$sections = ycf_get_field('sections', $post_id);
		$html = ycf_get_field('html', null, $sections[0]);
		$this->assertStringNotContainsString('<script', $html);
		$this->assertStringContainsString('<div>Widget</div>', $html);
		$this->assertSame('Hi', ycf_get_field('caption', null, $sections[0]));
	}

	public function testAdministratorKeepsRawCode() {
		$this->actingAs($this->createTestUser('administrator'));
		if (!current_user_can('unfiltered_html')) {
			$this->markTestSkipped('Administrators lack unfiltered_html in this environment (e.g. multisite).');
		}
		$post_id = $this->createTestPost();

		$this->submit($post_id, ['embed' => '<script>track()</script>']);

		$this->assertSame('<script>track()</script>', ycf_get_field('embed', $post_id));
	}

	public function testBackslashesAndRichTextSurviveSave() {
		$this->actingAs($this->createTestUser('administrator'));
		$post_id = $this->createTestPost();

		$this->submit($post_id, [
			'title' => 'C:\\temp\\file',
			'body' => '<p><strong>Bold</strong> text</p>',
			'embed' => 'console.log("a\\"b");',
		]);

		$this->assertSame('C:\\temp\\file', ycf_get_field('title', $post_id));
		$this->assertSame('<p><strong>Bold</strong> text</p>', ycf_get_field('body', $post_id));
		$this->assertSame('console.log("a\\"b");', ycf_get_field('embed', $post_id));
	}

	public function testStoredMarkerInNonCodeFieldIsNotDecodedOnRead() {
		$post_id = $this->createTestPost([], ['title' => $this->encoded('<script>old()</script>')]);

		// Data saved by an older version: the schema says "string", so the value must not be decoded
		$this->assertSame($this->encoded('<script>old()</script>'), ycf_get_field('title', $post_id));
		$this->assertSame($this->encoded('<script>old()</script>'), ycf_get_fields($post_id)['title']);
	}

	public function testTemplateGlobalOnlyFieldsAreNotFrozenInPostData() {
		update_option('yaml_cf_template_global_schemas', ['single.php' => "fields:\n  - name: banner\n    type: string\n"]);
		update_option('yaml_cf_template_global_data', ['single.php' => ['banner' => 'Current banner']]);

		$this->actingAs($this->createTestUser('administrator'));
		$post_id = $this->createTestPost([], ['banner' => 'Stale copy', 'title' => 'Hello']);

		// Read side: the template global value wins over a stale copy in post data
		$this->assertSame('Current banner', ycf_get_field('banner', $post_id));
		$this->assertSame('Current banner', ycf_get_fields($post_id)['banner']);

		// Write side: the stale copy is left alone rather than destroyed. It is
		// never shown, because the read side above always prefers the shared
		// value, and keeping it means moving a field into the shared schema and
		// back again does not lose what each page had.
		$this->submit($post_id, ['title' => 'Hello', 'banner' => 'Submitted copy']);
		$this->assertSame('Current banner', ycf_get_field('banner', $post_id));
		$this->assertSame('Current banner', ycf_get_fields($post_id)['banner']);
	}

	public function testInvalidNonceSavesNothing() {
		$this->actingAs($this->createTestUser('administrator'));
		$post_id = $this->createTestPost();

		$_POST = wp_slash([
			'yaml_cf_meta_box_nonce' => 'invalid',
			'yaml_cf' => ['title' => 'Nope'],
		]);
		\YAML_Custom_Fields::get_instance()->save_schema_data($post_id);

		$this->assertSame('', get_post_meta($post_id, '_yaml_cf_data', true));
	}

	public function testUserWhoCannotEditThePostSavesNothing() {
		$author_id = $this->createTestUser('author');
		$post_id = $this->createTestPost(['post_author' => $author_id]);

		$this->actingAs($this->createTestUser('contributor'));
		$this->submit($post_id, ['title' => 'Hijacked']);

		$this->assertSame('', get_post_meta($post_id, '_yaml_cf_data', true));
	}
}
