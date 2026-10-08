<?php
namespace WP_Stream;

class Test_Functions extends WP_StreamTestCase {
	public function test_wp_stream_get_iso_8601_extended_date() {
		$time = '1095379198';

		$date = wp_stream_get_iso_8601_extended_date( $time );
		$this->assertSame( $date, '2004-09-16T23:59:58+0000' );

		$offset_date = wp_stream_get_iso_8601_extended_date( $time, 5 );
		$this->assertSame( $offset_date, '2004-09-16T23:59:58+0500' );
	}

	/**
	 * After bootstrap the getter returns the same Plugin stored on the global.
	 */
	public function test_wp_stream_get_instance_returns_plugin_when_global_is_set() {
		// Arrange + Act
		$instance = wp_stream_get_instance();

		// Assert
		$this->assertInstanceOf( Plugin::class, $instance );
		$this->assertSame( $this->plugin, $instance );
	}

	/**
	 * AC 8: calling the getter before $GLOBALS['wp_stream'] is assigned must not warn.
	 */
	public function test_wp_stream_get_instance_returns_null_when_global_unset() {
		// Arrange
		$saved = $GLOBALS['wp_stream'];
		unset( $GLOBALS['wp_stream'] );

		try {
			// Act — PHPUnit converts Warnings to exceptions; reaching Assert means none fired.
			$result = wp_stream_get_instance();

			// Assert
			$this->assertNull( $result );
		} finally {
			$GLOBALS['wp_stream'] = $saved;
		}
	}
}
