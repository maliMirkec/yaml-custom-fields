<?php
/**
 * Tests for FieldNormalizer
 */

namespace YamlCF\Tests\Unit\Schema;

use YamlCF\Tests\TestCase;
use YamlCF\Schema\FieldNormalizer;

class FieldNormalizerTest extends TestCase {
	private $normalizer;

	protected function setUp(): void {
		parent::setUp();
		$this->normalizer = new FieldNormalizer();
	}

	/**
	 * Test normalizes info field shorthand
	 */
	public function testNormalizesInfoFieldShorthand() {
		$fields = [
			['info' => 'This is an info message']
		];

		$result = $this->normalizer->normalizeInfoFields($fields);

		$this->assertCount(1, $result);
		$this->assertEquals('info', $result[0]['type']);
		$this->assertEquals('info_0', $result[0]['name']);
		$this->assertEquals('This is an info message', $result[0]['text']);
	}

	/**
	 * Test normalizes multiple info field shorthands
	 */
	public function testNormalizesMultipleInfoFieldShorthands() {
		$fields = [
			['info' => 'First info'],
			['info' => 'Second info'],
			['info' => 'Third info']
		];

		$result = $this->normalizer->normalizeInfoFields($fields);

		$this->assertCount(3, $result);
		$this->assertEquals('info_0', $result[0]['name']);
		$this->assertEquals('info_1', $result[1]['name']);
		$this->assertEquals('info_2', $result[2]['name']);
	}

	/**
	 * Test leaves standard fields unchanged
	 */
	public function testLeavesStandardFieldsUnchanged() {
		$fields = [
			[
				'name' => 'title',
				'type' => 'string',
				'label' => 'Title'
			]
		];

		$result = $this->normalizer->normalizeInfoFields($fields);

		$this->assertCount(1, $result);
		$this->assertEquals('title', $result[0]['name']);
		$this->assertEquals('string', $result[0]['type']);
		$this->assertEquals('Title', $result[0]['label']);
	}

	/**
	 * Test normalizes mixed shorthand and standard fields
	 */
	public function testNormalizesMixedFields() {
		$fields = [
			['name' => 'title', 'type' => 'string'],
			['info' => 'Info message'],
			['name' => 'description', 'type' => 'text']
		];

		$result = $this->normalizer->normalizeInfoFields($fields);

		$this->assertCount(3, $result);
		$this->assertEquals('title', $result[0]['name']);
		$this->assertEquals('info', $result[1]['type']);
		$this->assertEquals('info_0', $result[1]['name']);
		$this->assertEquals('description', $result[2]['name']);
	}

	/**
	 * Test does not normalize info field with explicit type
	 */
	public function testDoesNotNormalizeExplicitInfoField() {
		$fields = [
			[
				'name' => 'custom_info',
				'type' => 'info',
				'text' => 'Custom info text'
			]
		];

		$result = $this->normalizer->normalizeInfoFields($fields);

		$this->assertCount(1, $result);
		$this->assertEquals('custom_info', $result[0]['name']);
		$this->assertEquals('info', $result[0]['type']);
		$this->assertEquals('Custom info text', $result[0]['text']);
	}

	/**
	 * Test handles empty fields array
	 */
	public function testHandlesEmptyFieldsArray() {
		$fields = [];

		$result = $this->normalizer->normalizeInfoFields($fields);

		$this->assertIsArray($result);
		$this->assertCount(0, $result);
	}

	/**
	 * Test normalizeAll calls normalizeInfoFields
	 */
	public function testNormalizeAllCallsNormalizeInfoFields() {
		$fields = [
			['info' => 'Test info'],
			['name' => 'title', 'type' => 'string']
		];

		$result = $this->normalizer->normalizeAll($fields);

		$this->assertCount(2, $result);
		$this->assertEquals('info', $result[0]['type']);
		$this->assertEquals('info_0', $result[0]['name']);
	}

	/**
	 * Test counter increments correctly
	 */
	public function testCounterIncrementsCorrectly() {
		$fields = [
			['info' => 'First'],
			['name' => 'regular', 'type' => 'string'],
			['info' => 'Second'],
			['name' => 'another', 'type' => 'text'],
			['info' => 'Third']
		];

		$result = $this->normalizer->normalizeInfoFields($fields);

		$this->assertEquals('info_0', $result[0]['name']);
		$this->assertEquals('info_1', $result[2]['name']);
		$this->assertEquals('info_2', $result[4]['name']);
	}

	/**
	 * Test preserves all properties of standard fields
	 */
	public function testPreservesAllPropertiesOfStandardFields() {
		$fields = [
			[
				'name' => 'title',
				'type' => 'string',
				'label' => 'Title',
				'help' => 'Help text',
				'required' => true,
				'default' => 'Default value'
			]
		];

		$result = $this->normalizer->normalizeInfoFields($fields);

		$this->assertCount(1, $result);
		$this->assertEquals('title', $result[0]['name']);
		$this->assertEquals('string', $result[0]['type']);
		$this->assertEquals('Title', $result[0]['label']);
		$this->assertEquals('Help text', $result[0]['help']);
		$this->assertTrue($result[0]['required']);
		$this->assertEquals('Default value', $result[0]['default']);
	}

	/**
	 * Test handles info field with additional properties
	 */
	public function testHandlesInfoFieldWithAdditionalProperties() {
		$fields = [
			[
				'info' => 'Message',
				'class' => 'custom-class'
			]
		];

		$result = $this->normalizer->normalizeInfoFields($fields);

		$this->assertCount(1, $result);
		$this->assertEquals('info', $result[0]['type']);
		$this->assertEquals('Message', $result[0]['text']);
		// Shorthand syntax only extracts the required fields
		// Additional properties are not preserved in current implementation
		$this->assertArrayHasKey('type', $result[0]);
		$this->assertArrayHasKey('name', $result[0]);
		$this->assertArrayHasKey('text', $result[0]);
	}

	/**
	 * Test normalizes info with empty text
	 */
	public function testNormalizesInfoWithEmptyText() {
		$fields = [
			['info' => '']
		];

		$result = $this->normalizer->normalizeInfoFields($fields);

		$this->assertCount(1, $result);
		$this->assertEquals('info', $result[0]['type']);
		$this->assertEquals('', $result[0]['text']);
	}

	/**
	 * Test normalizes info with multiline text
	 */
	public function testNormalizesInfoWithMultilineText() {
		$fields = [
			['info' => "Line 1\nLine 2\nLine 3"]
		];

		$result = $this->normalizer->normalizeInfoFields($fields);

		$this->assertCount(1, $result);
		$this->assertEquals('info', $result[0]['type']);
		$this->assertStringContains('Line 1', $result[0]['text']);
		$this->assertStringContains('Line 3', $result[0]['text']);
	}
}
