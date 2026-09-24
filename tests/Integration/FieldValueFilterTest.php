<?php
/**
 * Integration: the yaml_cf_field_value filter
 *
 * The README documented this filter long before it existed, so these tests pin
 * down that it now runs for every route a value can arrive by.
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Tests\WPTestCase;

class FieldValueFilterTest extends WPTestCase {
	const SCHEMA = "fields:\n  - name: headline\n    type: string\n";

	private $calls = [];

	public function setUp(): void {
		parent::setUp();
		update_option('yaml_cf_schemas', ['single.php' => self::SCHEMA]);
		update_option('yaml_cf_template_settings', ['single.php' => true]);
		$this->calls = [];
	}

	public function tearDown(): void {
		remove_all_filters('yaml_cf_field_value');
		parent::tearDown();
	}

	public function testFilterCanChangeAValueFromPostData() {
		$post_id = $this->createTestPost();
		update_post_meta($post_id, '_yaml_cf_data', ['headline' => 'Stored']);

		add_filter('yaml_cf_field_value', function ($value) {
			return is_string($value) ? strtoupper($value) : $value;
		});

		$this->assertSame('STORED', yaml_cf_get_field('headline', $post_id));
	}

	public function testFilterReceivesTheFieldNameAndPostId() {
		$post_id = $this->createTestPost();
		update_post_meta($post_id, '_yaml_cf_data', ['headline' => 'Stored']);

		add_filter('yaml_cf_field_value', function ($value, $field_name, $filtered_post_id) {
			$this->calls[] = [$field_name, $filtered_post_id];
			return $value;
		}, 10, 3);

		yaml_cf_get_field('headline', $post_id);

		$this->assertSame([['headline', $post_id]], $this->calls);
	}

	/**
	 * A field with no value still passes through, so a filter can supply a
	 * default rather than the theme having to check for null.
	 */
	public function testFilterRunsForAMissingField() {
		$post_id = $this->createTestPost();

		add_filter('yaml_cf_field_value', function ($value) {
			return $value === null ? 'fallback' : $value;
		});

		$this->assertSame('fallback', yaml_cf_get_field('headline', $post_id));
	}

	public function testFilterRunsForBlockContextValues() {
		add_filter('yaml_cf_field_value', function ($value, $field_name, $post_id, $context_data) {
			$this->calls[] = is_array($context_data);
			return $value;
		}, 10, 4);

		$this->assertSame('In block', yaml_cf_get_field('title', null, ['title' => 'In block']));
		$this->assertSame([true], $this->calls);
	}

	public function testUnfilteredAccessorSkipsTheFilter() {
		$post_id = $this->createTestPost();
		update_post_meta($post_id, '_yaml_cf_data', ['headline' => 'Stored']);

		add_filter('yaml_cf_field_value', function () {
			return 'filtered';
		});

		$this->assertSame('Stored', yaml_cf_get_field_unfiltered('headline', $post_id));
		$this->assertSame('filtered', yaml_cf_get_field('headline', $post_id));
	}
}
