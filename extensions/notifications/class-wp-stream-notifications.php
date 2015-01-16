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

	/**
	 * Holds admin notices messages
	 *
	 * @var array
	 */
	public static $messages = array();

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
	 * Return active instance of this class, create one if it doesn't exist
	 *
	 * @return WP_Stream_Notifications
	 */
	public static function get_instance() {
		if ( empty( self::$instance ) ) {
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
	 * GO GO GO!
	 *
	 * @return void
	 */
	public function load() {
		// Register new submenu
		if ( ! apply_filters( 'wp_stream_notifications_disallow_site_access', false ) && ! WP_Stream_Admin::$disable_access && ( WP_Stream::is_connected() || WP_Stream::is_development_mode() ) ) {
			add_action( 'admin_menu', array( $this, 'register_menu' ), 11 );
		}

		// Load settings, enabling extensions to hook in
		require_once WP_STREAM_NOTIFICATIONS_INC_DIR . 'class-wp-stream-notifications-settings.php';
		add_action( 'init', array( 'WP_Stream_Notifications_Settings', 'load' ), 9 );

		if ( WP_Stream_API::is_restricted() ) {
			add_action( 'in_admin_header', array( __CLASS__, 'in_admin_header' ) );
			return;
		}

		// Include all adapters
		include_once WP_STREAM_NOTIFICATIONS_INC_DIR . 'class-wp-stream-notifications-adapter.php';
		$adapters = array( 'email', 'push', 'sms' );

		foreach ( $adapters as $adapter ) {
			include WP_STREAM_NOTIFICATIONS_INC_DIR . 'adapters/class-wp-stream-notifications-adapter-' . $adapter . '.php';
		}

		// Load Matcher
		include_once WP_STREAM_NOTIFICATIONS_INC_DIR . 'class-wp-stream-notifications-matcher.php';
		$this->matcher = new WP_Stream_Notifications_Matcher();
	}

	public static function in_admin_header() {
		global $typenow;

		if ( WP_Stream_Notifications_Post_Type::POSTTYPE !== $typenow ) {
			return;
		}
		?>
		<div class="stream-example">
			<div class="stream-example-modal">
				<h1><i class="dashicons dashicons-admin-comments"></i> <?php _e( 'Stream Notifications', 'stream' ) ?></h1>
				<p><?php _e( 'Get notified instantly when important changes are made on your site.', 'stream' ) ?></p>
				<ul>
					<li><i class="dashicons dashicons-yes"></i> <?php _e( 'Create notification rules quickly and easily', 'stream' ) ?></li>
					<li><i class="dashicons dashicons-yes"></i> <?php _e( 'Smart and powerful trigger matching', 'stream' ) ?></li>
					<li><i class="dashicons dashicons-yes"></i> <?php _e( 'Fully customized e-mail and SMS alerts', 'stream' ) ?></li>
					<li><i class="dashicons dashicons-yes"></i> <?php _e( 'Push alerts to your smartphone or tablet', 'stream' ) ?></li>
				</ul>
				<a href="<?php echo esc_url( WP_Stream_Admin::account_url( sprintf( 'upgrade?site_uuid=%s', WP_Stream::$api->site_uuid ) ) ); ?>" class="button button-primary button-large"><?php _e( 'Upgrade to Pro', 'stream' ) ?></a>
			</div>
		</div>
		<?php
	}

	/**
	 * Register Notification menu under Stream's main one
	 *
	 * @action admin_menu
	 * @return void
	 */
	public function register_menu() {
		self::$screen_id = add_submenu_page(
			WP_Stream_Admin::RECORDS_PAGE_SLUG,
			__( 'Notifications', 'stream' ),
			__( 'Notifications', 'stream' ),
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
	 * Display all messages on admin board
	 *
	 * @return void
	 */
	public static function admin_notices() {
		foreach ( self::$messages as $message ) {
			echo wp_kses_post( $message );
		}
	}

	/**
	 * Plugin activation routine
	 * @return void
	 */
	public function on_activation() {
		// Add sample rule
		$args = array(
			'post_type'      => WP_Stream_Notifications_Post_Type::POSTTYPE,
			'post_status'    => 'any',
			'posts_per_page' => 1,
		);

		if ( ! get_posts( $args ) ) {
			$this->add_sample_rule();
		}
	}

	/**
	 * Add a sample rule, used upon activation
	 *
	 */
	public function add_sample_rule() {
		$postarr = array(
			'post_title'  => __( 'Sample Rule', 'stream' ),
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
					'subject' => sprintf( __( '[Site Activity Alert] %s', 'stream' ), get_bloginfo( 'name' ) ),
					'message' => sprintf( __( 'The following just happened on your site: %s by %s Date of action: %s', 'stream' ), "\r\n\r\n{summary}", "{author.display_name}\r\n\r\n", '{created}' )
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
