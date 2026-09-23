<?php
/**
 * Integration: Data Validation page (paginated, detects missing attachments)
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Tests\WPTestCase;

class ValidationPageTest extends WPTestCase {
	private function render($paged = 1) {
		$_GET['paged'] = (string) $paged;
		ob_start();
		try {
			\YamlCF\Core\Plugin::getInstance()->get('validation_controller')->render();
		} finally {
			$html = ob_get_clean();
			unset($_GET['paged']);
		}
		return $html;
	}

	private function createImportedPost($data) {
		$post_id = $this->createTestPost([], $data);
		update_post_meta($post_id, '_yaml_cf_imported', true);
		update_post_meta($post_id, '_yaml_cf_schema', ['fields' => [['name' => 'hero', 'type' => 'image']]]);
		return $post_id;
	}

	public function testDetectsMissingAttachments() {
		$this->actingAs($this->createTestUser('administrator'));
		$attachment_id = self::factory()->attachment->create();
		$this->createImportedPost(['hero' => (string) $attachment_id]);
		$this->createImportedPost(['hero' => '999999']);

		$html = $this->render();

		$this->assertStringContainsString('1 post has 1 missing image or file.', $html);
		$this->assertStringContainsString('ID 999999', $html);
	}

	public function testIsPaginated() {
		$this->actingAs($this->createTestUser('administrator'));
		for ($i = 0; $i < 55; $i++) {
			$this->createImportedPost(['hero' => '']);
		}

		$page1 = $this->render(1);
		$page2 = $this->render(2);

		$this->assertSame(50, substr_count($page1, 'class="yaml-cf-validation-row"'));
		$this->assertSame(5, substr_count($page2, 'class="yaml-cf-validation-row"'));
		$this->assertStringContainsString('Showing posts 1–50 of 55', $page1);
	}

	public function testRequiresManageOptions() {
		$this->actingAs($this->createTestUser('editor'));

		$this->expectException(\WPDieException::class);
		$this->render();
	}
}
