<?php
/**
 * Integration: plugin boots inside WordPress
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Tests\WPTestCase;

class PluginBootTest extends WPTestCase {
	public function testPluginAndArchitectureLoad() {
		$this->assertTrue(class_exists('YAML_Custom_Fields'));
		$this->assertTrue(function_exists('ycf_get_field'));
		$this->assertInstanceOf(\YamlCF\Form\DataSanitizer::class, \YamlCF\Core\Plugin::getInstance()->get('data_sanitizer'));
	}

	public function testAjaxActionsAreRegisteredByHookManager() {
		foreach (['yaml_cf_import_page_data', 'yaml_cf_get_posts_with_data', 'yaml_cf_save_template_settings', 'yaml_cf_refresh_templates'] as $action) {
			$this->assertNotFalse(has_action('wp_ajax_' . $action), $action);
		}
	}
}
