<?php
/**
 * Integration: where saving a collection item goes next
 *
 * "Save item" comes back to the item just saved. "Save and add another" goes
 * on to an empty form, because a collection is usually filled in several items
 * at a time.
 *
 * @package YamlCF
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Tests\WPTestCase;

class CollectionItemSaveTest extends WPTestCase {
	const COLLECTION = 'team';

	public function setUp(): void {
		parent::setUp();
		$this->actingAs($this->createTestUser('administrator'));
		update_option('yaml_cf_data_object_types', [
			self::COLLECTION => ['name' => 'Team', 'schema' => "fields:\n  - name: name\n    label: Name\n    type: string\n"],
		], false);
	}

	public function tearDown(): void {
		$_POST = [];
		$_GET = [];
		(new \YamlCF\Data\EntryStore())->deleteAll(self::COLLECTION);
		\YamlCF\Core\Plugin::getInstance()->get('notification_manager')->clear();
		parent::tearDown();
	}

	/**
	 * Submit the item form and return where it redirects to
	 */
	private function save(array $extra) {
		$_GET = ['page' => 'yaml-cf-manage-data-object-entries', 'type_id' => self::COLLECTION];
		$_POST = wp_slash(array_merge([
			'yaml_cf_save_entry_nonce' => wp_create_nonce('yaml_cf_save_entry'),
			'yaml_cf' => ['name' => 'Ada'],
		], $extra));

		$location = null;
		$stop = function ($to) use (&$location) {
			$location = $to;
			throw new \RuntimeException('redirected');
		};
		add_filter('wp_redirect', $stop, 1);
		try {
			\YamlCF\Core\Plugin::getInstance()->get('data_object_controller')->handleFormSubmissions();
		} catch (\RuntimeException $e) {
			$this->assertSame('redirected', $e->getMessage());
		} finally {
			remove_filter('wp_redirect', $stop, 1);
		}

		return (string) $location;
	}

	public function testSaveItemComesBackToTheItem() {
		$location = $this->save([]);

		$this->assertStringContainsString('action=edit', $location);
		$this->assertStringContainsString('entry=', $location);
		$this->assertSame(['Ada'], array_values((new \YamlCF\Data\EntryStore())->index(self::COLLECTION)));
	}

	public function testSaveAndAddAnotherGoesToAnEmptyForm() {
		$location = $this->save(['yaml_cf_after_save' => 'add_another']);

		$this->assertStringContainsString('action=add', $location);
		$this->assertStringNotContainsString('entry=', $location);
		$this->assertSame(['Ada'], array_values((new \YamlCF\Data\EntryStore())->index(self::COLLECTION)), 'The item is saved first.');

		$notices = \YamlCF\Core\Plugin::getInstance()->get('notification_manager')->peek();
		$this->assertStringContainsString('Fill in the next one', $notices[0]['message']);
	}

	public function testTheFormOffersBothButtons() {
		$_GET = ['page' => 'yaml-cf-manage-data-object-entries', 'type_id' => self::COLLECTION, 'action' => 'add'];

		ob_start();
		\YamlCF\Core\Plugin::getInstance()->get('data_object_controller')->renderEntries();
		$html = ob_get_clean();

		$this->assertStringContainsString('Save item', $html);
		$this->assertStringContainsString('name="yaml_cf_after_save" value="add_another"', $html);
		$this->assertLessThan(strpos($html, 'yaml_cf_after_save'), strpos($html, 'Save item'), 'Save item comes first, so Enter in a field does a plain save.');
	}
}
