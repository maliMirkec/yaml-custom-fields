<?php

/**
 * Tests for SchemaDiff
 *
 * Renaming a field strands whatever was typed into it. These tests pin down that
 * the old name is reported so saving a schema can say so.
 */

namespace YamlCF\Tests\Unit\Schema;

use YamlCF\Schema\SchemaDiff;
use YamlCF\Schema\SchemaParser;
use YamlCF\Tests\TestCase;

class SchemaDiffTest extends TestCase {
  /** @var SchemaDiff */
  private $diff;

  /** @var SchemaParser */
  private $parser;

  protected function setUp(): void {
    parent::setUp();
    $this->diff = new SchemaDiff();
    $this->parser = new SchemaParser();
  }

  private function parse($yaml) {
    return $this->parser->parse($yaml);
  }

  public function testCollectsTopLevelFieldNames() {
    $schema = $this->parse("fields:\n  - name: title\n    type: string\n  - name: body\n    type: text\n");

    $this->assertSame(['title', 'body'], $this->diff->collectFieldNames($schema));
  }

  public function testCollectsNestedObjectFieldNames() {
    $schema = $this->parse(
      "fields:\n  - name: hero\n    type: object\n    fields:\n      - name: heading\n        type: string\n      - name: subheading\n        type: string\n"
    );

    $this->assertSame(['hero', 'heading', 'subheading'], $this->diff->collectFieldNames($schema));
  }

  public function testCollectsBlockFieldNames() {
    $schema = $this->parse(
      "fields:\n  - name: sections\n    type: block\n    list: true\n    blocks:\n      - name: feature\n        fields:\n          - name: caption\n            type: string\n"
    );

    $this->assertSame(['sections', 'caption'], $this->diff->collectFieldNames($schema));
  }

  /**
   * "- section: Hero" and "- break: true" are layout only: they hold no data,
   * so they cannot strand any.
   */
  public function testIgnoresSectionAndBreakEntries() {
    $schema = $this->parse("fields:\n  - section: Hero\n  - break: true\n  - name: title\n    type: string\n");

    $this->assertSame(['title'], $this->diff->collectFieldNames($schema));
  }

  public function testEmptyAndMalformedSchemasCollectNothing() {
    $this->assertSame([], $this->diff->collectFieldNames(null));
    $this->assertSame([], $this->diff->collectFieldNames([]));
    $this->assertSame([], $this->diff->collectFieldNames(['fields' => 'not an array']));
  }

  public function testRenamedFieldIsReportedUnderItsOldName() {
    $old = $this->parse("fields:\n  - name: hero_title\n    type: string\n");
    $new = $this->parse("fields:\n  - name: headline\n    type: string\n");

    $this->assertSame(['hero_title'], $this->diff->removedFieldNames($old, $new));
  }

  public function testDeletedFieldIsReported() {
    $old = $this->parse("fields:\n  - name: title\n    type: string\n  - name: subtitle\n    type: string\n");
    $new = $this->parse("fields:\n  - name: title\n    type: string\n");

    $this->assertSame(['subtitle'], $this->diff->removedFieldNames($old, $new));
  }

  public function testAddingAFieldStrandsNothing() {
    $old = $this->parse("fields:\n  - name: title\n    type: string\n");
    $new = $this->parse("fields:\n  - name: title\n    type: string\n  - name: extra\n    type: string\n");

    $this->assertSame([], $this->diff->removedFieldNames($old, $new));
  }

  /**
   * Changing a field's type keeps its name, so its content still has a home
   */
  public function testChangingATypeStrandsNothing() {
    $old = $this->parse("fields:\n  - name: title\n    type: string\n");
    $new = $this->parse("fields:\n  - name: title\n    type: text\n");

    $this->assertSame([], $this->diff->removedFieldNames($old, $new));
  }

  public function testRemovingAWholeBlockReportsItsInnerFieldsToo() {
    $old = $this->parse(
      "fields:\n  - name: sections\n    type: block\n    list: true\n    blocks:\n      - name: feature\n        fields:\n          - name: caption\n            type: string\n"
    );
    $new = $this->parse("fields:\n  - name: title\n    type: string\n");

    $this->assertSame(['sections', 'caption'], $this->diff->removedFieldNames($old, $new));
  }

  /**
   * Emptying the schema strands everything, rather than reporting nothing
   */
  public function testClearingTheSchemaReportsEveryField() {
    $old = $this->parse("fields:\n  - name: title\n    type: string\n");

    $this->assertSame(['title'], $this->diff->removedFieldNames($old, null));
  }

  /**
   * A field moved from a block into the top level keeps its name and its value,
   * because stored data is keyed by field name at every level.
   */
  public function testMovingAFieldBetweenLevelsStrandsNothing() {
    $old = $this->parse(
      "fields:\n  - name: sections\n    type: block\n    blocks:\n      - name: feature\n        fields:\n          - name: caption\n            type: string\n"
    );
    $new = $this->parse("fields:\n  - name: sections\n    type: block\n  - name: caption\n    type: string\n");

    $this->assertSame([], $this->diff->removedFieldNames($old, $new));
  }
}
