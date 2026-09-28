<?php
/**
 * Integration: whether saving site-wide content clears every page's cache
 *
 * Site details, shared content and site parts show on many pages at once, so a
 * save has cleared the cache of every page with fields. clean_post_cache() is
 * what a page-cache plugin listens for, but it is one call per page: on a large
 * site that made every save slow. It is now a choice, on by default.
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Cache\CacheManager;
use YamlCF\Tests\WPTestCase;

class SiteSaveCacheClearingTest extends WPTestCase {
	/** @var int[] Posts clean_post_cache() ran for */
	private $cleaned = [];

	public function setUp(): void {
		parent::setUp();
		add_action('clean_post_cache', [$this, 'recordCleaned']);
	}

	public function tearDown(): void {
		remove_action('clean_post_cache', [$this, 'recordCleaned']);
		remove_all_filters('yaml_cf_clear_post_caches_on_site_save');
		delete_option(CacheManager::CLEAR_ALL_POSTS_OPTION);
		parent::tearDown();
	}

	public function recordCleaned($post_id) {
		$this->cleaned[] = (int) $post_id;
	}

	private function givenTrackedPosts() {
		$ids = [];
		foreach (['one', 'two'] as $name) {
			$id = $this->createTestPost(['post_name' => $name], ['headline' => ucfirst($name)]);
			\YamlCF\Cache\PostTracker::track($id);
			$ids[] = $id;
		}
		$this->cleaned = [];

		return $ids;
	}

	public function testEveryPageIsClearedByDefault() {
		$ids = $this->givenTrackedPosts();

		(new CacheManager())->clearDataCaches();

		foreach ($ids as $id) {
			$this->assertContains($id, $this->cleaned);
		}
	}

	public function testSwitchedOffNoPageIsClearedAndTheSaveIsAnnounced() {
		$this->givenTrackedPosts();
		update_option(CacheManager::CLEAR_ALL_POSTS_OPTION, '0', false);
		$announced = did_action('yaml_cf_site_content_saved');

		(new CacheManager())->clearDataCaches();

		$this->assertSame([], $this->cleaned);
		$this->assertSame($announced + 1, did_action('yaml_cf_site_content_saved'));
	}

	/**
	 * Unticking the box on the Settings screen, on a site that never saved the
	 * choice. A plain false would not have been stored at all.
	 */
	public function testTheMainScreenCanSwitchItOff() {
		$this->actingAs($this->createTestUser('administrator'));
		$_POST = ['yaml_cf_clear_caches_nonce' => wp_create_nonce('yaml_cf_clear_caches')];

		$saved = \YamlCF\Core\Plugin::getInstance()->get('settings_controller')->saveSubmittedChoices();
		$_POST = [];
		\YamlCF\Core\Plugin::getInstance()->get('notification_manager')->clear();

		$this->assertTrue($saved);
		$this->assertFalse(CacheManager::clearsAllPostsOnSiteSave());
	}

	/**
	 * A single page's own save always clears that page, whatever the choice.
	 */
	public function testSavingOnePageStillClearsThatPage() {
		list($id) = $this->givenTrackedPosts();
		update_option(CacheManager::CLEAR_ALL_POSTS_OPTION, '0', false);

		(new CacheManager())->clearDataCaches($id);

		$this->assertSame([$id], array_values(array_unique($this->cleaned)));
	}

	public function testTheFilterHasTheLastWord() {
		$this->givenTrackedPosts();
		add_filter('yaml_cf_clear_post_caches_on_site_save', '__return_false');

		(new CacheManager())->clearDataCaches();

		$this->assertSame([], $this->cleaned);
	}

	/**
	 * Field values are read from the options, so a page shows new site details
	 * straight away even when its cache was not cleared.
	 */
	public function testValuesAreFreshEvenWithoutClearing() {
		update_option(CacheManager::CLEAR_ALL_POSTS_OPTION, '0', false);
		update_option('yaml_cf_global_data', ['phone' => 'Before'], false);
		$this->assertSame('Before', yaml_cf_get_global_field('phone'));

		update_option('yaml_cf_global_data', ['phone' => 'After'], false);
		(new CacheManager())->clearDataCaches();

		$this->assertSame('After', yaml_cf_get_global_field('phone'));
	}
}
