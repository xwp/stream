<?php
namespace WP_Stream;

class Test_WP_Stream_Connector_Editor extends WP_StreamTestCase {
	public function setUp() {
		parent::setUp();

		$this->plugin->connectors->unload_connectors();

		$this->mock = $this->getMockBuilder( Connector_Editor::class )
			->setMethods( array( 'log' ) )
			->getMock();

		$this->mock->register();
	}

	public function test_log_changes() {
		$theme  = wp_get_theme( 'twentytwenty' );
		$plugin = get_plugins()['hello.php'];

		$this->mock->expects( $this->exactly( 2 ) )
			->method( 'log' )
			->withConsecutive(
				array(
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
							'theme_slug' => 'twentytwenty',
							'file_path'  => $theme->get_files( 'css' )['style.css'],
						)
					),
					$this->equalTo( null ),
					$this->equalTo( 'themes' ),
					$this->equalTo( 'updated' ),
				),
				array(
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
					$this->equalTo( 'updated' ),
				)
			);

		// Update theme file.
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['action']           = 'update';
		$_POST['theme']            = 'twentytwenty';
		do_action( 'load-theme-editor.php' );

		\file_put_contents( $theme->get_files( 'css' )['style.css'], "\r\n", FILE_APPEND );
		apply_filters( 'wp_redirect', 'theme-editor.php' );

		// Update plugin file
		$_POST['plugin'] = 'hello.php';
		$_POST['file']   = 'hello.php';
		unset( $_POST['theme'] );
		do_action( 'load-plugin-editor.php' );

		\file_put_contents( WP_PLUGIN_DIR . '/hello.php', "\r\n", FILE_APPEND );
		apply_filters( 'wp_redirect', 'plugin-editor.php' );
	}
}
