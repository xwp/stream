<?php
/**
 * Tests for the Form_Generator native select rendering.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

use Brain\Monkey\Functions;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Class Form_Generator_Unit_Test
 */
class Form_Generator_Unit_Test extends TestCase {

	/**
	 * Generator under test.
	 *
	 * @var Form_Generator
	 */
	protected $generator;

	/**
	 * Stand-in for WordPress selected().
	 *
	 * @param mixed $selected Value to check.
	 * @param mixed $current  Comparison value.
	 * @param bool  $echo     Unused.
	 * @return string
	 */
	public static function selected_stub( $selected, $current = true, $echo = true ) {
		unset( $echo );
		return ( (string) $selected === (string) $current ) ? ' selected="selected"' : '';
	}

	/**
	 * Stand-in for WordPress wp_parse_args().
	 *
	 * @param array $args     User values.
	 * @param array $defaults Default values.
	 * @return array
	 */
	public static function wp_parse_args_stub( $args, $defaults = array() ) {
		if ( is_object( $args ) ) {
			$args = get_object_vars( $args );
		}
		return array_merge( $defaults, (array) $args );
	}

	protected function set_up() {
		parent::set_up();

		$this->stubTranslationFunctions();
		$this->stubEscapeFunctions();
		Functions\when( 'selected' )->alias( array( self::class, 'selected_stub' ) );
		Functions\when( 'wp_parse_args' )->alias( array( self::class, 'wp_parse_args_stub' ) );

		$this->generator = new Form_Generator();
	}

	/**
	 * Render a grouped_select field.
	 *
	 * @param string|array $value   Current value(s).
	 * @param array        $options Option tree.
	 * @param array        $extra   Extra args (placeholder, multiple).
	 * @return string HTML.
	 */
	private function render_select( $value, array $options, array $extra = array() ) {
		$data = array();
		if ( ! empty( $extra['placeholder'] ) ) {
			$data['placeholder'] = $extra['placeholder'];
		}

		return $this->generator->render_field(
			'grouped_select',
			array(
				'name'    => 'test_select',
				'value'   => $value,
				'options' => $options,
				'classes' => 'test-class',
				'data'    => $data,
			),
			false
		);
	}

	public function test_parent_and_children_render_as_flat_options() {
		$html = $this->render_select(
			'',
			array(
				array(
					'value'    => 'posts',
					'text'     => 'Posts',
					'children' => array(
						array(
							'value' => 'post',
							'text'  => 'Single Post',
						),
					),
				),
			)
		);

		$this->assertStringContainsString( '<option class="parent" value="posts"', $html );
		$this->assertStringContainsString( '<option class="child" value="post"', $html );
		// The previous markup kept parent and children flat (no optgroup).
		$this->assertStringNotContainsString( '<optgroup', $html );
	}

	public function test_valueless_groups_render_as_optgroups() {
		$html = $this->render_select(
			'',
			array(
				array(
					'text'     => 'Roles',
					'children' => array(
						array(
							'value' => 'administrator',
							'text'  => 'Administrator',
						),
					),
				),
			)
		);

		$this->assertStringContainsString( '<optgroup label="Roles">', $html );
		$this->assertStringContainsString( '<option value="administrator"', $html );
		$this->assertStringContainsString( '</optgroup>', $html );
		// Group headers are not selectable.
		$this->assertStringNotContainsString( 'value="Roles"', $html );
	}

	public function test_array_value_preselects_matching_options() {
		$html = $this->render_select(
			array( 'posts', 'pages' ),
			array(
				array(
					'value' => 'posts',
					'text'  => 'Posts',
				),
				array(
					'value' => 'pages',
					'text'  => 'Pages',
				),
				array(
					'value' => 'media',
					'text'  => 'Media',
				),
			)
		);

		$this->assertSame( 2, substr_count( $html, 'selected="selected"' ) );
		$this->assertStringContainsString( '>Posts</option>', $html );
		$this->assertStringNotContainsString( 'value="media"  selected="selected"', $html );
	}

	public function test_placeholder_option_carries_text_and_aria_label() {
		$html = $this->render_select(
			'',
			array(
				array(
					'value' => 'posts',
					'text'  => 'Posts',
				),
			),
			array( 'placeholder' => 'Any Context' )
		);

		$this->assertStringContainsString( 'aria-label="Any Context"', $html );
		$this->assertStringContainsString( '<option value="">Any Context</option>', $html );
	}

	public function test_missing_selected_value_appends_fallback_option() {
		$html = $this->render_select(
			'not-an-option',
			array(
				array(
					'value' => 'posts',
					'text'  => 'Posts',
				),
			)
		);

		$this->assertStringContainsString(
			'<option value="not-an-option" selected="selected">not-an-option</option>',
			$html
		);
	}

	public function test_text_field_renders_placeholder_attribute() {
		$html = $this->generator->render_field(
			'text',
			array(
				'name'    => 'test_text',
				'value'   => '8.8.8.8',
				'classes' => 'ip_address',
				'data'    => array(
					'placeholder' => 'Any IP Address',
				),
			),
			false
		);

		$this->assertStringContainsString( 'type="text"', $html );
		$this->assertStringContainsString( 'placeholder="Any IP Address"', $html );
		$this->assertStringContainsString( 'value="8.8.8.8"', $html );
	}
}
