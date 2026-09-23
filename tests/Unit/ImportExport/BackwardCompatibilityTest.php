<?php
/**
 * Backward compatibility of the export file formats
 *
 * Files written by earlier versions of the plugin came in four shapes: a
 * settings file, a multi-post file, a single-post file and a data objects file.
 * All four must still import, and must still be listed correctly in the import
 * preview. These tests pin that down with the real formats, byte for byte as
 * the old exporters wrote them.
 */

namespace YamlCF\Tests\Unit\ImportExport;

use YamlCF\Tests\TestCase;
use YamlCF\ImportExport\BundleImporter;
use YamlCF\ImportExport\BundleCatalog;
use YamlCF\ImportExport\SettingsImporter;
use YamlCF\ImportExport\PageDataImporter;
use YamlCF\ImportExport\DataObjectImporter;

class BackwardCompatibilityTest extends TestCase {
  private $importer;
  private $catalog;
  private $settings;
  private $posts;
  private $lists;

  protected function setUp(): void {
    parent::setUp();
    yaml_cf_test_reset_options();
    $GLOBALS['yaml_cf_test_posts'] = [];

    $this->settings = new RecordingSettingsImporter();
    $this->posts = new RecordingPageDataImporter();
    $this->lists = new RecordingDataObjectImporter();
    $this->importer = new BundleImporter($this->settings, $this->posts, $this->lists);
    $this->catalog = new BundleCatalog();
  }

  protected function tearDown(): void {
    yaml_cf_test_reset_options();
    unset($GLOBALS['yaml_cf_test_posts']);
    parent::tearDown();
  }

  /**
   * A settings file, exactly as SettingsExporter has always written it
   */
  private function legacySettingsFile() {
    return [
      'plugin' => 'yaml-custom-fields',
      'version' => '1.2.9',
      'exported_at' => '2026-01-01 10:00:00',
      'site_url' => 'https://old.example',
      'settings' => [
        'template_settings' => ['page.php' => true],
        'schemas' => ['page.php' => "fields:\n  - name: title\n    type: string\n"],
        'partial_data' => ['header.php' => ['tagline' => 'Hello']],
        'template_global_schemas' => ['page.php' => "fields:\n  - name: shared\n    type: string\n"],
        'template_global_data' => ['page.php' => ['shared' => 'value']],
        'global_schema' => "fields:\n  - name: phone\n    type: string\n",
        'global_data' => ['phone' => '123'],
      ],
    ];
  }

  /**
   * A multi-post file, as PageDataExporter::exportMultiple() writes it
   */
  private function legacyPostsFile() {
    return [
      'plugin' => 'yaml-custom-fields',
      'version' => '1.2.9',
      'exported_at' => '2026-01-01 10:00:00',
      'site_url' => 'https://old.example',
      'match_by' => 'slug',
      'posts' => [
        ['id' => 11, 'title' => 'About', 'slug' => 'about', 'type' => 'page', 'template' => 'page.php', 'data' => ['title' => 'A']],
        ['id' => 22, 'title' => 'Contact', 'slug' => 'contact', 'type' => 'page', 'template' => 'page.php', 'data' => ['title' => 'B']],
      ],
    ];
  }

  /**
   * A single-post file, as the meta box "Export fields" button writes it
   */
  private function legacySinglePostFile() {
    return [
      'plugin' => 'yaml-custom-fields',
      'version' => '1.2.9',
      'exported_at' => '2026-01-01 10:00:00',
      'site_url' => 'https://old.example',
      'type' => 'single-post',
      'post' => ['id' => 7, 'title' => 'Solo', 'slug' => 'solo', 'type' => 'post', 'data' => ['title' => 'C']],
    ];
  }

  /**
   * A data objects file, as DataObjectExporter has always written it
   */
  private function legacyDataObjectsFile() {
    return [
      'plugin' => 'yaml-custom-fields',
      'type' => 'data_objects',
      'version' => '1.2.9',
      'site_url' => 'https://old.example',
      'exported_at' => '2026-01-01 10:00:00',
      'types' => [
        'universities' => [
          'name' => 'Universities',
          'schema' => "fields:\n  - name: name\n    type: string\n",
          'entries' => ['u1' => ['name' => 'One']],
        ],
      ],
    ];
  }

  // ------------------------------------------------- old files still import

  /**
   * Test an old settings file passes validation
   */
  public function testAcceptsLegacySettingsFile() {
    $result = $this->importer->validate($this->legacySettingsFile());
    $this->assertTrue($result['valid'], $result['message']);
  }

  /**
   * Collect every selectable id out of the grouped setup tree
   *
   * @param array $setup Setup groups
   * @return string[]
   */
  private function setupIds($setup) {
    $ids = [];
    foreach ($setup as $group) {
      foreach ($group['children'] as $child) {
        $ids[] = $child['id'];
      }
    }
    return $ids;
  }

  /**
   * Test every piece of an old settings file is listed in the preview
   */
  public function testListsEveryPieceOfLegacySettingsFile() {
    $items = $this->catalog->fromBundle($this->legacySettingsFile());
    $ids = $this->setupIds($items['setup']);

    $this->assertContains('schema:page.php', $ids);
    $this->assertContains('tglobal_schema:page.php', $ids);
    $this->assertContains('tglobal_data:page.php', $ids);
    $this->assertContains('partial_data:header.php', $ids);
    $this->assertContains('global_schema', $ids);
    $this->assertContains('global_data', $ids);
    $this->assertContains('template_settings', $ids);
  }

  /**
   * Test a template's pieces are gathered under one row instead of scattered
   */
  public function testGroupsEachTemplatesPiecesTogether() {
    $items = $this->catalog->fromBundle($this->legacySettingsFile());

    $byKey = [];
    foreach ($items['setup'] as $group) {
      $byKey[$group['key']] = $group;
    }

    // page.php has a schema, shared fields and shared content: one row, three pieces
    $this->assertArrayHasKey('tpl:page.php', $byKey);
    $this->assertCount(3, $byKey['tpl:page.php']['children']);
    $this->assertEquals(
      ['schema:page.php', 'tglobal_schema:page.php', 'tglobal_data:page.php'],
      array_column($byKey['tpl:page.php']['children'], 'id')
    );

    // header.php only has content
    $this->assertArrayHasKey('tpl:header.php', $byKey);
    $this->assertEquals(['partial_data:header.php'], array_column($byKey['tpl:header.php']['children'], 'id'));

    // Site details keeps its schema and content together
    $this->assertArrayHasKey('site-details', $byKey);
    $this->assertEquals(['global_schema', 'global_data'], array_column($byKey['site-details']['children'], 'id'));
  }

  /**
   * Test a template whose schema was never written is left out of the list
   */
  public function testSkipsEmptyPieces() {
    $file = $this->legacySettingsFile();
    $file['settings']['schemas']['never-used.php'] = '';

    $ids = $this->setupIds($this->catalog->fromBundle($file)['setup']);

    $this->assertNotContains('schema:never-used.php', $ids);
  }

  /**
   * Test an old settings file imports through the settings importer
   */
  public function testImportsLegacySettingsFile() {
    $result = $this->importer->import($this->legacySettingsFile(), [
      'schema:page.php',
      'global_schema',
    ]);

    $this->assertTrue($result['success']);
    $this->assertEquals(2, $result['imported']);
    $this->assertArrayHasKey('page.php', $this->settings->received['settings']['schemas']);
  }

  /**
   * Test an old multi-post file is listed with one row per post
   */
  public function testListsLegacyPostsFile() {
    $items = $this->catalog->fromBundle($this->legacyPostsFile());

    $this->assertCount(2, $items['posts']);
    $this->assertEquals('post:11', $items['posts'][0]['id']);
    $this->assertEquals('About', $items['posts'][0]['label']);
  }

  /**
   * Test an old multi-post file imports, keeping its match_by
   */
  public function testImportsLegacyPostsFile() {
    $result = $this->importer->import($this->legacyPostsFile(), ['post:11', 'post:22']);

    $this->assertTrue($result['success']);
    $this->assertCount(2, $this->posts->received['posts']);
    $this->assertEquals('slug', $this->posts->received['match_by']);
  }

  /**
   * Test an old single-post file is listed as one row
   */
  public function testListsLegacySinglePostFile() {
    $items = $this->catalog->fromBundle($this->legacySinglePostFile());

    $this->assertCount(1, $items['posts']);
    $this->assertEquals('post:7', $items['posts'][0]['id']);
  }

  /**
   * Test an old single-post file imports
   */
  public function testImportsLegacySinglePostFile() {
    $result = $this->importer->import($this->legacySinglePostFile(), ['post:7']);

    $this->assertTrue($result['success']);
    $this->assertCount(1, $this->posts->received['posts']);
    $this->assertEquals('solo', $this->posts->received['posts'][0]['slug']);
  }

  /**
   * Test an old data objects file is listed with its item count
   */
  public function testListsLegacyDataObjectsFile() {
    $items = $this->catalog->fromBundle($this->legacyDataObjectsFile());

    $this->assertCount(1, $items['lists']);
    $this->assertEquals('list:universities', $items['lists'][0]['id']);
    $this->assertEquals('Universities', $items['lists'][0]['label']);
  }

  /**
   * Test an old data objects file imports, and is handed on in the shape the
   * data object importer still demands (it checks type === 'data_objects')
   */
  public function testImportsLegacyDataObjectsFile() {
    $result = $this->importer->import($this->legacyDataObjectsFile(), ['list:universities']);

    $this->assertTrue($result['success']);
    $this->assertEquals('data_objects', $this->lists->received['type']);
    $this->assertArrayHasKey('universities', $this->lists->received['types']);
  }

  /**
   * Test the payload handed to the real data object importer would pass its
   * own validation, since it insists on type === 'data_objects'
   */
  public function testPayloadSatisfiesRealDataObjectImporter() {
    $this->importer->import($this->legacyDataObjectsFile(), ['list:universities']);

    $real = new DataObjectImporter();
    $check = $real->validate($this->lists->received);

    $this->assertTrue($check['valid'], $check['message']);
  }

  /**
   * Test the payload handed to the real settings importer would pass its own
   * validation
   */
  public function testPayloadSatisfiesRealSettingsImporter() {
    $this->importer->import($this->legacySettingsFile(), ['schema:page.php']);

    $real = new SettingsImporter();
    $check = $real->validate($this->settings->received);

    $this->assertTrue($check['valid'], $check['message']);
  }

  /**
   * Test the payload handed to the real page data importer would pass its own
   * validation
   */
  public function testPayloadSatisfiesRealPageDataImporter() {
    $this->importer->import($this->legacyPostsFile(), ['post:11']);

    $real = new PageDataImporter();
    $check = $real->validate($this->posts->received);

    $this->assertTrue($check['valid'], $check['message']);
  }

  /**
   * Test a file from some other plugin is still refused
   */
  public function testStillRefusesForeignFiles() {
    $result = $this->importer->validate(['plugin' => 'other-plugin', 'settings' => []]);
    $this->assertFalse($result['valid']);
  }

  /**
   * Test a file of ours holding nothing importable is refused
   */
  public function testRefusesEmptyFile() {
    $result = $this->importer->validate(['plugin' => 'yaml-custom-fields', 'version' => '1.0.0']);
    $this->assertFalse($result['valid']);
  }
}
