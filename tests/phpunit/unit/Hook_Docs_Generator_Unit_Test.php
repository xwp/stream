<?php
/**
 * Unit tests for Stream_Hook_Docs_Generator.
 *
 * @package WP_Stream
 */

use PHPUnit\Framework\TestCase;

/**
 * Hook docs generator tests.
 */
class Hook_Docs_Generator_Unit_Test extends TestCase {

	/**
	 * Generator instance.
	 *
	 * @var Stream_Hook_Docs_Generator
	 */
	private Stream_Hook_Docs_Generator $generator;

	/**
	 * Plugin root path.
	 *
	 * @var string
	 */
	private string $root;

	/**
	 * Setup.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->root     = dirname( __DIR__, 3 );
		$generator_file = $this->root . '/local/scripts/class-hook-docs-generator.php';
		require_once $generator_file;
		$this->generator = new Stream_Hook_Docs_Generator( $this->root );
	}

	/**
	 * Extract calls from the fixture file.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function fixture_calls(): array {
		$absolute = $this->root . '/tests/phpunit/unit/fixtures/hook-docs-sample.php';
		$relative = 'tests/phpunit/unit/fixtures/hook-docs-sample.php';
		return $this->generator->extract_from_file( $absolute, $relative );
	}

	/**
	 * Find a call by hook name.
	 *
	 * @param string $name Hook name.
	 * @return array<string, mixed>|null
	 */
	private function find_call( string $name ): ?array {
		foreach ( $this->fixture_calls() as $call ) {
			if ( $call['name'] === $name ) {
				return $call;
			}
		}
		return null;
	}

	/**
	 * Static hook name and summary are parsed.
	 */
	public function test_static_hook_with_docblock(): void {
		$call = $this->find_call( 'wp_stream_static_example' );
		$this->assertNotNull( $call );
		$this->assertSame( 'filter', $call['type'] );
		$this->assertStringContainsString( 'static example', $call['summary'] );
		$this->assertNotEmpty( $call['params'] );
	}

	/**
	 * Dynamic hook names are reconstructed.
	 */
	public function test_dynamic_hook_name(): void {
		$call = $this->find_call( 'wp_stream_action_links_{connector}' );
		$this->assertNotNull( $call );
	}

	/**
	 * Undocumented hooks still appear.
	 */
	public function test_undocumented_hook(): void {
		$call = $this->find_call( 'wp_stream_undocumented' );
		$this->assertNotNull( $call );
		$this->assertSame( '', $call['summary'] );
	}

	/**
	 * Deprecated filters are detected.
	 */
	public function test_deprecated_filter(): void {
		$call = $this->find_call( 'wp_stream_register_column_defaults' );
		$this->assertNotNull( $call );
		$this->assertSame( 'apply_filters_deprecated', $call['function'] );
	}

	/**
	 * External hooks are extracted.
	 */
	public function test_external_hook(): void {
		$call = $this->find_call( 'sidebars_widgets' );
		$this->assertNotNull( $call );
		$this->assertTrue( $call['is_pointer'] );
	}

	/**
	 * Markdown output includes Stream and external sections.
	 */
	public function test_render_markdown_sections(): void {
		$markdown = $this->generator->render_markdown( $this->fixture_calls() );
		$this->assertStringContainsString( '## Stream hooks', $markdown );
		$this->assertStringContainsString( '## External hooks invoked', $markdown );
		$this->assertStringContainsString( 'wp_stream_static_example', $markdown );
		$this->assertStringContainsString( 'sidebars_widgets', $markdown );
	}
}
