<?php
namespace WP_Stream;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

class List_Table_Column_Renderer_Unit_Test extends TestCase {
	/**
	 * Renderer under test.
	 *
	 * @var List_Table_Column_Renderer
	 */
	protected $renderer;

	/**
	 * Plugin mock with admin + connectors stubs.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	protected function set_up() {
		parent::set_up();
		$this->stubTranslationFunctions();
		$this->stubEscapeFunctions();

		$this->plugin             = Mockery::mock( Plugin::class );
		$this->plugin->admin      = Mockery::mock( Admin::class );
		$this->plugin->connectors = Mockery::mock( Connectors::class );

		$this->plugin->admin->records_page_slug = 'wp_stream';
		$this->plugin->admin->admin_parent_page = 'admin.php';

		$this->plugin->connectors->term_labels = array(
			'stream_action'    => array(
				'updated' => 'Updated',
			),
			'stream_connector' => array(
				'posts' => 'Posts',
			),
			'stream_context'   => array(
				'post' => 'Posts',
			),
		);

		Functions\when( 'self_admin_url' )->justReturn( 'http://example.com/wp-admin/admin.php' );
		Functions\when( 'add_query_arg' )->alias( array( self::class, 'add_query_arg_stub' ) );
		Functions\when( 'wp_stream_get_iso_8601_extended_date' )->justReturn( '2020-01-15T12:00:00+00:00' );
		Functions\when( 'get_date_from_gmt' )->alias( array( self::class, 'get_date_from_gmt_stub' ) );
		Functions\when( 'apply_filters_deprecated' )->justReturn( array() );

		$this->renderer = new List_Table_Column_Renderer( $this->plugin );
	}

	/**
	 * Minimal add_query_arg stand-in that appends query keys.
	 *
	 * @param mixed ...$args add_query_arg argument list.
	 * @return string
	 */
	public static function add_query_arg_stub( ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			return 'http://example.com/wp-admin/admin.php?' . http_build_query( $args[0] );
		}

		if ( 2 === count( $args ) && is_array( $args[0] ) ) {
			$url   = (string) $args[1];
			$query = http_build_query( $args[0] );
			return false === strpos( $url, '?' ) ? $url . '?' . $query : $url . '&' . $query;
		}

		if ( 3 === count( $args ) ) {
			$key   = (string) $args[0];
			$value = (string) $args[1];
			$url   = (string) $args[2];
			$pair  = rawurlencode( $key ) . '=' . rawurlencode( $value );
			return false === strpos( $url, '?' ) ? $url . '?' . $pair : $url . '&' . $pair;
		}

		return 'http://example.com/wp-admin/admin.php';
	}

	/**
	 * get_date_from_gmt stand-in keyed off the requested format.
	 *
	 * @param string $date   GMT date string (unused; fixture is fixed).
	 * @param string $format PHP date format.
	 * @return string
	 */
	public static function get_date_from_gmt_stub( $date, $format = '' ) {
		unset( $date );

		if ( 'Y/m/d' === $format ) {
			return '2020/01/15';
		}

		if ( 'h:i:s A T' === $format ) {
			return '12:00:00 PM UTC';
		}

		return '';
	}

	/**
	 * Build a minimal record object for the renderer.
	 *
	 * @param array $overrides Property overrides.
	 * @return stdClass
	 */
	private function make_item( array $overrides = array() ) {
		return (object) array_merge(
			array(
				'ID'        => 1,
				'created'   => '2020-01-15 12:00:00',
				'site_id'   => 1,
				'blog_id'   => 1,
				'object_id' => 0,
				'user_id'   => 1,
				'user_role' => 'administrator',
				'summary'   => 'Updated "Hello World"',
				'connector' => 'posts',
				'context'   => 'post',
				'action'    => 'updated',
				'ip'        => '127.0.0.1',
				'meta'      => array(),
			),
			$overrides
		);
	}

	/**
	 * Test get_term_title returns label when present and falls back to term.
	 */
	public function test_get_term_title_label_and_fallback() {
		$this->assertSame( 'Updated', $this->renderer->get_term_title( 'updated', 'action' ) );
		$this->assertSame( 'missing', $this->renderer->get_term_title( 'missing', 'action' ) );
	}

	/**
	 * Test column_link builds a filter URL with page and key/value.
	 */
	public function test_column_link_builds_filter_url() {
		$html = $this->renderer->column_link( '127.0.0.1', 'ip', '127.0.0.1', 'Filter by IP' );

		$this->assertStringContainsString( 'page=wp_stream', $html );
		$this->assertStringContainsString( 'ip=127.0.0.1', $html );
		$this->assertStringContainsString( 'title="Filter by IP"', $html );
		$this->assertStringContainsString( '>127.0.0.1</a>', $html );
	}

	/**
	 * Test action column returns labeled filter link HTML.
	 */
	public function test_render_action_column_returns_labeled_link() {
		$html = $this->renderer->render( $this->make_item(), 'action' );

		$this->assertStringContainsString( 'Updated', $html );
		$this->assertStringContainsString( 'action=updated', $html );
		$this->assertStringContainsString( '<a href=', $html );
	}

	/**
	 * Test IP column returns a filter link for the IP value.
	 */
	public function test_render_ip_column_returns_filter_link() {
		$html = $this->renderer->render( $this->make_item(), 'ip' );

		$this->assertStringContainsString( '127.0.0.1', $html );
		$this->assertStringContainsString( 'ip=127.0.0.1', $html );
	}

	/**
	 * Test get_action_links wraps connector action links in row-actions markup.
	 */
	public function test_get_action_links_wraps_row_actions() {
		$record = new Record( $this->make_item() );

		Filters\expectApplied( 'wp_stream_action_links_posts' )
			->once()
			->andReturn( array( 'Edit' => 'http://example.com/edit' ) );
		Filters\expectApplied( 'wp_stream_custom_action_links_posts' )
			->once()
			->andReturn( array() );

		$html = $this->renderer->get_action_links( $record );

		$this->assertStringContainsString( 'row-actions', $html );
		$this->assertStringContainsString( 'action-link', $html );
		$this->assertStringContainsString( 'Edit', $html );
		$this->assertStringContainsString( 'http://example.com/edit', $html );
	}

	/**
	 * Test summary column with and without an object_id filter link.
	 *
	 * @param array $overrides Item property overrides.
	 * @param bool  $expect_object_filter Whether the object-id dashicon link is expected.
	 */
	#[DataProvider( 'data_render_summary_column' )]
	public function test_render_summary_column( $overrides, $expect_object_filter ) {
		$html = $this->renderer->render( $this->make_item( $overrides ), 'summary' );

		$this->assertStringContainsString( 'Updated "Hello World"', $html );

		if ( $expect_object_filter ) {
			$this->assertStringContainsString( 'stream-filter-object-id', $html );
			$this->assertStringContainsString( 'object_id=42', $html );
			$this->assertStringContainsString( 'context=post', $html );
		} else {
			$this->assertStringNotContainsString( 'stream-filter-object-id', $html );
		}
	}

	/**
	 * Data provider for summary column object_id branches.
	 *
	 * @return array<string, array{0: array, 1: bool}>
	 */
	public static function data_render_summary_column() {
		return array(
			'without_object_id' => array(
				array(),
				false,
			),
			'with_object_id'    => array(
				array(
					'object_id' => 42,
					'context'   => 'post',
				),
				true,
			),
		);
	}

	/**
	 * Test date column emits time element, filter link, and local time line.
	 */
	public function test_render_date_column_outputs_time_and_filter_link() {
		$html = $this->renderer->render( $this->make_item(), 'date' );

		$this->assertStringContainsString( '<time', $html );
		$this->assertStringContainsString( 'datetime="2020-01-15T12:00:00+00:00"', $html );
		$this->assertStringContainsString( 'relative-time record-created', $html );
		$this->assertTrue(
			false !== strpos( $html, 'date=2020/01/15' ) || false !== strpos( $html, 'date=2020%2F01%2F15' ),
			'Date filter URL should include date=2020/01/15 (raw or encoded)'
		);
		$this->assertStringContainsString( '<br />', $html );
		$this->assertStringContainsString( '12:00:00 PM UTC', $html );
	}

	/**
	 * Test context column nests connector and context filter links.
	 */
	public function test_render_context_column_nests_connector_and_context_links() {
		$html = $this->renderer->render( $this->make_item(), 'context' );

		$this->assertStringContainsString( 'Posts', $html );
		$this->assertStringContainsString( 'connector=posts', $html );
		$this->assertStringContainsString( 'context=post', $html );
		$this->assertTrue(
			false !== strpos( $html, '&#8627;' ) || false !== strpos( $html, '↳' ),
			'Context column should include the nested arrow marker'
		);
	}

	/**
	 * Test default column applies the insert-column filter for custom columns.
	 */
	public function test_render_default_column_applies_insert_filter() {
		$custom = '<span class="custom">x</span>';

		Filters\expectApplied( 'wp_stream_insert_column_default_custom_col' )
			->once()
			->andReturn( $custom );

		$html = $this->renderer->render( $this->make_item(), 'custom_col' );

		$this->assertSame( $custom, $html );
	}
}
