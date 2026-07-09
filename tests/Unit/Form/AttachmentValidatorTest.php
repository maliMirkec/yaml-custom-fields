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

	public function testFindFieldInSchemaReturnsMatchingField() {
		$schema = [
			['name' => 'title', 'type' => 'string'],
			['name' => 'photo', 'type' => 'image'],
		];

		$field = $this->validator->findFieldInSchema($schema, 'photo');

		$this->assertIsArray($field);
		$this->assertSame('image', $field['type']);
	}

	public function testFindFieldInSchemaReturnsNullWhenNotFound() {
		$schema = [
			['name' => 'title', 'type' => 'string'],
		];

		$this->assertNull($this->validator->findFieldInSchema($schema, 'missing'));
	}

	public function testFindFieldInSchemaReturnsNullForNonArraySchema() {
		$this->assertNull($this->validator->findFieldInSchema(null, 'title'));
		$this->assertNull($this->validator->findFieldInSchema('not-an-array', 'title'));
	}

	public function testValidateAttachmentsReturnsEmptyForNonArrayData() {
		$this->assertSame([], $this->validator->validateAttachments('not-an-array', '', [['name' => 'photo', 'type' => 'image']]));
		$this->assertSame([], $this->validator->validateAttachments(123, '', [['name' => 'photo', 'type' => 'image']]));
		$this->assertSame([], $this->validator->validateAttachments(null, '', [['name' => 'photo', 'type' => 'image']]));
	}

	public function testValidateAttachmentsReturnsEmptyWhenSchemaMissing() {
		$data = ['photo' => 123];

		$this->assertSame([], $this->validator->validateAttachments($data));
		$this->assertSame([], $this->validator->validateAttachments($data, '', []));
		$this->assertSame([], $this->validator->validateAttachments($data, '', null));
	}

	public function testValidateAttachmentsSkipsNonAttachmentFieldTypes() {
		$schema = [
			['name' => 'title', 'type' => 'string'],
			['name' => 'count', 'type' => 'number'],
		];
		$data = ['title' => 'Hello', 'count' => 5];

		// Neither field is an image/file type, so no attachment lookups
		// (and therefore no get_post() calls) should occur.
		$this->assertSame([], $this->validator->validateAttachments($data, '', $schema));
	}

	public function testValidateAttachmentsRecursesIntoNestedObjectFields() {
		$schema = [
			[
				'name' => 'author',
				'type' => 'object',
				'fields' => [
					['name' => 'bio', 'type' => 'string'],
				],
			],
		];
		$data = ['author' => ['bio' => 'Hello world']];

		// Nested field is a string, not image/file, so validation should
		// recurse without error and report nothing missing.
		$this->assertSame([], $this->validator->validateAttachments($data, '', $schema));
	}
}
