<?php

class WP_Stream_Notifications {

	/**
	 * Hold Stream Notifications instance
	 *
	 * @var string
	 */
	public static $instance;

	/**
	 * Screen ID for my admin page
	 * @var string
	 */
	public static $screen_id;

	/*
	 * List of registered adapters
	 * @var array
	 */
	public static $adapters = array();

	/**
	 * Matcher object
	 *
	 * @var  WP_Stream_Notifications_Matcher
	 */
	public $matcher;

	/**
	 * Page slug for notifications list table screen
	 *
	 * @const string
	 */
	const NOTIFICATIONS_PAGE_SLUG = 'wp_stream_notifications';
	// Todo: We should probably check whether the current user has caps to
	// view and edit the notifications as this can differ from caps to Stream.

	/**
	 * Capability for the Notifications to be viewed
	 *
	 * @const string
	 */
	const VIEW_CAP = 'view_stream_notifications';

	/**
	 * Return an active instance of this class, and create one if it doesn't exist
	 *
	 * @return WP_Stream_Notifications
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Class constructor
	 */
	private function __construct() {
		define( 'WP_STREAM_NOTIFICATIONS_DIR', WP_STREAM_EXTENSIONS_DIR . 'notifications/' ); // Has trailing slash
		define( 'WP_STREAM_NOTIFICATIONS_URL', WP_STREAM_URL . 'extensions/notifications/' ); // Has trailing slash
		define( 'WP_STREAM_NOTIFICATIONS_INC_DIR', WP_STREAM_NOTIFICATIONS_DIR . 'includes/' ); // Has trailing slash

		if ( ! apply_filters( 'wp_stream_notifications_load', true ) ) {
			return;
		}

		add_action( 'init', array( $this, 'load' ) );

		// Register post type
		require_once WP_STREAM_NOTIFICATIONS_INC_DIR . 'class-wp-stream-notifications-post-type.php';

		WP_Stream_Notifications_Post_Type::get_instance();
	}

	/**
	 * Load our classes, actions/filters, only if our big brother is activated.
	 *
	 * @return void
	 */
	public function load() {
		/**
		 * Filter to disallow access to Stream Notifications
		 *
		 * @return bool
		 */
		$disallow = apply_filters( 'wp_stream_notifications_disallow_site_access', false );

		// Register new submenu
		if ( ! $disallow && ! WP_Stream_Admin::$disable_access && ( WP_Stream::is_connected() || WP_Stream::is_development_mode() ) ) {
			add_action( 'admin_menu', array( $this, 'register_menu' ), 11 );
		}

		// Load settings, enabling extensions to hook in
		require_once WP_STREAM_NOTIFICATIONS_INC_DIR . 'class-wp-stream-notifications-settings.php';

		add_action( 'init', array( 'WP_Stream_Notifications_Settings', 'load' ), 9 );

		if ( WP_Stream::$api->is_restricted() ) {
			add_action( 'in_admin_header', array( __CLASS__, 'in_admin_header' ) );

			return;
		}

		// Load adapter parent class
		require_once WP_STREAM_NOTIFICATIONS_INC_DIR . 'class-wp-stream-notifications-adapter.php';

		/**
		 * Filter the Stream Notification adapters that will be made available
		 *
		 * To include a custom adapter located outside the main adapter directory,
		 * simply add it as an array within the array, with a key being the slug name
		 * and a value being the path to the file:
		 *
		 * e.g. array( 'custom-adapter' => '/path/to/custom-adapter.php' )
		 *
		 * @return array
		 */
		$adapters = apply_filters( 'wp_stream_notifications_adapters', array( 'email', 'push', 'sms' ) );

		// Load all adapters
		foreach ( $adapters as $adapter ) {
			if ( is_array( $adapter ) ) {
				$path = array_shift( $adapter );

				if ( file_exists( $path ) ) {
					include_once $path;
				}
			} else {
				$path = WP_STREAM_NOTIFICATIONS_INC_DIR . 'adapters/class-wp-stream-notifications-adapter-' . $adapter . '.php';

				if ( file_exists( $path ) ) {
					include_once $path;
				}
			}
		}

		// Load matcher
		require_once WP_STREAM_NOTIFICATIONS_INC_DIR . 'class-wp-stream-notifications-matcher.php';

		$this->matcher = new WP_Stream_Notifications_Matcher();
	}

	/**
	 * Display extension preview info
	 *
	 * @action in_admin_header
	 *
	 * @return void
	 */
	public static function in_admin_header() {
		global $typenow;

		if ( WP_Stream_Notifications_Post_Type::POSTTYPE !== $typenow ) {
			return;
		}
		?>
		<div class="stream-example">
			<div class="stream-example-modal">
				<h1><i class="dashicons dashicons-admin-comments"></i> <?php esc_html_e( 'Stream Notifications', 'stream' ) ?></h1>
				<p><?php esc_html_e( 'Get notified instantly when important changes are made on your site.', 'stream' ) ?></p>
				<ul>
					<li><i class="dashicons dashicons-yes"></i> <?php esc_html_e( 'Create notification rules quickly and easily', 'stream' ) ?></li>
					<li><i class="dashicons dashicons-yes"></i> <?php esc_html_e( 'Smart and powerful trigger matching', 'stream' ) ?></li>
					<li><i class="dashicons dashicons-yes"></i> <?php esc_html_e( 'Fully customized e-mail and SMS alerts', 'stream' ) ?></li>
					<li><i class="dashicons dashicons-yes"></i> <?php esc_html_e( 'Push alerts to your smartphone or tablet', 'stream' ) ?></li>
				</ul>
				<a href="<?php echo esc_url( WP_Stream_Admin::account_url( sprintf( 'upgrade?site_uuid=%s', WP_Stream::$api->site_uuid ) ) ); ?>" class="button button-primary button-large"><?php esc_html_e( 'Upgrade to Pro', 'stream' ) ?></a>
			</div>
		</div>
		<?php
	}

	/**
	 * Register Notification menu under Stream's main one
	 *
	 * @action admin_menu
	 *
	 * @return void
	 */
	public function register_menu() {
		self::$screen_id = add_submenu_page(
			WP_Stream_Admin::RECORDS_PAGE_SLUG,
			esc_html__( 'Notifications', 'stream' ),
			esc_html__( 'Notifications', 'stream' ),
			self::VIEW_CAP,
			sprintf( 'edit.php?post_type=%s', WP_Stream_Notifications_Post_Type::POSTTYPE )
		);
	}

	public static function register_adapter( $adapter, $name, $title ) {
		self::$adapters[ $name ] = array(
			'title' => $title,
			'class' => $adapter,
		);
	}

	/**
	 * Do things when being set up for the first time
	 *
	 * @return void
	 */
	public function on_activation() {
		$rules = array_sum( (array) wp_count_posts( WP_Stream_Notifications_Post_Type::POSTTYPE ) );

		if ( empty( $rules ) ) {
			$this->add_sample_rule();
		}
	}

	/**
	 * Add a sample rule, used upon activation
	 *
	 * @return void
	 */
	public function add_sample_rule() {
		$postarr = array(
			'post_title'  => esc_html__( 'Sample Rule', 'stream' ),
			'post_status' => 'draft',
			'post_type'   => WP_Stream_Notifications_Post_Type::POSTTYPE,
		);

		$meta = array(
			'triggers' => array(
				array(
					'group'    => 0,
					'relation' => 'and',
					'type'     => 'author_role',
					'operator' => '!=',
					'value'    => 'administrator',
				),
				array(
					'group'    => 0,
					'relation' => 'and',
					'type'     => 'action',
					'operator' => '=',
					'value'    => 'updated',
				),
				array(
					'group'    => 1,
					'relation' => 'and',
					'type'     => 'author_role',
					'operator' => '=',
					'value'    => 'administrator',
				),
				array(
					'group'    => 1,
					'relation' => 'and',
					'type'     => 'connector',
					'operator' => '=',
					'value'    => 'widgets',
				),
				array(
					'group'    => 1,
					'relation' => 'and',
					'type'     => 'action',
					'operator' => '=',
					'value'    => 'sorted',
				),
			),
			'groups' => array(
				1 => array(
					'group'    => 0,
					'relation' => 'or',
				),
			),
			'alerts' => array(
				array(
					'type'    => 'email',
					'users'   => '1',
					'emails'  => '',
					'subject' => sprintf( esc_html__( '[Site Activity Alert] %s', 'stream' ), get_bloginfo( 'name' ) ),
					'message' => sprintf( esc_html__( 'The following just happened on your site: %s by %s Date of action: %s', 'stream' ), "\r\n\r\n{summary}", "{author.display_name}\r\n\r\n", '{created}' )
				),
			),
		);

		$post_id = wp_insert_post( $postarr );

		if ( is_a( $post_id, 'WP_Error' ) ) {
			return $post_id;
		}

		foreach ( $meta as $key => $val ) {
			update_post_meta( $post_id, $key, $val );
		}
	}

}

$GLOBALS['wp_stream_notifications'] = WP_Stream_Notifications::get_instance();
