<?php
/**
 * Tests for SchemaValidator
 */

namespace YamlCF\Tests\Unit\Schema;

use YamlCF\Tests\TestCase;
use YamlCF\Schema\SchemaValidator;

class SchemaValidatorTest extends TestCase {
	private $validator;

	protected function setUp(): void {
		parent::setUp();
		$this->validator = new SchemaValidator();
	}

	/**
	 * Test validates valid schema
	 */
	public function testValidatesValidSchema() {
		$yaml = <<<YAML
fields:
  - name: title
    type: string
    label: Title
YAML;

		$result = $this->validator->validate($yaml);

		$this->assertIsArray($result);
		$this->assertTrue($result['valid']);
		$this->assertEquals('Schema is valid', $result['message']);
	}

	/**
	 * Test rejects empty YAML
	 */
	public function testRejectsEmptyYaml() {
		$result = $this->validator->validate('');

		$this->assertIsArray($result);
		$this->assertFalse($result['valid']);
		$this->assertStringContains('Empty or invalid', $result['message']);
	}

	/**
	 * Test rejects schema without fields array
	 */
	public function testRejectsSchemaWithoutFieldsArray() {
		$yaml = <<<YAML
title: My Schema
description: This is a schema
YAML;

		$result = $this->validator->validate($yaml);

		$this->assertFalse($result['valid']);
		$this->assertStringContains('fields', $result['message']);
	}

	/**
	 * Test rejects field without name
	 */
	public function testRejectsFieldWithoutName() {
		$yaml = <<<YAML
fields:
  - type: string
    label: Title
YAML;

		$result = $this->validator->validate($yaml);

		$this->assertFalse($result['valid']);
		$this->assertStringContains('name', $result['message']);
	}

	/**
	 * Test rejects field without type
	 */
	public function testRejectsFieldWithoutType() {
		$yaml = <<<YAML
fields:
  - name: title
    label: Title
YAML;

		$result = $this->validator->validate($yaml);

		$this->assertFalse($result['valid']);
		$this->assertStringContains('type', $result['message']);
	}

	/**
	 * Test rejects invalid YAML syntax
	 */
	public function testRejectsInvalidYamlSyntax() {
		$yaml = "invalid:\n\tyaml:\nstructure";

		$result = $this->validator->validate($yaml);

		$this->assertFalse($result['valid']);
		$this->assertStringContains('syntax error', $result['message']);
	}

	/**
	 * Test validates multiple fields
	 */
	public function testValidatesMultipleFields() {
		$yaml = <<<YAML
fields:
  - name: title
    type: string
  - name: description
    type: text
  - name: active
    type: checkbox
YAML;

		$result = $this->validator->validate($yaml);

		$this->assertTrue($result['valid']);
	}

	/**
	 * Test rejects non-array field
	 */
	public function testRejectsNonArrayField() {
		$yaml = <<<YAML
fields:
  - name: title
    type: string
  - "invalid field"
YAML;

		$result = $this->validator->validate($yaml);

		$this->assertFalse($result['valid']);
		$this->assertStringContains('not a valid array', $result['message']);
	}

	/**
	 * Test info field shorthand is normalized during validation
	 */
	public function testNormalizesInfoFieldShorthand() {
		$yaml = <<<YAML
fields:
  - info: "This is an info message"
  - name: title
    type: string
YAML;

		// Info fields should be normalized and validated
		// Without template restriction, they should be valid
		$result = $this->validator->validate($yaml);

		$this->assertTrue($result['valid']);
	}

	/**
	 * Test info field is allowed in page templates
	 */
	public function testAllowsInfoFieldInPageTemplate() {
		$yaml = <<<YAML
fields:
  - name: info_field
    type: info
    text: "Information text"
YAML;

		$result = $this->validator->validate($yaml, 'page.php');

		$this->assertTrue($result['valid']);
	}

	/**
	 * Test info field is allowed in single templates
	 */
	public function testAllowsInfoFieldInSingleTemplate() {
		$yaml = <<<YAML
fields:
  - name: info_field
    type: info
    text: "Information text"
YAML;

		$result = $this->validator->validate($yaml, 'single.php');

		$this->assertTrue($result['valid']);
	}

	/**
	 * Test info field is allowed in custom page templates
	 */
	public function testAllowsInfoFieldInCustomTemplate() {
		$yaml = <<<YAML
fields:
  - name: info_field
    type: info
    text: "Information text"
YAML;

		$result = $this->validator->validate($yaml, 'template-custom.php');

		$this->assertTrue($result['valid']);
	}

	/**
	 * Test info field is rejected in header partial
	 */
	public function testRejectsInfoFieldInHeaderPartial() {
		$yaml = <<<YAML
fields:
  - name: info_field
    type: info
    text: "Information text"
YAML;

		$result = $this->validator->validate($yaml, 'header.php');

		$this->assertFalse($result['valid']);
		$this->assertStringContains('Info fields are not allowed', $result['message']);
		$this->assertStringContains('header.php', $result['message']);
	}

	/**
	 * Test info field is rejected in footer partial
	 */
	public function testRejectsInfoFieldInFooterPartial() {
		$yaml = <<<YAML
fields:
  - name: info_field
    type: info
    text: "Information text"
YAML;

		$result = $this->validator->validate($yaml, 'footer.php');

		$this->assertFalse($result['valid']);
		$this->assertStringContains('Info fields are not allowed', $result['message']);
	}

	/**
	 * Test info field is rejected in archive template
	 */
	public function testRejectsInfoFieldInArchiveTemplate() {
		$yaml = <<<YAML
fields:
  - name: info_field
    type: info
    text: "Information text"
YAML;

		$result = $this->validator->validate($yaml, 'archive.php');

		$this->assertFalse($result['valid']);
		$this->assertStringContains('Info fields are not allowed', $result['message']);
	}

	/**
	 * Test info field is rejected in category template
	 */
	public function testRejectsInfoFieldInCategoryTemplate() {
		$yaml = <<<YAML
fields:
  - name: info_field
    type: info
    text: "Information text"
YAML;

		$result = $this->validator->validate($yaml, 'category.php');

		$this->assertFalse($result['valid']);
	}

	/**
	 * Test info field is rejected in 404 template
	 */
	public function testRejectsInfoFieldIn404Template() {
		$yaml = <<<YAML
fields:
  - name: info_field
    type: info
    text: "Information text"
YAML;

		$result = $this->validator->validate($yaml, '404.php');

		$this->assertFalse($result['valid']);
	}

	/**
	 * Test validation result structure
	 */
	public function testValidationResultStructure() {
		$yaml = <<<YAML
fields:
  - name: title
    type: string
YAML;

		$result = $this->validator->validate($yaml);

		$this->assertArrayHasKey('valid', $result);
		$this->assertArrayHasKey('message', $result);
		$this->assertIsBool($result['valid']);
		$this->assertIsString($result['message']);
	}

	/**
	 * Test validates nested field structures
	 */
	public function testValidatesNestedFieldStructures() {
		$yaml = <<<YAML
fields:
  - name: group
    type: group
    fields:
      - name: title
        type: string
      - name: description
        type: text
YAML;

		$result = $this->validator->validate($yaml);

		$this->assertTrue($result['valid']);
	}

	/**
	 * Test info field shorthand is rejected in partials
	 */
	public function testRejectsInfoFieldShorthandInPartials() {
		$yaml = <<<YAML
fields:
  - info: "This should be rejected"
YAML;

		$result = $this->validator->validate($yaml, 'sidebar.php');

		$this->assertFalse($result['valid']);
		$this->assertStringContains('Info fields are not allowed', $result['message']);
	}
}
