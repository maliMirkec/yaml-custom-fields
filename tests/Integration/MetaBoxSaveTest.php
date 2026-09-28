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
			'post_ID' => $post_id,
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

	/**
	 * The classic editor saves through wp_update_post() with the form still in
	 * $_POST, so save_post fires again for the revision WordPress creates after
	 * the post. That pass must not touch the post's fields.
	 */
	public function testSavingWithARevisionKeepsRichTextAndRemovedFields() {
		$this->actingAs($this->createTestUser('administrator'));
		$post_id = $this->createTestPost(['post_content' => 'Before']);
		update_post_meta($post_id, '_yaml_cf_data', ['dropped_field' => 'Still here']);

		$_POST = wp_slash([
			'post_ID' => $post_id,
			'yaml_cf_meta_box_nonce' => wp_create_nonce('yaml_cf_meta_box'),
			'yaml_cf' => ['title' => 'Hello', 'body' => '<p><strong>Bold</strong></p>'],
		]);
		wp_update_post(['ID' => $post_id, 'post_content' => 'After']);

		$this->assertNotEmpty(wp_get_post_revisions($post_id), 'The update created a revision.');
		$stored = get_post_meta($post_id, '_yaml_cf_data', true);
		$this->assertSame('<p><strong>Bold</strong></p>', $stored['body']);
		$this->assertSame('Still here', $stored['dropped_field'] ?? null);
		$this->assertSame([$post_id], array_values(get_option('yaml_cf_tracked_posts', [])), 'Only the post is tracked, not its revision.');
	}

	/**
	 * Another plugin saving a second post during the same request (a translation,
	 * a copy) fires save_post for it with this form still in $_POST. The fields
	 * belong to the post the form was drawn for, never to that one.
	 */
	public function testSavingAnotherPostInTheSameRequestLeavesItsFieldsAlone() {
		$this->actingAs($this->createTestUser('administrator'));
		$post_id = $this->createTestPost();
		$other_id = $this->createTestPost();
		update_post_meta($other_id, '_yaml_cf_data', ['title' => 'The other post']);

		$_POST = wp_slash([
			'post_ID' => $post_id,
			'yaml_cf_meta_box_nonce' => wp_create_nonce('yaml_cf_meta_box'),
			'yaml_cf' => ['title' => 'Typed on the first post'],
			'yaml_cf_use_template_global_fields' => ['title' => '1'],
		]);
		wp_update_post(['ID' => $other_id, 'post_title' => 'Touched by another plugin']);

		$this->assertSame(['title' => 'The other post'], get_post_meta($other_id, '_yaml_cf_data', true));
		$this->assertSame('', get_post_meta($other_id, '_yaml_cf_use_template_global_fields', true));

		wp_update_post(['ID' => $post_id, 'post_title' => 'The post being edited']);
		$this->assertSame('Typed on the first post', get_post_meta($post_id, '_yaml_cf_data', true)['title'] ?? null);
	}

	/**
	 * Choosing another template and saving in one go posts the old template's
	 * fields. Those the new schema lacks keep their formatting; those it has
	 * still follow the new schema.
	 */
	public function testSwitchingTemplateKeepsTheOldTemplatesRichText() {
		update_option('yaml_cf_schemas', [
			'single.php' => self::SCHEMA,
			'template-old.php' => "fields:\n  - name: intro\n    type: rich-text\n  - name: title\n    type: rich-text\n",
		]);
		$this->actingAs($this->createTestUser('administrator'));
		$post_id = $this->createTestPost();

		$this->submit($post_id, [
			'intro' => '<p><em>Kept</em></p>',
			'title' => '<b>Plain now</b>',
		], ['yaml_cf_rendered_template' => 'template-old.php']);

		$stored = get_post_meta($post_id, '_yaml_cf_data', true);
		$this->assertSame('<p><em>Kept</em></p>', $stored['intro']);
		$this->assertSame('Plain now', $stored['title'], 'A field the new schema has follows the new schema.');
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
