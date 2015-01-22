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

	/**
	 * Option to disable access to Stream
	 *
	 * @var bool
	 */
	public static $disable_access = false;

	/**
	 * URL used for authenticating with Stream
	 *
	 * @var string
	 */
	public static $connect_url;

	const ADMIN_BODY_CLASS        = 'wp_stream_screen';
	const RECORDS_PAGE_SLUG       = 'wp_stream';
	const SETTINGS_PAGE_SLUG      = 'wp_stream_settings';
	const ACCOUNT_PAGE_SLUG       = 'wp_stream_account';
	const ADMIN_PARENT_PAGE       = 'admin.php';
	const VIEW_CAP                = 'view_stream';
	const SETTINGS_CAP            = 'manage_options';
	const UNREAD_COUNT_OPTION_KEY = 'stream_unread_count';
	const LAST_READ_OPTION_KEY    = 'stream_last_read';
	const PRELOAD_AUTHORS_MAX     = 50;
	const PUBLIC_URL              = 'https://wp-stream.com';

	public static function load() {
		// User and role caps
		add_filter( 'user_has_cap', array( __CLASS__, '_filter_user_caps' ), 10, 4 );
		add_filter( 'role_has_cap', array( __CLASS__, '_filter_role_caps' ), 10, 3 );

		$home_url      = str_ireplace( array( 'http://', 'https://' ), '', home_url() );
		$connect_nonce = wp_create_nonce( 'stream_connect_site-' . sanitize_key( $home_url ) );

		self::$connect_url = add_query_arg(
			array(
				'auth'       => 'true',
				'action'     => 'connect',
				'home_url'   => urlencode( $home_url ),
				'plugin_url' => urlencode(
					add_query_arg(
						array(
							'page'  => self::RECORDS_PAGE_SLUG,
							'nonce' => $connect_nonce,
						),
						admin_url( self::ADMIN_PARENT_PAGE )
					)
				),
			),
			esc_url_raw( untrailingslashit( self::PUBLIC_URL ) . '/pricing/' )
		);

		$api_key   = wp_stream_filter_input( INPUT_GET, 'api_key' );
		$site_uuid = wp_stream_filter_input( INPUT_GET, 'site_uuid' );

		// Connect
		if ( ! empty( $api_key ) && ! empty( $site_uuid ) ) {
			add_action( 'admin_init', array( __CLASS__, 'save_api_authentication' ) );
		}

		// Disconnect
		if ( self::ACCOUNT_PAGE_SLUG === wp_stream_filter_input( INPUT_GET, 'page' ) && '1' === wp_stream_filter_input( INPUT_GET, 'disconnect' ) ) {
			add_action( 'admin_init', array( __CLASS__, 'remove_api_authentication' ) );
		}

		self::$disable_access = apply_filters( 'wp_stream_disable_admin_access', false );

		// Register settings page
		if ( ! self::$disable_access ) {
			add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		}

		// Admin notices
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );

		// Show connect notice on dashboard and plugins pages
		add_action( 'load-index.php', array( __CLASS__, 'prepare_connect_notice' ) );
		add_action( 'load-plugins.php', array( __CLASS__, 'prepare_connect_notice' ) );

		// Add admin body class
		add_filter( 'admin_body_class', array( __CLASS__, 'admin_body_class' ) );

		// Plugin action links
		add_filter( 'plugin_action_links', array( __CLASS__, 'plugin_action_links' ), 10, 2 );

		// Load admin scripts and styles
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_menu_css' ) );

		// Catch list table results
		add_filter( 'wp_stream_query_results', array( __CLASS__, 'mark_as_read' ), 10, 3 );

		// Add user option for enabling unread counts
		add_action( 'show_user_profile', array( __CLASS__, 'unread_count_user_option' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'unread_count_user_option' ) );

		// Save unread counts user option
		add_action( 'personal_options_update', array( __CLASS__, 'save_unread_count_user_option' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_unread_count_user_option' ) );

		// Delete user-specific transient when user is deleted
		add_action( 'delete_user', array( __CLASS__, 'delete_unread_count_transient' ), 10, 1 );

		// Reset Streams settings
		add_action( 'wp_ajax_wp_stream_defaults', array( __CLASS__, 'wp_ajax_defaults' ) );

		// Ajax authors list
		add_action( 'wp_ajax_wp_stream_filters', array( __CLASS__, 'ajax_filters' ) );

		// Ajax author's name by ID
		add_action( 'wp_ajax_wp_stream_get_filter_value_by_id', array( __CLASS__, 'get_filter_value_by_id' ) );
	}

	/**
	 * Prepare the Connect to Stream prompt
	 *
	 * @return void
	 */
	public static function prepare_connect_notice() {
		if ( ! WP_Stream::is_connected() && ! WP_Stream::is_development_mode() ) {
			wp_enqueue_style( 'wp-stream-connect', WP_STREAM_URL . 'ui/css/connect.css', array(), WP_Stream::VERSION );
			wp_enqueue_script( 'wp-stream-connect', WP_STREAM_URL . 'ui/js/connect.js', array(), WP_Stream::VERSION );
			add_action( 'admin_notices', array( __CLASS__, 'admin_connect_notice' ) );
		}
	}

	/**
	 * Prompt the user to connect to Stream
	 *
	 * @return void
	 */
	public static function admin_connect_notice() {
		if ( ! current_user_can( self::SETTINGS_CAP ) ) {
			return;
		}

		$dismiss_and_deactivate_url = wp_nonce_url( 'plugins.php?action=deactivate&plugin=' . WP_STREAM_PLUGIN, 'deactivate-plugin_' . WP_STREAM_PLUGIN );
		?>
		<div id="stream-message" class="updated stream-connect" style="display:block !important;">

			<div class="stream-message-container">

				<div class="stream-button-container">
					<a href="<?php echo esc_url( self::$connect_url ) ?>" class="stream-button"><i class="stream-icon"></i><?php _e( 'Connect to Stream', 'stream' ) ?></a>
				</div>

				<div class="stream-message-text">
					<h4><?php esc_html_e( 'Stream is almost ready!', 'stream' ) ?></h4>
					<p>
						<?php
						$tooltip = sprintf(
							esc_html__( 'Stream only uses your WordPress.com ID during sign up to authorize your account. You can sign up for free at %swordpress.com/signup%s.', 'stream' ),
							'<a href="https://signup.wordpress.com/signup/?user=1" target="_blank">',
							'</a>'
						);
						echo wp_kses_post(
							sprintf(
								esc_html__( 'Connect to Stream with your %sWordPress.com ID%s to see every change made to your site in beautifully organized detail.', 'stream' ),
								'<span class="wp-stream-tooltip-text">',
								'</span><span class="wp-stream-tooltip">' . $tooltip . '</span>' // xss ok
							)
						);
						?>
					</p>
				</div>

				<div class="clear"></div>

			</div>

		</div>
		<?php
	}

	/**
	 * Output specific update
	 *
	 * @action admin_notices
	 * @return string
	 */
	public static function admin_notices() {
		$message = wp_stream_filter_input( INPUT_GET, 'message' );
		$notice  = false;

		switch ( $message ) {
			case 'settings_reset':
				$notice = esc_html__( 'All site settings have been successfully reset.', 'stream' );
				break;
			case 'connected':
				if ( ! WP_Stream_Migrate::show_migrate_notice() ) {
					$notice = sprintf(
						'<strong>%s</strong></p><p>%s',
						esc_html__( 'You have successfully connected to Stream!', 'stream' ),
						esc_html__( 'Check back here regularly to see a history of the changes being made to this site.', 'stream' )
					);
				}
				break;
		}

		if ( $notice ) {
			WP_Stream::notice( $notice, false );
		}
	}

	/**
	 * Return URL used for account level actions
	 *
	 * @return string
	 */
	public static function account_url( $path = '' ) {
		$account_url = add_query_arg(
			array(
				'auth'       => 'true',
				'plugin_url' => urlencode(
					add_query_arg(
						array(
							'page' => self::RECORDS_PAGE_SLUG,
						),
						admin_url( self::ADMIN_PARENT_PAGE )
					)
				),
			),
			esc_url_raw( sprintf( '%s/dashboard/%s', untrailingslashit( self::PUBLIC_URL ), untrailingslashit( $path ) ) )
		);

		return $account_url;
	}

	/**
	 * Register menu page
	 *
	 * @action admin_menu
	 * @return bool|void
	 */
	public static function register_menu() {
		if ( WP_Stream::is_connected() || WP_Stream::is_development_mode() ) {
			$unread_count = self::get_unread_count();
			$menu_title   = __( 'Stream', 'stream' );

			if ( self::unread_enabled_for_user() && ! empty( $unread_count ) ) {
				$formatted_count = ( $unread_count > 99 ) ? __( '99 +', 'stream' ) : absint( $unread_count );
				$menu_title      = sprintf( '%s <span class="update-plugins count-%d"><span class="plugin-count">%s</span></span>', esc_html( $menu_title ), absint( $unread_count ), esc_html( $formatted_count ) );
			}

			self::$screen_id['main'] = add_menu_page(
				__( 'Stream', 'stream' ),
				$menu_title,
				self::VIEW_CAP,
				self::RECORDS_PAGE_SLUG,
				array( __CLASS__, 'render_stream_page' ),
				'div',
				apply_filters( 'wp_stream_menu_position', '2.999999' ) // Using longtail decimal string to reduce the chance of position conflicts, see Codex
			);

			self::$screen_id['settings'] = add_submenu_page(
				self::RECORDS_PAGE_SLUG,
				__( 'Stream Settings', 'stream' ),
				__( 'Settings', 'stream' ),
				self::SETTINGS_CAP,
				self::SETTINGS_PAGE_SLUG,
				array( __CLASS__, 'render_settings_page' )
			);

			self::$screen_id['account'] = add_submenu_page(
				self::RECORDS_PAGE_SLUG,
				__( 'Stream Account', 'stream' ),
				__( 'Account', 'stream' ),
				self::SETTINGS_CAP,
				self::ACCOUNT_PAGE_SLUG,
				array( __CLASS__, 'render_account_page' )
			);
		} else {
			self::$screen_id['connect'] = add_menu_page(
				__( 'Connect to Stream', 'stream' ),
				__( 'Stream', 'stream' ),
				self::SETTINGS_CAP,
				self::RECORDS_PAGE_SLUG,
				array( __CLASS__, 'render_connect_page' ),
				'div',
				apply_filters( 'wp_stream_menu_position', '2.999999' ) // Using longtail decimal string to reduce the chance of position conflicts, see Codex
			);
		}

		if ( isset( self::$screen_id['main'] ) ) {
			/**
			 * Fires just before the Stream list table is registered.
			 *
			 * @since 1.4.0
			 *
			 * @return void
			 */
			do_action( 'wp_stream_admin_menu_screens' );

			// Register the list table early, so it associates the column headers with 'Screen settings'
			add_action( 'load-' . self::$screen_id['main'], array( __CLASS__, 'register_list_table' ) );
		}
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
		wp_register_script( 'select2', WP_STREAM_URL . 'ui/lib/select2/select2.js', array( 'jquery' ), '3.5.2', true );
		wp_register_style( 'select2', WP_STREAM_URL . 'ui/lib/select2/select2.css', array(), '3.5.2' );
		wp_register_script( 'timeago', WP_STREAM_URL . 'ui/lib/timeago/jquery.timeago.js', array(), '1.4.1', true );

		$locale    = strtolower( substr( get_locale(), 0, 2 ) );
		$file_tmpl = 'ui/lib/timeago/locales/jquery.timeago.%s.js';

		if ( file_exists( WP_STREAM_DIR . sprintf( $file_tmpl, $locale ) ) ) {
			wp_register_script( 'timeago-locale', WP_STREAM_URL . sprintf( $file_tmpl, $locale ), array( 'timeago' ), '1' );
		} else {
			wp_register_script( 'timeago-locale', WP_STREAM_URL . sprintf( $file_tmpl, 'en' ), array( 'timeago' ), '1' );
		}

		wp_enqueue_style( 'wp-stream-admin', WP_STREAM_URL . 'ui/css/admin.css', array(), WP_Stream::VERSION );

		$script_screens = array( 'plugins.php', 'user-edit.php', 'user-new.php', 'profile.php' );

		if ( 'index.php' === $hook ) {
			wp_enqueue_script( 'wp-stream-dashboard', WP_STREAM_URL . 'ui/js/dashboard.js', array( 'jquery' ), WP_Stream::VERSION );
			wp_enqueue_script( 'wp-stream-live-updates', WP_STREAM_URL . 'ui/js/live-updates.js', array( 'jquery', 'heartbeat' ), WP_Stream::VERSION );
		} elseif ( in_array( $hook, self::$screen_id ) || in_array( $hook, $script_screens ) ) {
			wp_enqueue_script( 'select2' );
			wp_enqueue_style( 'select2' );

			wp_enqueue_script( 'timeago' );
			wp_enqueue_script( 'timeago-locale' );

			wp_enqueue_script( 'wp-stream-admin', WP_STREAM_URL . 'ui/js/admin.js', array( 'jquery', 'select2' ), WP_Stream::VERSION );
			wp_enqueue_script( 'wp-stream-live-updates', WP_STREAM_URL . 'ui/js/live-updates.js', array( 'jquery', 'heartbeat' ), WP_Stream::VERSION );

			wp_localize_script(
				'wp-stream-admin',
				'wp_stream',
				array(
					'i18n'       => array(
						'confirm_defaults' => __( 'Are you sure you want to reset all site settings to default? This cannot be undone.', 'stream' ),
					),
					'locale'     => esc_js( $locale ),
					'gmt_offset' => get_option( 'gmt_offset' ),
				)
			);
		}

		wp_localize_script(
			'wp-stream-live-updates',
			'wp_stream_live_updates',
			array(
				'current_screen'      => $hook,
				'current_page'        => isset( $_GET['paged'] ) ? esc_js( $_GET['paged'] ) : '1',
				'current_order'       => isset( $_GET['order'] ) ? esc_js( $_GET['order'] ) : 'desc',
				'current_query'       => json_encode( $_GET ),
				'current_query_count' => count( $_GET ),
			)
		);

		if ( WP_Stream_Migrate::show_migrate_notice() ) {
			$limit                = absint( WP_Stream_Migrate::$limit );
			$record_count         = absint( WP_Stream_Migrate::$record_count );
			$estimated_time       = ( $limit < $record_count ) ? round( ( ( $record_count / $limit ) * ( 0.04 * $limit ) ) / 60 ) : 0;
			$migrate_time_message = ( $estimated_time > 1 ) ? sprintf( __( 'This will take about %d minutes.', 'stream' ), absint( $estimated_time ) ) : __( 'This could take a few minutes.', 'stream' );
			$delete_time_message  = ( $estimated_time > 1 && is_multisite() ) ? sprintf( __( 'This will take about %d minutes.', 'stream' ), absint( $estimated_time ) ) : __( 'This could take a few minutes.', 'stream' );

			wp_enqueue_script( 'wp-stream-migrate', WP_STREAM_URL . 'ui/js/migrate.js', array( 'jquery' ), WP_Stream::VERSION );
			wp_localize_script(
				'wp-stream-migrate',
				'wp_stream_migrate',
				array(
					'i18n'         => array(
						'migrate_process_title'    => __( 'Migrating Stream Records', 'stream' ),
						'delete_process_title'     => __( 'Deleting Stream Records', 'stream' ),
						'error_message'            => __( 'An unknown error occurred during migration. Please try again later or contact support.', 'stream' ),
						'migrate_process_message'  => __( 'Please do not exit this page until the process has completed.', 'stream' ) . ' ' . esc_html( $migrate_time_message ),
						'delete_process_message'   => __( 'Please do not exit this page until the process has completed.', 'stream' ) . ' ' . esc_html( $delete_time_message ),
						'confirm_start_migrate'    => ( $estimated_time > 1 ) ? sprintf( __( 'Please note: This process will take about %d minutes to complete.', 'stream' ), absint( $estimated_time ) ) : __( 'Please note: This process could take a few minutes to complete.', 'stream' ),
						'confirm_migrate_reminder' => __( 'Please note: Your existing records will not appear in Stream until you have migrated them to your account.', 'stream' ),
						'confirm_delete_records'   => sprintf( __( 'Are you sure you want to delete all %s existing Stream records without migrating? This will take %s minutes and cannot be undone.', 'stream' ), number_format( WP_Stream_Migrate::$record_count ), ( $estimated_time > 1 && is_multisite() ) ? sprintf( __( 'about %d', 'stream' ), absint( $estimated_time ) ) : __( 'a few', 'stream' ) ),
					),
					'chunk_size'   => absint( $limit ),
					'record_count' => absint( $record_count ),
					'nonce'        => wp_create_nonce( 'wp_stream_migrate-' . absint( get_current_blog_id() ) . absint( get_current_user_id() ) ),
				)
			);
		}

		/**
		 * The maximum number of items that can be updated in bulk without receiving a warning.
		 *
		 * Stream watches for bulk actions performed in the WordPress Admin (such as updating
		 * many posts at once) and warns the user before proceeding if the number of items they
		 * are attempting to update exceeds this threshold value. Since Stream will try to save
		 * a log for each item, it will take longer than usual to complete the operation.
		 *
		 * The default threshold is 100 items.
		 *
		 * @return int
		 */
		$bulk_actions_threshold = apply_filters( 'wp_stream_bulk_actions_threshold', 100 );

		wp_enqueue_script( 'wp-stream-global', WP_STREAM_URL . 'ui/js/global.js', array( 'jquery' ), WP_Stream::VERSION );
		wp_localize_script(
			'wp-stream-global',
			'wp_stream_global',
			array(
				'bulk_actions' => array(
					'i18n' => array(
						'confirm_action' => sprintf( __( 'Are you sure you want to perform bulk actions on over %s items? This process could take a while to complete.', 'stream' ), number_format( absint( $bulk_actions_threshold ) ) ),
					),
					'threshold' => absint( $bulk_actions_threshold ),
				),
				'plugins_screen_url' => self_admin_url( 'plugins.php#stream' ),
			)
		);
	}

	/**
	 * Check whether or not the current admin screen belongs to Stream
	 *
	 * @return bool
	 */
	public static function is_stream_screen() {
		global $typenow;

		if ( is_admin() && ( false !== strpos( wp_stream_filter_input( INPUT_GET, 'page' ), self::RECORDS_PAGE_SLUG ) || WP_Stream_Notifications_Post_Type::POSTTYPE === $typenow ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Add a specific body class to all Stream admin screens
	 *
	 * @filter admin_body_class
	 *
	 * @param  string $classes
	 *
	 * @return string $classes
	 */
	public static function admin_body_class( $classes ) {
		if ( self::is_stream_screen() ) {
			$classes .= sprintf( ' %s ', self::ADMIN_BODY_CLASS );

			if ( WP_Stream::is_connected() || WP_Stream::is_development_mode() ) {
				$classes .= ' wp_stream_connected ';
			} else {
				$classes .= ' wp_stream_disconnected ';
			}

			if ( WP_Stream_API::is_restricted() ) {
				$classes .= ' wp_stream_restricted ';
			}
		}

		$settings_pages = array( self::SETTINGS_PAGE_SLUG );

		if ( isset( $_GET['page'] ) && in_array( $_GET['page'], $settings_pages ) ) {
			$classes .= sprintf( ' %s ', self::SETTINGS_PAGE_SLUG );
		}

		return $classes;
	}

	/**
	 * Add menu styles for various WP Admin skins
	 *
	 * @uses wp_add_inline_style()
	 * @action admin_enqueue_scripts
	 * @return bool true on success false on failure
	 */
	public static function admin_menu_css() {
		wp_register_style( 'jquery-ui', '//ajax.googleapis.com/ajax/libs/jqueryui/1.10.1/themes/base/jquery-ui.css', array(), '1.10.1' );
		wp_register_style( 'wp-stream-datepicker', WP_STREAM_URL . 'ui/css/datepicker.css', array( 'jquery-ui' ), WP_Stream::VERSION );
		wp_register_style( 'wp-stream-icons', WP_STREAM_URL . 'ui/stream-icons/style.css', array(), WP_Stream::VERSION );

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
	 * Check whether or not the current user should see the unread counter.
	 *
	 * Defaults to TRUE if user option does not exist.
	 *
	 * @param int $user_id (optional)
	 *
	 * @return bool
	 */
	public static function unread_enabled_for_user( $user_id = 0 ) {
		$user_id = empty( $user_id ) ? get_current_user_id() : $user_id;
		$enabled = get_user_meta( $user_id, self::UNREAD_COUNT_OPTION_KEY, true );
		$enabled = ( 'off' !== $enabled );

		return (bool) $enabled;
	}

	/**
	 * Get the unread count for the current user.
	 *
	 * Results are cached in transient with a 5 min TTL.
	 *
	 * @return int
	 */
	public static function get_unread_count() {
		if ( ! self::unread_enabled_for_user() ) {
			return false;
		}

		$user_id   = get_current_user_id();
		$cache_key = sprintf( '%s_%d', self::UNREAD_COUNT_OPTION_KEY, $user_id );

		if ( false === ( $count = get_transient( $cache_key ) ) ) {
			$count     = 0;
			$last_read = get_user_meta( $user_id, self::LAST_READ_OPTION_KEY, true );

			if ( ! empty( $last_read ) ) {
				$args = array(
					'records_per_page' => 101,
					'author__not_in'   => array( $user_id ), // Ignore changes authored by the current user
					'date_after'       => date( 'c', strtotime( $last_read . ' + 1 second' ) ), // Bump time to bypass gte issue
					'fields'           => array( 'created' ), // We don't need the entire record
				);

				$unread_records = wp_stream_query( $args );

				$count = empty( $unread_records ) ? 0 : count( $unread_records );
			}

			set_transient( $cache_key, $count, 5 * 60 ); // TTL 5 min
		}

		return absint( $count );
	}

	/**
	 * Mark records as read based on Records screen results
	 *
	 * @filter wp_stream_query_results
	 *
	 * @param array $results
	 * @param array $query
	 * @param array $fields
	 *
	 * @return array
	 */
	public static function mark_as_read( $results, $query, $fields ) {
		if ( ! is_admin() ) {
			return $results;
		}

		$screen        = get_current_screen();
		$is_list_table = ( isset( $screen->id ) && sprintf( 'toplevel_page_%s', self::RECORDS_PAGE_SLUG ) === $screen->id );
		$is_first_page = empty( $query['from'] );
		$is_date_desc  = ( isset( $query['sort'][0]['created']['order'] ) && 'desc' === $query['sort'][0]['created']['order'] );

		if ( $is_list_table && $is_first_page && $is_date_desc ) {
			$user_id   = get_current_user_id();
			$cache_key = sprintf( '%s_%d', self::UNREAD_COUNT_OPTION_KEY, $user_id );

			if ( self::unread_enabled_for_user() && isset( $results[0]->created ) ) {
				update_user_meta( $user_id, self::LAST_READ_OPTION_KEY, date( 'c', strtotime( $results[0]->created ) ) );
			}

			set_transient( $cache_key, 0 ); // No expiration
		}

		return $results;
	}

	/**
	 * Output for Stream Unread Count field in user profiles.
	 *
	 * @action show_user_profile
	 * @action edit_user_profile
	 *
	 * @param WP_User $user
	 *
	 * @return void
	 */
	public static function unread_count_user_option( $user ) {
		if ( ! array_intersect( $user->roles, WP_Stream_Settings::$options['general_role_access'] ) ) {
			return;
		}

		$unread_enabled = self::unread_enabled_for_user();
		?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="<?php echo esc_attr( self::UNREAD_COUNT_OPTION_KEY ) ?>">
						<?php esc_html_e( 'Stream Unread Count', 'stream' ) ?>
					</label>
				</th>
				<td>
					<label for="<?php echo esc_attr( self::UNREAD_COUNT_OPTION_KEY ) ?>">
						<input type="checkbox" name="<?php echo esc_attr( self::UNREAD_COUNT_OPTION_KEY ) ?>" id="<?php echo esc_attr( self::UNREAD_COUNT_OPTION_KEY ) ?>" value="1" <?php checked( $unread_enabled ) ?>>
						<?php esc_html_e( 'Enabled', 'stream' ) ?>
					</label>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Saves unread count user meta option in profiles.
	 *
	 * @action personal_options_update
	 * @action edit_user_profile_update
	 *
	 * @param $user_id
	 *
	 * @return void
	 */
	public static function save_unread_count_user_option( $user_id ) {
		$enabled = wp_stream_filter_input( INPUT_POST, self::UNREAD_COUNT_OPTION_KEY );
		$enabled = ( '1' === $enabled ) ? 'on' : 'off';

		update_user_meta( $user_id, self::UNREAD_COUNT_OPTION_KEY, $enabled );
	}

	/**
	 * Delete user-specific transient when a user is deleted
	 *
	 * @action delete_user
	 *
	 * @param int $user_id
	 *
	 * @return void
	 */
	public static function delete_unread_count_transient( $user_id ) {
		$cache_key = sprintf( '%s_%d', self::UNREAD_COUNT_OPTION_KEY, $user_id );

		delete_transient( $cache_key );
	}

	/**
	 * @filter plugin_action_links
	 */
	public static function plugin_action_links( $links, $file ) {
		if ( plugin_basename( WP_STREAM_DIR . 'stream.php' ) === $file ) {
			$admin_page_url = add_query_arg( array( 'page' => self::SETTINGS_PAGE_SLUG ), admin_url( self::ADMIN_PARENT_PAGE ) );

			$links[] = sprintf( '<a href="%s">%s</a>', esc_url( $admin_page_url ), esc_html__( 'Settings', 'stream' ) );
		}

		return $links;
	}

	public static function save_api_authentication() {
		$home_url           = str_ireplace( array( 'http://', 'https://' ), '', home_url() );
		$connect_nonce_name = 'stream_connect_site-' . sanitize_key( $home_url );

		if ( ! isset( $_GET['api_key'] ) || ! isset( $_GET['site_uuid'] ) ) {
			wp_die( 'There was a problem connecting to Stream. Please try again later.', 'stream' );
		}

		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], $connect_nonce_name ) ) {
			wp_die( 'Doing it wrong.', 'stream' );
		}

		WP_Stream::$api->site_uuid = wp_stream_filter_input( INPUT_GET, 'site_uuid' );
		WP_Stream::$api->api_key   = wp_stream_filter_input( INPUT_GET, 'api_key' );

		// Verify the API Key and Site UUID
		$site = WP_Stream::$api->get_site();

		WP_Stream_API::$restricted = ( ! isset( $site->plan->type ) || 'free' === $site->plan->type ) ? 1 : 0;

		if ( ! isset( $site->site_id ) ) {
			wp_die( 'There was a problem verifying your site with Stream. Please try again later.', 'stream' );
		}

		if ( ! WP_Stream_API::$restricted ) {
			WP_Stream_Notifications::$instance->on_activation();
		}

		update_option( WP_Stream_API::SITE_UUID_OPTION_KEY, WP_Stream::$api->site_uuid );
		update_option( WP_Stream_API::API_KEY_OPTION_KEY, WP_Stream::$api->api_key );
		update_option( WP_Stream_API::RESTRICTED_OPTION_KEY, WP_Stream_API::$restricted );

		do_action( 'wp_stream_site_connected', WP_Stream::$api->site_uuid, WP_Stream::$api->api_key, get_current_blog_id() );

		$redirect_url = add_query_arg(
			array(
				'page'    => self::RECORDS_PAGE_SLUG,
				'message' => 'connected',
			),
			admin_url( self::ADMIN_PARENT_PAGE )
		);

		wp_safe_redirect( $redirect_url );

		exit;
	}

	public static function remove_api_authentication() {
		delete_option( WP_Stream_API::SITE_UUID_OPTION_KEY );
		delete_option( WP_Stream_API::API_KEY_OPTION_KEY );

		do_action( 'wp_stream_site_disconnected', WP_Stream::$api->site_uuid, WP_Stream::$api->api_key, get_current_blog_id() );

		WP_Stream::$api->site_uuid = false;
		WP_Stream::$api->api_key   = false;

		if ( '1' !== wp_stream_filter_input( INPUT_GET, 'disconnect' ) ) {
			return;
		}

		$redirect_url = add_query_arg(
			array(
				'page' => self::RECORDS_PAGE_SLUG,
			),
			admin_url( self::ADMIN_PARENT_PAGE )
		);

		wp_safe_redirect( $redirect_url );

		exit;
	}

	public static function get_testimonials() {
		$testimonials = get_site_transient( 'wp_stream_testimonials' );

		if ( false !== $testimonials ) {
			return $testimonials;
		}

		$url     = sprintf( '%s/wp-content/themes/wp-stream.com/assets/testimonials.json', untrailingslashit( self::PUBLIC_URL ) );
		$request = wp_remote_request( esc_url_raw( $url ), array( 'sslverify' => false ) );

		if ( ! is_wp_error( $request ) && $request['response']['code'] === 200 ) {
			$testimonials = json_decode( $request['body'], true );
		} else {
			$testimonials = false;
		}

		// Cache failed attempts as zero, to distinguish them from expired transients
		if ( ! $testimonials ) {
			$testimonials = 0;
		}

		set_site_transient( 'wp_stream_testimonials', $testimonials, WEEK_IN_SECONDS );

		return $testimonials;
	}

	/**
	 * Render settings page
	 *
	 * @return void
	 */
	public static function render_settings_page() {

		$option_key  = WP_Stream_Settings::$option_key;
		$form_action = apply_filters( 'wp_stream_settings_form_action', admin_url( 'options.php' ) );

		$page_title       = apply_filters( 'wp_stream_settings_form_title', get_admin_page_title() );
		$page_description = apply_filters( 'wp_stream_settings_form_description', '' );

		$sections   = WP_Stream_Settings::get_fields();
		$active_tab = wp_stream_filter_input( INPUT_GET, 'tab' );

		wp_enqueue_script(
			'stream-settings',
			plugins_url( '../ui/js/settings.js', __FILE__ ),
			array( 'jquery' ),
			WP_Stream::VERSION,
			true
		);
		?>
		<div class="wrap">
			<h2><?php echo esc_html( $page_title ) ?></h2>

			<?php if ( ! empty( $page_description ) ) : ?>
				<p><?php echo esc_html( $page_description ) ?></p>
			<?php endif; ?>

			<?php settings_errors() ?>

			<?php if ( count( $sections ) > 1 ) : ?>
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
			<?php endif; ?>

			<div class="nav-tab-content" id="tab-content-settings">
				<form method="post" action="<?php echo esc_attr( $form_action ) ?>" enctype="multipart/form-data">
					<div class="settings-sections">
		<?php
		$i = 0;
		foreach ( $sections as $section => $data ) {
			$i++;
			$is_active = ( ( 1 === $i && ! $active_tab ) || $active_tab === $section );
			if ( $is_active ) {
				settings_fields( $option_key );
				do_settings_sections( $option_key );
			}
		}
		?>
					</div>
					<?php submit_button() ?>
				</form>
			</div>
		</div>
	<?php
	}

	/**
	 * Render account page
	 *
	 * @return void
	 */
	public static function render_account_page() {
		$page_title   = apply_filters( 'wp_stream_account_page_title', get_admin_page_title() );
		$date_format  = get_option( 'date_format' );
		$site_details = WP_Stream::$api->get_site();
		?>
		<div class="wrap">
			<h2><?php echo esc_html( $page_title ) ?></h2>
			<div class="postbox">
		<?php
		if ( ! $site_details ) {
			?>
				<h3><?php esc_html_e( 'Error retrieving account details.' ); ?></h3>
				<div class="plan-details">
					<p><?php esc_html_e( 'If this problem persists, please disconnect from Stream and try connecting again.', 'stream' ); ?></p>
				</div>
				<div class="plan-actions submitbox">
					<a class="submitdelete disconnect" href="<?php echo esc_url( add_query_arg( 'disconnect', '1' ) ); ?>">Disconnect</a>
				</div>
			<?php
		} else {
			$plan_label = __( 'Free', 'stream' );

			if ( isset( $site_details->plan ) ) {
				if ( 0 === strpos( $site_details->plan->type, 'pro' ) ) {
					$plan_label = __( 'Pro', 'stream' );
				} elseif ( 0 === strpos( $site_details->plan->type, 'standard' ) ) {
					$plan_label = __( 'Standard', 'stream' );
				}
			}

			if ( 'free' !== $site_details->plan->type ) {
				$next_billing_label = sprintf(
					_x( '$%1$s on %2$s', '1: Price, 2: Renewal date', 'stream' ),
					isset( $site_details->plan->amount ) ? $site_details->plan->amount : 0,
					isset( $site_details->expiry->date ) ? date_i18n( $date_format, strtotime( $site_details->expiry->date ) ) : __( 'N/A', 'stream' )
				);
			}

			$retention_label = '';

			if ( 0 == $site_details->plan->retention ) { // Loose comparison needed
				$retention_label = __( 'Unlimited', 'stream' );
			} else {
				$retention_label = sprintf(
					_n( '1 Day', '%s Days', $site_details->plan->retention, 'stream' ),
					$site_details->plan->retention
				);
			}
			?>
				<h3><?php echo esc_html( $site_details->site_url ); ?></h3>
				<div class="plan-details">
					<table class="form-table">
						<tbody>
							<tr>
								<th><?php _e( 'Plan', 'stream' ); ?></th>
								<td><?php echo esc_html( $plan_label ); ?></td>
							</tr>
							<tr>
								<th><?php _e( 'Activity History', 'stream' ); ?></th>
								<td><?php echo esc_html( $retention_label ); ?></td>
							</tr>
							<?php if ( 'free' !== $site_details->plan->type ) : ?>
							<tr>
								<th><?php _e( 'Next Billing', 'stream' ); ?></th>
								<td><?php echo esc_html( $next_billing_label ); ?></td>
							</tr>
							<?php endif; ?>
							<tr>
								<th><?php _e( 'Created', 'stream' ); ?></th>
								<td><?php echo esc_html( date_i18n( $date_format, strtotime( $site_details->created ) ) ); ?></td>
							</tr>
							<tr>
								<th><?php _e( 'Site ID', 'stream' ); ?></th>
								<td>
									<code class="site-uuid"><?php echo esc_html( WP_Stream::$api->site_uuid ); ?></code>
								</td>
							</tr>
							<tr>
								<th><?php _e( 'API Key', 'stream' ); ?></th>
								<td>
									<code class="api-key"><?php echo esc_html( WP_Stream::$api->api_key ); ?></code>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
				<div class="plan-actions submitbox">
					<?php if ( 'free' === $site_details->plan->type ) : ?>
						<a href="<?php echo esc_url( WP_Stream_Admin::account_url( sprintf( 'upgrade/?site_uuid=%s', WP_Stream::$api->site_uuid ) ) ); ?>" class="button button-primary button-large"><?php _e( 'Upgrade to Pro', 'stream' ) ?></a>
					<?php else : ?>
						<a href="<?php echo esc_url( sprintf( '%s/dashboard/?site_uuid=%s', self::PUBLIC_URL, WP_Stream::$api->site_uuid ) ); ?>" class="button button-primary button-large" target="_blank"><?php _e( 'Modify This Plan', 'stream' ) ?></a>
					<?php endif; ?>
						<a class="submitdelete disconnect" href="<?php echo esc_url( add_query_arg( 'disconnect', '1' ) ); ?>">Disconnect</a>
				</div>
			<?php
		}
		?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render connect page
	 *
	 * @return void
	 */
	public static function render_connect_page() {
		$page_title   = apply_filters( 'wp_stream_connect_page_title', get_admin_page_title() );

		if ( $testimonials = self::get_testimonials() ) {
			$testimonial = $testimonials[ array_rand( $testimonials ) ];
		}

		wp_enqueue_style( 'wp-stream-connect', WP_STREAM_URL . 'ui/css/connect.css', array(), WP_Stream::VERSION );
		wp_enqueue_script( 'wp-stream-connect', WP_STREAM_URL . 'ui/js/connect.js', array(), WP_Stream::VERSION );
		?>
		<div id="wp-stream-connect">

			<div class="wrap">

				<div class="stream-connect-container">
					<a href="<?php echo esc_url( self::$connect_url ) ?>" class="stream-button"><i class="stream-icon"></i><?php _e( 'Connect to Stream', 'stream' ) ?></a>
					<p>
						<?php
						$tooltip = sprintf(
							esc_html__( 'Stream only uses your WordPress.com ID during sign up to authorize your account. You can sign up for free at %swordpress.com/signup%s.', 'stream' ),
							'<a href="https://signup.wordpress.com/signup/?user=1" target="_blank">',
							'</a>'
						);
						wp_kses_post(
							printf(
								esc_html__( 'with your %sWordPress.com ID%s', 'stream' ),
								'<span class="wp-stream-tooltip-text">',
								'</span><span class="wp-stream-tooltip">' . $tooltip . '</span>' // xss ok
							)
						);
						?>
					</p>
				</div>

				<?php if ( isset( $testimonial ) ) : ?>
					<div class="stream-quotes-container">
						<p class="stream-quote">&ldquo;<?php echo esc_html( $testimonial['quote'] ) ?>&rdquo;</p>
						<p class="stream-quote-author">&dash; <?php echo esc_html( $testimonial['author'] ) ?>, <a class="stream-quote-organization" href="<?php echo esc_url( $testimonial['link'] ) ?>"><?php echo esc_html( $testimonial['organization'] ) ?></a></p>
					</div>
				<?php endif; ?>

			</div>

		</div>
		<?php
	}

	public static function register_list_table() {
		self::$list_table = new WP_Stream_List_Table( array( 'screen' => self::$screen_id['main'] ) );
	}

	public static function render_stream_page() {
		$page_title = __( 'Stream Records', 'stream' );

		self::$list_table->prepare_items();

		echo '<div class="wrap">';

		printf( '<h2>%s</h2>', __( 'Stream Records', 'stream' ) ); // xss ok

		self::$list_table->display();

		echo '</div>';
	}

	public static function wp_ajax_defaults() {
		check_ajax_referer( 'stream_nonce', 'wp_stream_nonce' );

		if ( current_user_can( self::SETTINGS_CAP ) ) {
			self::reset_stream_settings();

			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'wp_stream_settings',
						'message' => 'settings_reset',
					),
					admin_url( self::ADMIN_PARENT_PAGE )
				)
			);

			exit;
		} else {
			wp_die( "You don't have sufficient privileges to do this action." );
		}
	}

	private static function reset_stream_settings() {
		global $wpdb;

		$blogs = wp_get_sites();

		if ( $blogs ) {
			foreach ( $blogs as $blog ) {
				switch_to_blog( $blog['blog_id'] );
				delete_option( WP_Stream_Settings::OPTION_KEY );
			}
			restore_current_blog();
		}
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
		global $wp_roles;

		$_wp_roles = isset( $wp_roles ) ? $wp_roles : new WP_Roles();

		$user = is_a( $user, 'WP_User' ) ? $user : wp_get_current_user();

		// @see
		// https://github.com/WordPress/WordPress/blob/c67c9565f1495255807069fdb39dac914046b1a0/wp-includes/capabilities.php#L758
		$roles = array_unique(
			array_merge(
				$user->roles,
				array_filter(
					array_keys( $user->caps ),
					array( $_wp_roles, 'is_role' )
				)
			)
		);

		$stream_view_caps = array(
			self::VIEW_CAP,
			WP_Stream_Notifications::VIEW_CAP,
			WP_Stream_Reports::VIEW_CAP,
		);

		foreach ( $caps as $cap ) {
			if ( in_array( $cap, $stream_view_caps ) ) {
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
		$stream_view_caps = array(
			self::VIEW_CAP,
			WP_Stream_Notifications::VIEW_CAP,
			WP_Stream_Reports::VIEW_CAP,
		);

		if ( in_array( $cap, $stream_view_caps ) && self::_role_can_view_stream( $role ) ) {
			$allcaps[ $cap ] = true;
		}

		return $allcaps;
	}

	/**
	 * @action wp_ajax_wp_stream_filters
	 */
	public static function ajax_filters() {
		switch ( wp_stream_filter_input( INPUT_GET, 'filter' ) ) {
			case 'author':
				$users = array_merge(
					array( 0 => (object) array( 'display_name' => 'WP-CLI' ) ),
					get_users()
				);

				// `search` arg for get_users() is not enough
				$users = array_filter(
					$users,
					function ( $user ) {
						return false !== mb_strpos( mb_strtolower( $user->display_name ), mb_strtolower( wp_stream_filter_input( INPUT_GET, 'q' ) ) );
					}
				);

				if ( count( $users ) > self::PRELOAD_AUTHORS_MAX ) {
					$users = array_slice( $users, 0, self::PRELOAD_AUTHORS_MAX );
					// @todo $extra is not used
					$extra = array(
						'id'       => 0,
						'disabled' => true,
						'text'     => sprintf( _n( 'One more result...', '%d more results...', $results_count - self::PRELOAD_AUTHORS_MAX, 'stream' ), $results_count - self::PRELOAD_AUTHORS_MAX ),
					);
				}

				// Get gravatar / roles for final result set
				$results = self::get_authors_record_meta( $users );

				break;
		}
		if ( isset( $results ) ) {
			echo json_encode( array_values( $results ) );
		}
		die();
	}

	/**
	 * @action wp_ajax_wp_stream_get_filter_value_by_id
	 */
	public static function get_filter_value_by_id() {
		$filter = wp_stream_filter_input( INPUT_POST, 'filter' );

		switch ( $filter ) {
			case 'author':
				$id = wp_stream_filter_input( INPUT_POST, 'id' );
				if ( '0' === $id ) {
					$value = 'WP-CLI';
					break;
				}
				$user = get_userdata( $id );
				if ( ! $user || is_wp_error( $user ) ) {
					$value = '';
				} else {
					$value = $user->display_name;
				}
				break;
			default:
				$value = '';
		}

		echo json_encode( $value );

		wp_die();
	}

	public static function get_authors_record_meta( $authors ) {
		$authors_records = array();

		foreach ( $authors as $user_id => $args ) {
			$author   = new WP_Stream_Author( $user_id );
			$disabled = isset( $args['disabled'] ) ? $args['disabled'] : null;

			$authors_records[ $user_id ] = array(
				'text'     => $author->get_display_name(),
				'id'       => $user_id,
				'label'    => $author->get_display_name(),
				'icon'     => $author->get_avatar_src( 32 ),
				'title'    => '',
				'disabled' => $disabled,
			);
		}

		return $authors_records;
	}
}
