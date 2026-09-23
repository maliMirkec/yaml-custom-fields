<?php
/**
 * Tests for SectionGrouper
 */

namespace YamlCF\Tests\Unit\Schema;

use YamlCF\Tests\TestCase;
use YamlCF\Schema\SectionGrouper;
use YamlCF\Schema\FieldNormalizer;

class SectionGrouperTest extends TestCase {
	private $grouper;
	private $normalizer;

	protected function setUp(): void {
		parent::setUp();
		$this->grouper = new SectionGrouper();
		$this->normalizer = new FieldNormalizer();
	}

	/**
	 * Test a schema without section breaks yields a single headerless group
	 */
	public function testFieldsWithoutSectionsStayInOneGroup() {
		$fields = [
			['name' => 'title', 'type' => 'string'],
			['name' => 'body', 'type' => 'text'],
		];

		$groups = $this->grouper->group($fields);

		$this->assertCount(1, $groups);
		$this->assertNull($groups[0]['section']);
		$this->assertCount(2, $groups[0]['fields']);
	}

	/**
	 * Test a section break starts a new group and is removed from the fields
	 */
	public function testSectionStartsNewGroup() {
		$fields = $this->normalizer->normalizeAll([
			['name' => 'title', 'type' => 'string'],
			['section' => 'Contact details'],
			['name' => 'email', 'type' => 'string'],
			['name' => 'phone', 'type' => 'string'],
		]);

		$groups = $this->grouper->group($fields);

		$this->assertCount(2, $groups);

		$this->assertNull($groups[0]['section']);
		$this->assertCount(1, $groups[0]['fields']);
		$this->assertEquals('title', $groups[0]['fields'][0]['name']);

		$this->assertEquals('Contact details', $groups[1]['section']['label']);
		$this->assertCount(2, $groups[1]['fields']);
		$this->assertEquals('email', $groups[1]['fields'][0]['name']);

		// The break itself is never part of the fields it groups
		foreach ($groups as $group) {
			foreach ($group['fields'] as $field) {
				$this->assertNotEquals('section', $field['type']);
			}
		}
	}

	/**
	 * Test a schema that opens with a section has no leading headerless group
	 */
	public function testLeadingSectionProducesNoEmptyGroup() {
		$fields = $this->normalizer->normalizeAll([
			['section' => 'Page basics'],
			['name' => 'title', 'type' => 'string'],
		]);

		$groups = $this->grouper->group($fields);

		$this->assertCount(1, $groups);
		$this->assertEquals('Page basics', $groups[0]['section']['label']);
	}

	/**
	 * Test a bare section break has no label
	 */
	public function testBareSectionHasNoLabel() {
		$fields = $this->normalizer->normalizeAll([
			['name' => 'title', 'type' => 'string'],
			['section' => null],
			['name' => 'note', 'type' => 'text'],
		]);

		$groups = $this->grouper->group($fields);

		$this->assertCount(2, $groups);
		$this->assertEquals('section', $groups[1]['section']['type']);
		$this->assertArrayNotHasKey('label', $groups[1]['section']);
	}

	/**
	 * Test an empty section still renders, so an empty group is kept
	 */
	public function testSectionWithNoFieldsIsKept() {
		$fields = $this->normalizer->normalizeAll([
			['section' => 'Empty'],
		]);

		$groups = $this->grouper->group($fields);

		$this->assertCount(1, $groups);
		$this->assertEquals('Empty', $groups[0]['section']['label']);
		$this->assertCount(0, $groups[0]['fields']);
	}

	/**
	 * Test hasSections detects both shorthand and long form
	 */
	public function testHasSections() {
		$this->assertFalse($this->grouper->hasSections([
			['name' => 'title', 'type' => 'string'],
		]));

		$this->assertTrue($this->grouper->hasSections($this->normalizer->normalizeAll([
			['section' => 'Heading'],
		])));

		$this->assertTrue($this->grouper->hasSections([
			['type' => 'section', 'name' => 'section_0', 'label' => 'Heading'],
		]));
	}

	/**
	 * Test stripSections leaves only fields that hold data
	 */
	public function testStripSections() {
		$fields = $this->normalizer->normalizeAll([
			['name' => 'title', 'type' => 'string'],
			['section' => 'Contact details'],
			['name' => 'email', 'type' => 'string'],
		]);

		$stripped = $this->grouper->stripSections($fields);

		$this->assertCount(2, $stripped);
		$this->assertEquals('title', $stripped[0]['name']);
		$this->assertEquals('email', $stripped[1]['name']);
	}

	/**
	 * Test an empty field list groups into nothing
	 */
	public function testEmptyFieldsProduceNoGroups() {
		$this->assertSame([], $this->grouper->group([]));
		$this->assertSame([], $this->grouper->group(null));
	}
}
