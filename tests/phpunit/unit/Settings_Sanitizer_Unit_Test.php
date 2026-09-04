<?php
namespace WP_Stream;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\DataProvider;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

class Settings_Sanitizer_Unit_Test extends TestCase {
	/**
	 * Sanitizer under test.
	 *
	 * @var Settings_Sanitizer
	 */
	protected $sanitizer;

	protected function set_up() {
		parent::set_up();
		Functions\when( 'sanitize_text_field' )->alias( array( self::class, 'sanitize_text_field_stub' ) );
		Functions\when( 'absint' )->alias( array( self::class, 'absint_stub' ) );
		$registry           = \Mockery::mock( Settings_Registry::class );
		$settings           = \Mockery::mock( Settings::class );
		$settings->registry = $registry;
		$plugin             = \Mockery::mock( Plugin::class );
		$plugin->settings   = $settings;
		$this->sanitizer    = new Settings_Sanitizer( $plugin );
	}

	/**
	 * Trim stand-in for sanitize_text_field.
	 *
	 * @param mixed $value Raw value.
	 * @return mixed
	 */
	public static function sanitize_text_field_stub( $value ) {
		return is_string( $value ) ? trim( $value ) : $value;
	}

	/**
	 * WordPress absint stand-in.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public static function absint_stub( $value ) {
		return abs( (int) $value );
	}

	/**
	 * Number fields coerce numeric strings to int and reject non-numeric input.
	 *
	 * @param mixed $value    Raw posted value.
	 * @param mixed $expected Expected sanitized value.
	 */
	#[DataProvider( 'data_number_values' )]
	public function test_sanitize_setting_by_field_type_number( $value, $expected ) {
		$this->assertSame(
			$expected,
			$this->sanitizer->sanitize_setting_by_field_type( $value, 'number' )
		);
	}

	/**
	 * Number sanitizer cases.
	 *
	 * @return array<string, array{0: mixed, 1: mixed}>
	 */
	public static function data_number_values() {
		return array(
			'integer string' => array( '30', 30 ),
			'padded string'  => array( '  7  ', 7 ),
			'integer'        => array( 42, 42 ),
			'zero'           => array( '0', 0 ),
			'non-numeric'    => array( 'abc', '' ),
			'empty string'   => array( '', '' ),
		);
	}

	/**
	 * Checkbox fields coerce numeric values via absint and reject non-numeric input.
	 *
	 * @param mixed $value    Raw posted value.
	 * @param mixed $expected Expected sanitized value.
	 */
	#[DataProvider( 'data_checkbox_values' )]
	public function test_sanitize_setting_by_field_type_checkbox( $value, $expected ) {
		$this->assertSame(
			$expected,
			$this->sanitizer->sanitize_setting_by_field_type( $value, 'checkbox' )
		);
	}

	/**
	 * Checkbox sanitizer cases.
	 *
	 * @return array<string, array{0: mixed, 1: mixed}>
	 */
	public static function data_checkbox_values() {
		return array(
			'one'         => array( '1', 1 ),
			'zero'        => array( '0', 0 ),
			'negative'    => array( '-3', 3 ),
			'non-numeric' => array( 'yes', '' ),
			'empty'       => array( '', '' ),
		);
	}

	public function test_sanitize_setting_by_field_type_default_scalar() {
		$this->assertSame(
			'hello world',
			$this->sanitizer->sanitize_setting_by_field_type( '  hello world  ', 'text' )
		);
	}

	public function test_sanitize_setting_by_field_type_default_nested_array() {
		$input = array(
			'outer' => array(
				'inner' => '  nested  ',
			),
			'flat'  => '  value  ',
		);

		$expected = array(
			'outer' => array(
				'inner' => 'nested',
			),
			'flat'  => 'value',
		);

		$this->assertSame(
			$expected,
			$this->sanitizer->sanitize_setting_by_field_type( $input, 'rule_list' )
		);
	}

	public function test_sanitize_settings_emits_section_name_keys_and_skips_empty() {
		$fields = array(
			'general'  => array(
				'fields' => array(
					array(
						'name' => 'records_ttl',
						'type' => 'number',
					),
					array(
						'name' => 'keep_records_indefinitely',
						'type' => 'checkbox',
					),
					array(
						'name' => 'role_access',
						'type' => 'multi_checkbox',
					),
				),
			),
			'advanced' => array(
				'fields' => array(),
			),
		);

		$input = array(
			'general_records_ttl'               => '30',
			'general_keep_records_indefinitely' => '',
			'general_role_access'               => array( ' administrator ', 'editor' ),
			'general_missing_from_input'        => 'ignored-because-not-a-field',
		);

		$output = $this->sanitizer->sanitize_settings( $input, $fields );

		$this->assertSame( 30, $output['general_records_ttl'] );
		$this->assertArrayNotHasKey( 'general_keep_records_indefinitely', $output );
		$this->assertSame( array( 'administrator', 'editor' ), $output['general_role_access'] );
		$this->assertArrayNotHasKey( 'general_missing_from_input', $output );
	}

	public function test_sanitize_settings_skips_fields_without_type_or_name() {
		$fields = array(
			'general' => array(
				'fields' => array(
					array(
						'name' => 'no_type',
					),
					array(
						'type' => 'text',
					),
				),
			),
		);

		$output = $this->sanitizer->sanitize_settings(
			array(
				'general_no_type' => 'x',
				'general_'        => 'y',
			),
			$fields
		);

		$this->assertSame( array(), $output );
	}
}
