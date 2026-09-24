<?php
/**
 * Integration: yaml_cf_get_field() and yaml_cf_get_fields() resolve the same way
 *
 * The two public accessors used to walk their own precedence rules, so the same
 * field on the same post could come back with two different values. These tests
 * pin every source a value can come from to one documented order:
 *
 *   1. The shared value, when "Use the shared value on this page" is ticked.
 *   2. The value on the page.
 *   3. The shared value, for fields that only exist as shared fields.
 *   4. Site details.
 *
 * Each case is asserted through both accessors, so they cannot drift apart again.
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Tests\WPTestCase;

class FieldResolutionTest extends WPTestCase {
	/** Post schema: both fields also exist as shared fields */
	const POST_SCHEMA = "fields:\n  - name: headline\n    label: Headline\n    type: string\n  - name: byline\n    label: Byline\n    type: string\n";

	/** Shared (template global) schema: adds a field the post schema does not have */
	const SHARED_SCHEMA = "fields:\n  - name: headline\n    label: Headline\n    type: string\n  - name: byline\n    label: Byline\n    type: string\n  - name: disclaimer\n    label: Disclaimer\n    type: string\n";

	/** Site details schema */
	const GLOBAL_SCHEMA = "fields:\n  - name: headline\n    label: Headline\n    type: string\n  - name: phone\n    label: Phone\n    type: string\n";

	public function setUp(): void {
		parent::setUp();
		update_option('yaml_cf_schemas', ['single.php' => self::POST_SCHEMA]);
		update_option('yaml_cf_template_settings', ['single.php' => true]);
	}

	/**
	 * Assert both accessors agree, and return the value they agree on.
	 *
	 * @param string $field_name Field to read
	 * @param int $post_id Post to read it from
	 * @return mixed The agreed value
	 */
	private function assertAccessorsAgree($field_name, $post_id) {
		$single = yaml_cf_get_field($field_name, $post_id);
		$all = yaml_cf_get_fields($post_id);
		$from_all = isset($all[$field_name]) ? $all[$field_name] : null;

		$this->assertSame(
			$single,
			$from_all,
			sprintf(
				'yaml_cf_get_field(%1$s) returned %2$s but yaml_cf_get_fields()[%1$s] returned %3$s',
				$field_name,
				wp_json_encode($single),
				wp_json_encode($from_all)
			)
		);

		return $single;
	}

	private function givenSharedFields(array $values) {
		update_option('yaml_cf_template_global_schemas', ['single.php' => self::SHARED_SCHEMA]);
		update_option('yaml_cf_template_global_data', ['single.php' => $values]);
	}

	private function givenSiteDetails(array $values) {
		update_option('yaml_cf_global_schema', self::GLOBAL_SCHEMA);
		update_option('yaml_cf_global_data', $values);
		$settings = (array) get_option('yaml_cf_template_settings', []);
		$settings['single.php_use_global'] = true;
		update_option('yaml_cf_template_settings', $settings);
	}

	// ------------------------------------------------------- 1. the tick wins

	/**
	 * The editor ticked "Use the shared value on this page", so both accessors
	 * must report the shared value and ignore what the page itself holds.
	 */
	public function testTickedFieldUsesTheSharedValueInBothAccessors() {
		$post_id = $this->createTestPost([], ['headline' => 'Page headline']);
		$this->givenSharedFields(['headline' => 'Shared headline']);
		update_post_meta($post_id, '_yaml_cf_use_template_global_fields', ['headline' => '1']);

		$this->assertSame('Shared headline', $this->assertAccessorsAgree('headline', $post_id));
	}

	/**
	 * A tick on one field must not drag any other field to its shared value.
	 */
	public function testAnUntickedFieldKeepsThePageValueWhileAnotherIsTicked() {
		$post_id = $this->createTestPost([], ['headline' => 'Page headline', 'byline' => 'Page byline']);
		$this->givenSharedFields(['headline' => 'Shared headline', 'byline' => 'Shared byline']);
		update_post_meta($post_id, '_yaml_cf_use_template_global_fields', ['headline' => '1']);

		$this->assertSame('Shared headline', $this->assertAccessorsAgree('headline', $post_id));
		$this->assertSame('Page byline', $this->assertAccessorsAgree('byline', $post_id));
	}

	/**
	 * A tick with nothing filled in on the shared side falls back to the page,
	 * rather than blanking the field.
	 */
	public function testTickWithNoSharedValueFallsBackToThePageValue() {
		$post_id = $this->createTestPost([], ['headline' => 'Page headline']);
		$this->givenSharedFields([]);
		update_post_meta($post_id, '_yaml_cf_use_template_global_fields', ['headline' => '1']);

		$this->assertSame('Page headline', $this->assertAccessorsAgree('headline', $post_id));
	}

	// -------------------------------------------------- 2. the page value wins

	public function testUntickedFieldUsesThePageValue() {
		$post_id = $this->createTestPost([], ['headline' => 'Page headline']);
		$this->givenSharedFields(['headline' => 'Shared headline']);

		$this->assertSame('Page headline', $this->assertAccessorsAgree('headline', $post_id));
	}

	/**
	 * A page that was saved with the field left empty keeps its empty value:
	 * clearing a field is a decision, not a reason to show the shared value.
	 */
	public function testAnEmptyPageValueIsNotReplacedByTheSharedValue() {
		$post_id = $this->createTestPost([], ['headline' => '']);
		$this->givenSharedFields(['headline' => 'Shared headline']);

		$this->assertSame('', $this->assertAccessorsAgree('headline', $post_id));
	}

	/**
	 * A page never saved since the field was added has no value at all, so the
	 * shared value stands in. This is what yaml_cf_get_field() has always done.
	 */
	public function testAPageWithNoStoredValueFallsBackToTheSharedValue() {
		$post_id = $this->createTestPost();
		$this->givenSharedFields(['headline' => 'Shared headline']);

		$this->assertSame('Shared headline', $this->assertAccessorsAgree('headline', $post_id));
	}

	// ------------------------------------------- 3. shared-only fields

	public function testAFieldThatOnlyExistsAsSharedComesFromSharedData() {
		$post_id = $this->createTestPost([], ['headline' => 'Page headline']);
		$this->givenSharedFields(['disclaimer' => 'Shared disclaimer']);

		$this->assertSame('Shared disclaimer', $this->assertAccessorsAgree('disclaimer', $post_id));
	}

	/**
	 * Older versions froze a copy of the shared value into post meta. That stale
	 * copy must never win over the shared value it was copied from.
	 */
	public function testAStaleCopyOfASharedOnlyFieldIsIgnored() {
		$post_id = $this->createTestPost([], ['disclaimer' => 'Stale copy']);
		$this->givenSharedFields(['disclaimer' => 'Shared disclaimer']);

		$this->assertSame('Shared disclaimer', $this->assertAccessorsAgree('disclaimer', $post_id));
	}

	// ------------------------------------------------------ 4. site details

	/**
	 * Site details stand in for fields the page has no value for.
	 */
	public function testSiteDetailsSupplyAFieldThePageDoesNotHave() {
		$post_id = $this->createTestPost();
		$this->givenSiteDetails(['phone' => '555-0100']);

		$this->assertSame('555-0100', $this->assertAccessorsAgree('phone', $post_id));
	}

	/**
	 * When a page field and a site detail share a name, the page wins. The meta
	 * box renders the page field as editable and the site detail as read-only,
	 * so a value typed on the page has to be the one that shows.
	 */
	public function testThePageValueBeatsASiteDetailOfTheSameName() {
		$post_id = $this->createTestPost([], ['headline' => 'Page headline']);
		$this->givenSiteDetails(['headline' => 'Site headline']);

		$this->assertSame('Page headline', $this->assertAccessorsAgree('headline', $post_id));
	}

	/**
	 * A ticked shared value outranks a site detail of the same name.
	 */
	public function testASharedValueBeatsASiteDetailOfTheSameName() {
		$post_id = $this->createTestPost([], ['headline' => 'Page headline']);
		$this->givenSharedFields(['headline' => 'Shared headline']);
		$this->givenSiteDetails(['headline' => 'Site headline']);
		update_post_meta($post_id, '_yaml_cf_use_template_global_fields', ['headline' => '1']);

		$this->assertSame('Shared headline', $this->assertAccessorsAgree('headline', $post_id));
	}

	// ------------------------------------------------------------ code fields

	/**
	 * Code fields are stored base64-encoded behind a marker. Whichever source a
	 * value arrives from, both accessors must hand back the decoded code.
	 */
	public function testCodeFieldsAreDecodedByBothAccessorsFromEitherSource() {
		$schema = "fields:\n  - name: embed\n    label: Embed\n    type: code\n";
		update_option('yaml_cf_schemas', ['single.php' => $schema]);
		update_option('yaml_cf_template_global_schemas', ['single.php' => $schema]);

		$marker = \YAML_Custom_Fields::CODE_FIELD_MARKER;
		$post_id = $this->createTestPost([], ['embed' => $marker . base64_encode('<b>page</b>')]);
		update_option('yaml_cf_template_global_data', ['single.php' => ['embed' => $marker . base64_encode('<b>shared</b>')]]);

		$this->assertSame('<b>page</b>', $this->assertAccessorsAgree('embed', $post_id));

		update_post_meta($post_id, '_yaml_cf_use_template_global_fields', ['embed' => '1']);
		$this->assertSame('<b>shared</b>', $this->assertAccessorsAgree('embed', $post_id));
	}

	// ------------------------------------------------------------- unrelated

	/**
	 * Values stored under names no schema mentions (left over from an older
	 * schema) stay readable through both accessors.
	 */
	public function testValuesWithNoSchemaFieldAreStillReturned() {
		$post_id = $this->createTestPost([], ['legacy_field' => 'Still here']);

		$this->assertSame('Still here', $this->assertAccessorsAgree('legacy_field', $post_id));
	}

	public function testAMissingFieldIsNullInBothAccessors() {
		$post_id = $this->createTestPost([], ['headline' => 'Page headline']);

		$this->assertNull($this->assertAccessorsAgree('nothing_here', $post_id));
	}
}
