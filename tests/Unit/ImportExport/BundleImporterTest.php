<?php
/**
 * Tests for BundleImporter
 *
 * The importers it delegates to are replaced with recorders, so these tests are
 * about one thing only: does a partial import hand on exactly what was ticked,
 * and nothing else?
 */

namespace YamlCF\Tests\Unit\ImportExport;

use YamlCF\Tests\TestCase;
use YamlCF\ImportExport\BundleImporter;

class RecordingSettingsImporter {
  public $received = null;
  public $merge = null;
  /**
   * What the importer reports as written; null leaves the key out, the way an
   * older importer would
   */
  public $imported = null;

  public function import($data, $merge = false) {
    $this->received = $data;
    $this->merge = $merge;
    $result = ['success' => true, 'message' => 'ok', 'errors' => []];
    if ($this->imported !== null) {
      $result['imported'] = $this->imported;
    }
    return $result;
  }
}

class RecordingPageDataImporter {
  public $received = null;

  public function importMany($data) {
    $this->received = $data;
    return ['success' => true, 'message' => 'ok', 'imported' => count($data['posts']), 'errors' => []];
  }
}

class RecordingDataObjectImporter {
  public $received = null;

  public function import($data) {
    $this->received = $data;
    return ['success' => true, 'types_imported' => count($data['types']), 'entries_imported' => 0, 'errors' => []];
  }
}

class BundleImporterTest extends TestCase {
  private $importer;
  private $settings;
  private $posts;
  private $lists;

  protected function setUp(): void {
    parent::setUp();
    $this->settings = new RecordingSettingsImporter();
    $this->posts = new RecordingPageDataImporter();
    $this->lists = new RecordingDataObjectImporter();
    $this->importer = new BundleImporter($this->settings, $this->posts, $this->lists);
  }

  private function bundle() {
    return [
      'plugin' => 'yaml-custom-fields',
      'type' => 'bundle',
      'match_by' => 'slug',
      'settings' => [
        'schemas' => [
          'page.php' => "fields:\n  - name: a\n    type: string\n",
          'single.php' => "fields:\n  - name: b\n    type: string\n",
        ],
        'global_schema' => "fields:\n  - name: c\n    type: string\n",
        'global_data' => ['c' => 'value'],
      ],
      'posts' => [
        ['id' => 11, 'slug' => 'about', 'title' => 'About', 'data' => ['a' => '1']],
        ['id' => 22, 'slug' => 'contact', 'title' => 'Contact', 'data' => ['a' => '2']],
      ],
      'types' => [
        'universities' => ['name' => 'Universities', 'schema' => '', 'entries' => []],
        'companies' => ['name' => 'Companies', 'schema' => '', 'entries' => []],
      ],
    ];
  }

  /**
   * Test only the ticked schema is passed on
   */
  public function testImportsOnlyTickedSchemas() {
    $this->importer->import($this->bundle(), ['schema:page.php']);

    $sent = $this->settings->received['settings'];
    $this->assertArrayHasKey('page.php', $sent['schemas']);
    $this->assertArrayNotHasKey('single.php', $sent['schemas']);
    $this->assertArrayNotHasKey('global_schema', $sent);
    $this->assertArrayNotHasKey('global_data', $sent);
  }

  /**
   * Test settings are always merged, so untouched items on this site survive
   */
  public function testSettingsAreAlwaysMerged() {
    $this->importer->import($this->bundle(), ['schema:page.php']);

    $this->assertTrue($this->settings->merge);
  }

  /**
   * Test only the ticked posts are passed on, keyed by their id in the file
   */
  public function testImportsOnlyTickedPosts() {
    $this->importer->import($this->bundle(), ['post:22']);

    $sent = $this->posts->received['posts'];
    $this->assertCount(1, $sent);
    $this->assertEquals('contact', $sent[0]['slug']);
    $this->assertEquals('slug', $this->posts->received['match_by']);
  }

  /**
   * Test only the ticked lists are passed on
   */
  public function testImportsOnlyTickedLists() {
    $this->importer->import($this->bundle(), ['list:companies']);

    $sent = $this->lists->received['types'];
    $this->assertArrayHasKey('companies', $sent);
    $this->assertArrayNotHasKey('universities', $sent);
  }

  /**
   * Test a group with nothing ticked is never handed to its importer at all
   */
  public function testUntouchedGroupsAreNotImported() {
    $this->importer->import($this->bundle(), ['schema:page.php']);

    $this->assertNull($this->posts->received);
    $this->assertNull($this->lists->received);
  }

  /**
   * Test picking across all three groups at once
   */
  public function testImportsAcrossGroups() {
    $result = $this->importer->import($this->bundle(), [
      'schema:page.php',
      'global_data',
      'post:11',
      'list:universities',
    ]);

    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('page.php', $this->settings->received['settings']['schemas']);
    $this->assertArrayHasKey('global_data', $this->settings->received['settings']);
    $this->assertCount(1, $this->posts->received['posts']);
    $this->assertArrayHasKey('universities', $this->lists->received['types']);
    // 2 settings items + 1 post + 1 list
    $this->assertEquals(4, $result['imported']);
  }

  /**
   * Test an empty selection changes nothing
   */
  public function testEmptySelectionImportsNothing() {
    $result = $this->importer->import($this->bundle(), []);

    $this->assertFalse($result['success']);
    $this->assertEquals(0, $result['imported']);
    $this->assertNull($this->settings->received);
    $this->assertNull($this->posts->received);
    $this->assertNull($this->lists->received);
  }

  /**
   * Test an id that is not in the file is ignored rather than invented
   */
  public function testUnknownSelectionIsIgnored() {
    $result = $this->importer->import($this->bundle(), ['schema:nothere.php', 'post:999']);

    $this->assertEquals(0, $result['imported']);
    $this->assertNull($this->settings->received);
    $this->assertNull($this->posts->received);
  }

  /**
   * Test a file from another plugin is refused
   */
  public function testRejectsForeignFile() {
    $result = $this->importer->import(['plugin' => 'something-else'], ['schema:page.php']);

    $this->assertFalse($result['success']);
    $this->assertNull($this->settings->received);
  }

  /**
   * Test an older single-post export still imports
   */
  public function testReadsSinglePostFormat() {
    $data = [
      'plugin' => 'yaml-custom-fields',
      'type' => 'single-post',
      'post' => ['id' => 7, 'slug' => 'solo', 'data' => ['a' => '1']],
    ];

    $this->importer->import($data, ['post:7']);

    $this->assertCount(1, $this->posts->received['posts']);
    $this->assertEquals('solo', $this->posts->received['posts'][0]['slug']);
  }

  /**
   * Test the count follows what the settings importer really wrote
   *
   * Ticking four setup pieces and having one rejected must not be reported as four.
   */
  public function testSettingsCountFollowsWhatWasWritten() {
    $this->settings->imported = 3;

    $result = $this->importer->import($this->bundle(), [
      'schema:page.php',
      'schema:single.php',
      'global_schema',
      'global_data',
    ]);

    $this->assertEquals(3, $result['imported']);
  }

  /**
   * Test an importer that reports no count still counts what was ticked
   */
  public function testSettingsCountFallsBackToTheTickedItems() {
    $this->settings->imported = null;

    $result = $this->importer->import($this->bundle(), ['schema:page.php', 'global_schema']);

    $this->assertEquals(2, $result['imported']);
  }

  /**
   * Test an older data objects export still imports
   */
  public function testReadsLegacyDataObjectsFile() {
    $data = [
      'plugin' => 'yaml-custom-fields',
      'type' => 'data_objects',
      'types' => ['universities' => ['name' => 'Universities', 'schema' => '', 'entries' => []]],
    ];

    $this->importer->import($data, ['list:universities']);

    $this->assertArrayHasKey('universities', $this->lists->received['types']);
  }
}
