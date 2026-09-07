<?php
/**
 * Test-local WP_Roles stand-in for Settings_Registry unit tests.
 *
 * Lives in its own file so the WPCS Generic.Files.OneObjectStructurePerFile
 * sniff is satisfied and so PHPUnit does not auto-discover it as a test.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Settings_Registry_Wp_Roles_Stub
 */
class Settings_Registry_Wp_Roles_Stub {

	/**
	 * Return role slug => label pairs.
	 *
	 * @return array<string, string>
	 */
	public function get_names() {
		return array(
			'administrator' => 'Administrator',
			'editor'        => 'Editor',
			'author'        => 'Author',
			'contributor'   => 'Contributor',
			'subscriber'    => 'Subscriber',
		);
	}
}
