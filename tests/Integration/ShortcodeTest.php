<?php
/**
 * Integration: the [ycf] shortcode
 *
 * A field shows up on the website when the theme asks for it, which means
 * editing a PHP template. A block theme has no PHP template to edit, so until
 * now a block theme could hold field content and had no way to display any of
 * it. The shortcode is the way in: it works in a Shortcode block, a widget, or
 * a classic theme's content.
 *
 * Two things matter most here. Values have to be escaped for what they are —
 * text as text, rich text through wp_kses_post() — and naming another post has
 * to be checked, because anybody who can edit a post can write a shortcode, and
 * without a check it would be a way to read a field off a draft.
 *
 * @package YamlCF
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Tests\FailsOnPluginWarnings;
use YamlCF\Tests\WPTestCase;

class ShortcodeTest extends WPTestCase {
	use FailsOnPluginWarnings;

	const TEMPLATE = 'single.php';

	const SCHEMA = "fields:\n"
		. "  - name: headline\n    label: Headline\n    type: string\n"
		. "  - name: body\n    label: Body\n    type: rich-text\n"
		. "  - name: embed\n    label: Embed\n    type: code\n"
		. "  - name: featured\n    label: Featured\n    type: boolean\n"
		. "  - name: hero\n    label: Hero\n    type: image\n"
		. "  - name: gallery\n    label: Gallery\n    type: image\n    list: true\n"
		. "  - name: brochure\n    label: Brochure\n    type: file\n"
		. "  - name: topic\n    label: Topic\n    type: taxonomy\n    taxonomy: category\n"
		. "  - name: button\n    label: Button\n    type: object\n    fields:\n"
		. "      - name: label\n        label: Label\n        type: string\n";

	public function setUp(): void {
		parent::setUp();
		$this->failOnPluginWarnings();

		update_option('yaml_cf_schemas', [self::TEMPLATE => self::SCHEMA], false);
		update_option('yaml_cf_template_settings', [self::TEMPLATE => true], false);

		// init has already run for this process, so register directly
		\YamlCF\Core\Plugin::getInstance()->get('shortcode')->register();
	}

	public function tearDown(): void {
		$GLOBALS['post'] = null;
		$this->stopFailingOnPluginWarnings();
		parent::tearDown();
	}

	/**
	 * Run a shortcode as if it were in the content of the given post
	 */
	private function onPost($post_id, $shortcode) {
		$GLOBALS['post'] = get_post($post_id);
		setup_postdata($GLOBALS['post']);

		$out = do_shortcode($shortcode);

		wp_reset_postdata();

		return $out;
	}

	// ----------------------------------------------------------- the basics

	public function testItPrintsAFieldFromThePageItIsOn() {
		$post_id = $this->createTestPost([], ['headline' => 'Hello there']);

		$this->assertSame('Hello there', $this->onPost($post_id, '[ycf field="headline"]'));
	}

	public function testAMissingFieldPrintsNothing() {
		$post_id = $this->createTestPost([], []);

		$this->assertSame('', $this->onPost($post_id, '[ycf field="headline"]'));
	}

	public function testTheDefaultIsUsedWhenThereIsNoValue() {
		$post_id = $this->createTestPost([], []);

		$this->assertSame('Nothing yet', $this->onPost($post_id, '[ycf field="headline" default="Nothing yet"]'));
	}

	public function testAShortcodeWithNoFieldNamePrintsNothing() {
		$post_id = $this->createTestPost([], ['headline' => 'Hello']);

		$this->assertSame('', $this->onPost($post_id, '[ycf]'));
	}

	// ---------------------------------------------------------- escaping

	public function testPlainTextIsEscaped() {
		$post_id = $this->createTestPost([], ['headline' => '<script>alert(1)</script>']);

		$out = $this->onPost($post_id, '[ycf field="headline"]');

		$this->assertStringNotContainsString('<script', $out);
		$this->assertStringContainsString('&lt;script', $out);
	}

	public function testRichTextKeepsItsFormattingButNotScripts() {
		$post_id = $this->createTestPost([], ['body' => '<p>Keep <strong>this</strong></p><script>drop()</script>']);

		$out = $this->onPost($post_id, '[ycf field="body"]');

		$this->assertStringContainsString('<strong>this</strong>', $out);
		$this->assertStringNotContainsString('<script', $out);
	}

	/**
	 * A code field exists to put code on the page, and is cleaned when saved by
	 * anybody without unfiltered_html.
	 */
	public function testACodeFieldPrintsAsItStands() {
		$post_id = $this->createTestPost([], [
			'embed' => \YAML_Custom_Fields::CODE_FIELD_MARKER . base64_encode('<div class="embed">x</div>'),
		]);

		$this->assertSame('<div class="embed">x</div>', $this->onPost($post_id, '[ycf field="embed"]'));
	}

	// ------------------------------------------------------- by field type

	public function testABooleanReadsAsYesOrNo() {
		$yes = $this->createTestPost([], ['featured' => '1']);

		$this->assertSame('Yes', $this->onPost($yes, '[ycf field="featured"]'));
	}

	public function testAnImageBecomesAnImgTag() {
		$attachment_id = self::factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg');
		$post_id = $this->createTestPost([], ['hero' => (string) $attachment_id]);

		$out = $this->onPost($post_id, '[ycf field="hero"]');

		$this->assertStringContainsString('<img', $out);
	}

	public function testAGalleryPrintsEveryImage() {
		$one = self::factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg');
		$two = self::factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg');
		$post_id = $this->createTestPost([], ['gallery' => [(string) $one, (string) $two]]);

		$out = $this->onPost($post_id, '[ycf field="gallery"]');

		$this->assertSame(2, substr_count($out, '<img'));
	}

	public function testAFileBecomesALink() {
		$attachment_id = self::factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg');
		$post_id = $this->createTestPost([], ['brochure' => (string) $attachment_id]);

		$out = $this->onPost($post_id, '[ycf field="brochure"]');

		$this->assertStringContainsString('<a href=', $out);
	}

	public function testATaxonomyFieldPrintsTheTermName() {
		$term_id = self::factory()->term->create(['taxonomy' => 'category', 'name' => 'Reports']);
		$post_id = $this->createTestPost([], ['topic' => (string) $term_id]);

		$this->assertSame('Reports', $this->onPost($post_id, '[ycf field="topic"]'));
	}

	/**
	 * A group is not one value, so there is nothing sensible to print. It must
	 * not fall back to printing the array.
	 */
	public function testAGroupPrintsNothingRatherThanAnArray() {
		$post_id = $this->createTestPost([], ['button' => ['label' => 'Go']]);

		$out = $this->onPost($post_id, '[ycf field="button"]');

		$this->assertSame('', $out);
		$this->assertStringNotContainsString('Array', $out);
	}

	// ------------------------------------------------------- site details

	public function testItCanPrintASiteDetail() {
		update_option('yaml_cf_global_schema', "fields:\n  - name: phone\n    label: Phone\n    type: string\n", false);
		update_option('yaml_cf_global_data', ['phone' => '555-0100'], false);

		$post_id = $this->createTestPost([], []);

		$this->assertSame('555-0100', $this->onPost($post_id, '[ycf field="phone" source="site"]'));
	}

	// ------------------------------------------- reading another page

	public function testItCanPrintAFieldFromAnotherPublishedPage() {
		$other = $this->createTestPost([], ['headline' => 'From elsewhere']);
		$here = $this->createTestPost([], []);

		$out = $this->onPost($here, '[ycf field="headline" post="' . $other . '"]');

		$this->assertSame('From elsewhere', $out);
	}

	/**
	 * Anybody who may edit a post can write a shortcode. Naming another post has
	 * to be checked, or this is a way to read a field off somebody's draft.
	 */
	public function testItRefusesToReadFromADraftWhenNotAllowed() {
		$draft = $this->createTestPost(['post_status' => 'draft'], ['headline' => 'Not for you']);
		$here = $this->createTestPost([], []);

		wp_set_current_user(0);
		$out = $this->onPost($here, '[ycf field="headline" post="' . $draft . '"]');

		$this->assertSame('', $out);
		$this->assertStringNotContainsString('Not for you', $out);
	}

	public function testAnEditorCanStillReadFromTheirOwnDraft() {
		wp_set_current_user($this->createTestUser('administrator'));
		$draft = $this->createTestPost(['post_status' => 'draft'], ['headline' => 'Mine']);
		$here = $this->createTestPost([], []);

		$this->assertSame('Mine', $this->onPost($here, '[ycf field="headline" post="' . $draft . '"]'));
	}

	public function testAPostIdThatDoesNotExistPrintsNothing() {
		$here = $this->createTestPost([], []);

		$this->assertSame('', $this->onPost($here, '[ycf field="headline" post="999999"]'));
	}

	public function testOutsideAnyPostItPrintsNothing() {
		$GLOBALS['post'] = null;

		$this->assertSame('', do_shortcode('[ycf field="headline"]'));
	}
}
