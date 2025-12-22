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
		$yaml = "fields:\n" .
			"  - name: title\n" .
			"    type: string\n" .
			"    label: Title\n";

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
		$yaml = "title: My Schema\n" .
			"description: This is a schema\n";

		$result = $this->validator->validate($yaml);

		$this->assertFalse($result['valid']);
		$this->assertStringContains('fields', $result['message']);
	}

	/**
	 * Test rejects field without name
	 */
	public function testRejectsFieldWithoutName() {
		$yaml = "fields:\n" .
			"  - type: string\n" .
			"    label: Title\n";

		$result = $this->validator->validate($yaml);

		$this->assertFalse($result['valid']);
		$this->assertStringContains('name', $result['message']);
	}

	/**
	 * Test rejects field without type
	 */
	public function testRejectsFieldWithoutType() {
		$yaml = "fields:\n" .
			"  - name: title\n" .
			"    label: Title\n";

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
		$yaml = "fields:\n" .
			"  - name: title\n" .
			"    type: string\n" .
			"  - name: description\n" .
			"    type: text\n" .
			"  - name: active\n" .
			"    type: checkbox\n";

		$result = $this->validator->validate($yaml);

		$this->assertTrue($result['valid']);
	}

	/**
	 * Test rejects non-array field
	 */
	public function testRejectsNonArrayField() {
		$yaml = "fields:\n" .
			"  - name: title\n" .
			"    type: string\n" .
			"  - \"invalid field\"\n";

		$result = $this->validator->validate($yaml);

		$this->assertFalse($result['valid']);
		$this->assertStringContains('not a valid array', $result['message']);
	}

	/**
	 * Test info field shorthand is normalized during validation
	 */
	public function testNormalizesInfoFieldShorthand() {
		$yaml = "fields:\n" .
			"  - info: \"This is an info message\"\n" .
			"  - name: title\n" .
			"    type: string\n";

		// Info fields should be normalized and validated
		// Without template restriction, they should be valid
		$result = $this->validator->validate($yaml);

		$this->assertTrue($result['valid']);
	}

	/**
	 * Test info field is allowed in page templates
	 */
	public function testAllowsInfoFieldInPageTemplate() {
		$yaml = "fields:\n" .
			"  - name: info_field\n" .
			"    type: info\n" .
			"    text: \"Information text\"\n";

		$result = $this->validator->validate($yaml, 'page.php');

		$this->assertTrue($result['valid']);
	}

	/**
	 * Test info field is allowed in single templates
	 */
	public function testAllowsInfoFieldInSingleTemplate() {
		$yaml = "fields:\n" .
			"  - name: info_field\n" .
			"    type: info\n" .
			"    text: \"Information text\"\n";

		$result = $this->validator->validate($yaml, 'single.php');

		$this->assertTrue($result['valid']);
	}

	/**
	 * Test info field is allowed in custom page templates
	 */
	public function testAllowsInfoFieldInCustomTemplate() {
		$yaml = "fields:\n" .
			"  - name: info_field\n" .
			"    type: info\n" .
			"    text: \"Information text\"\n";

		$result = $this->validator->validate($yaml, 'template-custom.php');

		$this->assertTrue($result['valid']);
	}

	/**
	 * Test info field is rejected in header partial
	 */
	public function testRejectsInfoFieldInHeaderPartial() {
		$yaml = "fields:\n" .
			"  - name: info_field\n" .
			"    type: info\n" .
			"    text: \"Information text\"\n";

		$result = $this->validator->validate($yaml, 'header.php');

		$this->assertFalse($result['valid']);
		$this->assertStringContains('Info fields are not allowed', $result['message']);
		$this->assertStringContains('header.php', $result['message']);
	}

	/**
	 * Test info field is rejected in footer partial
	 */
	public function testRejectsInfoFieldInFooterPartial() {
		$yaml = "fields:\n" .
			"  - name: info_field\n" .
			"    type: info\n" .
			"    text: \"Information text\"\n";

		$result = $this->validator->validate($yaml, 'footer.php');

		$this->assertFalse($result['valid']);
		$this->assertStringContains('Info fields are not allowed', $result['message']);
	}

	/**
	 * Test info field is rejected in archive template
	 */
	public function testRejectsInfoFieldInArchiveTemplate() {
		$yaml = "fields:\n" .
			"  - name: info_field\n" .
			"    type: info\n" .
			"    text: \"Information text\"\n";

		$result = $this->validator->validate($yaml, 'archive.php');

		$this->assertFalse($result['valid']);
		$this->assertStringContains('Info fields are not allowed', $result['message']);
	}

	/**
	 * Test info field is rejected in category template
	 */
	public function testRejectsInfoFieldInCategoryTemplate() {
		$yaml = "fields:\n" .
			"  - name: info_field\n" .
			"    type: info\n" .
			"    text: \"Information text\"\n";

		$result = $this->validator->validate($yaml, 'category.php');

		$this->assertFalse($result['valid']);
	}

	/**
	 * Test info field is rejected in 404 template
	 */
	public function testRejectsInfoFieldIn404Template() {
		$yaml = "fields:\n" .
			"  - name: info_field\n" .
			"    type: info\n" .
			"    text: \"Information text\"\n";

		$result = $this->validator->validate($yaml, '404.php');

		$this->assertFalse($result['valid']);
	}

	/**
	 * Test validation result structure
	 */
	public function testValidationResultStructure() {
		$yaml = "fields:\n" .
			"  - name: title\n" .
			"    type: string\n";

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
		$yaml = "fields:\n" .
			"  - name: group\n" .
			"    type: group\n" .
			"    fields:\n" .
			"      - name: title\n" .
			"        type: string\n" .
			"      - name: description\n" .
			"        type: text\n";

		$result = $this->validator->validate($yaml);

		$this->assertTrue($result['valid']);
	}

	/**
	 * Test info field shorthand is rejected in partials
	 */
	public function testRejectsInfoFieldShorthandInPartials() {
		$yaml = "fields:\n" .
			"  - info: \"This should be rejected\"\n";

		$result = $this->validator->validate($yaml, 'sidebar.php');

		$this->assertFalse($result['valid']);
		$this->assertStringContains('Info fields are not allowed', $result['message']);
	}
}
