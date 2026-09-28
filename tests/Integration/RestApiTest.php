<?php
/**
 * Integration: field content over the REST API
 *
 * Until 2.0.0 none of this existed: no register_meta(), no routes, so a headless
 * front end or another plugin could not read a single value.
 *
 * The write path is the part worth watching. This plugin's safety rests on
 * schema-aware sanitising at a small number of write points, and adding an HTTP
 * one multiplies that. These tests pin that a write over REST is sanitised the
 * same way the editor sanitises, needs the same permission, and cannot be used
 * to reach a page the caller may not edit.
 *
 * @package YamlCF
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Tests\FailsOnPluginWarnings;
use YamlCF\Tests\WPTestCase;

class RestApiTest extends WPTestCase {
	use FailsOnPluginWarnings;

	const TEMPLATE = 'single.php';
	const COLLECTION = 'people';

	const SCHEMA = "fields:\n"
		. "  - name: headline\n    label: Headline\n    type: string\n"
		. "  - name: body\n    label: Body\n    type: rich-text\n"
		. "  - name: embed\n    label: Embed\n    type: code\n";

	/** @var \WP_REST_Server */
	private $server;

	public function setUp(): void {
		parent::setUp();
		$this->failOnPluginWarnings();

		update_option('yaml_cf_schemas', [self::TEMPLATE => self::SCHEMA], false);
		update_option('yaml_cf_template_settings', [self::TEMPLATE => true], false);

		// The site details and collection tests below are about what is returned,
		// so reading is made public for them; the default is tested on its own
		update_option(\YamlCF\Rest\RestApi::PUBLIC_READ_OPTION, '1', false);

		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		$this->server = $wp_rest_server;
		do_action('rest_api_init');
	}

	public function tearDown(): void {
		global $wp_rest_server;
		$wp_rest_server = null;
		remove_all_filters('yaml_cf_rest_public_read');
		$this->stopFailingOnPluginWarnings();
		parent::tearDown();
	}

	private function get($route, array $params = []) {
		$request = new \WP_REST_Request('GET', $route);
		foreach ($params as $key => $value) {
			$request->set_param($key, $value);
		}

		return $this->server->dispatch($request);
	}

	private function write($post_id, array $fields) {
		$request = new \WP_REST_Request('POST', '/wp/v2/posts/' . $post_id);
		$request->set_body_params(['yaml_cf' => $fields]);

		return $this->server->dispatch($request);
	}

	// ------------------------------------------------------- reading a post

	public function testAPostCarriesItsFieldValues() {
		$post_id = $this->createTestPost([], ['headline' => 'Hello']);

		$response = $this->get('/wp/v2/posts/' . $post_id);

		$this->assertSame(200, $response->get_status());
		$this->assertSame('Hello', $response->get_data()['yaml_cf']['headline']);
	}

	/**
	 * A code field is stored encoded; the API must hand back the real thing.
	 */
	public function testCodeFieldsComeBackDecoded() {
		$post_id = $this->createTestPost([], [
			'embed' => \YAML_Custom_Fields::CODE_FIELD_MARKER . base64_encode('<b>x</b>'),
		]);

		$data = $this->get('/wp/v2/posts/' . $post_id)->get_data();

		$this->assertSame('<b>x</b>', $data['yaml_cf']['embed']);
	}

	/**
	 * Shared values and site details are resolved the same way a theme sees
	 * them, so a headless front end does not have to re-implement the rules.
	 */
	public function testValuesAreResolvedNotRaw() {
		update_option('yaml_cf_template_global_schemas', [self::TEMPLATE => self::SCHEMA], false);
		update_option('yaml_cf_template_global_data', [self::TEMPLATE => ['headline' => 'Shared']], false);

		$post_id = $this->createTestPost([], ['headline' => 'Own']);
		update_post_meta($post_id, '_yaml_cf_use_template_global_fields', ['headline' => '1']);

		$data = $this->get('/wp/v2/posts/' . $post_id)->get_data();

		$this->assertSame('Shared', $data['yaml_cf']['headline']);
	}

	/**
	 * A draft is not readable without logging in, and the field must not be a
	 * way around that. WordPress decides before a field callback runs; this
	 * pins it so a future change cannot quietly open a hole.
	 */
	public function testADraftIsNotReadableWithoutPermission() {
		$post_id = $this->createTestPost(['post_status' => 'draft'], ['headline' => 'Secret']);

		$response = $this->get('/wp/v2/posts/' . $post_id);

		$this->assertNotSame(200, $response->get_status());
		$this->assertStringNotContainsString('Secret', wp_json_encode($response->get_data()));
	}

	/**
	 * WordPress blanks a protected post's content for anyone without the
	 * password. The fields are content too.
	 */
	public function testAPasswordProtectedPostHidesItsFields() {
		wp_set_current_user(0);
		$post_id = $this->createTestPost(['post_password' => 'open sesame'], ['headline' => 'Behind the password']);

		$data = $this->get('/wp/v2/posts/' . $post_id)->get_data();

		$this->assertSame([], $data['yaml_cf']);
		$this->assertStringNotContainsString('Behind the password', wp_json_encode($data));
	}

	public function testTheRightPasswordShowsTheFields() {
		wp_set_current_user(0);
		$post_id = $this->createTestPost(['post_password' => 'open sesame'], ['headline' => 'Behind the password']);

		$data = $this->get('/wp/v2/posts/' . $post_id, ['password' => 'open sesame'])->get_data();

		$this->assertSame('Behind the password', $data['yaml_cf']['headline']);
	}

	// ------------------------------------------------------- writing a post

	/**
	 * A client that reads a post, changes one field and sends the whole object
	 * back must not copy site details or shared values into the page, where
	 * they would stop following later changes.
	 */
	public function testSendingBackWhatWasReadDoesNotFreezeInheritedValues() {
		update_option('yaml_cf_global_schema', "fields:\n  - name: phone\n    type: string\n", false);
		update_option('yaml_cf_global_data', ['phone' => '555-0100'], false);
		update_option('yaml_cf_template_settings', [self::TEMPLATE => true, self::TEMPLATE . '_use_global' => true], false);
		update_option('yaml_cf_template_global_schemas', [self::TEMPLATE => self::SCHEMA], false);
		update_option('yaml_cf_template_global_data', [self::TEMPLATE => ['body' => 'Shared body']], false);

		wp_set_current_user($this->createTestUser('administrator'));
		$post_id = $this->createTestPost([], ['headline' => 'Before']);

		$fields = $this->get('/wp/v2/posts/' . $post_id)->get_data()['yaml_cf'];
		$this->assertSame('555-0100', $fields['phone']);
		$this->assertSame('Shared body', $fields['body']);

		$fields['headline'] = 'After';
		$this->assertSame(200, $this->write($post_id, $fields)->get_status());

		$stored = get_post_meta($post_id, '_yaml_cf_data', true);
		$this->assertSame('After', $stored['headline']);
		$this->assertArrayNotHasKey('phone', $stored, 'A site detail is not a field of this page.');
		$this->assertArrayNotHasKey('body', $stored, 'A shared value sent back unchanged is not the page\'s own.');

		update_option('yaml_cf_global_data', ['phone' => '555-0199'], false);
		$this->assertSame('555-0199', yaml_cf_get_field('phone', $post_id));
	}

	/**
	 * A page's fields are as public as the page, but site details are not: the
	 * site details route is closed by default. A template that uses them must not
	 * hand every site detail to anyone who can read one of its pages.
	 */
	public function testAPostDoesNotRevealSiteDetailsToAVisitorWhenTheyAreClosed() {
		delete_option(\YamlCF\Rest\RestApi::PUBLIC_READ_OPTION);
		update_option('yaml_cf_global_schema', "fields:\n  - name: internal_email\n    type: string\n  - name: headline\n    type: string\n", false);
		update_option('yaml_cf_global_data', ['internal_email' => 'ops@example.org', 'headline' => 'Site wide'], false);
		update_option('yaml_cf_template_settings', [self::TEMPLATE => true, self::TEMPLATE . '_use_global' => true], false);
		$post_id = $this->createTestPost([], ['headline' => 'The page\'s own']);
		wp_set_current_user(0);

		$fields = $this->get('/wp/v2/posts/' . $post_id)->get_data()['yaml_cf'];

		$this->assertSame('The page\'s own', $fields['headline'], 'A name the page fills in is the page\'s value.');
		$this->assertArrayNotHasKey('internal_email', $fields);
	}

	public function testAPostCarriesSiteDetailsWhenTheyArePublic() {
		update_option('yaml_cf_global_data', ['internal_email' => 'ops@example.org'], false);
		update_option('yaml_cf_template_settings', [self::TEMPLATE => true, self::TEMPLATE . '_use_global' => true], false);
		$post_id = $this->createTestPost([], ['headline' => 'Hello']);
		wp_set_current_user(0);

		$fields = $this->get('/wp/v2/posts/' . $post_id)->get_data()['yaml_cf'];

		$this->assertSame('ops@example.org', $fields['internal_email']);
	}

	public function testAWriterStillSeesSiteDetailsInAPost() {
		delete_option(\YamlCF\Rest\RestApi::PUBLIC_READ_OPTION);
		update_option('yaml_cf_global_data', ['internal_email' => 'ops@example.org'], false);
		update_option('yaml_cf_template_settings', [self::TEMPLATE => true, self::TEMPLATE . '_use_global' => true], false);
		$post_id = $this->createTestPost([], ['headline' => 'Hello']);
		wp_set_current_user($this->createTestUser('author'));

		$fields = $this->get('/wp/v2/posts/' . $post_id)->get_data()['yaml_cf'];

		$this->assertSame('ops@example.org', $fields['internal_email']);
	}

	/**
	 * A field may be named with digits only ("2024"). PHP keys it as an integer,
	 * and array_merge() renumbered it to 0 on every partial update.
	 */
	public function testAPartialUpdateKeepsAFieldNamedWithDigits() {
		update_option('yaml_cf_schemas', [self::TEMPLATE => self::SCHEMA . "  - name: \"2024\"\n    type: string\n"], false);
		wp_set_current_user($this->createTestUser('administrator'));
		$post_id = $this->createTestPost([], ['headline' => 'Before', '2024' => 'The year in review']);

		$this->assertSame(200, $this->write($post_id, ['headline' => 'After'])->get_status());

		$stored = get_post_meta($post_id, '_yaml_cf_data', true);
		$this->assertSame('After', $stored['headline']);
		$this->assertSame('The year in review', $stored['2024'] ?? null);
		$this->assertArrayNotHasKey(0, $stored);
	}

	public function testAnEditorCanWriteFields() {
		wp_set_current_user($this->createTestUser('administrator'));
		$post_id = $this->createTestPost([], ['headline' => 'Before']);

		$response = $this->write($post_id, ['headline' => 'After']);

		$this->assertSame(200, $response->get_status());
		$this->assertSame('After', yaml_cf_get_field('headline', $post_id));
	}

	public function testWritingIsRefusedWithoutPermission() {
		wp_set_current_user(0);
		$post_id = $this->createTestPost([], ['headline' => 'Before']);

		$this->write($post_id, ['headline' => 'After']);

		$this->assertSame('Before', yaml_cf_get_field('headline', $post_id), 'An anonymous request must not change field content.');
	}

	public function testAContributorCannotWriteToSomebodyElsesPost() {
		$owner = $this->createTestUser('administrator');
		$post_id = $this->createTestPost(['post_author' => $owner], ['headline' => 'Before']);

		wp_set_current_user($this->createTestUser('contributor'));
		$this->write($post_id, ['headline' => 'After']);

		$this->assertSame('Before', yaml_cf_get_field('headline', $post_id));
	}

	/**
	 * The same sanitising as the editor. A writer without unfiltered_html must
	 * not be able to put a script into a rich-text field over HTTP either.
	 */
	public function testWrittenValuesAreSanitisedLikeTheEditor() {
		wp_set_current_user($this->createTestUser('editor'));
		$post_id = $this->createTestPost([], []);

		$this->write($post_id, ['body' => '<p>Keep</p><script>drop()</script>']);

		$body = yaml_cf_get_field('body', $post_id);
		$this->assertStringContainsString('<p>Keep</p>', $body);
		$this->assertStringNotContainsString('<script', $body);
	}

	/**
	 * A request changes the fields it names and leaves the rest alone. The
	 * editor's form is different: it renders every field, so anything missing
	 * from it really has been cleared. Over HTTP a caller sends the one field it
	 * cares about, and treating the others as deleted would empty a page.
	 */
	public function testWritingOnlyChangesTheFieldsItNames() {
		wp_set_current_user($this->createTestUser('administrator'));
		$post_id = $this->createTestPost([], ['headline' => 'Kept', 'gone_from_schema' => 'Also kept']);

		$this->write($post_id, ['body' => 'New body']);

		$stored = get_post_meta($post_id, '_yaml_cf_data', true);
		$this->assertSame('New body', $stored['body']);
		$this->assertSame('Kept', $stored['headline'], 'A field the request did not mention must not be cleared.');
		$this->assertSame('Also kept', $stored['gone_from_schema']);
	}

	/**
	 * Naming a field with an empty value does clear it, which is how a caller
	 * empties one deliberately.
	 */
	public function testWritingAnEmptyValueClearsThatField() {
		wp_set_current_user($this->createTestUser('administrator'));
		$post_id = $this->createTestPost([], ['headline' => 'Before']);

		$this->write($post_id, ['headline' => '']);

		$this->assertSame('', get_post_meta($post_id, '_yaml_cf_data', true)['headline']);
	}

	public function testWritingSomethingThatIsNotAnObjectIsRefused() {
		wp_set_current_user($this->createTestUser('administrator'));
		$post_id = $this->createTestPost([], ['headline' => 'Before']);

		$request = new \WP_REST_Request('POST', '/wp/v2/posts/' . $post_id);
		$request->set_body_params(['yaml_cf' => 'not an object']);
		$this->server->dispatch($request);

		$this->assertSame('Before', yaml_cf_get_field('headline', $post_id));
	}

	// --------------------------------------------------------- site details

	public function testSiteDetailsAreReadable() {
		update_option('yaml_cf_global_schema', self::SCHEMA, false);
		update_option('yaml_cf_global_data', ['headline' => 'Site wide'], false);

		$response = $this->get('/yaml-cf/v1/site-details');

		$this->assertSame(200, $response->get_status());
		$this->assertSame('Site wide', $response->get_data()['headline']);
	}

	/**
	 * Not public until an administrator says so: site details and collections
	 * can hold things no page shows
	 */
	public function testSiteWideRoutesAreClosedByDefault() {
		delete_option(\YamlCF\Rest\RestApi::PUBLIC_READ_OPTION);
		update_option('yaml_cf_global_data', ['headline' => 'Site wide'], false);
		update_option('yaml_cf_data_object_types', ['people' => ['name' => 'People', 'schema' => "fields:\n  - name: name\n    type: string\n"]], false);
		wp_set_current_user(0);

		$this->assertSame(401, $this->get('/yaml-cf/v1/site-details')->get_status());
		$this->assertSame(401, $this->get('/yaml-cf/v1/collections/people')->get_status());
	}

	public function testTheMainScreenChoiceOpensThem() {
		update_option('yaml_cf_global_data', ['headline' => 'Site wide'], false);
		wp_set_current_user(0);

		update_option(\YamlCF\Rest\RestApi::PUBLIC_READ_OPTION, '0', false);
		$this->assertSame(401, $this->get('/yaml-cf/v1/site-details')->get_status());

		update_option(\YamlCF\Rest\RestApi::PUBLIC_READ_OPTION, '1', false);
		$this->assertSame(200, $this->get('/yaml-cf/v1/site-details')->get_status());
	}

	/**
	 * Code wins over the setting, both ways
	 */
	public function testTheFilterCanOpenThemWhateverTheSetting() {
		delete_option(\YamlCF\Rest\RestApi::PUBLIC_READ_OPTION);
		update_option('yaml_cf_global_data', ['headline' => 'Site wide'], false);
		add_filter('yaml_cf_rest_public_read', '__return_true');
		wp_set_current_user(0);

		$this->assertSame(200, $this->get('/yaml-cf/v1/site-details')->get_status());
	}

	/**
	 * The box on the main screen saves the choice, both ways
	 */
	public function testTheMainScreenFormSavesTheChoice() {
		$this->actingAs($this->createTestUser('administrator'));
		$controller = \YamlCF\Core\Plugin::getInstance()->get('template_schema_controller');
		delete_option(\YamlCF\Rest\RestApi::PUBLIC_READ_OPTION);

		$_POST = ['yaml_cf_rest_access_nonce' => wp_create_nonce('yaml_cf_rest_access'), 'yaml_cf_rest_public_read' => '1'];
		$this->assertTrue($controller->saveSubmittedChoices());
		$this->assertTrue(\YamlCF\Rest\RestApi::publicReadEnabled());

		$_POST = ['yaml_cf_rest_access_nonce' => wp_create_nonce('yaml_cf_rest_access')];
		$controller->saveSubmittedChoices();
		$this->assertFalse(\YamlCF\Rest\RestApi::publicReadEnabled());

		$_POST = [];
		\YamlCF\Core\Plugin::getInstance()->get('notification_manager')->clear();
	}

	public function testTheFilterCanCloseTheSiteWideRoutes() {
		update_option('yaml_cf_global_data', ['headline' => 'Site wide'], false);
		add_filter('yaml_cf_rest_public_read', '__return_false');
		wp_set_current_user(0);

		$response = $this->get('/yaml-cf/v1/site-details');

		$this->assertSame(401, $response->get_status());
	}

	public function testClosingTheRoutesStillAllowsAWriter() {
		update_option('yaml_cf_global_data', ['headline' => 'Site wide'], false);
		add_filter('yaml_cf_rest_public_read', '__return_false');
		wp_set_current_user($this->createTestUser('editor'));

		$this->assertSame(200, $this->get('/yaml-cf/v1/site-details')->get_status());
	}

	// ----------------------------------------------------------- collections

	private function givenCollection($count) {
		update_option('yaml_cf_data_object_types', [
			self::COLLECTION => ['name' => 'People', 'schema' => "fields:\n  - name: name\n    type: string\n"],
		], false);

		$repository = new \YamlCF\Data\Repositories\DataObjectRepository();
		for ($i = 1; $i <= $count; $i++) {
			$repository->saveEntry(self::COLLECTION, 'entry_' . $i, ['name' => 'Person ' . $i], 'name');
		}
	}

	public function testACollectionIsReadableAndPaged() {
		$this->givenCollection(120);

		$response = $this->get('/yaml-cf/v1/collections/' . self::COLLECTION, ['page' => 2, 'per_page' => 50]);
		$data = $response->get_data();

		$this->assertSame(200, $response->get_status());
		$this->assertCount(50, $data['items']);
		$this->assertSame(120, $data['total']);
		$this->assertSame(3, $data['total_pages']);
		$this->assertSame('120', $response->get_headers()['X-WP-Total']);
	}

	/**
	 * Code fields are stored encoded; the API hands out the code, as the
	 * template functions do
	 */
	public function testACollectionsCodeFieldsAreDecoded() {
		update_option('yaml_cf_data_object_types', [
			self::COLLECTION => ['name' => 'People', 'schema' => "fields:\n  - name: name\n    type: string\n  - name: embed\n    type: code\n"],
		], false);
		(new \YamlCF\Data\Repositories\DataObjectRepository())->saveEntry(self::COLLECTION, 'entry_1', [
			'name' => 'Ada',
			'embed' => '__YAMLCF_B64__' . base64_encode('<div>x</div>'),
		], 'name');

		$data = $this->get('/yaml-cf/v1/collections/' . self::COLLECTION)->get_data();

		$this->assertSame('<div>x</div>', $data['items']['entry_1']['embed']);
	}

	public function testACollectionCanBeSearched() {
		$this->givenCollection(30);

		$data = $this->get('/yaml-cf/v1/collections/' . self::COLLECTION, ['search' => 'Person 7'])->get_data();

		$this->assertArrayHasKey('entry_7', $data['items']);
		$this->assertCount(1, $data['items']);
	}

	public function testSearchResultsArePaged() {
		$this->givenCollection(30);

		$response = $this->get('/yaml-cf/v1/collections/' . self::COLLECTION, ['search' => 'Person', 'per_page' => 10, 'page' => 3]);
		$data = $response->get_data();

		$this->assertCount(10, $data['items']);
		$this->assertSame(30, $data['total']);
		$this->assertSame(3, $data['page']);
		$this->assertSame(3, $data['total_pages']);
		$this->assertArrayHasKey('entry_21', $data['items']);
	}

	public function testAskingForACollectionThatDoesNotExistIsA404() {
		$response = $this->get('/yaml-cf/v1/collections/nothing_here');

		$this->assertSame(404, $response->get_status());
	}

	/**
	 * A page size is a promise about how much work one request can ask for.
	 */
	public function testPerPageIsCapped() {
		$this->givenCollection(150);

		$response = $this->get('/yaml-cf/v1/collections/' . self::COLLECTION, ['per_page' => 5000]);
		$data = $response->get_data();

		$this->assertLessThanOrEqual(
			\YamlCF\Rest\RestApi::MAX_PER_PAGE,
			count($data['items']),
			'An unbounded page size must not be honoured.'
		);
	}
}
