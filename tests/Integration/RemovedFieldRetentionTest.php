<?php
/**
 * Integration: removing a field from a schema keeps its content
 *
 * When a schema drops a field, the editor says:
 *
 *   "Whatever was filled in for them is still saved, but nothing displays it
 *    any more. Adding a field back under the same name brings its content
 *    back too."
 *
 * That was not true. Saving rebuilds the stored data from what the form sent,
 * a dropped field sends nothing, and its content was destroyed the next time
 * anybody pressed Update on any page using that template. Since a dropped field
 * is usually a typo or a rename, one mistyped line could take the content of
 * every page on the template with it.
 *
 * These tests are the promise, written down.
 *
 * @package YamlCF
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Tests\FailsOnPluginWarnings;
use YamlCF\Tests\WPTestCase;

class RemovedFieldRetentionTest extends WPTestCase {
	use FailsOnPluginWarnings;

	const TEMPLATE = 'single.php';

	const BOTH_FIELDS = "fields:\n"
		. "  - name: headline\n    label: Headline\n    type: string\n"
		. "  - name: subtitle\n    label: Subtitle\n    type: string\n";

	const ONLY_HEADLINE = "fields:\n  - name: headline\n    label: Headline\n    type: string\n";

	public function setUp(): void {
		parent::setUp();
		$this->failOnPluginWarnings();
		$this->givenSchema(self::BOTH_FIELDS);
		update_option('yaml_cf_template_settings', [self::TEMPLATE => true], false);
		$this->actingAs($this->createTestUser('administrator'));
		set_current_screen('post');
	}

	public function tearDown(): void {
		$this->stopFailingOnPluginWarnings();
		$_POST = [];
		parent::tearDown();
	}

	private function givenSchema($yaml) {
		update_option('yaml_cf_schemas', [self::TEMPLATE => $yaml], false);
	}

	private function submit($post_id, array $fields) {
		$_POST = wp_slash([
			'post_ID' => $post_id,
			'yaml_cf_meta_box_nonce' => wp_create_nonce('yaml_cf_meta_box'),
			'yaml_cf' => $fields,
		]);
		\YAML_Custom_Fields::get_instance()->save_schema_data($post_id);
		$_POST = [];
	}

	private function storedFor($post_id) {
		$stored = get_post_meta($post_id, '_yaml_cf_data', true);

		return is_array($stored) ? $stored : [];
	}

	// ------------------------------------------------------------ the promise

	public function testContentSurvivesSavingThePageAfterItsFieldIsRemoved() {
		$post_id = $this->createTestPost([], ['headline' => 'Headline', 'subtitle' => 'Must survive']);

		$this->givenSchema(self::ONLY_HEADLINE);

		// The editor renders no input for "subtitle", so the form sends only "headline"
		$this->submit($post_id, ['headline' => 'Edited headline']);

		$stored = $this->storedFor($post_id);
		$this->assertSame('Edited headline', $stored['headline']);
		$this->assertSame('Must survive', $stored['subtitle'], 'The promise: removing a field keeps its content.');
	}

	public function testAddingTheFieldBackBringsItsContentBack() {
		$post_id = $this->createTestPost([], ['headline' => 'Headline', 'subtitle' => 'Must survive']);

		$this->givenSchema(self::ONLY_HEADLINE);
		$this->submit($post_id, ['headline' => 'Edited headline']);

		$this->givenSchema(self::BOTH_FIELDS);

		$this->assertSame('Must survive', yaml_cf_get_field('subtitle', $post_id));
	}

	public function testSavingRepeatedlyDoesNotWearTheContentAway() {
		$post_id = $this->createTestPost([], ['headline' => 'Headline', 'subtitle' => 'Must survive']);
		$this->givenSchema(self::ONLY_HEADLINE);

		for ($i = 0; $i < 3; $i++) {
			$this->submit($post_id, ['headline' => 'Pass ' . $i]);
		}

		$this->assertSame('Must survive', $this->storedFor($post_id)['subtitle']);
	}

	/**
	 * A rename is a delete plus an add as far as the schema can tell, so the old
	 * name's content has to stay put until somebody decides otherwise.
	 */
	public function testRenamingAFieldKeepsTheContentUnderTheOldName() {
		$post_id = $this->createTestPost([], ['headline' => 'Headline', 'subtitle' => 'Must survive']);

		$this->givenSchema("fields:\n"
			. "  - name: headline\n    label: Headline\n    type: string\n"
			. "  - name: standfirst\n    label: Standfirst\n    type: string\n");

		$this->submit($post_id, ['headline' => 'Headline', 'standfirst' => '']);

		$this->assertSame('Must survive', $this->storedFor($post_id)['subtitle']);
	}

	/**
	 * The counterexample that made the first version of this rule wrong.
	 *
	 * Moving a field into a group is an ordinary edit. The flat list of every
	 * name a schema defines still contains "title" — one level down, inside the
	 * group — so a rule built on that list reads the stranded top-level value as
	 * a field still in use and destroys it. And because the flat lists match,
	 * the schema editor shows no warning either.
	 */
	public function testPromotingAFieldIntoAGroupKeepsTheOldTopLevelValue() {
		$this->givenSchema("fields:\n  - name: title\n    label: Title\n    type: string\n");
		$post_id = $this->createTestPost([], ['title' => 'Old copy']);

		$this->givenSchema("fields:\n"
			. "  - name: hero\n    label: Hero\n    type: object\n    fields:\n"
			. "      - name: title\n        label: Title\n        type: string\n");

		$this->submit($post_id, ['hero' => ['title' => 'New copy']]);

		$stored = $this->storedFor($post_id);
		$this->assertSame('New copy', $stored['hero']['title']);
		$this->assertSame('Old copy', $stored['title'], 'The stranded top-level value is a different field from the group child.');
	}

	// ------------------------------------------------------ inside a group

	/**
	 * A field removed from inside a group strands its value just as surely as
	 * one removed from the top. "Removing a field keeps its content, unless you
	 * happened to put it in a group" is not a rule anybody would remember.
	 */
	public function testContentSurvivesWhenAGroupChildIsRemoved() {
		$this->givenSchema("fields:\n"
			. "  - name: button\n    label: Button\n    type: object\n    fields:\n"
			. "      - name: label\n        label: Label\n        type: string\n"
			. "      - name: url\n        label: URL\n        type: string\n");

		$post_id = $this->createTestPost([], [
			'button' => ['label' => 'Go', 'url' => 'https://example.org'],
		]);

		$this->givenSchema("fields:\n"
			. "  - name: button\n    label: Button\n    type: object\n    fields:\n"
			. "      - name: label\n        label: Label\n        type: string\n");

		$this->submit($post_id, ['button' => ['label' => 'Go on']]);

		$stored = $this->storedFor($post_id);
		$this->assertSame('Go on', $stored['button']['label']);
		$this->assertSame('https://example.org', $stored['button']['url'], 'A removed group child keeps its content too.');
	}

	public function testAGroupChildStillInTheSchemaStillClears() {
		$this->givenSchema("fields:\n"
			. "  - name: button\n    label: Button\n    type: object\n    fields:\n"
			. "      - name: label\n        label: Label\n        type: string\n"
			. "      - name: url\n        label: URL\n        type: string\n");

		$post_id = $this->createTestPost([], [
			'button' => ['label' => 'Go', 'url' => 'https://example.org'],
		]);

		$this->submit($post_id, ['button' => ['label' => 'Go', 'url' => '']]);

		$this->assertSame('', $this->storedFor($post_id)['button']['url']);
	}

	/**
	 * Removing the whole group keeps everything that was inside it.
	 */
	public function testRemovingAWholeGroupKeepsItsContents() {
		$this->givenSchema("fields:\n"
			. "  - name: headline\n    label: Headline\n    type: string\n"
			. "  - name: button\n    label: Button\n    type: object\n    fields:\n"
			. "      - name: label\n        label: Label\n        type: string\n");

		$post_id = $this->createTestPost([], [
			'headline' => 'Headline',
			'button' => ['label' => 'Go'],
		]);

		$this->givenSchema(self::ONLY_HEADLINE);
		$this->submit($post_id, ['headline' => 'Edited headline']);

		$this->assertSame(['label' => 'Go'], $this->storedFor($post_id)['button']);
	}

	// ------------------------------------------- what must still be cleared

	public function testAFieldStillInTheSchemaStillClears() {
		$post_id = $this->createTestPost([], ['headline' => 'Headline', 'subtitle' => 'Clear me']);

		$this->submit($post_id, ['headline' => 'Headline', 'subtitle' => '']);

		$this->assertSame('', $this->storedFor($post_id)['subtitle']);
	}

	/**
	 * A tick box sends nothing when unticked, so it needs its hidden companion
	 * or it can never be turned off again.
	 */
	public function testATickBoxCanBeUnticked() {
		$this->givenSchema("fields:\n  - name: featured\n    label: Featured\n    type: boolean\n");
		$post_id = $this->createTestPost([], ['featured' => '1']);

		$post = get_post($post_id);
		ob_start();
		try {
			\YAML_Custom_Fields::get_instance()->render_schema_meta_box($post);
		} finally {
			$html = ob_get_clean();
		}

		$this->assertStringContainsString(
			'<input type="hidden" name="yaml_cf[featured]" value="" />',
			$html,
			'Without the hidden companion an unticked box sends nothing and cannot be cleared.'
		);

		// What the browser sends for an unticked box: the hidden value alone
		$this->submit($post_id, ['featured' => '']);

		$this->assertSame('', $this->storedFor($post_id)['featured']);
	}

	/**
	 * Keys the plugin owns are not content. _template_global_override is left
	 * over from development builds before 1.2.0, and no screen can clear it.
	 */
	public function testThePluginsOwnKeysAreStillRemoved() {
		$post_id = $this->createTestPost([], [
			'headline' => 'Headline',
			'_template_global_override' => ['headline' => 'Stale override'],
		]);

		$this->submit($post_id, ['headline' => 'Edited headline']);

		$this->assertArrayNotHasKey('_template_global_override', $this->storedFor($post_id));
	}

	/**
	 * Moving a field into the shared schema hides the page's own copy without
	 * destroying it. The read side always takes a shared field from shared data,
	 * so the page shows the shared value — and moving the field back restores
	 * what each page had, which is the same promise as everywhere else.
	 */
	public function testAFieldMovedIntoTheSharedSchemaIsHiddenButKept() {
		update_option('yaml_cf_template_global_schemas', [
			self::TEMPLATE => "fields:\n  - name: subtitle\n    label: Subtitle\n    type: string\n",
		], false);
		update_option('yaml_cf_template_global_data', [self::TEMPLATE => ['subtitle' => 'Shared']], false);

		$post_id = $this->createTestPost([], ['headline' => 'Headline', 'subtitle' => 'Stale frozen copy']);

		$this->givenSchema(self::ONLY_HEADLINE);
		$this->submit($post_id, ['headline' => 'Edited headline']);

		// Hidden: the shared value is what the page shows
		$this->assertSame('Shared', yaml_cf_get_field('subtitle', $post_id));

		// Kept: moving the field back brings the page's own value back
		$this->assertSame('Stale frozen copy', $this->storedFor($post_id)['subtitle']);

		update_option('yaml_cf_template_global_schemas', [], false);
		$this->givenSchema(self::BOTH_FIELDS);
		$this->assertSame('Stale frozen copy', yaml_cf_get_field('subtitle', $post_id));
	}

	// ----------------------------------------------------------- theme reads

	public function testTheThemeStillSeesAKeptValue() {
		$post_id = $this->createTestPost([], ['headline' => 'Headline', 'subtitle' => 'Must survive']);

		$this->givenSchema(self::ONLY_HEADLINE);
		$this->submit($post_id, ['headline' => 'Edited headline']);

		$this->assertSame('Must survive', yaml_cf_get_field('subtitle', $post_id));
		$this->assertSame('Must survive', yaml_cf_get_fields($post_id)['subtitle']);
	}
}
