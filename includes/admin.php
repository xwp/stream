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
	 *
	 * @var WP_Stream_List_Table
	 */
	public static $list_table = null;

	const ADMIN_BODY_CLASS     = 'wp_stream_screen';
	const RECORDS_PAGE_SLUG    = 'wp_stream';
	const SETTINGS_PAGE_SLUG   = 'wp_stream_settings';
	const EXTENSIONS_PAGE_SLUG = 'wp_stream_extensions';
	const ADMIN_PARENT_PAGE    = 'admin.php';
	const VIEW_CAP             = 'view_stream';
	const SETTINGS_CAP         = 'manage_options';
	const PRELOAD_AUTHORS_MAX  = 50;

	public static function load() {
		// User and role caps
		add_filter( 'user_has_cap', array( __CLASS__, '_filter_user_caps' ), 10, 4 );
		add_filter( 'role_has_cap', array( __CLASS__, '_filter_role_caps' ), 10, 3 );

		// Add admin body class
		add_filter( 'admin_body_class', array( __CLASS__, 'admin_body_class' ) );

		// Register settings page
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );

		// Plugin action links
		add_filter( 'plugin_action_links', array( __CLASS__, 'plugin_action_links' ), 10, 2 );

		// Load admin scripts and styles
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_menu_css' ) );

		// Reset Streams database
		add_action( 'wp_ajax_wp_stream_reset', array( __CLASS__, 'wp_ajax_reset' ) );

		// Uninstall Streams and Deactivate plugin
		add_action( 'wp_ajax_wp_stream_uninstall', array( __CLASS__, 'uninstall_plugin' ) );

		// Auto purge setup
		add_action( 'wp', array( __CLASS__, 'purge_schedule_setup' ) );
		add_action( 'wp_stream_auto_purge', array( __CLASS__, 'purge_scheduled_action' ) );

		// Admin notices
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );

		// Toggle filters in list table on/off
		add_action( 'wp_ajax_stream_toggle_filters', array( __CLASS__, 'toggle_filters' ) );

		// Ajax authors list
		add_action( 'wp_ajax_wp_stream_filters', array( __CLASS__, 'ajax_filters' ) );

		// Ajax author's name by ID
		add_action( 'wp_ajax_wp_stream_get_author_name_by_id', array( __CLASS__, 'get_author_name_by_id' ) );
	}

	/**
	 * Output specific update
	 *
	 * @action admin_notices
	 * @return string
	 */
	public static function admin_notices() {
		$message = wp_stream_filter_input( INPUT_GET, 'message' );

		switch ( $message ) {
			case 'data_erased':
				printf( '<div class="updated"><p>%s</p></div>', __( 'All records have been successfully erased.', 'stream' ) );
				break;
		}
	}

	/**
	 * Register menu page
	 *
	 * @action admin_menu
	 * @return void
	 */
	public static function register_menu() {
		self::$screen_id['main'] = add_menu_page(
			__( 'Stream', 'stream' ),
			__( 'Stream', 'stream' ),
			self::VIEW_CAP,
			self::RECORDS_PAGE_SLUG,
			array( __CLASS__, 'stream_page' ),
			'div',
			'2.999999' // Using longtail decimal string to reduce the chance of position conflicts, see Codex
		);

		self::$screen_id['settings'] = add_submenu_page(
			self::RECORDS_PAGE_SLUG,
			__( 'Stream Settings', 'stream' ),
			__( 'Settings', 'stream' ),
			self::SETTINGS_CAP,
			self::SETTINGS_PAGE_SLUG,
			array( __CLASS__, 'render_settings_page' )
		);

		self::$screen_id['extensions'] = add_submenu_page(
			self::RECORDS_PAGE_SLUG,
			__( 'Stream Extensions', 'stream' ),
			__( 'Extensions', 'stream' ),
			self::SETTINGS_CAP,
			self::EXTENSIONS_PAGE_SLUG,
			array( __CLASS__, 'render_extensions_page' )
		);

		// Register the list table early, so it associates the column headers with 'Screen settings'
		add_action( 'load-' . self::$screen_id['main'], array( __CLASS__, 'register_list_table' ) );
	}

	/**
	 * Enqueue scripts/styles for admin screen
	 *
	 * @action admin_enqueue_scripts
	 *
	 * @param $hook
	 *
	 * @return void
	 */
	public static function admin_enqueue_scripts( $hook ) {
		wp_register_script( 'select2', WP_STREAM_URL . 'ui/select2/select2.min.js', array( 'jquery' ), '3.4.5', true );
		wp_register_style( 'select2', WP_STREAM_URL . 'ui/select2/select2.css', array(), '3.4.5' );

		wp_register_script( 'timeago', WP_STREAM_URL . 'ui/timeago/timeago.js', array(), '0.2.0', true );
		$locale    = substr( get_locale(), 0, 2 );
		$file_tmpl = 'ui/timeago/locale/jquery.timeago.%s.js';
		if ( file_exists( WP_STREAM_DIR . sprintf( $file_tmpl, $locale ) ) ) {
			wp_register_script( 'timeago-locale', WP_STREAM_URL . sprintf( $file_tmpl, $locale ), array( 'timeago' ), '1' );
		} else {
			wp_register_script( 'timeago-locale', WP_STREAM_URL . sprintf( $file_tmpl, 'en' ), array( 'timeago' ), '1' );
		}

		wp_enqueue_style( 'wp-stream-admin', WP_STREAM_URL . 'ui/admin.css', array() );

		if ( 'index.php' === $hook ) {
			wp_enqueue_script( 'wp-stream-admin-dashboard', WP_STREAM_URL . 'ui/dashboard.js', array( 'jquery', 'heartbeat' ) );
		} elseif ( in_array( $hook, self::$screen_id ) || 'plugins.php' === $hook ) {
			wp_enqueue_script( 'select2' );
			wp_enqueue_style( 'select2' );

			wp_enqueue_script( 'timeago' );
			wp_enqueue_script( 'timeago-locale' );

			wp_enqueue_script( 'wp-stream-admin', WP_STREAM_URL . 'ui/admin.js', array( 'jquery', 'select2', 'heartbeat' ) );
			wp_localize_script(
				'wp-stream-admin',
				'wp_stream',
				array(
					'i18n'            => array(
						'confirm_purge'     => __( 'Are you sure you want to delete all Stream activity records from the database? This cannot be undone.', 'stream' ),
						'confirm_uninstall' => __( 'Are you sure you want to uninstall and deactivate Stream? This will delete all Stream tables from the database and cannot be undone.', 'stream' ),
					),
					'gmt_offset'      => get_option( 'gmt_offset' ),
					'current_screen'  => $hook,
					'current_page'    => isset( $_GET['paged'] ) ? esc_js( $_GET['paged'] ) : '1',
					'current_order'   => isset( $_GET['order'] ) ? esc_js( $_GET['order'] ) : 'desc',
					'current_query'   => json_encode( $_GET ),
					'filter_controls' => get_user_meta( get_current_user_id(), 'stream_toggle_filters', true ),
				)
			);
		}
	}

	/**
	 * Add a specific body class to all Stream admin screens
	 *
	 * @filter admin_body_class
	 *
	 * @param  array $classes
	 *
	 * @return array $classes
	 */
	public static function admin_body_class( $classes ) {
		if ( isset( $_GET['page'] ) && false !== strpos( $_GET['page'], self::RECORDS_PAGE_SLUG ) ) {
			$classes .= self::ADMIN_BODY_CLASS;
		}

		return $classes;
	}

	/**
	 * Add menu styles for various WP Admin skins
	 *
	 * @action admin_enqueue_scripts
	 * @return void
	 */
	public static function admin_menu_css() {
		wp_register_style( 'jquery-ui', '//ajax.googleapis.com/ajax/libs/jqueryui/1.10.1/themes/base/jquery-ui.css', array(), '1.10.1' );
		wp_register_style( 'wp-stream-datepicker', WP_STREAM_URL . 'ui/datepicker.css', array( 'jquery-ui' ) );
		wp_register_style( 'wp-stream-icons', WP_STREAM_URL . 'ui/stream-icons/style.css' );

		// Make sure we're working off a clean version
		include( ABSPATH . WPINC . '/version.php' );

		$body_class   = self::ADMIN_BODY_CLASS;
		$records_page = self::RECORDS_PAGE_SLUG;
		$stream_url   = WP_STREAM_URL;

		if ( version_compare( $wp_version, '3.8-alpha', '>=' ) ) {
			wp_enqueue_style( 'wp-stream-icons' );
			$css = "
				#toplevel_page_{$records_page} .wp-menu-image:before {
					font-family: 'WP Stream' !important;
					content: '\\73' !important;
				}
				#toplevel_page_{$records_page} .wp-menu-image {
					background-repeat: no-repeat;
				}
				#menu-posts-feedback .wp-menu-image:before {
					font-family: dashicons !important;
					content: '\\f175';
				}
				#adminmenu #menu-posts-feedback div.wp-menu-image {
					background: none !important;
					background-repeat: no-repeat;
				}
				body.{$body_class} #wpbody-content .wrap h2:nth-child(1):before {
					font-family: 'WP Stream' !important;
					content: '\\73';
					padding: 0 8px 0 0;
				}
			";
		} else {
			$css = "
				#toplevel_page_{$records_page} .wp-menu-image {
					background: url( {$stream_url}ui/stream-icons/menuicon-sprite.png ) 0 90% no-repeat;
				}
				/* Retina Stream Menu Icon */
				@media  only screen and (-moz-min-device-pixel-ratio: 1.5),
						only screen and (-o-min-device-pixel-ratio: 3/2),
						only screen and (-webkit-min-device-pixel-ratio: 1.5),
						only screen and (min-device-pixel-ratio: 1.5) {
					#toplevel_page_{$records_page} .wp-menu-image {
						background: url( {$stream_url}ui/stream-icons/menuicon-sprite-2x.png ) 0 90% no-repeat;
						background-size:30px 64px;
					}
				}
				#toplevel_page_{$records_page}.current .wp-menu-image,
				#toplevel_page_{$records_page}.wp-has-current-submenu .wp-menu-image,
				#toplevel_page_{$records_page}:hover .wp-menu-image {
					background-position: top left;
				}
			";
		}

		wp_add_inline_style( 'wp-admin', $css );
	}

	/**
	 * @filter plugin_action_links
	 */
	public static function plugin_action_links( $links, $file ) {
		if ( plugin_basename( WP_STREAM_DIR . 'stream.php' ) === $file ) {
			$admin_page_url = add_query_arg( array( 'page' => self::SETTINGS_PAGE_SLUG ), admin_url( self::ADMIN_PARENT_PAGE ) );
			$links[] = sprintf( '<a href="%s">%s</a>', esc_url( $admin_page_url ), esc_html__( 'Settings', 'stream' ) );

			$url     = add_query_arg(
				array(
					'action'          => 'wp_stream_uninstall',
					'wp_stream_nonce' => wp_create_nonce( 'stream_nonce' ),
				),
				admin_url( 'admin-ajax.php' )
			);
			$links[] = sprintf( '<span id="wp_stream_uninstall" class="delete"><a href="%s">%s</a></span>', esc_url( $url ), esc_html__( 'Uninstall', 'stream' ) );
		}

		return $links;
	}

	/**
	 * Register a routine to be called when stream or a stream connector has been updated
	 * It works by comparing the current version with the version previously stored in the database.
	 *
	 * @param string $file A reference to the main plugin file
	 * @param string $callback The function to run when the hook is called.
	 * @param string $version The version to which the plugin is updating.
	 * @return void
	 */
	public static function register_update_hook( $file, $callback, $version ) {
		if ( ! is_admin() ) {
			return;
		}

		$plugin = plugin_basename( $file );

		if ( is_plugin_active_for_network( $plugin ) ) {
			$current_versions = get_site_option( WP_Stream_Install::KEY . '_connectors', array() );
			$network          = true;
		} elseif ( is_plugin_active( $plugin ) ) {
			$current_versions = get_option( WP_Stream_Install::KEY . '_connectors', array() );
			$network          = false;
		} else {
			return;
		}

		if ( version_compare( $version, $current_versions[ $plugin ], '>' ) ) {
			call_user_func( $callback, $current_versions[ $plugin ], $network );
			$current_versions[ $plugin ] = $version;
		}

		if ( $network ) {
			update_site_option( WP_Stream_Install::KEY . '_registered_connectors', $current_versions );
		} else {
			update_option( WP_Stream_Install::KEY . '_registered_connectors', $current_versions );
		}

		return;
	}

	/**
	 * Render settings page
	 *
	 * @return void
	 */
	public static function render_settings_page() {
		?>
		<div class="wrap">

			<h2><?php _e( 'Stream Settings', 'stream' ) ?></h2>
			<?php settings_errors() ?>

			<?php
			$sections   = WP_Stream_Settings::get_fields();
			$active_tab = wp_stream_filter_input( INPUT_GET, 'tab' );
			?>

			<h2 class="nav-tab-wrapper">
				<?php $i = 0 ?>
				<?php foreach ( $sections as $section => $data ) : ?>
					<?php $i ++ ?>
					<?php $is_active = ( ( 1 === $i && ! $active_tab ) || $active_tab === $section ) ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $section ) ) ?>" class="nav-tab<?php if ( $is_active ) { echo esc_attr( ' nav-tab-active' ); } ?>">
						<?php echo esc_html( $data['title'] ) ?>
					</a>
				<?php endforeach; ?>
			</h2>

			<div class="nav-tab-content" id="tab-content-settings">
				<form method="post" action="options.php" enctype="multipart/form-data">
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

	/**
	 * Render extensions page
	 *
	 * @return void
	 */
	public static function render_extensions_page() {

		if ( $install = wp_stream_filter_input( INPUT_GET, 'install' ) ) {
			return self::render_extension_download_page( $install );
		}

		wp_enqueue_style( 'thickbox' );
		wp_enqueue_script(
			'stream-activation',
			plugins_url( '../ui/license.js', __FILE__ ),
			array( 'jquery', 'thickbox' ),
			WP_Stream::VERSION,
			true
		);
		$action = 'license';
		wp_localize_script(
			'stream-activation',
			'stream_activation',
			array(
				'action' => get_option( 'stream-license' ) ? 'disconnect' : 'connect',
				'api'    => array(
					'connect'          => apply_filters( 'stream-api-url', WP_Stream_Updater::instance()->get_api_url() . 'connect', 'connect' ),
					'disconnect'       => apply_filters( 'stream-api-url', WP_Stream_Updater::instance()->get_api_url() . 'disconnect', 'disconnect' ),
					'disconnect_local' => add_query_arg( 'action', 'stream-license-remove', admin_url( 'admin-ajax.php' ) ),
				),
				'nonce' => array(
					'license_check'  => wp_create_nonce( 'license_check' ),
					'license_remove' => wp_create_nonce( 'license_remove' ),
				),
				'i18n' => array(
					'connected'       => __( 'Connected', 'stream' ),
					'login_to_stream' => __( 'Connect to Stream Premium', 'stream' ),
				),
			)
		);

		?>
		<div class="themes-php">
			<div class="wrap">
				<?php $extensions = WP_Stream_Extensions::get_instance() ?>
				<?php $extensions->render_page() ?>
			</div>
		</div>
		<?php
	}

	public static function render_extension_download_page( $extension ) {
		add_filter(
			'install_plugin_complete_actions',
			function( $actions ) {
				echo sprintf(
					'<a href="%s">%s</a>',
					remove_query_arg( 'install' ),
					esc_html__( 'Return to Stream Extensions', 'stream' )
				); // xss okay
			}
		);
		WP_Stream_Updater::instance()->install_extension( $extension );
	}

	public static function register_list_table() {
		require_once WP_STREAM_INC_DIR . 'list-table.php';
		self::$list_table = new WP_Stream_List_Table( array( 'screen' => self::$screen_id['main'] ) );
	}

	public static function stream_page() {
		self::$list_table->prepare_items();

		echo '<div class="wrap">';
		printf( '<h2>%s</h2>', __( 'Stream Records', 'stream' ) ); // xss ok
		self::$list_table->display();
		echo '</div>';
	}

	public static function wp_ajax_reset() {
		check_ajax_referer( 'stream_nonce', 'wp_stream_nonce' );

		if ( current_user_can( self::SETTINGS_CAP ) ) {
			self::erase_stream_records();
			wp_redirect(
				add_query_arg(
					array(
						'page'    => 'wp_stream_settings',
						'message' => 'data_erased',
					),
					admin_url( self::ADMIN_PARENT_PAGE )
				)
			);
			exit;
		} else {
			wp_die( "You don't have sufficient privileges to do this action." );
		}
	}

	public static function erase_stream_records() {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE `stream`, `context`, `meta`
				FROM {$wpdb->stream} AS `stream`
				LEFT JOIN {$wpdb->streamcontext} AS `context`
				ON `context`.`record_id` = `stream`.`ID`
				LEFT JOIN {$wpdb->streammeta} AS `meta`
				ON `meta`.`record_id` = `stream`.`ID`
				WHERE `stream`.`type` = %s;",
				'stream'
			)
		);
	}

	/**
	 * This function is used to uninstall all custom tables and uninstall the plugin
	 * It will also uninstall custom actions
	 */
	public static function uninstall_plugin() {
		global $wpdb;

		check_ajax_referer( 'stream_nonce', 'wp_stream_nonce' );

		if ( current_user_can( self::SETTINGS_CAP ) ) {
			// Prevent stream action from being fired on plugin
			remove_action( 'deactivate_plugin', array( 'WP_Stream_Connector_Installer', 'callback' ), null );

			// Deactivate the plugin
			deactivate_plugins( plugin_basename( WP_STREAM_DIR ) . '/stream.php' );

			// Delete all tables
			foreach ( WP_Stream_DB::get_instance()->get_table_names() as $table ) {
				$wpdb->query( "DROP TABLE $table" );
			}

			// Delete database option
			delete_option( WP_Stream_Install::KEY );
			delete_option( WP_Stream_Settings::KEY );
			delete_option( 'dashboard_stream_activity_options' );

			// Redirect to plugin page
			wp_redirect( add_query_arg( array( 'deactivate' => true ), admin_url( 'plugins.php' ) ) );
			exit;
		} else {
			wp_die( "You don't have sufficient privileges to do this action." );
		}

	}

	public static function purge_schedule_setup() {
		if ( ! wp_next_scheduled( 'wp_stream_auto_purge' ) ) {
			wp_schedule_event( time(), 'daily', 'wp_stream_auto_purge' );
		}
	}

	public static function purge_scheduled_action() {
		global $wpdb;

		$options = WP_Stream_Settings::get_options();
		$days    = $options['general_records_ttl'];
		$date    = new DateTime( 'now', $timezone = new DateTimeZone( 'UTC' ) );

		$date->sub( DateInterval::createFromDateString( "$days days" ) );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE `stream`, `context`, `meta`
				FROM {$wpdb->stream} AS `stream`
				LEFT JOIN {$wpdb->streamcontext} AS `context`
				ON `context`.`record_id` = `stream`.`ID`
				LEFT JOIN {$wpdb->streammeta} AS `meta`
				ON `meta`.`record_id` = `stream`.`ID`
				WHERE `stream`.`type` = %s
				AND `stream`.`created` < %s;",
				'stream',
				$date->format( 'Y-m-d H:i:s' )
			)
		);
	}

	private static function _role_can_view_stream( $role ) {
		if ( in_array( $role, WP_Stream_Settings::$options['general_role_access'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Filter user caps to dynamically grant our view cap based on allowed roles
	 *
	 * @filter user_has_cap
	 *
	 * @param $allcaps
	 * @param $caps
	 * @param $args
	 * @param $user
	 *
	 * @return array
	 */
	public static function _filter_user_caps( $allcaps, $caps, $args, $user = null ) {
		$user = is_a( $user, 'WP_User' ) ? $user : wp_get_current_user();
		// @see
		// https://github.com/WordPress/WordPress/blob/c67c9565f1495255807069fdb39dac914046b1a0/wp-includes/capabilities.php#L758
		$roles = array_unique(
			array_merge(
				$user->roles,
				array_filter(
					array_keys( $user->caps ),
					array( $GLOBALS['wp_roles'], 'is_role' )
				)
			)
		);

		foreach ( $caps as $cap ) {
			if ( self::VIEW_CAP === $cap ) {
				foreach ( $roles as $role ) {
					if ( self::_role_can_view_stream( $role ) ) {
						$allcaps[ $cap ] = true;
						break 2;
					}
				}
			}
		}

		return $allcaps;
	}

	/**
	 * Filter role caps to dynamically grant our view cap based on allowed roles
	 *
	 * @filter role_has_cap
	 *
	 * @param $allcaps
	 * @param $cap
	 * @param $role
	 *
	 * @return array
	 */
	public static function _filter_role_caps( $allcaps, $cap, $role ) {
		if ( self::VIEW_CAP === $cap && self::_role_can_view_stream( $role ) ) {
			$allcaps[ $cap ] = true;
		}

		return $allcaps;
	}

	public static function toggle_filters() {
		check_ajax_referer( 'stream_toggle_filters_nonce', 'nonce' );

		$input = array(
			'checked'  => wp_stream_filter_input( INPUT_POST, 'checked', FILTER_SANITIZE_STRING ),
			'user'     => wp_stream_filter_input( INPUT_POST, 'user', FILTER_SANITIZE_NUMBER_INT ),
			'checkbox' => sanitize_key( $_POST['checkbox'] ),
		);

		$filters_option = get_user_meta( $input['user'], 'stream_toggle_filters', true );

		$filters_option[ $input['checkbox'] ] = ( 'checked' === $input['checked'] );

		$success = update_user_meta( $input['user'], 'stream_toggle_filters', $filters_option );

		if ( $success ) {
			wp_send_json( array( 'control' => $input['checkbox'] ) );
		} else {
			wp_send_json_error( 'Toggled filter checkbox error' );
		}
	}

	/**
	 * @action wp_ajax_wp_stream_filters
	 */
	public static function ajax_filters() {
		switch ( $_REQUEST['filter'] ) {
			case 'author':
				$results = array_map(
					function ( $user ) {
						return array(
							'id'   => $user->id,
							'text' => $user->display_name,
						);
					},
					get_users()
				);
				break;
		}

		// `search` arg for get_users() is not enough
		$results = array_filter(
			$results,
			function ( $result ) {
				return mb_strpos( mb_strtolower( $result['text'] ), mb_strtolower( $_REQUEST['q'] ) ) !== false;
			}
		);

		$results_count = count( $results );

		if ( $results_count > self::PRELOAD_AUTHORS_MAX ) {
			$results   = array_slice( $results, 0, self::PRELOAD_AUTHORS_MAX );
			$results[] = array(
				'id'       => 0,
				'disabled' => true,
				'text'     => sprintf( _n( 'One more result...', '%d more results...', $results_count - self::PRELOAD_AUTHORS_MAX, 'stream' ), $results_count - self::PRELOAD_AUTHORS_MAX ),
			);
		}

		echo json_encode( array_values( $results ) );
		die();
	}

	/**
	 * @action wp_ajax_wp_stream_get_author_name_by_id
	 */
	public static function get_author_name_by_id() {
		$user = get_userdata( $_REQUEST['id'] );
		echo json_encode( $user->display_name );
		die();
	}

}
