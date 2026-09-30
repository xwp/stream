<?php
/**
 * Minimal stand-in for Gravity Forms' GFLogging class.
 *
 * Deliberately unnamespaced, so it satisfies class_exists( '\GFLogging' )
 * like the real (uninstalled here) class would.
 *
 * @package WP_Stream
 */

/**
 * Class - GFLogging
 */
class GFLogging {

	/**
	 * Slug-to-label map returned by get_supported_plugins()
	 *
	 * @var array
	 */
	public static $supported_plugins = array();

	/**
	 * Fake singleton accessor
	 *
	 * @return self
	 */
	public static function get_instance() {
		return new self();
	}

	/**
	 * Fake supported-plugins lookup
	 *
	 * @return array
	 */
	public function get_supported_plugins() {
		return self::$supported_plugins;
	}
}
