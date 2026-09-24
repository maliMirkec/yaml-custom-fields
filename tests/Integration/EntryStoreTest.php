<?php
/**
 * Integration: a collection's items, one option each
 *
 * Every item of a collection used to live in one option, read and written whole.
 * Showing one item, searching for one, or saving one meant unserialising all of
 * them — on every keystroke in the picker. Each item now has an option to
 * itself, beside a small index of id => label that listing and searching read.
 *
 * The important promises here: nothing is lost in the move, a site that has not
 * moved yet still works, and the ids already stored in pages' fields keep
 * pointing at the same items.
 *
 * @package YamlCF
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Core\Upgrader;
use YamlCF\Data\EntryStore;
use YamlCF\Data\Repositories\DataObjectRepository;
use YamlCF\Tests\FailsOnPluginWarnings;
use YamlCF\Tests\WPTestCase;

class EntryStoreTest extends WPTestCase {
	use FailsOnPluginWarnings;

	const COLLECTION = 'people';
	const SCHEMA = "fields:\n  - name: name\n    label: Name\n    type: string\n  - name: role\n    label: Role\n    type: string\n";

	/** @var EntryStore */
	private $store;

	public function setUp(): void {
		parent::setUp();
		$this->failOnPluginWarnings();
		$this->store = new EntryStore();

		update_option('yaml_cf_data_object_types', [
			self::COLLECTION => ['name' => 'People', 'schema' => self::SCHEMA],
		], false);
	}

	public function tearDown(): void {
		$this->store->deleteAll(self::COLLECTION);
		delete_option('yaml_cf_db_version');
		$this->stopFailingOnPluginWarnings();
		parent::tearDown();
	}

	/**
	 * Put a collection in the state a pre-2.0.0 site left it in
	 */
	private function givenLegacyEntries(array $entries) {
		update_option(EntryStore::LEGACY_PREFIX . self::COLLECTION, $entries, false);
		delete_option(EntryStore::INDEX_PREFIX . self::COLLECTION);
	}

	// ------------------------------------------------------- reading legacy

	/**
	 * A site that has not run the move yet has to keep working, whatever order
	 * things happen in.
	 */
	public function testItemsAreReadableBeforeTheMoveHasRun() {
		$this->givenLegacyEntries([
			'entry_1' => ['name' => 'Ada', 'role' => 'Engineer'],
			'entry_2' => ['name' => 'Grace', 'role' => 'Admiral'],
		]);

		$this->assertSame(['name' => 'Ada', 'role' => 'Engineer'], $this->store->get(self::COLLECTION, 'entry_1'));
		$this->assertSame(2, $this->store->count(self::COLLECTION));
		$this->assertSame(['entry_1', 'entry_2'], array_keys($this->store->all(self::COLLECTION)));
	}

	public function testSearchWorksBeforeTheMoveHasRun() {
		$this->givenLegacyEntries([
			'entry_1' => ['name' => 'Ada'],
			'entry_2' => ['name' => 'Grace'],
		]);

		$found = $this->store->search(self::COLLECTION, 'Gra', 10);

		$this->assertSame(['entry_2'], $found['ids']);
	}

	// -------------------------------------------------------------- the move

	public function testTheMoveKeepsEveryItem() {
		$this->givenLegacyEntries([
			'entry_1' => ['name' => 'Ada', 'role' => 'Engineer'],
			'entry_2' => ['name' => 'Grace', 'role' => 'Admiral'],
		]);

		(new Upgrader())->maybeUpgrade();

		$this->assertFalse(get_option(EntryStore::LEGACY_PREFIX . self::COLLECTION, false), 'The old option should be gone.');
		$this->assertSame(['name' => 'Ada', 'role' => 'Engineer'], $this->store->get(self::COLLECTION, 'entry_1'));
		$this->assertSame(['name' => 'Grace', 'role' => 'Admiral'], $this->store->get(self::COLLECTION, 'entry_2'));
		$this->assertSame(2, $this->store->count(self::COLLECTION));
	}

	/**
	 * The index labels items by the collection's first field, which is what the
	 * pickers have always shown.
	 */
	public function testTheMoveLabelsItemsByTheFirstField() {
		$this->givenLegacyEntries(['entry_1' => ['name' => 'Ada', 'role' => 'Engineer']]);

		(new Upgrader())->maybeUpgrade();

		$this->assertSame(['entry_1' => 'Ada'], $this->store->index(self::COLLECTION));
	}

	public function testTheMoveIsSafeToRunTwice() {
		$this->givenLegacyEntries(['entry_1' => ['name' => 'Ada']]);

		(new Upgrader())->maybeUpgrade();
		$this->store->save(self::COLLECTION, 'entry_1', ['name' => 'Ada Lovelace'], 'name');

		delete_option('yaml_cf_db_version');
		(new Upgrader())->maybeUpgrade();

		$this->assertSame(['name' => 'Ada Lovelace'], $this->store->get(self::COLLECTION, 'entry_1'), 'A second run must not overwrite what has been edited since.');
	}

	/**
	 * Ids already stored in pages' fields have to keep pointing at the same item.
	 */
	public function testStoredReferencesStillResolveAfterTheMove() {
		$this->givenLegacyEntries(['entry_1' => ['name' => 'Ada']]);

		(new Upgrader())->maybeUpgrade();

		$this->assertSame(['name' => 'Ada'], yaml_cf_get_data_objects(self::COLLECTION)['entry_1']);
	}

	/**
	 * Ids come from import files and forms, so they can be anything.
	 */
	public function testAwkwardEntryIdsSurviveTheMove() {
		$ids = ['entry.1', 'Entry 2', 'ünïcode', '3', 'a/b', str_repeat('x', 200)];

		$legacy = [];
		foreach ($ids as $i => $id) {
			$legacy[$id] = ['name' => 'Name ' . $i];
		}
		$this->givenLegacyEntries($legacy);

		(new Upgrader())->maybeUpgrade();

		foreach ($ids as $i => $id) {
			$this->assertSame(['name' => 'Name ' . $i], $this->store->get(self::COLLECTION, $id), 'Entry id: ' . $id);
		}
	}

	// ------------------------------------------------------ everyday working

	public function testSavingAndReadingBackOneItem() {
		$this->store->save(self::COLLECTION, 'entry_1', ['name' => 'Ada'], 'name');

		$this->assertSame(['name' => 'Ada'], $this->store->get(self::COLLECTION, 'entry_1'));
		$this->assertSame(['entry_1' => 'Ada'], $this->store->index(self::COLLECTION));
	}

	public function testSavingUpdatesTheLabelInTheIndex() {
		$this->store->save(self::COLLECTION, 'entry_1', ['name' => 'Ada'], 'name');
		$this->store->save(self::COLLECTION, 'entry_1', ['name' => 'Ada Lovelace'], 'name');

		$this->assertSame(['entry_1' => 'Ada Lovelace'], $this->store->index(self::COLLECTION));
	}

	public function testDeletingRemovesTheItemAndItsIndexEntry() {
		$this->store->save(self::COLLECTION, 'entry_1', ['name' => 'Ada'], 'name');
		$this->store->save(self::COLLECTION, 'entry_2', ['name' => 'Grace'], 'name');

		$this->store->delete(self::COLLECTION, 'entry_1');

		$this->assertNull($this->store->get(self::COLLECTION, 'entry_1'));
		$this->assertSame(['entry_2' => 'Grace'], $this->store->index(self::COLLECTION));
	}

	/**
	 * Deleting a whole collection must not leave its items behind as options
	 * nothing points at. The index is what makes that possible without asking
	 * the database for option names, which this plugin does not do.
	 */
	public function testDeletingACollectionRemovesEveryItem() {
		$this->store->save(self::COLLECTION, 'entry_1', ['name' => 'Ada'], 'name');
		$this->store->save(self::COLLECTION, 'entry_2', ['name' => 'Grace'], 'name');

		$this->store->deleteAll(self::COLLECTION);

		$this->assertSame([], $this->store->index(self::COLLECTION));
		$this->assertNull($this->store->get(self::COLLECTION, 'entry_1'));
		$this->assertNull($this->store->get(self::COLLECTION, 'entry_2'));
	}

	public function testReplacingEverythingDropsWhatWasThereBefore() {
		$this->store->save(self::COLLECTION, 'old', ['name' => 'Gone'], 'name');

		$this->store->replaceAll(self::COLLECTION, ['new' => ['name' => 'Kept']], 'name');

		$this->assertNull($this->store->get(self::COLLECTION, 'old'));
		$this->assertSame(['name' => 'Kept'], $this->store->get(self::COLLECTION, 'new'));
	}

	// ------------------------------------------------------ paging and search

	public function testAPageOfItemsReadsOnlyThatPage() {
		for ($i = 1; $i <= 120; $i++) {
			$this->store->save(self::COLLECTION, 'entry_' . $i, ['name' => 'Person ' . $i], 'name');
		}

		$page = (new DataObjectRepository())->getEntryPage(self::COLLECTION, 2, 50);

		$this->assertCount(50, $page['entries']);
		$this->assertSame(120, $page['total']);
		$this->assertSame(3, $page['total_pages']);
		$this->assertArrayHasKey('entry_51', $page['entries']);
		$this->assertArrayNotHasKey('entry_1', $page['entries']);
	}

	public function testAskingForAPageBeyondTheEndGivesTheLastOne() {
		$this->store->save(self::COLLECTION, 'entry_1', ['name' => 'Ada'], 'name');

		$page = (new DataObjectRepository())->getEntryPage(self::COLLECTION, 99, 50);

		$this->assertSame(1, $page['paged']);
		$this->assertCount(1, $page['entries']);
	}

	public function testSearchingReturnsMatchesAndTheirCount() {
		$this->store->save(self::COLLECTION, 'entry_1', ['name' => 'Ada Lovelace'], 'name');
		$this->store->save(self::COLLECTION, 'entry_2', ['name' => 'Grace Hopper'], 'name');
		$this->store->save(self::COLLECTION, 'entry_3', ['name' => 'Ada Byron'], 'name');

		$found = (new DataObjectRepository())->searchEntries(self::COLLECTION, 'ada', 10);

		$this->assertSame(2, $found['total']);
		$this->assertSame(['entry_1', 'entry_3'], array_keys($found['labels']));
	}

	public function testSearchingRespectsTheLimit() {
		for ($i = 1; $i <= 40; $i++) {
			$this->store->save(self::COLLECTION, 'entry_' . $i, ['name' => 'Person ' . $i], 'name');
		}

		$found = (new DataObjectRepository())->searchEntries(self::COLLECTION, 'Person', 10);

		$this->assertCount(10, $found['labels']);
		$this->assertSame(40, $found['total'], 'The total counts everything that matched, not what was returned.');
	}

	// ------------------------------------------------------- the public API

	public function testTheTemplateFunctionStillReturnsEveryItem() {
		$this->store->save(self::COLLECTION, 'entry_1', ['name' => 'Ada'], 'name');
		$this->store->save(self::COLLECTION, 'entry_2', ['name' => 'Grace'], 'name');

		$all = yaml_cf_get_data_objects(self::COLLECTION);

		$this->assertCount(2, $all);
		$this->assertSame('Ada', $all['entry_1']['name']);
	}

	public function testAskingForACollectionThatDoesNotExistIsEmpty() {
		$this->assertSame([], yaml_cf_get_data_objects('no_such_collection'));
		$this->assertSame([], $this->store->index('no_such_collection'));
		$this->assertNull($this->store->get('no_such_collection', 'entry_1'));
	}
}
