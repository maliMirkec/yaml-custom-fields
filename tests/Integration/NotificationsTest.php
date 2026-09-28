<?php
/**
 * Integration: queued notices (NotificationManager) and where they are raised
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Tests\WPTestCase;

class NotificationsTest extends WPTestCase {
	private function notifications() {
		return \YamlCF\Core\Plugin::getInstance()->get('notification_manager');
	}

	private function renderNotices() {
		ob_start();
		do_action('admin_notices');
		return ob_get_clean();
	}

	public function tearDown(): void {
		$_POST = [];
		$this->notifications()->clear();
		parent::tearDown();
	}

	public function testQueuedNoticesRenderOnceAndEscaped() {
		$this->actingAs($this->createTestUser('administrator'));
		$this->notifications()->success('Saved <b>ok</b>');
		$this->notifications()->error('Broken', ['title' => 'Not saved:', 'details' => ['Line 3']]);
		// Duplicates are not stacked
		$this->notifications()->success('Saved <b>ok</b>');

		$html = $this->renderNotices();

		$this->assertSame(1, substr_count($html, 'notice-success'));
		$this->assertStringContainsString('Saved &lt;b&gt;ok&lt;/b&gt;', $html);
		// The kind is said in words, not only shown by colour and icon; a
		// live-region role on markup present at page load would say nothing
		$this->assertStringContainsString('<span class="screen-reader-text">Error: </span>', $html);
		$this->assertStringContainsString('<span class="screen-reader-text">Done: </span>', $html);
		$this->assertStringNotContainsString('role=', $html);
		$this->assertStringContainsString('<strong>Not saved:</strong> Broken', $html);
		$this->assertStringContainsString('<li>Line 3</li>', $html);
		$this->assertSame('', $this->renderNotices(), 'Notices are shown only once');
	}

	/**
	 * The block editor does not show admin_notices, so "Import fields" on a
	 * block editor page used to report nothing at all. Queued notices go to the
	 * editor's own notice store instead, as text.
	 */
	public function testTheBlockEditorGetsQueuedNoticesAsText() {
		$this->actingAs($this->createTestUser('administrator'));
		$this->notifications()->error('The file has no content for this page.', ['title' => 'The import did not work.']);
		wp_scripts()->registered['wp-notices']->extra['after'] = [];

		do_action('enqueue_block_editor_assets');

		$script = implode("\n", (array) wp_scripts()->get_data('wp-notices', 'after'));
		$this->assertStringContainsString('core/notices', $script);
		$this->assertStringContainsString('"error"', $script);
		$this->assertStringContainsString('The import did not work. The file has no content for this page.', $script);
		$this->assertSame([], $this->notifications()->peek(), 'Handed over once, not printed again.');
	}

	public function testNoticesArePerUser() {
		$this->actingAs($this->createTestUser('administrator'));
		$this->notifications()->success('For the first admin');

		$this->actingAs($this->createTestUser('administrator'));
		$this->assertSame('', $this->renderNotices());
	}

	public function testInvalidDataObjectSchemaQueuesAnError() {
		$this->actingAs($this->createTestUser('administrator'));
		$_POST = wp_slash([
			'yaml_cf_save_data_object_type_nonce' => wp_create_nonce('yaml_cf_save_data_object_type'),
			'type_name' => 'Companies',
			'type_slug' => 'companies',
			'schema' => "fields:\n  - label: Missing name\n    type: string\n",
		]);

		\YamlCF\Core\Plugin::getInstance()->get('data_object_controller')->handleFormSubmissions();

		$html = $this->renderNotices();
		$this->assertStringContainsString('The list was not saved.', $html);
		$this->assertStringContainsString('needs a name', $html);
		$this->assertArrayNotHasKey('companies', (array) get_option('yaml_cf_data_object_types', []));
	}

	public function testFooterCreditOnlyOnPluginScreens() {
		$_GET['page'] = 'yaml-custom-fields';
		$this->assertStringContainsString('https://www.cita.hr', apply_filters('admin_footer_text', 'Default'));

		$_GET['page'] = 'some-other-plugin';
		$this->assertSame('Default', apply_filters('admin_footer_text', 'Default'));
		unset($_GET['page']);
	}
}
