<?php
/**
 * Tests for AttachmentValidator
 */

namespace YamlCF\Tests\Unit\Form;

use YamlCF\Tests\TestCase;
use YamlCF\Form\AttachmentValidator;

class AttachmentValidatorTest extends TestCase {
	private $validator;

	protected function setUp(): void {
		parent::setUp();
		$this->validator = new AttachmentValidator();
		$GLOBALS['yaml_cf_test_missing_posts'] = [];
	}

	protected function tearDown(): void {
		$GLOBALS['yaml_cf_test_missing_posts'] = [];
		parent::tearDown();
	}

	/**
	 * Test validateAndClean keeps IDs of existing posts
	 */
	public function testValidateAndCleanReturnsDataUnchanged() {
		$data = ['attachment_id' => 123];
		$result = $this->validator->validateAndClean($data);

		$this->assertEquals($data, $result);
	}

	/**
	 * Test validateAndClean with string data
	 */
	public function testValidateAndCleanWithStringData() {
		$data = '123';
		$result = $this->validator->validateAndClean($data);

		$this->assertEquals($data, $result);
	}

	/**
	 * Test validateAndClean with numeric data
	 */
	public function testValidateAndCleanWithNumericData() {
		$data = 456;
		$result = $this->validator->validateAndClean($data);

		$this->assertEquals($data, $result);
	}

	/**
	 * Test validateAndClean with array of attachment IDs
	 */
	public function testValidateAndCleanWithArrayOfIds() {
		$data = [123, 456, 789];
		$result = $this->validator->validateAndClean($data);

		$this->assertEquals($data, $result);
	}

	/**
	 * Test validateAndClean with null data
	 */
	public function testValidateAndCleanWithNullData() {
		$data = null;
		$result = $this->validator->validateAndClean($data);

		$this->assertNull($result);
	}

	/**
	 * Test validateAndClean with empty array
	 */
	public function testValidateAndCleanWithEmptyArray() {
		$data = [];
		$result = $this->validator->validateAndClean($data);

		$this->assertIsArray($result);
		$this->assertCount(0, $result);
	}

	/**
	 * Test validateAndClean with schema parameter
	 */
	public function testValidateAndCleanWithSchemaParameter() {
		$data = 123;
		$schema = [
			'type' => 'attachment',
			'name' => 'image'
		];

		$result = $this->validator->validateAndClean($data, $schema);

		$this->assertEquals($data, $result);
	}

	/**
	 * Test validateAndClean with parent_key parameter
	 */
	public function testValidateAndCleanWithParentKeyParameter() {
		$data = 123;
		$result = $this->validator->validateAndClean($data, null, 'images');

		$this->assertEquals($data, $result);
	}

	/**
	 * Test validateAndClean with nested structure
	 */
	public function testValidateAndCleanWithNestedStructure() {
		$data = [
			'main_image' => 123,
			'gallery' => [456, 789],
			'featured' => [
				'image_id' => 111,
				'thumbnail_id' => 222
			]
		];

		$result = $this->validator->validateAndClean($data);

		$this->assertEquals($data, $result);
	}

	/**
	 * Test validateAndClean preserves data types
	 */
	public function testValidateAndCleanPreservesDataTypes() {
		$int_data = 123;
		$string_data = '456';
		$array_data = [789];

		$this->assertIsInt($this->validator->validateAndClean($int_data));
		$this->assertIsString($this->validator->validateAndClean($string_data));
		$this->assertIsArray($this->validator->validateAndClean($array_data));
	}

	/**
	 * Test validateAndClean with complex attachment structure
	 */
	public function testValidateAndCleanWithComplexStructure() {
		$data = [
			'items' => [
				[
					'image' => 123,
					'title' => 'Image 1'
				],
				[
					'image' => 456,
					'title' => 'Image 2'
				]
			]
		];

		$result = $this->validator->validateAndClean($data);

		$this->assertEquals($data, $result);
		$this->assertEquals(123, $result['items'][0]['image']);
	}

	/**
	 * Test validateAndClean with false value
	 */
	public function testValidateAndCleanWithFalseValue() {
		$data = false;
		$result = $this->validator->validateAndClean($data);

		$this->assertFalse($result);
	}

	/**
	 * Test validateAndClean with zero value
	 */
	public function testValidateAndCleanWithZeroValue() {
		$data = 0;
		$result = $this->validator->validateAndClean($data);

		$this->assertEquals(0, $result);
	}

	/**
	 * Test validateAndClean with empty string
	 */
	public function testValidateAndCleanWithEmptyString() {
		$data = '';
		$result = $this->validator->validateAndClean($data);

		$this->assertEquals('', $result);
	}

	/**
	 * Test validateAndClean with boolean values
	 */
	public function testValidateAndCleanWithBooleanValues() {
		$this->assertTrue($this->validator->validateAndClean(true));
		$this->assertFalse($this->validator->validateAndClean(false));
	}

	/**
	 * Test validateAndClean with string attachment IDs
	 */
	public function testValidateAndCleanWithStringAttachmentIds() {
		$data = ['123', '456', '789'];
		$result = $this->validator->validateAndClean($data);

		$this->assertEquals($data, $result);
	}

	/**
	 * Test missing attachment IDs in image/file fields are cleared
	 */
	public function testValidateAndCleanClearsMissingAttachment() {
		$GLOBALS['yaml_cf_test_missing_posts'] = [999];
		$schema = ['fields' => [
			['name' => 'hero', 'type' => 'image'],
			['name' => 'doc', 'type' => 'file'],
		]];

		$result = $this->validator->validateAndClean(['hero' => '999', 'doc' => '12'], $schema);

		$this->assertSame('', $result['hero']);
		$this->assertSame('12', $result['doc']);
	}

	/**
	 * Test numeric values in non-attachment fields are never cleared when a schema is given
	 */
	public function testValidateAndCleanIgnoresNonAttachmentFields() {
		$GLOBALS['yaml_cf_test_missing_posts'] = [999];
		$schema = ['fields' => [['name' => 'count', 'type' => 'number']]];

		$result = $this->validator->validateAndClean(['count' => '999'], $schema);

		$this->assertSame('999', $result['count']);
	}

	/**
	 * Test missing IDs are cleared in nested block fields
	 */
	public function testValidateAndCleanClearsMissingAttachmentInBlocks() {
		$GLOBALS['yaml_cf_test_missing_posts'] = [999];
		$schema = ['fields' => [
			['name' => 'sections', 'type' => 'block', 'list' => true, 'blocks' => [
				['name' => 'gallery', 'fields' => [['name' => 'photo', 'type' => 'image']]],
			]],
		]];

		$result = $this->validator->validateAndClean(['sections' => [
			['type' => 'gallery', 'photo' => '999'],
			['type' => 'gallery', 'photo' => '5'],
		]], $schema);

		$this->assertSame('', $result['sections'][0]['photo']);
		$this->assertSame('5', $result['sections'][1]['photo']);
	}

	/**
	 * A sections field without list: true holds one section, typed by its own key
	 */
	public function testValidateAttachmentsReportsMissingImageInASingleSection() {
		$GLOBALS['yaml_cf_test_missing_posts'] = [999];
		$fields = [
			['name' => 'hero', 'type' => 'block', 'blocks' => [
				['name' => 'banner', 'fields' => [['name' => 'photo', 'type' => 'image']]],
			]],
		];

		$missing = $this->validator->validateAttachments(['hero' => ['type' => 'banner', 'photo' => '999']], '', $fields);

		$this->assertSame([['field' => 'hero > photo', 'id' => 999]], $missing);
	}

	/**
	 * Without a schema there is no telling an attachment ID from a price or a
	 * phone number, so nothing is cleared
	 */
	public function testValidateAndCleanWithoutSchemaClearsNothing() {
		$GLOBALS['yaml_cf_test_missing_posts'] = [999, 385911234567];
		$data = ['price' => '999', 'b' => 'text', 'phone' => '385911234567', 'nested' => ['year' => 999]];

		$this->assertSame($data, $this->validator->validateAndClean($data));
	}
}
