<?php
/**
 * Integration: the theme-facing functions survive every shape of schema
 *
 * The third net, after AdminScreenSmokeTest (what the admin draws) and
 * AjaxSmokeTest (what the browser asks for). This one covers what a theme calls,
 * which is the surface where a warning ends up printed on a live page for
 * visitors to see.
 *
 * Every accessor is called for every field of every schema in the matrix, with
 * values saved, with nothing saved, inside a repeating section, on a site part,
 * and with arguments a theme can plausibly get wrong. A warning raised by the
 * plugin's own code fails the test and names the line.
 *
 * @package YamlCF
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Tests\FailsOnPluginWarnings;
use YamlCF\Tests\WPTestCase;

class PublicApiSmokeTest extends WPTestCase {
	use FailsOnPluginWarnings;

	const TEMPLATE = 'single.php';
	const PARTIAL = 'header.php';
	const COLLECTION = 'people';

	public function setUp(): void {
		parent::setUp();
		$this->failOnPluginWarnings();
	}

	public function tearDown(): void {
		$this->stopFailingOnPluginWarnings();
		parent::tearDown();
	}

	/**
	 * The same matrix the other two smoke tests use
	 *
	 * @return array<string, array{0: string}>
	 */
	public function schemas() {
		return (new AdminScreenSmokeTest('schemas'))->schemas();
	}

	/**
	 * Field names that appear anywhere in the matrix
	 *
	 * @return string[]
	 */
	private function everyFieldName() {
		return [
			'a_string', 'a_text', 'a_rich_text', 'a_code', 'a_boolean', 'a_number',
			'a_date', 'a_select', 'a_taxonomy', 'a_post_type', 'an_image', 'a_file',
			'gallery', 'downloads', 'topics', 'headline', 'body', 'phone', 'email',
			'one', 'two', 'only', 'button', 'details', 'sections',
			// Names generated for shorthand, and names no schema has at all
			'info_0', 'section_0', 'not_a_field_at_all', '', '0',
		];
	}

	private function applyEverywhere($yaml) {
		update_option('yaml_cf_schemas', [
			self::TEMPLATE => $yaml,
			self::PARTIAL => $yaml,
		], false);
		update_option('yaml_cf_template_settings', [
			self::TEMPLATE => true,
			self::TEMPLATE . '_use_global' => true,
		], false);
		update_option('yaml_cf_template_global_schemas', [self::TEMPLATE => $yaml], false);
		update_option('yaml_cf_global_schema', $yaml, false);
		update_option('yaml_cf_data_object_types', [
			self::COLLECTION => ['name' => 'People', 'schema' => $yaml],
		], false);
	}

	/**
	 * A plausible value for every field the matrix can contain
	 */
	private function sampleData($attachment_id, $term_id) {
		return [
			'a_string' => 'Text',
			'a_text' => "Two\nlines",
			'a_rich_text' => '<p>Rich</p>',
			'a_code' => \YAML_Custom_Fields::CODE_FIELD_MARKER . base64_encode('<b>x</b>'),
			'a_boolean' => '1',
			'a_number' => '42',
			'a_date' => '2026-01-01',
			'a_select' => 'one',
			'a_taxonomy' => (string) $term_id,
			'a_post_type' => 'post',
			'an_image' => (string) $attachment_id,
			'a_file' => (string) $attachment_id,
			'gallery' => [(string) $attachment_id],
			'downloads' => [(string) $attachment_id],
			'topics' => [(string) $term_id],
			'headline' => 'Headline',
			'body' => 'Body',
			'phone' => '555-0100',
			'email' => 'a@example.org',
			'one' => 'One',
			'two' => 'Two',
			'only' => 'Only',
			'button' => ['label' => 'Go', 'url' => 'https://example.org'],
			'details' => ['label' => 'Detail'],
			'sections' => [
				['type' => 'banner', 'image' => (string) $attachment_id, 'title' => 'Banner title'],
				['type' => 'quote', 'body' => '<p>Quoted</p>'],
			],
		];
	}

	/**
	 * Call every theme-facing function for every field name
	 *
	 * @param int|string|null $post_id What a theme would pass as the second argument
	 */
	private function callEveryAccessor($post_id) {
		$all = yaml_cf_get_fields($post_id);
		$this->assertIsArray($all);

		foreach ($this->everyFieldName() as $name) {
			yaml_cf_get_field($name, $post_id);
			yaml_cf_has_field($name, $post_id);
			yaml_cf_get_image($name, $post_id);
			yaml_cf_get_image($name, $post_id, 'thumbnail');
			yaml_cf_get_file($name, $post_id);
			yaml_cf_get_term($name, $post_id);
			yaml_cf_get_post_type($name, $post_id);
			yaml_cf_get_data_object($name, $post_id);

			// The short aliases a theme is more likely to use
			ycf_get_field($name, $post_id);
			ycf_has_field($name, $post_id);
			ycf_get_image($name, $post_id);
			ycf_get_file($name, $post_id);
		}

		// Reading inside a repeating section, the documented block-context form
		$sections = yaml_cf_get_field('sections', $post_id);
		if (is_array($sections)) {
			foreach ($sections as $section) {
				if (!is_array($section)) {
					continue;
				}
				foreach ($this->everyFieldName() as $name) {
					yaml_cf_get_field($name, $post_id, $section);
					yaml_cf_get_image($name, $post_id, 'full', $section);
					yaml_cf_get_file($name, $post_id, $section);
					yaml_cf_get_term($name, $post_id, $section);
				}
			}
		}
	}

	/**
	 * @dataProvider schemas
	 */
	public function testEveryAccessorWorksOnAFilledInPage($yaml) {
		$this->applyEverywhere($yaml);

		$attachment_id = self::factory()->attachment->create();
		$term_id = self::factory()->term->create(['taxonomy' => 'category']);
		$post_id = $this->createTestPost([], $this->sampleData($attachment_id, $term_id));

		$this->callEveryAccessor($post_id);
	}

	/**
	 * A page with nothing saved yet is the state every page starts in.
	 *
	 * @dataProvider schemas
	 */
	public function testEveryAccessorWorksOnAnEmptyPage($yaml) {
		$this->applyEverywhere($yaml);

		$post_id = $this->createTestPost();

		$this->callEveryAccessor($post_id);
	}

	/**
	 * Values pointing at things that have since been deleted: an attachment
	 * removed from the media library, a term deleted, a post trashed.
	 *
	 * @dataProvider schemas
	 */
	public function testEveryAccessorWorksWhenTheThingsPointedAtAreGone($yaml) {
		$this->applyEverywhere($yaml);

		$attachment_id = self::factory()->attachment->create();
		$term_id = self::factory()->term->create(['taxonomy' => 'category']);
		$post_id = $this->createTestPost([], $this->sampleData($attachment_id, $term_id));

		wp_delete_attachment($attachment_id, true);
		wp_delete_term($term_id, 'category');

		$this->callEveryAccessor($post_id);
	}

	/**
	 * Arguments a theme can plausibly get wrong, or that arrive by accident.
	 *
	 * @dataProvider schemas
	 */
	public function testEveryAccessorCopesWithAwkwardArguments($yaml) {
		$this->applyEverywhere($yaml);

		// No post at all: what happens outside the loop
		$this->callEveryAccessor(null);

		// A post ID that does not exist
		$this->callEveryAccessor(999999);

		// A site part, by the documented "partial:" form, and one that is missing
		$this->callEveryAccessor('partial:' . self::PARTIAL);
		$this->callEveryAccessor('partial:no-such-file.php');
	}

	/**
	 * Site details and collections have their own accessors.
	 *
	 * @dataProvider schemas
	 */
	public function testTheSiteWideAccessorsWork($yaml) {
		$this->applyEverywhere($yaml);

		$attachment_id = self::factory()->attachment->create();
		$term_id = self::factory()->term->create(['taxonomy' => 'category']);
		update_option('yaml_cf_global_data', $this->sampleData($attachment_id, $term_id), false);
		update_option('yaml_cf_data_object_entries_' . self::COLLECTION, [
			'entry_1' => $this->sampleData($attachment_id, $term_id),
		], false);

		$this->assertIsArray(yaml_cf_get_global_fields());
		$this->assertIsArray(yaml_cf_get_data_objects(self::COLLECTION));
		$this->assertIsArray(yaml_cf_get_data_objects('no_such_collection'));

		foreach ($this->everyFieldName() as $name) {
			yaml_cf_get_global_field($name);
			yaml_cf_has_global_field($name);
		}
	}

	/**
	 * A code field is stored encoded and has to come back decoded, whichever
	 * accessor asks and whichever source the value came from.
	 *
	 * @dataProvider schemas
	 */
	public function testCodeFieldsComeBackDecoded($yaml) {
		$this->applyEverywhere($yaml);

		$post_id = $this->createTestPost([], [
			'a_code' => \YAML_Custom_Fields::CODE_FIELD_MARKER . base64_encode('<b>decoded</b>'),
		]);

		$value = yaml_cf_get_field('a_code', $post_id);

		if ($value !== null) {
			$this->assertStringNotContainsString(
				\YAML_Custom_Fields::CODE_FIELD_MARKER,
				(string) $value,
				'A code field must never reach a theme still carrying its marker.'
			);
		}

		$all = yaml_cf_get_fields($post_id);
		if (isset($all['a_code'])) {
			$this->assertStringNotContainsString(\YAML_Custom_Fields::CODE_FIELD_MARKER, (string) $all['a_code']);
		}
	}
}
