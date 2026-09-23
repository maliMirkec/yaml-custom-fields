<?php
/**
 * Tests for BundleExporter
 *
 * The risk here is the mirror image of the importer's: a file that holds more
 * than was ticked would quietly carry settings to another site.
 */

namespace YamlCF\Tests\Unit\ImportExport;

use YamlCF\Tests\TestCase;
use YamlCF\ImportExport\BundleExporter;

class StubPageDataExporter {
  public $postIds = null;
  public $matchBy = null;

  public function exportMultiple($post_ids, $match_by = 'slug') {
    $this->postIds = $post_ids;
    $this->matchBy = $match_by;

    $posts = [];
    foreach ($post_ids as $id) {
      $posts[] = ['id' => $id, 'slug' => 'post-' . $id, 'data' => []];
    }

    return ['match_by' => $match_by, 'posts' => $posts];
  }
}

class BundleExporterTest extends TestCase {
  private $exporter;
  private $pageData;

  protected function setUp(): void {
    parent::setUp();

    yaml_cf_test_reset_options([
      'yaml_cf_schemas' => [
        'page.php' => "fields:\n  - name: a\n    type: string\n",
        'single.php' => "fields:\n  - name: b\n    type: string\n",
      ],
      'yaml_cf_template_global_schemas' => ['page.php' => "fields:\n  - name: s\n    type: string\n"],
      'yaml_cf_template_global_data' => ['page.php' => ['s' => 'shared']],
      'yaml_cf_partial_data' => ['header.php' => ['h' => 'hello']],
      'yaml_cf_global_schema' => "fields:\n  - name: g\n    type: string\n",
      'yaml_cf_global_data' => ['g' => 'site'],
      'yaml_cf_template_settings' => ['page.php' => true],
      'yaml_cf_data_object_types' => [
        'universities' => ['name' => 'Universities', 'schema' => 'fields: []'],
        'companies' => ['name' => 'Companies', 'schema' => 'fields: []'],
      ],
      'yaml_cf_data_object_entries_universities' => ['u1' => ['name' => 'One']],
      'yaml_cf_data_object_entries_companies' => ['c1' => ['name' => 'Two']],
    ]);

    $this->pageData = new StubPageDataExporter();
    $this->exporter = new BundleExporter($this->pageData);
  }

  protected function tearDown(): void {
    yaml_cf_test_reset_options();
    parent::tearDown();
  }

  /**
   * Test only the ticked schema lands in the file
   */
  public function testExportsOnlyTickedSchemas() {
    $bundle = $this->exporter->export(['schema:page.php']);

    $this->assertArrayHasKey('page.php', $bundle['settings']['schemas']);
    $this->assertArrayNotHasKey('single.php', $bundle['settings']['schemas']);
  }

  /**
   * Test nothing else from the settings sneaks in
   */
  public function testUntickedSettingsAreLeftOut() {
    $bundle = $this->exporter->export(['schema:page.php']);

    $settings = $bundle['settings'];
    $this->assertArrayNotHasKey('global_schema', $settings);
    $this->assertArrayNotHasKey('global_data', $settings);
    $this->assertArrayNotHasKey('template_settings', $settings);
    $this->assertArrayNotHasKey('partial_data', $settings);
    $this->assertArrayNotHasKey('template_global_data', $settings);
  }

  /**
   * Test each kind of setup item can be exported on its own
   */
  public function testExportsEachSetupKind() {
    $bundle = $this->exporter->export([
      'tglobal_schema:page.php',
      'tglobal_data:page.php',
      'partial_data:header.php',
      'global_schema',
      'global_data',
      'template_settings',
    ]);

    $settings = $bundle['settings'];
    $this->assertArrayHasKey('page.php', $settings['template_global_schemas']);
    $this->assertArrayHasKey('page.php', $settings['template_global_data']);
    $this->assertArrayHasKey('header.php', $settings['partial_data']);
    $this->assertNotEmpty($settings['global_schema']);
    $this->assertEquals(['g' => 'site'], $settings['global_data']);
    $this->assertEquals(['page.php' => true], $settings['template_settings']);
    $this->assertArrayNotHasKey('schemas', $settings);
  }

  /**
   * Test a ticked list carries its items with it
   */
  public function testExportsTickedListWithEntries() {
    $bundle = $this->exporter->export(['list:universities']);

    $this->assertArrayHasKey('universities', $bundle['types']);
    $this->assertArrayNotHasKey('companies', $bundle['types']);
    $this->assertEquals(['u1' => ['name' => 'One']], $bundle['types']['universities']['entries']);
  }

  /**
   * Test posts are taken from the post table, not from the tick list
   */
  public function testExportsChosenPosts() {
    $bundle = $this->exporter->export([], [11, 22], 'id');

    $this->assertEquals([11, 22], $this->pageData->postIds);
    $this->assertEquals('id', $this->pageData->matchBy);
    $this->assertCount(2, $bundle['posts']);
    $this->assertEquals('id', $bundle['match_by']);
  }

  /**
   * Test a file with no posts has no posts key at all
   */
  public function testNoPostsKeyWhenNonePicked() {
    $bundle = $this->exporter->export(['schema:page.php']);

    $this->assertArrayNotHasKey('posts', $bundle);
    $this->assertNull($this->pageData->postIds);
  }

  /**
   * Test the file says what it is, so the importer can recognise it
   */
  public function testBundleCarriesItsHeader() {
    $bundle = $this->exporter->export(['schema:page.php']);

    $this->assertEquals('yaml-custom-fields', $bundle['plugin']);
    $this->assertEquals('bundle', $bundle['type']);
    $this->assertArrayHasKey('exported_at', $bundle);
    $this->assertArrayHasKey('site_url', $bundle);
  }

  /**
   * Test an empty selection produces a file with a header and nothing else
   */
  public function testEmptySelectionExportsNothing() {
    $bundle = $this->exporter->export([]);

    $this->assertArrayNotHasKey('settings', $bundle);
    $this->assertArrayNotHasKey('posts', $bundle);
    $this->assertArrayNotHasKey('types', $bundle);
  }
}
