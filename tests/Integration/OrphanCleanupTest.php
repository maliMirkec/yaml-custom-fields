<?php
/**
 * Integration: finding and removing values whose field is gone
 *
 * Removing a field from a schema keeps its content, which is the right default
 * but leaves values nothing displays. The Content Check screen lists them and
 * offers to remove them — deliberately, never as a side effect of anything else.
 *
 * The dangerous case, and the reason several of these tests exist: a post whose
 * template has no schema at all. "No schema" must not be read as "every value is
 * stranded", or the screen would offer to wipe every field on those pages in one
 * click.
 *
 * @package YamlCF
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Tests\FailsOnPluginWarnings;
use YamlCF\Tests\WPTestCase;

class OrphanCleanupTest extends WPTestCase {
	use FailsOnPluginWarnings;

	const TEMPLATE = 'single.php';
	const SCHEMA = "fields:\n  - name: headline\n    label: Headline\n    type: string\n";

	public function setUp(): void {
		parent::setUp();
		$this->failOnPluginWarnings();
		update_option('yaml_cf_schemas', [self::TEMPLATE => self::SCHEMA], false);
		update_option('yaml_cf_template_settings', [self::TEMPLATE => true], false);
		$this->actingAs($this->createTestUser('administrator'));
	}

	public function tearDown(): void {
		$this->stopFailingOnPluginWarnings();
		$_POST = [];
		$_GET = [];
		parent::tearDown();
	}

	private function render() {
		ob_start();
		try {
			\YamlCF\Core\Plugin::getInstance()->get('validation_controller')->render();
		} finally {
			$html = ob_get_clean();
		}

		return $html;
	}

	/**
	 * Submit the removal form for the given posts and field names
	 *
	 * @param array<int, string[]> $orphans post ID => field names
	 */
	private function submitRemoval(array $orphans) {
		$_POST = wp_slash([
			'yaml_cf_remove_orphans_nonce' => wp_create_nonce('yaml_cf_remove_orphans'),
			'yaml_cf_orphans' => $orphans,
		]);

		$html = $this->render();
		$_POST = [];

		return $html;
	}

	private function storedFor($post_id) {
		$stored = get_post_meta($post_id, '_yaml_cf_data', true);

		return is_array($stored) ? $stored : [];
	}

	/**
	 * Everything the notice queue is holding, as one string. Notices are printed
	 * by the admin_notices hook rather than inline in the page.
	 */
	private function queuedNotices() {
		ob_start();
		try {
			do_action('admin_notices');
		} finally {
			$html = ob_get_clean();
		}

		return $html;
	}

	// -------------------------------------------------------------- finding

	public function testAStrandedValueIsListed() {
		$post_id = $this->createTestPost([], ['headline' => 'Headline', 'subtitle' => 'Stranded']);

		$html = $this->render();

		$this->assertStringContainsString('subtitle', $html);
		$this->assertStringContainsString('No field for:', $html);
		$this->assertStringContainsString('Values with no field', $html);
	}

	public function testAPageWhoseFieldsAreAllInTheSchemaIsNotListed() {
		$this->createTestPost([], ['headline' => 'Headline']);

		$html = $this->render();

		$this->assertStringNotContainsString('No field for:', $html);
	}

	/**
	 * A name reused inside a group is a different field. The stranded top-level
	 * value has to still be found, or it is invisible to this screen for ever.
	 */
	public function testANameReusedInsideAGroupDoesNotHideAStrandedValue() {
		update_option('yaml_cf_schemas', [
			self::TEMPLATE => "fields:\n"
				. "  - name: hero\n    label: Hero\n    type: object\n    fields:\n"
				. "      - name: subtitle\n        label: Subtitle\n        type: string\n",
		], false);

		$this->createTestPost([], ['subtitle' => 'Stranded at the top level']);

		$this->assertStringContainsString('subtitle', $this->render());
	}

	/**
	 * The plugin's own bookkeeping keys are not the writer's content. Not
	 * cleanup; not listed.
	 */
	public function testThePluginsOwnKeysAreNotOffered() {
		$this->createTestPost([], [
			'headline' => 'Headline',
			'_template_global_override' => ['headline' => 'Override'],
		]);

		$this->assertStringNotContainsString('_template_global_override', $this->render());
	}

	// ----------------------------------------------- the no-schema footgun

	/**
	 * The one that matters. A template with no schema means nothing is known
	 * about the post's fields — not that all of them are stranded.
	 */
	public function testAPageWithNoSchemaIsNotTreatedAsAllStranded() {
		delete_option('yaml_cf_schemas');
		$this->createTestPost([], ['headline' => 'Headline', 'subtitle' => 'Subtitle']);

		$html = $this->render();

		$this->assertStringNotContainsString('No field for:', $html);
		$this->assertStringContainsString('not checked', $html);
	}

	public function testRemovalRefusesToTouchAPageWithNoSchema() {
		$post_id = $this->createTestPost([], ['headline' => 'Headline', 'subtitle' => 'Subtitle']);
		delete_option('yaml_cf_schemas');

		$this->submitRemoval([$post_id => ['headline', 'subtitle']]);

		$this->assertSame(
			['headline' => 'Headline', 'subtitle' => 'Subtitle'],
			$this->storedFor($post_id),
			'A post with no schema must never be wiped by the cleanup.'
		);
	}

	// ------------------------------------------------------------- removing

	public function testRemovingTakesOnlyTheStrandedValue() {
		$post_id = $this->createTestPost([], ['headline' => 'Headline', 'subtitle' => 'Stranded']);

		$this->submitRemoval([$post_id => ['subtitle']]);

		$this->assertSame(['headline' => 'Headline'], $this->storedFor($post_id));
	}

	/**
	 * The form's list is a claim, not an instruction. If the field went back into
	 * the schema while the page sat open, removing it would destroy content that
	 * is in use again.
	 */
	public function testAFieldPutBackIntoTheSchemaIsNotRemoved() {
		$post_id = $this->createTestPost([], ['headline' => 'Headline', 'subtitle' => 'Back in use']);

		// The page was rendered when "subtitle" was stranded; by the time the
		// form is submitted an administrator has put it back
		update_option('yaml_cf_schemas', [
			self::TEMPLATE => self::SCHEMA . "  - name: subtitle\n    label: Subtitle\n    type: string\n",
		], false);

		$this->submitRemoval([$post_id => ['subtitle']]);

		$this->assertSame('Back in use', $this->storedFor($post_id)['subtitle']);
	}

	public function testRemovingNothingSaysSo() {
		$post_id = $this->createTestPost([], ['headline' => 'Headline']);

		$this->submitRemoval([$post_id => ['headline']]);

		$this->assertStringContainsString('Nothing was removed', $this->queuedNotices());
	}

	public function testRemovingReportsWhatItDid() {
		$post_id = $this->createTestPost([], ['headline' => 'Headline', 'subtitle' => 'Stranded']);

		$this->submitRemoval([$post_id => ['subtitle']]);

		$this->assertStringContainsString('1 stored value was removed', $this->queuedNotices());
	}

	/**
	 * A post left with nothing should lose its row rather than keep an empty
	 * array, or it turns up on this screen for ever.
	 */
	public function testAPageLeftWithNothingLosesItsRowEntirely() {
		update_option('yaml_cf_schemas', [self::TEMPLATE => "fields: []\n"], false);
		$post_id = $this->createTestPost([], ['subtitle' => 'Stranded']);
		\YamlCF\Cache\PostTracker::track($post_id);

		$this->submitRemoval([$post_id => ['subtitle']]);

		$this->assertSame('', get_post_meta($post_id, '_yaml_cf_data', true));
		$this->assertNotContains($post_id, \YamlCF\Cache\PostTracker::getAll());
	}

	public function testOtherPagesAreLeftAlone() {
		$target = $this->createTestPost([], ['headline' => 'A', 'subtitle' => 'Stranded']);
		$bystander = $this->createTestPost([], ['headline' => 'B', 'subtitle' => 'Also stranded']);

		$this->submitRemoval([$target => ['subtitle']]);

		$this->assertSame('Also stranded', $this->storedFor($bystander)['subtitle']);
	}

	// --------------------------------------------------------- permissions

	public function testARequestWithoutAValidNonceIsRefused() {
		$post_id = $this->createTestPost([], ['headline' => 'Headline', 'subtitle' => 'Stranded']);

		$_POST = wp_slash([
			'yaml_cf_remove_orphans_nonce' => 'not-a-real-nonce',
			'yaml_cf_orphans' => [$post_id => ['subtitle']],
		]);

		$died = false;
		ob_start();
		try {
			\YamlCF\Core\Plugin::getInstance()->get('validation_controller')->render();
		} catch (\WPDieException $e) {
			$died = true;
		} finally {
			ob_end_clean();
			$_POST = [];
		}

		$this->assertTrue($died);
		$this->assertSame('Stranded', $this->storedFor($post_id)['subtitle']);
	}

	/**
	 * The screen needs manage_options, but taking content off one post is still
	 * an edit of that post.
	 */
	public function testAPostTheUserCannotEditIsSkipped() {
		$post_id = $this->createTestPost([], ['headline' => 'Headline', 'subtitle' => 'Stranded']);

		add_filter('user_has_cap', function ($allcaps, $caps, $args) use ($post_id) {
			if (isset($args[0], $args[2]) && $args[0] === 'edit_post' && (int) $args[2] === $post_id) {
				unset($allcaps['edit_posts'], $allcaps['edit_others_posts'], $allcaps['edit_published_posts']);
			}

			return $allcaps;
		}, 10, 3);

		$this->submitRemoval([$post_id => ['subtitle']]);

		remove_all_filters('user_has_cap');

		$this->assertSame('Stranded', $this->storedFor($post_id)['subtitle']);
	}

	/**
	 * A page's own copy of a field that moved into the shared fields is kept on
	 * purpose, so moving it back restores it. It is not cleanup.
	 */
	public function testACopyOfASharedFieldIsNotOffered() {
		update_option('yaml_cf_template_global_schemas', [self::TEMPLATE => "fields:\n  - name: disclaimer\n    type: string\n"], false);
		$post_id = $this->createTestPost([], ['headline' => 'Headline', 'disclaimer' => 'Page copy']);

		$this->assertStringNotContainsString('No field for:', $this->render());

		$this->submitRemoval([$post_id => ['disclaimer']]);
		$this->assertSame('Page copy', $this->storedFor($post_id)['disclaimer'] ?? null);
	}

	/**
	 * YAML reads "name: 2024" as a number. That field is as live as any other.
	 */
	public function testAFieldNamedWithDigitsIsNotTakenForStranded() {
		update_option('yaml_cf_schemas', [self::TEMPLATE => self::SCHEMA . "  - name: 2024\n    type: string\n"], false);
		$post_id = $this->createTestPost([], ['headline' => 'Headline', '2024' => 'The year in review']);

		$this->assertStringNotContainsString('No field for:', $this->render());

		$this->submitRemoval([$post_id => ['2024']]);
		$this->assertSame('The year in review', $this->storedFor($post_id)['2024'] ?? null);
	}

	/**
	 * A stranded value is still returned to a theme that asks for it by name, so
	 * removing it changes the page: page caches are told, as on a save.
	 */
	public function testRemovingClearsThePagesCache() {
		$post_id = $this->createTestPost([], ['headline' => 'Headline', 'subtitle' => 'Stranded']);
		$cleaned = [];
		$record = function ($id) use (&$cleaned) {
			$cleaned[] = (int) $id;
		};
		add_action('clean_post_cache', $record);

		$this->submitRemoval([$post_id => ['subtitle']]);
		remove_action('clean_post_cache', $record);

		$this->assertContains($post_id, $cleaned);
	}
}
