<?php

class WP_Stream_Admin {

	/**
	 * Menu page screen id
	 *
	 * @var string
	 */
	public static $screen_id = array();

	const ADMIN_PAGE_SLUG   = 'wp_stream';
	const ADMIN_PARENT_PAGE = 'options-general.php';

	public static function load() {

		// Register settings page
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );

		// Plugin action links
		add_filter( 'plugin_action_links', array( __CLASS__, 'plugin_action_links' ), 10, 2 );

		// Load admin scripts and styles
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ) );
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
			null,
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
	}

	/**
	 * Enqueue scripts/styles for admin screen
	 *
	 * @action admin_enqueue_scripts
	 * @return void
	 */
	public static function admin_enqueue_scripts( $hook ) {
		if ( $hook !== self::$screen_id['main'] ) {
			return;
		}
		wp_enqueue_script( 'wp_stream-admin', plugins_url( 'ui/admin.js' , dirname( __FILE__ ) ), array( 'jquery' ) );
		wp_enqueue_style( 'wp_stream-admin', plugins_url( 'ui/admin.css' , dirname( __FILE__ ) ), array() );
	}

	/**
	 * @filter plugin_action_links
	 */
	public static function plugin_action_links( $links, $file ) {
		if ( plugin_basename( __FILE__ ) === $file ) {
			$admin_page_url  = admin_url( sprintf( '%s?page=%s', self::ADMIN_PARENT_PAGE, self::ADMIN_PAGE_SLUG ) );
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

	public static function stream_page() {
		require_once WP_STREAM_INC_DIR . 'list-table.php';
		$list_table = new WP_Stream_List_Table();
		$list_table->prepare_items();

		echo '<div class="wrap">';
		echo sprintf( '<h2>%s</h2>', __( 'Stream Records', 'wp_stream' ) ); // xss okay
		$list_table->display();
		echo '</div>';
	}

}