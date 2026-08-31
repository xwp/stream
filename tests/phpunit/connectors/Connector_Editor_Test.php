<?php
namespace WP_Stream;

use WP_UnitTestCase_Base;

class Connector_Editor_Test extends WP_StreamTestCase {
	/**
	 * Admin user ID
	 *
	 * @var int
	 */
	private int $admin_user_id;

	/**
	 * The original contents of the file.
	 *
	 * @var string
	 */
	private string $original_contents;

	public function setUp(): void {
		parent::setUp();

		$this->plugin->connectors->unload_connectors();
		$this->original_contents = file_get_contents( WP_PLUGIN_DIR . '/hello.php' );

		// Add admin user to test caps.
		$this->admin_user_id = (int) WP_UnitTestCase_Base::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		$this->mock = $this->getMockBuilder( Connector_Editor::class )
			->onlyMethods( array( 'log' ) )
			->getMock();

		$this->mock->register();
	}

	public function tearDown(): void {
		file_put_contents( WP_PLUGIN_DIR . '/hello.php', $this->original_contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	public function test_log_changes_theme_file() {
		$theme = wp_get_theme( 'twentytwentythree' );

		wp_set_current_user( $this->admin_user_id );

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'"%1$s" in "%2$s" updated',
						'1: File name, 2: Theme/plugin name',
						'stream'
					)
				),
				$this->equalTo(
					array(
						'file'       => 'style.css',
						'theme_name' => $theme->get( 'Name' ),
						'theme_slug' => 'twentytwentythree',
						'file_path'  => $theme->get_files( 'css' )['style.css'],
					)
				),
				$this->equalTo( null ),
				$this->equalTo( 'themes' ),
				$this->equalTo( 'updated' )
			);

		$_SERVER['REQUEST_METHOD'] = 'POST';

		$nonce             = wp_create_nonce( 'edit-theme_twentytwentythree_style.css' );
		$_REQUEST['nonce'] = $nonce;
		$_POST             = array(
			'nonce'            => $nonce,
			'_wp_http_referer' => '/wp-admin/network/theme-editor.php',
			'newcontent'       => '# hello!',
			'action'           => 'edit-theme-plugin-file',
			'file'             => 'style.css',
			'theme'            => 'twentytwentythree',
		);

		do_action( 'wp_ajax_edit-theme-plugin-file' ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
	}

	public function test_log_changes_plugin_file() {
		$plugin = get_plugins()['hello.php'];

		wp_set_current_user( $this->admin_user_id );

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'"%1$s" in "%2$s" updated',
						'1: File name, 2: Theme/plugin name',
						'stream'
					)
				),
				$this->equalTo(
					array(
						'file'        => 'hello.php',
						'plugin_name' => $plugin['Name'],
						'plugin_slug' => 'hello.php',
						'file_path'   => WP_PLUGIN_DIR . '/hello.php',
					)
				),
				$this->equalTo( null ),
				$this->equalTo( 'plugins' ),
				$this->equalTo( 'updated' )
			);

		$_SERVER['REQUEST_METHOD'] = 'POST';

		$nonce             = wp_create_nonce( 'edit-plugin_hello.php' );
		$_REQUEST['nonce'] = $nonce;
		$_POST             = array(
			'nonce'            => $nonce,
			'_wp_http_referer' => '/wp-admin/network/plugin-editor.php?plugin=hello.php&Submit=Select',
			'newcontent'       => "<?php\n/**\n * Plugin Name: Hello Dolly!\n * Description: A plugin used for PHP unit tests\n */\n",
			'action'           => 'edit-theme-plugin-file',
			'file'             => 'hello.php',
			'plugin'           => 'hello.php',
		);

		do_action( 'wp_ajax_edit-theme-plugin-file' ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
	}
}
