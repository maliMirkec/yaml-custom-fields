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

	public function testSearchingIgnoresCaseBeyondAscii() {
		$this->store->save(self::COLLECTION, 'entry_1', ['name' => 'Željko Šimić'], 'name');
		$this->store->save(self::COLLECTION, 'entry_2', ['name' => 'Ana'], 'name');

		$this->assertSame(['entry_1'], $this->store->search(self::COLLECTION, 'želj', 10)['ids']);
		$this->assertSame(['entry_1'], $this->store->search(self::COLLECTION, 'ŠIMIĆ', 10)['ids']);
	}

	public function testSearchingRespectsTheLimit() {
		for ($i = 1; $i <= 40; $i++) {
			$this->store->save(self::COLLECTION, 'entry_' . $i, ['name' => 'Person ' . $i], 'name');
		}

		$found = (new DataObjectRepository())->searchEntries(self::COLLECTION, 'Person', 10);

		$this->assertCount(10, $found['labels']);
		$this->assertSame(40, $found['total'], 'The total counts everything that matched, not what was returned.');
	}

	// ------------------------------------------------ saves at the same moment

	/**
	 * Two saves at once both read the list and the second write replaced the
	 * first, so an item vanished from the list. With the lock held, a save reads
	 * the list the other save just wrote, even when this request read an older
	 * copy before that save finished.
	 */
	public function testASaveBuildsOnTheListAnotherRequestJustWrote() {
		global $wpdb;
		$this->store->save(self::COLLECTION, 'entry_1', ['name' => 'Ada'], 'name');

		// This request reads the list, so it holds a copy with entry_1 only
		$this->assertSame(['entry_1'], array_keys($this->store->index(self::COLLECTION)));

		// Meanwhile another request adds entry_2, straight to the database
		$wpdb->update(
			$wpdb->options,
			['option_value' => maybe_serialize(['entry_1' => 'Ada', 'entry_2' => 'Grace'])],
			['option_name' => EntryStore::INDEX_PREFIX . self::COLLECTION]
		);

		$this->store->save(self::COLLECTION, 'entry_3', ['name' => 'Katherine'], 'name');

		$this->assertSame(['entry_1', 'entry_2', 'entry_3'], array_keys($this->store->index(self::COLLECTION)));
	}

	public function testTheLockIsGivenBackAfterEachChange() {
		$this->store->save(self::COLLECTION, 'entry_1', ['name' => 'Ada'], 'name');
		$this->store->delete(self::COLLECTION, 'entry_1');
		$this->store->replaceAll(self::COLLECTION, ['entry_2' => ['name' => 'Grace']], 'name');

		$this->assertFalse($this->lockRowExists());
	}

	/**
	 * A request that dies while saving leaves its lock behind. After a few
	 * seconds it is taken over, so the collection does not stay locked.
	 */
	public function testALockLeftBehindIsTakenOver() {
		global $wpdb;
		$wpdb->insert($wpdb->options, [
			'option_name' => \YamlCF\Data\CollectionLock::PREFIX . self::COLLECTION,
			'option_value' => (string) (time() - 60),
			'autoload' => 'no',
		]);

		$started = microtime(true);
		$this->store->save(self::COLLECTION, 'entry_1', ['name' => 'Ada'], 'name');

		$this->assertLessThan(2, microtime(true) - $started, 'A stale lock is taken over at once, not waited out.');
		$this->assertSame(['entry_1'], array_keys($this->store->index(self::COLLECTION)));
		$this->assertFalse($this->lockRowExists());
	}

	/**
	 * Replacing a large collection can outlast the few seconds after which a
	 * lock counts as left behind. Work that holds it keeps its time current, so
	 * another save cannot take it over halfway through.
	 */
	public function testALockInUseIsKeptCurrent() {
		$seen = null;

		\YamlCF\Data\CollectionLock::run(self::COLLECTION, function () use (&$seen) {
			global $wpdb;
			$name = \YamlCF\Data\CollectionLock::PREFIX . self::COLLECTION;
			// As if the work had been going for a minute
			$wpdb->update($wpdb->options, ['option_value' => (string) (time() - 60)], ['option_name' => $name]);

			// The request's own record of its last write is recent, so push it back too
			$touched = new \ReflectionProperty(\YamlCF\Data\CollectionLock::class, 'touched');
			$touched->setValue(null, [self::COLLECTION => time() - 60]);

			\YamlCF\Data\CollectionLock::keepAlive(self::COLLECTION);

			$seen = (int) $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name));
		});

		$this->assertGreaterThanOrEqual(time() - 1, $seen);
		$this->assertFalse($this->lockRowExists());
	}

	public function testKeepingAliveALockNotHeldWritesNothing() {
		\YamlCF\Data\CollectionLock::keepAlive(self::COLLECTION);

		$this->assertFalse($this->lockRowExists());
	}

	private function lockRowExists() {
		global $wpdb;

		return null !== $wpdb->get_var($wpdb->prepare(
			"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
			\YamlCF\Data\CollectionLock::PREFIX . self::COLLECTION
		));
	}

	// ------------------------------------------------------- the public API

	public function testTheTemplateFunctionStillReturnsEveryItem() {
		$this->store->save(self::COLLECTION, 'entry_1', ['name' => 'Ada'], 'name');
		$this->store->save(self::COLLECTION, 'entry_2', ['name' => 'Grace'], 'name');

		$all = yaml_cf_get_data_objects(self::COLLECTION);

		$this->assertCount(2, $all);
		$this->assertSame('Ada', $all['entry_1']['name']);
	}

	/**
	 * A data_object field with list: true holds several item IDs. The template
	 * function returns those items in the order they were chosen, skipping any
	 * that have since been deleted.
	 */
	public function testAListFieldReturnsTheChosenItemsInOrder() {
		$this->store->save(self::COLLECTION, 'entry_1', ['name' => 'Ada'], 'name');
		$this->store->save(self::COLLECTION, 'entry_2', ['name' => 'Grace'], 'name');
		$post_id = $this->createTestPost([], ['speakers' => ['entry_2', 'gone', 'entry_1']]);

		$speakers = yaml_cf_get_data_object('speakers', $post_id);

		$this->assertSame(['Grace', 'Ada'], array_column($speakers, 'name'));
	}

	/**
	 * Code fields are stored encoded. A theme reading an item gets the code,
	 * as it does from a page's fields.
	 */
	public function testTheTemplateFunctionsDecodeCodeFields() {
		update_option('yaml_cf_data_object_types', [
			self::COLLECTION => ['name' => 'People', 'schema' => self::SCHEMA . "  - name: embed\n    label: Embed\n    type: code\n"],
		], false);
		$this->store->save(self::COLLECTION, 'entry_1', ['name' => 'Ada', 'embed' => '__YAMLCF_B64__' . base64_encode('<div>x</div>')], 'name');
		$post_id = $this->createTestPost([], ['speaker' => 'entry_1']);

		$this->assertSame('<div>x</div>', yaml_cf_get_data_objects(self::COLLECTION)['entry_1']['embed']);
		$this->assertSame('<div>x</div>', yaml_cf_get_data_object('speaker', $post_id)['embed']);
	}

	/**
	 * An import keeps the item IDs in its file, so two collections can both hold
	 * "entry_1". A field that picks from the second one used to get the first
	 * one's item, because every collection was searched in turn.
	 */
	public function testAFieldGetsTheItemFromItsOwnCollection() {
		update_option('yaml_cf_data_object_types', [
			self::COLLECTION => ['name' => 'People', 'schema' => self::SCHEMA],
			'venues' => ['name' => 'Venues', 'schema' => "fields:\n  - name: name\n    type: string\n"],
		], false);
		update_option('yaml_cf_schemas', ['single.php' => "fields:\n  - name: venue\n    type: data_object\n    options:\n      object_type: venues\n"], false);
		$this->store->save(self::COLLECTION, 'entry_1', ['name' => 'Ada'], 'name');
		$this->store->save('venues', 'entry_1', ['name' => 'Main hall'], 'name');
		$post_id = $this->createTestPost([], ['venue' => 'entry_1']);

		$this->assertSame('Main hall', yaml_cf_get_data_object('venue', $post_id)['name']);

		$this->store->deleteAll('venues');
	}

	/**
	 * A field no schema describes has no collection to go by, so every
	 * collection is still searched for it, as before.
	 */
	public function testAFieldWithNoSchemaStillFindsItsItem() {
		update_option('yaml_cf_schemas', [], false);
		$this->store->save(self::COLLECTION, 'entry_1', ['name' => 'Ada'], 'name');
		$post_id = $this->createTestPost([], ['speaker' => 'entry_1']);

		$this->assertSame('Ada', yaml_cf_get_data_object('speaker', $post_id)['name']);
	}

	public function testAskingForACollectionThatDoesNotExistIsEmpty() {
		$this->assertSame([], yaml_cf_get_data_objects('no_such_collection'));
		$this->assertSame([], $this->store->index('no_such_collection'));
		$this->assertNull($this->store->get('no_such_collection', 'entry_1'));
	}
}
