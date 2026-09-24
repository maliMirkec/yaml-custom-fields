<?php
/**
 * Integration: where the custom fields box is printed
 *
 * Until 1.3.0 the box was printed on edit_form_after_title, directly under the
 * title and above the content. That hook does not fire in the block editor, so
 * making it a real meta box was the only way for the fields to appear there at
 * all — but a meta box in the "normal" context sits *below* the content, and
 * people who had been using the classic editor found their fields had moved.
 *
 * Both are now true at once: the classic editor keeps the old position, and the
 * block editor gets the meta box, because above the content is the block canvas
 * and no meta box can go there.
 *
 * What these tests guard is that exactly one of the two paths runs. Printing
 * twice would mean two sets of inputs with the same names on one page.
 *
 * @package YamlCF
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Tests\FailsOnPluginWarnings;
use YamlCF\Tests\WPTestCase;

class MetaBoxPlacementTest extends WPTestCase {
	use FailsOnPluginWarnings;

	const SCHEMA = "fields:\n  - name: headline\n    label: Headline\n    type: string\n";

	public function setUp(): void {
		parent::setUp();
		$this->failOnPluginWarnings();
		update_option('yaml_cf_schemas', ['single.php' => self::SCHEMA], false);
		update_option('yaml_cf_template_settings', ['single.php' => true], false);
		set_current_screen('post');
	}

	public function tearDown(): void {
		remove_all_filters('use_block_editor_for_post');
		$this->stopFailingOnPluginWarnings();
		parent::tearDown();
	}

	/**
	 * Decide which editor WordPress reports for every post
	 */
	private function usingBlockEditor($yes) {
		add_filter('use_block_editor_for_post', function () use ($yes) {
			return $yes;
		}, 99);
	}

	/**
	 * Whether our box is registered for the "normal" (below content) area
	 */
	private function metaBoxIsRegistered($post) {
		global $wp_meta_boxes;
		$wp_meta_boxes = [];

		do_action('add_meta_boxes', $post->post_type, $post);

		return isset($wp_meta_boxes[$post->post_type]['normal']['high']['yaml-cf-meta-box'])
			&& $wp_meta_boxes[$post->post_type]['normal']['high']['yaml-cf-meta-box'] !== false;
	}

	/**
	 * What edit_form_after_title prints (the classic editor's above-content slot)
	 */
	private function afterTitleOutput($post) {
		ob_start();
		try {
			do_action('edit_form_after_title', $post);
		} finally {
			$html = ob_get_clean();
		}

		return $html;
	}

	// ------------------------------------------------------- classic editor

	public function testTheClassicEditorPrintsTheBoxAboveTheContent() {
		$this->usingBlockEditor(false);
		$post = get_post($this->createTestPost());

		$html = $this->afterTitleOutput($post);

		$this->assertStringContainsString('yaml_cf_meta_box_nonce', $html);
		$this->assertStringContainsString('Headline', $html);
	}

	public function testTheClassicEditorDoesNotAlsoRegisterAMetaBox() {
		$this->usingBlockEditor(false);
		$post = get_post($this->createTestPost());

		$this->assertFalse(
			$this->metaBoxIsRegistered($post),
			'The classic editor prints the box above the content, so registering a meta box as well would show it twice.'
		);
	}

	// --------------------------------------------------------- block editor

	public function testTheBlockEditorRegistersAMetaBox() {
		$this->usingBlockEditor(true);
		$post = get_post($this->createTestPost());

		$this->assertTrue($this->metaBoxIsRegistered($post));
	}

	public function testTheBlockEditorPrintsNothingAboveTheContent() {
		$this->usingBlockEditor(true);
		$post = get_post($this->createTestPost());

		$this->assertSame('', trim($this->afterTitleOutput($post)));
	}

	// ------------------------------------------------------------- shared

	/**
	 * Whichever way the box is printed, the form has to post the same thing, or
	 * saving would break in one editor and not the other.
	 */
	public function testBothEditorsPostTheSameFieldNames() {
		$post = get_post($this->createTestPost([], ['headline' => 'Stored']));

		$this->usingBlockEditor(false);
		$classic = $this->afterTitleOutput($post);

		remove_all_filters('use_block_editor_for_post');
		$this->usingBlockEditor(true);

		ob_start();
		try {
			\YAML_Custom_Fields::get_instance()->render_schema_meta_box($post);
		} finally {
			$block = ob_get_clean();
		}

		foreach (['name="yaml_cf[headline]"', 'yaml_cf_meta_box_nonce'] as $needle) {
			$this->assertStringContainsString($needle, $classic, 'Classic editor markup');
			$this->assertStringContainsString($needle, $block, 'Block editor markup');
		}
	}

	/**
	 * A post whose template has no schema gets nothing, in either editor.
	 */
	public function testAPostWithNoSchemaGetsNothingEitherWay() {
		delete_option('yaml_cf_schemas');
		$post = get_post($this->createTestPost());

		$this->usingBlockEditor(false);
		$this->assertSame('', trim($this->afterTitleOutput($post)));
		$this->assertFalse($this->metaBoxIsRegistered($post));

		remove_all_filters('use_block_editor_for_post');
		$this->usingBlockEditor(true);
		$this->assertFalse($this->metaBoxIsRegistered($post));
	}

	/**
	 * Saving is unaffected by placement: the classic path has to round-trip too.
	 */
	public function testSavingWorksFromTheClassicEditorPlacement() {
		$this->usingBlockEditor(false);
		$this->actingAs($this->createTestUser('administrator'));
		$post_id = $this->createTestPost();

		$_POST = wp_slash([
			'yaml_cf_meta_box_nonce' => wp_create_nonce('yaml_cf_meta_box'),
			'yaml_cf' => ['headline' => 'Typed in the classic editor'],
		]);
		\YAML_Custom_Fields::get_instance()->save_schema_data($post_id);
		$_POST = [];

		$this->assertSame('Typed in the classic editor', yaml_cf_get_field('headline', $post_id));
	}
	// ------------------------------------- the structure the scripts rely on

	/**
	 * Two dozen style rules and every script that touches the fields are written
	 * against "#yaml-cf-meta-box > .inside > .yaml-cf-fields" — "Empty all
	 * fields" and gallery drag-sorting among them. The classic editor prints its
	 * own wrapper rather than one WordPress drew, so it has to reproduce that
	 * structure exactly or the box looks wrong and half of it stops working.
	 */
	public function testTheClassicWrapperCarriesTheMetaBoxId() {
		$this->usingBlockEditor(false);
		$post = get_post($this->createTestPost());

		$html = $this->afterTitleOutput($post);

		$this->assertStringContainsString('id="yaml-cf-meta-box"', $html);
	}

	public function testTheClassicWrapperReproducesTheInsideStructure() {
		$this->usingBlockEditor(false);
		$post = get_post($this->createTestPost());

		$html = $this->afterTitleOutput($post);

		$document = new \DOMDocument();
		libxml_use_internal_errors(true);
		$document->loadHTML('<?xml encoding="utf-8" ?><body>' . $html . '</body>');
		libxml_clear_errors();

		$xpath = new \DOMXPath($document);

		$this->assertSame(
			1,
			$xpath->query('//div[@id="yaml-cf-meta-box"]/div[contains(@class, "inside")]/div[contains(@class, "yaml-cf-fields")]')->length,
			'admin.js targets #yaml-cf-meta-box > .inside > .yaml-cf-fields; the classic wrapper must match it.'
		);
	}

	/**
	 * The id has to stay unique: it is an id, and the scripts take the first hit.
	 */
	public function testTheIdIsPrintedOnlyOnceInEitherEditor() {
		$post = get_post($this->createTestPost());

		$this->usingBlockEditor(false);
		$classic = $this->afterTitleOutput($post);
		$this->assertSame(1, substr_count($classic, 'id="yaml-cf-meta-box"'));
		$this->assertFalse($this->metaBoxIsRegistered($post));

		remove_all_filters('use_block_editor_for_post');
		$this->usingBlockEditor(true);
		$this->assertSame('', trim($this->afterTitleOutput($post)));
		$this->assertTrue($this->metaBoxIsRegistered($post));
	}

	/**
	 * The header bleeds to the edges of the box with a negative margin measured
	 * against the padding in "#yaml-cf-meta-box .inside". If the wrapper ever
	 * stops matching that selector the header hangs outside the box.
	 */
	public function testTheHeaderSitsInsideTheBoxThatPadsIt() {
		$this->usingBlockEditor(false);
		$post = get_post($this->createTestPost());

		$html = $this->afterTitleOutput($post);

		$document = new \DOMDocument();
		libxml_use_internal_errors(true);
		$document->loadHTML('<?xml encoding="utf-8" ?><body>' . $html . '</body>');
		libxml_clear_errors();

		$xpath = new \DOMXPath($document);

		$this->assertSame(
			1,
			$xpath->query('//div[@id="yaml-cf-meta-box"]/div[contains(@class, "inside")]/div[contains(@class, "yaml-cf-meta-box-header")]')->length,
			'The header must be a child of the padded .inside, not of the postbox itself.'
		);
	}

}
