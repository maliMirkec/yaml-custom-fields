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
	}

	/**
	 * Test validateAndClean returns data unchanged (current placeholder implementation)
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
}
