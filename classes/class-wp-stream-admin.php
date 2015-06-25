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

	const ADMIN_BODY_CLASS    = 'wp_stream_screen';
	const RECORDS_PAGE_SLUG   = 'wp_stream';
	const SETTINGS_PAGE_SLUG  = 'wp_stream_settings';
	const ADMIN_PARENT_PAGE   = 'admin.php';
	const VIEW_CAP            = 'view_stream';
	const SETTINGS_CAP        = 'manage_options';
	const PRELOAD_AUTHORS_MAX = 50;

	/**
	 * Load the WP Stream admin area hooks
	 *
	 * @return void
	 */
	public static function load() {
		// User and role caps
		add_filter( 'user_has_cap', array( __CLASS__, '_filter_user_caps' ), 10, 4 );
		add_filter( 'role_has_cap', array( __CLASS__, '_filter_role_caps' ), 10, 3 );

		self::$disable_access = apply_filters( 'wp_stream_disable_admin_access', false );

		// Register settings page
		if ( ! self::$disable_access ) {
			add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		}

		// Admin notices
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );

		// Add admin body class
		add_filter( 'admin_body_class', array( __CLASS__, 'admin_body_class' ) );

		// Plugin action links
		add_filter( 'plugin_action_links', array( __CLASS__, 'plugin_action_links' ), 10, 2 );

		// Load admin scripts and styles
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_menu_css' ) );

		// Ajax authors list
		add_action( 'wp_ajax_wp_stream_filters', array( __CLASS__, 'ajax_filters' ) );

		// Ajax author's name by ID
		add_action( 'wp_ajax_wp_stream_get_filter_value_by_id', array( __CLASS__, 'get_filter_value_by_id' ) );
	}

	/**
	 * Output specific update
	 *
	 * @action admin_notices
	 *
	 * @return string
	 */
	public static function admin_notices() {
		$message = wp_stream_filter_input( INPUT_GET, 'message' );
		$notice  = false;

		switch ( $message ) {
			case 'settings_reset':
				$notice = esc_html__( 'All site settings have been successfully reset.', 'stream' );
				break;
		}

		if ( $notice ) {
			WP_Stream::notice( $notice, false );
		}
	}

	/**
	 * Register menu page
	 *
	 * @action admin_menu
	 *
	 * @return bool|void
	 */
	public static function register_menu() {
		/**
		 * Filter the main admin menu title
		 *
		 * @since 2.0.6
		 *
		 * @return string
		 */
		$main_menu_title = apply_filters( 'wp_stream_admin_menu_title', esc_html__( 'Stream', 'stream' ) );

		/**
		 * Filter the main admin menu position
		 *
		 * Note: Using longtail decimal string to reduce the chance of position conflicts, see Codex
		 *
		 * @since 1.4.4
		 *
		 * @return string
		 */
		$main_menu_position = apply_filters( 'wp_stream_menu_position', '2.999999' );

		/**
		 * Filter the main admin page title
		 *
		 * @since 2.0.6
		 *
		 * @return string
		 */
		$main_page_title = apply_filters( 'wp_stream_admin_page_title', esc_html__( 'Stream Records', 'stream' ) );

		self::$screen_id['main'] = add_menu_page(
			$main_page_title,
			$main_menu_title,
			self::VIEW_CAP,
			self::RECORDS_PAGE_SLUG,
			array( __CLASS__, 'render_stream_page' ),
			'div',
			$main_menu_position
		);

		/**
		 * Filter the Settings admin page title
		 *
		 * @since 1.4.0
		 *
		 * @return string
		 */
		$settings_page_title = apply_filters( 'wp_stream_settings_form_title', esc_html__( 'Stream Settings', 'stream' ) );

		self::$screen_id['settings'] = add_submenu_page(
			self::RECORDS_PAGE_SLUG,
			$settings_page_title,
			esc_html__( 'Settings', 'stream' ),
			self::SETTINGS_CAP,
			self::SETTINGS_PAGE_SLUG,
			array( __CLASS__, 'render_settings_page' )
		);

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
						'confirm_defaults' => esc_html__( 'Are you sure you want to reset all site settings to default? This cannot be undone.', 'stream' ),
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
				'current_query'       => wp_stream_json_encode( $_GET ), // xss ok
				'current_query_count' => count( $_GET ),
			)
		);

		if ( WP_Stream_Migrate::show_migrate_notice() ) {
			$limit                = absint( WP_Stream_Migrate::$limit );
			$record_count         = absint( WP_Stream_Migrate::$record_count );
			$estimated_time       = ( $limit < $record_count ) ? round( ( ( $record_count / $limit ) * ( 0.04 * $limit ) ) / 60 ) : 0;
			$migrate_time_message = ( $estimated_time > 1 ) ? sprintf( esc_html__( 'This will take about %d minutes.', 'stream' ), absint( $estimated_time ) ) : esc_html__( 'This could take a few minutes.', 'stream' );
			$delete_time_message  = ( $estimated_time > 1 && is_multisite() ) ? sprintf( esc_html__( 'This will take about %d minutes.', 'stream' ), absint( $estimated_time ) ) : esc_html__( 'This could take a few minutes.', 'stream' );

			wp_enqueue_script( 'wp-stream-migrate', WP_STREAM_URL . 'ui/js/migrate.js', array( 'jquery' ), WP_Stream::VERSION );
			wp_localize_script(
				'wp-stream-migrate',
				'wp_stream_migrate',
				array(
					'i18n'         => array(
						'migrate_process_title'    => esc_html__( 'Migrating Stream Records', 'stream' ),
						'delete_process_title'     => esc_html__( 'Deleting Stream Records', 'stream' ),
						'migrate_process_message'  => esc_html__( 'Please do not exit this page until the process has completed.', 'stream' ) . ' ' . esc_html( $migrate_time_message ),
						'delete_process_message'   => esc_html__( 'Please do not exit this page until the process has completed.', 'stream' ) . ' ' . esc_html( $delete_time_message ),
						'confirm_start_migrate'    => ( $estimated_time > 1 ) ? sprintf( esc_html__( 'Please note: This process will take about %d minutes to complete.', 'stream' ), absint( $estimated_time ) ) : esc_html__( 'Please note: This process could take a few minutes to complete.', 'stream' ),
						'confirm_migrate_reminder' => esc_html__( 'Please note: Your existing records will not appear in Stream until you have migrated them to your account.', 'stream' ),
						'confirm_delete_records'   => sprintf( esc_html__( 'Are you sure you want to delete all %s existing Stream records without migrating? This will take %s minutes and cannot be undone.', 'stream' ), number_format( WP_Stream_Migrate::$record_count ), ( $estimated_time > 1 && is_multisite() ) ? sprintf( esc_html__( 'about %d', 'stream' ), absint( $estimated_time ) ) : esc_html__( 'a few', 'stream' ) ),
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
						'confirm_action' => sprintf( esc_html__( 'Are you sure you want to perform bulk actions on over %s items? This process could take a while to complete.', 'stream' ), number_format( absint( $bulk_actions_threshold ) ) ),
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
	 * @param string $classes
	 *
	 * @return string
	 */
	public static function admin_body_class( $classes ) {
		$stream_classes = array();

		if ( self::is_stream_screen() ) {
			$stream_classes[] = self::ADMIN_BODY_CLASS;

			if ( isset( $_GET['page'] ) ) {
				$stream_classes[] = sanitize_key( $_GET['page'] );
			}
		}

		/**
		 * Filter the Stream admin body classes
		 *
		 * @since 2.0.6
		 *
		 * @return array
		 */
		$stream_classes = apply_filters( 'wp_stream_admin_body_classes', $stream_classes );
		$stream_classes = implode( ' ', array_map( 'trim', $stream_classes ) );

		return sprintf( '%s %s ', $classes, $stream_classes );
	}

	/**
	 * Add menu styles for various WP Admin skins
	 *
	 * @uses wp_add_inline_style()
	 *
	 * @action admin_enqueue_scripts
	 *
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
	 * @filter plugin_action_links
	 */
	public static function plugin_action_links( $links, $file ) {
		if ( plugin_basename( WP_STREAM_DIR . 'stream.php' ) === $file ) {
			$admin_page_url = add_query_arg( array( 'page' => self::SETTINGS_PAGE_SLUG ), admin_url( self::ADMIN_PARENT_PAGE ) );

			$links[] = sprintf( '<a href="%s">%s</a>', esc_url( $admin_page_url ), esc_html__( 'Settings', 'stream' ) );
		}

		return $links;
	}

	/**
	 * Render settings page
	 *
	 * @return void
	 */
	public static function render_settings_page() {
		$option_key  = WP_Stream_Settings::$option_key;
		$form_action = apply_filters( 'wp_stream_settings_form_action', admin_url( 'options.php' ) );

		/**
		 *
		 *
		 * @since
		 *
		 * @return string
		 */
		$page_description = apply_filters( 'wp_stream_settings_form_description', '' );

		$sections   = WP_Stream_Settings::get_fields();
		$active_tab = wp_stream_filter_input( INPUT_GET, 'tab' );

		wp_enqueue_script( 'stream-settings', WP_STREAM_URL . 'ui/js/settings.js', array( 'jquery' ), WP_Stream::VERSION, true );
		?>
		<div class="wrap">
			<h2><?php echo esc_html( get_admin_page_title() ) ?></h2>

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

	public static function register_list_table() {
		self::$list_table = new WP_Stream_List_Table( array( 'screen' => self::$screen_id['main'] ) );
	}

	public static function render_stream_page() {
		self::$list_table->prepare_items();
		?>
		<div class="wrap">
			<h2><?php echo esc_html( get_admin_page_title() ) ?></h2>
			<?php self::$list_table->display() ?>
		</div>
		<?php
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
			echo wp_stream_json_encode( array_values( $results ) ); // xss ok
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

		echo wp_stream_json_encode( $value ); // xss ok

		die();
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
