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

	const ADMIN_BODY_CLASS   = 'wp_stream_screen';
	const RECORDS_PAGE_SLUG  = 'wp_stream';
	const SETTINGS_PAGE_SLUG = 'wp_stream_settings';
	const ADMIN_PARENT_PAGE  = 'admin.php';
	const VIEW_CAP           = 'view_stream';
	const SETTINGS_CAP       = 'manage_options';

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
		add_action( 'init', array( __CLASS__, 'purge_schedule_setup' ) );
		add_action( 'stream_auto_purge', array( __CLASS__, 'purge_scheduled_action' ) );

		// Admin notices
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );

		// Load Dashboard widget
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'dashboard_stream_activity' ) );

		// Heartbeat live update
		add_filter( 'heartbeat_received', array( __CLASS__, 'live_update' ), 10, 2 );

		// Enable/Disable live update per user
		add_action( 'wp_ajax_stream_enable_live_update', array( __CLASS__, 'enable_live_update' ) );

	}

	/**
	 * Output specific update
	 *
	 * @action admin_notices
	 * @return string
	 */
	public static function admin_notices() {
		$message = filter_input( INPUT_GET, 'message' );

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
	 *
	 * @param $hook
	 *
	 * @return void
	 */
	public static function admin_enqueue_scripts( $hook ) {
		wp_register_script( 'select2', WP_STREAM_URL . 'ui/select2/select2.min.js', array( 'jquery' ), '3.4.5', true );
		wp_register_style( 'select2', WP_STREAM_URL . 'ui/select2/select2.css', array(), '3.4.5' );

		wp_enqueue_style( 'wp-stream-admin', WP_STREAM_URL . 'ui/admin.css', array() );

		if ( ! in_array( $hook, self::$screen_id ) && 'plugins.php' !== $hook ) {
			return;
		}

		wp_enqueue_script( 'select2' );
		wp_enqueue_style( 'select2' );
		wp_enqueue_script( 'wp-stream-admin', WP_STREAM_URL . 'ui/admin.js', array( 'jquery', 'select2', 'heartbeat' ) );
		wp_localize_script(
			'wp-stream-admin',
			'wp_stream',
			array(
				'i18n' => array(
					'confirm_purge'     => __( 'Are you sure you want to delete all Stream activity records from the database? This cannot be undone.', 'stream' ),
					'confirm_uninstall' => __( 'Are you sure you want to uninstall and deactivate Stream? This will delete all Stream tables from the database and cannot be undone.', 'stream' ),
				),
				'current_screen' => $hook,
				'current_page'   => isset( $_GET['paged'] ) ? esc_js( $_GET['paged'] ) : '1',
				'current_order'  => isset( $_GET['order'] ) ? esc_js( $_GET['order'] ) : 'desc',
				'current_query'  => json_encode( $_GET ),
			)
		);
	}

	/**
	 * Add a specific body class to all Stream admin screens
	 *
	 * @filter admin_body_class
	 * @param  array $classes
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
	 * @return wp_add_inline_style
	 */
	public static function admin_menu_css() {
		wp_register_style( 'wp-stream-icons', WP_STREAM_URL . 'ui/stream-icons/style.css' );

		// Make sure we're working off a clean version.
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
			$admin_page_url  = add_query_arg( array( 'page' => self::SETTINGS_PAGE_SLUG ), admin_url( self::ADMIN_PARENT_PAGE ) );
			$links[] = sprintf( '<a href="%s">%s</a>', esc_url( $admin_page_url ), esc_html__( 'Settings', 'stream' ) );

			$url = add_query_arg(
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
	 * Render settings page
	 *
	 * @return void
	 */
	public static function render_page() {
		?>
		<div class="wrap">

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
		check_ajax_referer( 'stream_nonce', 'wp_stream_nonce' );
		if ( current_user_can( self::SETTINGS_CAP ) ) {
			self::erase_stream_records();
			wp_redirect( add_query_arg( array( 'page' => 'wp_stream_settings', 'message' => 'data_erased' ), admin_url( 'admin.php' ) ) );
			exit;
		} else {
			wp_die( "You don't have sufficient priviledges to do this action." );
		}
	}

	public static function erase_stream_records() {
		global $wpdb;

		$wpdb->query(
			"
			DELETE t1, t2, t3
			FROM {$wpdb->stream} as t1
				INNER JOIN {$wpdb->streamcontext} as t2
				INNER JOIN {$wpdb->streammeta} as t3
			WHERE t1.type = 'stream'
				AND t1.ID = t2.record_id
				AND t1.ID = t3.record_id;
			"
		);
	}

	/**
	 * This function is used to uninstall all custom tables and uninstall the plugin
	 * It will also uninstall custom actions
	 */
	public static function uninstall_plugin(){
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

			//Delete database option
			delete_option( plugin_basename( WP_STREAM_DIR ) . '_db' );
			delete_option( WP_Stream_Settings::KEY );
			delete_option( 'dashboard_stream_activity_options' );
			//Redirect to plugin page
			wp_redirect( add_query_arg( array( 'deactivate' => true ) , admin_url( 'plugins.php' ) ) );
			exit;
		} else {
			wp_die( "You don't have sufficient priviledges to do this action." );
		}

	}

	public static function purge_schedule_setup() {
		if ( ! wp_next_scheduled( 'stream_auto_purge' ) ) {
			wp_schedule_event( time(), 'daily', 'stream_auto_purge' );
		}
	}

	public static function purge_scheduled_action() {
		global $wpdb;

		$days = WP_Stream_Settings::$options['general_records_ttl'];
		$date = new DateTime( 'now', $timezone = new DateTimeZone( 'UTC' ) );
		$date->sub( DateInterval::createFromDateString( "$days days" ) );

		$wpdb->query(
			$wpdb->prepare(
				"
				DELETE t1, t2, t3
				FROM {$wpdb->stream} as t1
					INNER JOIN {$wpdb->streamcontext} as t2
					INNER JOIN {$wpdb->streammeta} as t3
				WHERE t1.type = 'stream'
					AND t1.created < %s
					AND t1.ID = t2.record_id
					AND t1.ID = t3.record_id;
				",
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

		foreach ( $caps as $cap ) {
			if ( self::VIEW_CAP === $cap ) {
				foreach ( $user->roles as $role ) {
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

	/**
	 * Add Stream Activity widget to the dashboard
	 *
	 * @action wp_dashboard_setup
	 */
	public static function dashboard_stream_activity() {
		if ( ! current_user_can( self::VIEW_CAP ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'dashboard_stream_activity',
			__( 'Stream Activity', 'stream' ),
			array( __CLASS__, 'dashboard_stream_activity_contents' ),
			array( __CLASS__, 'dashboard_stream_activity_options' )
		);
	}

	/**
	 * Contents of the Stream Activity dashboard widget
	 */
	public static function dashboard_stream_activity_contents() {
		$options = get_option( 'dashboard_stream_activity_options', array() );

		$args = array(
			'records_per_page' => isset( $options['records_per_page'] ) ? absint( $options['records_per_page'] ) : 5,
		);
		$records = stream_query( $args );

		if ( ! $records ) {
			?>
			<p class="no-records"><?php esc_html_e( 'Sorry, no activity records were found.', 'stream' ) ?></p>
			<?php
			return;
		}

		$i = 0;

		echo '<ul>';

		foreach ( $records as $record ) :
			$i++;

			$author = get_userdata( $record->author );

			$records_link = add_query_arg(
				array( 'page' => self::RECORDS_PAGE_SLUG ),
				admin_url( self::ADMIN_PARENT_PAGE )
			);

			$author_link = add_query_arg(
				array( 'author' => isset( $author->ID ) ? absint( $author->ID ) : 0 ),
				$records_link
			);

			if ( $author ) {
				$time_author = sprintf(
					'%s %s <a href="%s">%s</a>',
					human_time_diff( strtotime( $record->created ) ),
					esc_html__( 'ago by', 'stream' ),
					esc_url( $author_link ),
					esc_html( $author->display_name )
				);
			} else {
				$time_author = sprintf(
					__( '%s ago', 'stream' ),
					human_time_diff( strtotime( $record->created ) )
				);
			}
			?>
			<li class="<?php if ( $i % 2 ) { echo 'alternate'; } ?>">
				<?php if ( $author ) : ?>
					<div class="record-avatar">
						<a href="<?php echo esc_url( $author_link ) ?>">
							<?php echo get_avatar( $author->ID, 36 ) ?>
						</a>
					</div>
				<?php endif; ?>
				<span class="record-meta"><?php echo $time_author // xss ok ?></span>
				<br />
				<?php echo esc_html( $record->summary ) ?>
			</li>
			<?php
		endforeach;

		echo '</ul>';

		echo sprintf(
			'<div class="sub-links"><a href="%s" title="%s">%s</a></div>',
			esc_url( $records_link ),
			esc_attr__( 'View all Stream Records', 'stream' ),
			esc_html__( 'More', 'stream' )
		); // xss ok
	}

	/**
	 * Configurable options for the Stream Activity dashboard widget
	 */
	public static function dashboard_stream_activity_options() {
		$options = get_option( 'dashboard_stream_activity_options', array() );

		if ( 'POST' == $_SERVER['REQUEST_METHOD'] && isset( $_POST['dashboard_stream_activity_options'] ) ) {
			$options['records_per_page'] = absint( $_POST['dashboard_stream_activity_options']['records_per_page'] );
			update_option( 'dashboard_stream_activity_options', $options );
		}

		if ( ! isset( $options['records_per_page'] ) ) {
			$options['records_per_page'] = 5;
		}

		?>
		<div id="dashboard-stream-activity-options">
			<p>
				<label for="dashboard_stream_activity_options[records_per_page]"><?php esc_html_e( 'Number of Records', 'stream' ) ?></label>
				<input type="number" min="1" maxlength="3" class="small-text" name="dashboard_stream_activity_options[records_per_page]" id="dashboard_stream_activity_options[records_per_page]" value="<?php echo absint( $options['records_per_page'] ) ?>">
			</p>
		</div>
		<?php
	}


	/**
	 * Sends Updated Actions to the List Table View
	 *
	 * @todo fix reliability issues with sidebar widgets
	 *
	 * @uses gather_updated_items
	 * @uses generate_row
	 *
	 * @param  array  Response to heartbeat
	 * @param  array  Response from heartbeat
	 * @return array  Data sent to heartbeat
	 */
	public static function live_update( $response, $data ) {

		$enable_update = get_user_meta( get_current_user_id(), 'stream_live_update_records', true );
		$enable_update = isset( $enable_update ) ? $enable_update : '';

		if ( 'live-update' === $data['wp-stream-heartbeat'] && $enable_update == 'on' ) {
			// Register list table
			require_once WP_STREAM_INC_DIR . 'list-table.php';
			self::$list_table = new WP_Stream_List_Table( array( 'screen' => self::RECORDS_PAGE_SLUG ) );

			$last_id = filter_var( $data['wp-stream-heartbeat-last-id'], FILTER_VALIDATE_INT );
			$query   = filter_var( $data['wp-stream-heartbeat-query'], FILTER_DEFAULT, array( 'options' => array( 'default' => array() ) ) );

			// Decode the query
			$query = json_decode( wp_kses_stripslashes( $query ) );

			$updated_items = self::gather_updated_items( $last_id, $query );

			if ( ! empty( $updated_items ) ) {
				ob_start();
				foreach ( $updated_items as $item ) {
					self::$list_table->single_row( $item );
				}

				$response['wp-stream-heartbeat'] = ob_get_clean();
			}
		} else {
			$response['log'] = 'udpates disabled';
		}

		return $response;
	}


	/**
   * Sends Updated Actions to the List Table View
   *
   * @param       int    Timestamp of last update
   * @param array $query
   *
   * @return array  Array of recently updated items
   */
	public static function gather_updated_items( $last_id, $query = null ) {
		if ( $last_id === false ) {
			return '';
		}

		if ( is_null( $query ) ) {
			$query = array();
		}

		$default = array(
			'record_greater_than' => (int) $last_id,
		);

		// Filter default
		$query = wp_parse_args( $query, $default );

		//Run query
		return stream_query( $query );
	}

	/**
	 * Ajax function to enable/disable live update
	 * @return void/json
	 */
	public static function enable_live_update() {
		check_ajax_referer( 'stream_live_update_nonce', 'nonce' );

		$input = array(
			'checked' => FILTER_SANITIZE_STRING,
			'user'    => FILTER_SANITIZE_STRING,
		);

		$input = filter_input_array( INPUT_POST, $input );

		if ( false === $input ) {
			wp_send_json_error( 'Error in live update checkbox' );
		}

		$checked = ( 'checked' === $input['checked'] ) ? 'on' : 'off';

		$user = (int) $input['user'];

		$success = update_user_meta( $user, 'stream_live_update_records', $checked );

		if ( $success ) {
			wp_send_json_success( 'Live Updates Enabled' );
		} else {
			wp_send_json_error( 'Live Updates checkbox error' );
		}
	}

}
