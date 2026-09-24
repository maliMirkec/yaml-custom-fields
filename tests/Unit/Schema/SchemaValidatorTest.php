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
		$this->assertEquals('The schema looks good.', $result['message']);
	}

	/**
	 * Test rejects empty YAML
	 */
	public function testRejectsEmptyYaml() {
		$result = $this->validator->validate('');

		$this->assertIsArray($result);
		$this->assertFalse($result['valid']);
		$this->assertStringContains('The schema is empty', $result['message']);
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
		$this->assertStringContains('typing mistake', $result['message']);
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
			"    type: boolean\n";

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
		$this->assertStringContains('is not written correctly', $result['message']);
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
		$this->assertStringContains('Info boxes only work on page and post templates', $result['message']);
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
		$this->assertStringContains('Info boxes only work on page and post templates', $result['message']);
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
		$this->assertStringContains('Info boxes only work on page and post templates', $result['message']);
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
			"    type: object\n" .
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
		$this->assertStringContains('Info boxes only work on page and post templates', $result['message']);
	}

	/**
	 * Test a schema with section breaks validates
	 */
	public function testAcceptsSectionShorthand() {
		$yaml = "fields:\n" .
			"  - section: Page basics\n" .
			"  - name: title\n" .
			"    type: string\n" .
			"  - section:\n" .
			"  - name: body\n" .
			"    type: text\n";

		$result = $this->validator->validate($yaml);

		$this->assertTrue($result['valid'], $result['message']);
	}

	/**
	 * Test a long form section without a name still validates
	 */
	public function testAcceptsLongFormSectionWithoutName() {
		$yaml = "fields:\n" .
			"  - type: section\n" .
			"    label: Opening hours\n" .
			"    description: Shown in the footer\n" .
			"  - name: hours\n" .
			"    type: text\n";

		$result = $this->validator->validate($yaml);

		$this->assertTrue($result['valid'], $result['message']);
	}

	/**
	 * A typo in a type used to pass validation, render no input at all, and let
	 * the field's content be dropped on the next save.
	 */
	public function testRejectsAnUnknownFieldType() {
		$yaml = "fields:\n" .
			"  - name: headline\n" .
			"    type: strng\n";

		$result = $this->validator->validate($yaml);

		$this->assertFalse($result['valid']);
		$this->assertStringContains('does not know', $result['message']);
	}

	/**
	 * A near miss is worth naming: the fix is almost always the suggestion.
	 */
	public function testSuggestsTheClosestTypeForATypo() {
		$yaml = "fields:\n" .
			"  - name: headline\n" .
			"    type: strng\n";

		$result = $this->validator->validate($yaml);

		$this->assertStringContains('Did you mean "string"', $result['message']);
	}

	/**
	 * Nothing close enough to guess at gets the list of what does work instead.
	 */
	public function testListsTheTypesWhenNothingIsClose() {
		$yaml = "fields:\n" .
			"  - name: headline\n" .
			"    type: wysiwyg_editor_thing\n";

		$result = $this->validator->validate($yaml);

		$this->assertFalse($result['valid']);
		$this->assertStringContains('rich-text', $result['message']);
	}

	public function testAcceptsEveryTypeTheEditorCanRender() {
		foreach (SchemaValidator::FIELD_TYPES as $type) {
			$yaml = "fields:\n" .
				"  - name: a_field\n" .
				"    type: " . $type . "\n";

			$result = $this->validator->validate($yaml);

			$this->assertTrue($result['valid'], 'Type "' . $type . '" should be accepted: ' . $result['message']);
		}
	}

	/**
	 * "break" is shorthand that FieldNormalizer turns into a section, so it is
	 * accepted although it is not in the list of types.
	 */
	public function testAcceptsTheBreakShorthandType() {
		$yaml = "fields:\n" .
			"  - name: headline\n" .
			"    type: string\n" .
			"  - type: break\n";

		$result = $this->validator->validate($yaml);

		$this->assertTrue($result['valid'], $result['message']);
	}

	// ------------------------------------------------------- nested levels

	public function testRejectsAnUnknownTypeInsideAGroup() {
		$yaml = "fields:\n" .
			"  - name: button\n" .
			"    type: object\n" .
			"    fields:\n" .
			"      - name: link\n" .
			"        type: urll\n";

		$result = $this->validator->validate($yaml);

		$this->assertFalse($result['valid']);
		$this->assertStringContains('button', $result['message']);
		$this->assertStringContains('link', $result['message']);
	}

	public function testRejectsAFieldWithoutATypeInsideAGroup() {
		$yaml = "fields:\n" .
			"  - name: button\n" .
			"    type: object\n" .
			"    fields:\n" .
			"      - name: link\n";

		$result = $this->validator->validate($yaml);

		$this->assertFalse($result['valid']);
		$this->assertStringContains('needs a type', $result['message']);
	}

	public function testRejectsAnUnknownTypeInsideASection() {
		$yaml = "fields:\n" .
			"  - name: sections\n" .
			"    type: block\n" .
			"    list: true\n" .
			"    blocks:\n" .
			"      - name: banner\n" .
			"        fields:\n" .
			"          - name: image\n" .
			"            type: imag\n";

		$result = $this->validator->validate($yaml);

		$this->assertFalse($result['valid']);
		$this->assertStringContains('Did you mean "image"', $result['message']);
	}

	public function testAcceptsAWellFormedSectionField() {
		$yaml = "fields:\n" .
			"  - name: sections\n" .
			"    type: block\n" .
			"    list: true\n" .
			"    blocks:\n" .
			"      - name: banner\n" .
			"        fields:\n" .
			"          - name: image\n" .
			"            type: image\n" .
			"      - name: quote\n" .
			"        fields:\n" .
			"          - name: body\n" .
			"            type: rich-text\n";

		$result = $this->validator->validate($yaml);

		$this->assertTrue($result['valid'], $result['message']);
	}

	public function testAcceptsShorthandInsideAGroup() {
		$yaml = "fields:\n" .
			"  - name: details\n" .
			"    type: object\n" .
			"    fields:\n" .
			"      - info: \"Fill both in\"\n" .
			"      - name: link\n" .
			"        type: string\n";

		$result = $this->validator->validate($yaml);

		$this->assertTrue($result['valid'], $result['message']);
	}

}
