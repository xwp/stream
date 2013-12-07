<?php

class WP_Stream_Admin {

	/**
	 * Menu page screen id
	 *
	 * @var string
	 */
	public static $screen_id = array();

	/**
	 * List table object
	 * @var WP_Stream_List_Table
	 */
	public static $list_table = null;

	const ADMIN_PAGE_SLUG   = 'wp_stream_settings';
	const ADMIN_PARENT_PAGE = 'admin.php';

	public static function load() {

		// Register settings page
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );

		// Plugin action links
		add_filter( 'plugin_action_links', array( __CLASS__, 'plugin_action_links' ), 10, 2 );

		// Load admin scripts and styles
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_menu_css' ) );

		// Reset Streams database
		add_action( 'wp_ajax_wp_stream_reset', array( __CLASS__, 'wp_ajax_reset' ) );
	}

	/**
	 * Register menu page
	 *
	 * @action admin_menu
	 * @return void
	 */
	public static function register_menu() {
		global $menu;
		$cap = apply_filters( 'wp_stream_cap', 'manage_options' );
		if ( ! current_user_can( $cap ) ) {
			return;
		}

		self::$screen_id['main'] = add_menu_page(
			__( 'Stream', 'stream' ),
			__( 'Stream', 'stream' ),
			$cap,
			'wp_stream',
			array( __CLASS__, 'stream_page' ),
			'div',
			3
			);

		self::$screen_id['settings'] = add_submenu_page(
			'wp_stream',
			__( 'Settings', 'stream' ),
			__( 'Settings', 'stream' ),
			'manage_options',
			'wp_stream_settings',
			array( __CLASS__, 'render_page' )
		);

		// Register the list table early, so it associates the column headers with 'Screen settings'
		add_action( 'load-' . self::$screen_id['main'], array( __CLASS__, 'register_list_table' ) );
	}

	/**
	 * Enqueue scripts/styles for admin screen
	 *
	 * @action admin_enqueue_scripts
	 * @return void
	 */
	public static function admin_enqueue_scripts( $hook ) {
		if ( ! isset( self::$screen_id['main'] ) || $hook !== self::$screen_id['main'] ) {
			return;
		}
		wp_enqueue_script( 'wp-stream-admin', WP_STREAM_URL . 'ui/admin.js', array( 'jquery' ) );
		wp_enqueue_style( 'wp-stream-admin', WP_STREAM_URL . 'ui/admin.css', array() );
	}

	/**
	 * Add menu styles for various WP Admin skins
	 *
	 * @action admin_enqueue_scripts
	 * @return wp_add_inline_style
	 */
	public static function admin_menu_css() {
		wp_register_style( 'wp-stream-icons', WP_STREAM_URL . 'ui/stream-icons/style.css' );

		// Make sure we're working off a clean version.
		include( ABSPATH . WPINC . '/version.php' );
		if ( version_compare( $wp_version, '3.8-alpha', '>=' ) ) {
			wp_enqueue_style( 'wp-stream-icons' );
			$css = "
				#toplevel_page_wp_stream .wp-menu-image:before {
					font-family: 'WP Stream' !important;
					content: '\\73' !important;
				}
				#toplevel_page_wp_stream .wp-menu-image {
					background-repeat: no-repeat;
				}
				#menu-posts-feedback .wp-menu-image:before {
					font-family: dashicons !important;
					content: '\\f175';
				}
				#adminmenu #menu-posts-feedback div.wp-menu-image {
					background: none !important;
					background-repeat: no-repeat;
				}";
		} else {
			$css = '
				#toplevel_page_wp_stream .wp-menu-image {
					background: url( ' . WP_STREAM_URL . 'ui/stream-icons/menuicon-sprite.png ) 0 90% no-repeat;
				}
				/* Retina Stream Menu Icon */
				@media  only screen and (-moz-min-device-pixel-ratio: 1.5),
						only screen and (-o-min-device-pixel-ratio: 3/2),
						only screen and (-webkit-min-device-pixel-ratio: 1.5),
						only screen and (min-device-pixel-ratio: 1.5) {
					#toplevel_page_wp_stream .wp-menu-image {
						background: url( ' . WP_STREAM_URL . 'ui/stream-icons/menuicon-sprite-2x.png ) 0 90% no-repeat;
						background-size:30px 64px;
					}
				}
				#toplevel_page_wp_stream.current .wp-menu-image,
				#toplevel_page_wp_stream.wp-has-current-submenu .wp-menu-image,
				#toplevel_page_wp_stream:hover .wp-menu-image {
					background-position: top left;
				}';
		}
		wp_add_inline_style( 'wp-admin', $css );
	}

	/**
	 * @filter plugin_action_links
	 */
	public static function plugin_action_links( $links, $file ) {
		if ( plugin_basename( WP_STREAM_DIR . 'stream.php' ) === $file ) {
			$admin_page_url  = add_query_arg( array( 'page' => self::ADMIN_PAGE_SLUG ), admin_url( self::ADMIN_PARENT_PAGE ) );
			$admin_page_link = sprintf( '<a href="%s">%s</a>', esc_url( $admin_page_url ), esc_html__( 'Settings', 'stream' ) );
			array_push( $links, $admin_page_link );
		}
		return $links;
	}

	/**
	 * Render settings page
	 *
	 * @return void
	 */
	public static function render_page() {
		?>
		<div class="wrap">

			<?php screen_icon( 'options-general' ) ?>
			<h2><?php _e( 'Stream Settings', 'stream' ) ?></h2>
			<?php settings_errors() ?>

			<?php
			$sections   = WP_Stream_Settings::get_fields();
			$active_tab = filter_input( INPUT_GET, 'tab' );
			?>

			<h2 class="nav-tab-wrapper">
				<?php $i = 0 ?>
				<?php foreach ( $sections as $section => $data ) : ?>
					<?php $i++ ?>
					<?php $is_active = ( ( 1 === $i && ! $active_tab ) || $active_tab === $section ) ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $section ) ) ?>" class="nav-tab<?php if ( $is_active ) { echo esc_attr( ' nav-tab-active' ); } ?>">
						<?php echo esc_html( $data['title'] ) ?>
					</a>
				<?php endforeach; ?>
			</h2>

			<div class="nav-tab-content" id="tab-content-settings">
				<form method="post" action="options.php">
		<?php
		$i = 0;
		foreach ( $sections as $section => $data ) {
			$i++;
			$is_active = ( ( 1 === $i && ! $active_tab ) || $active_tab === $section );
			if ( $is_active ) {
				settings_fields( WP_Stream_Settings::KEY );
				do_settings_sections( WP_Stream_Settings::KEY );
			}
		}
		submit_button();
		?>
				</form>
			</div>

		</div>
		<?php
	}

	public static function register_list_table() {
		require_once WP_STREAM_INC_DIR . 'list-table.php';
		self::$list_table = new WP_Stream_List_Table( array( 'screen' => self::$screen_id['main'] ) );
	}

	public static function stream_page() {
		self::$list_table->prepare_items();

		echo '<div class="wrap">';
		echo sprintf( '<h2>%s</h2>', __( 'Stream Records', 'stream' ) ); // xss okay
		self::$list_table->display();
		echo '</div>';
	}

	public static function wp_ajax_reset() {
		self::erase_stream_records();
		wp_redirect( admin_url( 'admin.php?page=wp_stream_settings#reset' ) );
	}

	public static function erase_stream_records() {
		global $wpdb;
		foreach ( array( $wpdb->stream, $wpdb->streamcontext, $wpdb->streammeta ) as $table ) {
			$wpdb->query( "DELETE FROM $table" );
		}
	}

}