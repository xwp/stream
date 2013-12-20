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

	const RECORDS_PAGE_SLUG  = 'wp_stream';
	const SETTINGS_PAGE_SLUG = 'wp_stream_settings';
	const ADMIN_PARENT_PAGE  = 'admin.php';
	const VIEW_CAP           = 'view_stream';
	const SETTINGS_CAP       = 'manage_options';

	public static function load() {
		// User and role caps
		add_filter( 'user_has_cap', array( __CLASS__, '_filter_user_caps' ), 10, 4 );
		add_filter( 'role_has_cap', array( __CLASS__, '_filter_role_caps' ), 10, 3 );

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

		// Load Dashboard widget
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'dashboard_stream_activity' ) );
	}

	/**
	 * Register menu page
	 *
	 * @action admin_menu
	 * @return void
	 */
	public static function register_menu() {
		global $menu;

		self::$screen_id['main'] = add_menu_page(
			__( 'Stream', 'stream' ),
			__( 'Stream', 'stream' ),
			self::VIEW_CAP,
			self::RECORDS_PAGE_SLUG,
			array( __CLASS__, 'stream_page' ),
			'div',
			3
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
	 * @return void
	 */
	public static function admin_enqueue_scripts( $hook ) {
		wp_enqueue_style( 'wp-stream-admin', WP_STREAM_URL . 'ui/admin.css', array() );

		if ( ! in_array( $hook, self::$screen_id ) && 'plugins.php' !== $hook ) {
			return;
		}

		wp_enqueue_script( 'wp-stream-chosen', WP_STREAM_URL . 'ui/chosen/chosen.jquery.min.js', array( 'jquery' ), '1.0.0' );
		wp_enqueue_style( 'wp-stream-chosen', WP_STREAM_URL . 'ui/chosen/chosen.min.css', array(), '1.0.0' );
		wp_enqueue_script( 'wp-stream-admin', WP_STREAM_URL . 'ui/admin.js', array( 'jquery' ) );
		wp_localize_script(
			'wp-stream-admin',
			'wp_stream',
			array(
				'i18n' => array(
					'confirm_purge'     => __( 'Are you sure you want to delete all Stream activity records from the database? This cannot be undone.', 'stream' ),
					'confirm_uninstall' => __( 'Are you sure you want to uninstall and deactivate Stream? This will delete all Stream tables from the database and cannot be undone.', 'stream' ),
				),
			)
		);
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
		check_ajax_referer( 'stream_nonce', 'wp_stream_nonce' );
		if ( current_user_can( self::SETTINGS_CAP ) ) {
			self::erase_stream_records();
			wp_redirect( admin_url( 'admin.php?page=wp_stream_settings#reset' ) );
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
			// Deactivate the plugin
			deactivate_plugins( plugin_basename( WP_STREAM_DIR ) . '/stream.php' );
			// Delete all tables
			foreach ( array( $wpdb->stream, $wpdb->streamcontext, $wpdb->streammeta ) as $table ) {
				$wpdb->query( "DROP TABLE $table" );
			}
			//Delete database option
			delete_option( plugin_basename( WP_STREAM_DIR ) . '_db' );
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
	 * @return array
	 */
	public static function _filter_user_caps( $allcaps, $caps, $args, $user ) {
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
			$author      = get_userdata( $record->author );
			$author_link = add_query_arg(
				array(
					'page'   => self::RECORDS_PAGE_SLUG,
					'author' => isset( $author->ID ) ? absint( $author->ID ) : 0,
				),
				admin_url( self::ADMIN_PARENT_PAGE )
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

}
