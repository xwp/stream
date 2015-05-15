<?php

class WP_Stream_Notifications_Post_Type {

	/**
	 * Hold class instance
	 *
	 * @var string
	 */
	public static $instance;

	/**
	 * Hold the max number of items allowed during AJAX form autocomplete
	 *
	 * @var int
	 */
	public static $ajax_items_max;

	const POSTTYPE = 'stream_notification'; // Must be less than 20 chars

	private function __construct() {
		$this->register_post_type();

		// Endpoint for AJAX form autocomplete
		add_action( 'wp_ajax_wp_stream_notifications_endpoint', array( $this, 'form_ajax_ep' ) );

		// Rule occurrence reset
		add_action( 'wp_ajax_wp_stream_notifications_reset_occ', array( $this, 'ajax_reset_occ' ) );

		// Enqueue our form scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 11 );

		// Define `search_in` arg for WP_User_Query
		add_filter( 'user_search_columns', array( $this, 'define_search_in_arg' ), 10, 3 );

		// Change title placeholder
		add_filter( 'enter_title_here', array( $this, 'title_placeholder' ), 10, 2 );

		// Save meta data
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );

		// Load list-table customizations
		add_action( 'admin_init', array( $this, 'load_list_table' ) );

		/**
		 * Filter the max number of items allowed during AJAX form autocomplete
		 *
		 * @since 2.0.6
		 *
		 * @return int
		 */
		self::$ajax_items_max = apply_filters( 'wp_stream_notifications_ajax_items_max', 50 );
	}

	/**
	 * Return an active instance of this class, and create one if it doesn't exist
	 *
	 * @return WP_Stream_Notifications_Post_Type
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register custom post type
	 *
	 * @return void
	 */
	private function register_post_type() {
		register_post_type(
			self::POSTTYPE, array(
				'label'                => esc_html__( 'Stream Notification Rule', 'stream' ),
				'labels'               => array(
					'name'               => esc_html__( 'Stream Notification Rules', 'stream' ),
					'singular_name'      => esc_html__( 'Stream Notification Rule', 'stream' ),
					'menu_name'          => esc_html__( 'Notifications', 'stream' ),
					'add_new'            => esc_html__( 'New Rule', 'stream' ),
					'add_new_item'       => esc_html__( 'Add New Rule', 'stream' ),
					'new_item'           => esc_html__( 'New Stream Notification Rule', 'stream' ),
					'edit_item'          => esc_html__( 'Edit Stream Notification Rule', 'stream' ),
					'view_item'          => esc_html__( 'View Stream Notification Rule', 'stream' ),
					'search_items'       => esc_html__( 'Search Rules', 'stream' ),
					'not_found'          => esc_html__( 'No notification rules found.', 'stream' ),
					'not_found_in_trash' => esc_html__( 'No notification rules found in Trash.', 'stream' ),
				),
				'public'               => false,
				'show_ui'              => true,
				'show_in_nav_menus'    => false,
				'show_in_menu'         => false,
				'exclude_from_search'  => true,
				'publicly_queryable'   => false,
				'supports'             => WP_Stream::$api->is_restricted() ? false : array( 'title', 'author' ),
				'register_meta_box_cb' => WP_Stream::$api->is_restricted() ? null : array( $this, 'metaboxes' ),
				'rewrite'              => false,
			)
		);
	}

	/**
	 * Register custom meta boxes
	 *
	 * @return void
	 */
	public function metaboxes( $post ) {
		if ( self::POSTTYPE !== $post->post_type ) {
			return;
		}

		add_meta_box( 'stream-notifications-triggers', esc_html__( 'Triggers', 'stream' ), array( $this, 'metabox_triggers' ), self::POSTTYPE );
		add_meta_box( 'stream-notifications-alerts', esc_html__( 'Alerts', 'stream' ), array( $this, 'metabox_alerts' ), self::POSTTYPE );
		add_meta_box( 'stream-notifications-data-tags', esc_html__( 'Data Tags', 'stream' ), array( $this, 'metabox_data_tags' ), self::POSTTYPE, 'side' );

		add_action( 'post_submitbox_misc_actions', array( $this, 'metabox_save' ) );

		add_action(
			'edit_form_advanced', function () {
				global $post;
				include WP_STREAM_NOTIFICATIONS_DIR . 'views/form-templates.php';
			}
		);
	}

	/**
	 * Enqueue our scripts, in our own page only
	 *
	 * @action admin_enqueue_scripts
	 *
	 * @param  string $hook Current admin page slug
	 *
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
		global $typenow;

		if ( ! in_array( $hook, array( 'post-new.php', 'post.php' ) ) || self::POSTTYPE !== $typenow ) {
			return;
		}

		wp_enqueue_style( 'select2' );
		wp_enqueue_script( 'select2' );
		wp_enqueue_script( 'underscore' );
		wp_enqueue_style( 'jquery-ui' );
		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_style( 'wp-stream-datepicker' );
		wp_enqueue_script( 'jquery-ui-accordion' );
		wp_enqueue_script( 'accordion' );
		wp_enqueue_style( 'stream-notifications-form', WP_STREAM_NOTIFICATIONS_URL . '/ui/css/form.css' );
		wp_enqueue_script( 'stream-notifications-form', WP_STREAM_NOTIFICATIONS_URL . '/ui/js/form.js', array( 'underscore', 'select2' ) );
		wp_localize_script( 'stream-notifications-form', 'stream_notifications', $this->get_js_options() );
	}

	public function metabox_triggers() {
		?>
		<a class="add-trigger button button-secondary" href="#add-trigger" data-group="0"><?php esc_html_e( '+ Add Trigger', 'stream' ) ?></a>
		<a class="add-trigger-group button button-primary" href="#add-trigger-group" data-group="0"><?php esc_html_e( '+ Add Group', 'stream' ) ?></a>
		<div class="group" rel="0"></div>
	<?php
	}

	public function metabox_alerts() {
		?>
		<a class="add-alert button button-secondary" href="#add-alert"><?php esc_html_e( '+ Add Alert', 'stream' ) ?></a>
	<?php
	}

	public function metabox_save() {
		global $post;
		if ( 'auto-draft' === $post->post_status ) {
			return;
		}

		$reset_link = add_query_arg(
			array(
				'action'          => 'wp_stream_notifications_reset_occ',
				'id'              => absint( $post->ID ),
				'wp_stream_nonce' => wp_create_nonce( 'wp_stream_notifications_reset_occ-' . absint( $post->ID ) ),
			),
			admin_url( 'admin-ajax.php' )
		);

		$occ = absint( get_post_meta( $post->ID, 'occurrences', true ) );

		?>
		<div class="occurrences misc-pub-section">
			<p>
				<?php
				$occ = $occ ? $occ : 0;

				printf( _n( 'This rule has occurred 1 time.', 'This rule has occurred %s times.', absint( $occ ), 'stream' ), sprintf( '<strong>%d</strong>', absint( $occ ) ) ); // xss ok
				?>
			</p>
			<?php if ( 0 !== $occ ) : ?>
				<p>
				<a href="<?php echo esc_url( $reset_link ) ?>" class="button button-secondary reset-occ">
					<?php esc_html_e( 'Reset Count', 'stream' ) ?>
				</a>
			</p>
			<?php endif; ?>
		</div>
	<?php
	}

	public function metabox_data_tags() {
		$data_tags    = array(
			__( 'Basic', 'stream' )    => array(
				'summary'   => esc_html__( 'Summary message of the triggered record.', 'stream' ),
				'author'    => esc_html__( 'User ID of the triggered record author.', 'stream' ),
				'connector' => esc_html__( 'Connector of the triggered record.', 'stream' ),
				'context'   => esc_html__( 'Context of the triggered record.', 'stream' ),
				'action'    => esc_html__( 'Action of the triggered record.', 'stream' ),
				'created'   => esc_html__( 'Timestamp of triggered record.', 'stream' ),
				'ip'        => esc_html__( 'IP of the triggered record author.', 'stream' ),
				'object_id' => esc_html__( 'Object ID of the triggered record.', 'stream' ),
			),
			__( 'Advanced', 'stream' ) => array(
				'object.' => __(
					'Specific object data of the record, relative to what the object type is:
										<br /><br />
										<strong>{object.post_title}</strong>
										<br />
										<strong>{object.post_excerpt}</strong>
										<br />
										<strong>{object.post_status}</strong>
										<br />
										<a href="http://codex.wordpress.org/Function_Reference/get_userdata#Notes" target="_blank">See Codex for more Post values</a>
										<br /><br />
										<strong>{object.name}</strong>
										<br />
										<strong>{object.taxonomy}</strong>
										<br />
										<strong>{object.description}</strong>
										<br />
										<a href="http://codex.wordpress.org/Function_Reference/get_userdata#Notes" target="_blank">See Codex for more Term values</a>', 'stream'
				),

				'author.' => __(
					'Specific user data of the record author:
										<br /><br />
										<strong>{author.display_name}</strong>
										<br />
										<strong>{author.user_email}</strong>
										<br />
										<strong>{author.user_login}</strong>
										<br />
										<a href="http://codex.wordpress.org/Function_Reference/get_userdata#Notes" target="_blank">See Codex for more User values</a>', 'stream'
				),
				'meta.'   => __(
					'Specific meta data of the record, used to display specific meta values created by Connectors.
										<br /><br />
										Example: <strong>{meta.old_theme}</strong> to display the old theme name when a new theme is activated.', 'stream'
				),
			),
		);
		$allowed_html = array(
			'a'      => array(
				'href'   => array(),
				'target' => array(),
			),
			'code'   => array(),
			'strong' => array(),
			'br'     => array(),
		);
		?>
		<div id="data-tag-glossary" class="accordion-container">
			<ul class="outer-border">
				<?php foreach ( $data_tags as $section => $tags ) : ?>
					<li class="control-section accordion-section">
					<h3 class="accordion-section-title hndle" title="<?php echo esc_attr( $section ) ?>"><?php echo esc_html( $section ) ?></h3>
					<div class="accordion-section-content">
						<div class="inside">
							<dl>
								<?php foreach ( $tags as $tag => $desc ) : ?>
									<dt><code>{<?php echo esc_html( $tag ) ?>}</code></dt>
									<dd><?php echo wp_kses( $desc, $allowed_html ) ?></dd>
								<?php endforeach; ?>
							</dl>
						</div>
					</div>
				</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php
	}

	/**
	 * Save rule meta data
	 *
	 * @action save_post
	 *
	 * @param int    $post_id
	 * @param object $post
	 *
	 * @return void
	 */
	public function save( $post_id, $post ) {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		if ( self::POSTTYPE !== $post->post_type ) {
			return;
		}

		$defaults = array(
			'triggers' => array(),
			'groups'   => array(),
			'alerts'   => array(),
		);

		$args = wp_parse_args( $_POST, $defaults );
		$meta = array_intersect_key( $args, array_flip( array( 'triggers', 'groups', 'alerts' ) ) );

		foreach ( $meta as $key => $vals ) {
			update_post_meta( $post_id, $key, $vals );
		}
	}

	/**
	 * Callback for form AJAX operations
	 *
	 * @action wp_ajax_wp_stream_notifications_endpoint
	 *
	 * @return void
	 */
	public function form_ajax_ep() {
		$type      = wp_stream_filter_input( INPUT_POST, 'type' );
		$is_single = wp_stream_filter_input( INPUT_POST, 'single' );
		$query     = wp_stream_filter_input( INPUT_POST, 'q' );
		$args      = json_decode( wp_stream_filter_input( INPUT_POST, 'args' ), true );

		if ( ! is_array( $args ) ) {
			$args = array();
		}

		if ( $is_single ) {
			switch ( $type ) {
				case 'author':
				case 'post_author':
				case 'user':
					$users_query = new WP_User_Query(
						array(
							'include' => explode( ',', $query ),
							'fields'  => array( 'ID', 'user_email', 'display_name' ),
							'number'  => absint( self::$ajax_items_max ), // 50 by default
						)
					);
					$data = ( $users_query->results ) ? $this->format_json_for_select2( $users_query->results, 'ID', 'display_name' ) : array();
					break;
				case 'post':
				case 'post_parent':
					$posts_query = new WP_Query(
						array(
							'post_type'      => 'any',
							'post_status'    => 'any',
							'posts_per_page' => absint( self::$ajax_items_max ), // 50 by default
							'post__in'       => explode( ',', $query ),
						)
					);
					$posts = $posts_query->get_posts();
					$items = array_combine( wp_list_pluck( $posts, 'ID' ), wp_list_pluck( $posts, 'post_title' ) );
					$data  = $this->format_json_for_select2( $items );
					break;
				case 'tax':
					$items  = get_taxonomies( null, 'objects' );
					$items  = wp_list_pluck( $items, 'labels' );
					$items  = wp_list_pluck( $items, 'name' );
					$query  = explode( ',', $query );
					$chosen = array_intersect_key( $items, array_flip( $query ) );
					$data   = $this->format_json_for_select2( $chosen );
					break;
				case 'term':
				case 'term_parent':
					$tax   = isset( $args['tax'] ) ? $args['tax'] : null;
					$query = explode( ',', $query );
					$terms = $this->get_terms( $query, $tax );
					$data  = $this->format_json_for_select2( $terms );
					break;
			}
		} else {
			switch ( $type ) {
				case 'author':
				case 'post_author':
				case 'user':
					$users_query = new WP_User_Query(
						array(
							'search'    => sprintf( '*%s*', $query ),
							'search_in' => array( 'user_login', 'display_name', 'user_email', 'user_nicename' ), // Custom query arg
							'meta_key'  => empty( $args['push'] ) ? null : 'ckpn_user_key',
							'number'    => absint( self::$ajax_items_max ), // 50 by default
						)
					);
					$data = ( $users_query->results ) ? $this->format_json_for_select2( $users_query->results, 'ID', 'display_name' ) : array();
					break;
				case 'action':
				case 'context':
					$items = WP_Stream_Connectors::$term_labels[ 'stream_' . $type ];
					$items = preg_grep( sprintf( '/%s/i', $query ), $items );
					$data  = $this->format_json_for_select2( $items );
					break;
				case 'post':
				case 'post_parent':
					$posts_query = new WP_Query(
						array(
							'post_type'      => 'any',
							'post_status'    => 'any',
							'posts_per_page' => absint( self::$ajax_items_max ), // 50 by default
							's'              => $query,
						)
					);
					$posts = $posts_query->get_posts();
					$items = array_combine( wp_list_pluck( $posts, 'ID' ), wp_list_pluck( $posts, 'post_title' ) );
					$data  = $this->format_json_for_select2( $items );
					break;
				case 'tax':
					$items = get_taxonomies( null, 'objects' );
					$items = wp_list_pluck( $items, 'labels' );
					$items = wp_list_pluck( $items, 'name' );
					$items = preg_grep( sprintf( '/%s/i', $query ), $items );
					$data  = $this->format_json_for_select2( $items );
					break;
				case 'term':
				case 'term_parent':
					$tax   = isset( $args['tax'] ) ? $args['tax'] : null;
					$terms = $this->get_terms( $query, $tax );
					$data  = $this->format_json_for_select2( $terms );
					break;
			}
		}

		// Add gravatar for authors
		if ( 'author' === $type && get_option( 'show_avatars' ) ) {
			foreach ( $data as $i => $item ) {
				if ( $avatar = get_avatar( $item['id'], 20 ) ) {
					$item['avatar'] = $avatar;
				}
				$data[ $i ] = $item;
			}
		}

		if ( ! empty( $data ) ) {
			wp_send_json_success( $data );
		} else {
			wp_send_json_error();
		}
	}

	/**
	 * Callback for Reset Occurrences count AJAX request
	 */
	public function ajax_reset_occ() {
		$id    = wp_stream_filter_input( INPUT_GET, 'id' );
		$nonce = wp_stream_filter_input( INPUT_GET, 'wp_stream_nonce' );

		if ( ! wp_verify_nonce( $nonce, 'wp_stream_notifications_reset_occ-' . $id ) ) {
			wp_send_json_error( esc_html__( 'Invalid nonce', 'stream' ) );
		}

		// Loose comparison needed
		if ( empty( $id ) || (int) $id != $id ) {
			wp_send_json_error( esc_html__( 'Invalid record ID', 'stream' ) );
		}

		update_post_meta( $id, 'occurrences', 0 );

		wp_send_json_success();
	}

	/**
	 * Format JS options for the form, to be used with wp_localize_script
	 *
	 * @return array  Options for our form JS handling
	 */
	public function get_js_options() {
		global $wp_roles;

		$args = array();

		$connectors = WP_Stream_Connectors::$term_labels['stream_connector'];

		asort( $connectors );

		$roles     = $wp_roles->roles;
		$roles_arr = array_combine( array_keys( $roles ), wp_list_pluck( $roles, 'name' ) );

		$default_operators = array(
			'='  => esc_html__( 'is', 'stream' ),
			'!=' => esc_html__( 'is not', 'stream' ),
		);

		$text_operator = array(
			'='         => esc_html__( 'is', 'stream' ),
			'!='        => esc_html__( 'is not', 'stream' ),
			'contains'  => esc_html__( 'contains', 'stream' ),
			'!contains' => esc_html__( 'does not contain', 'stream' ),
			'starts'    => esc_html__( 'starts with', 'stream' ),
			'ends'      => esc_html__( 'ends with', 'stream' ),
			'regex'     => esc_html__( 'regex', 'stream' ),
		);

		$numeric_operators = array(
			'='  => esc_html__( 'equals', 'stream' ),
			'!=' => esc_html__( 'not equal', 'stream' ),
			'<'  => esc_html__( 'less than', 'stream' ),
			'<=' => esc_html__( 'equal or less than', 'stream' ),
			'>'  => esc_html__( 'greater than', 'stream' ),
			'>=' => esc_html__( 'equal or greater than', 'stream' ),
		);

		$args['types'] = array(
			'search'      => array(
				'title'     => esc_html__( 'Summary', 'stream' ),
				'type'      => 'text',
				'operators' => $text_operator,
			),
			'object_id'   => array(
				'title'     => esc_html__( 'Object ID', 'stream' ),
				'type'      => 'text',
				'tags'      => true,
				'operators' => $default_operators,
			),

			'author_role' => array(
				'title'     => esc_html__( 'Author Role', 'stream' ),
				'type'      => 'select',
				'multiple'  => true,
				'operators' => $default_operators,
				'options'   => $roles_arr,
			),

			'author'      => array(
				'title'     => esc_html__( 'Author', 'stream' ),
				'type'      => 'text',
				'ajax'      => true,
				'operators' => $default_operators,
			),

			'ip'          => array(
				'title'     => esc_html__( 'IP', 'stream' ),
				'type'      => 'text',
				'subtype'   => 'ip',
				'tags'      => true,
				'operators' => $default_operators,
			),

			'date'        => array(
				'title'     => esc_html__( 'Date', 'stream' ),
				'type'      => 'date',
				'operators' => array(
					'='  => esc_html__( 'is on', 'stream' ),
					'!=' => esc_html__( 'is not on', 'stream' ),
					'<'  => esc_html__( 'is before', 'stream' ),
					'<=' => esc_html__( 'is on or before', 'stream' ),
					'>'  => esc_html__( 'is after', 'stream' ),
					'>=' => esc_html__( 'is on or after', 'stream' ),
				),
			),

			'weekday'     => array(
				'title'     => esc_html__( 'Day of Week', 'stream' ),
				'type'      => 'select',
				'multiple'  => true,
				'operators' => $default_operators,
				'options'   => array_combine(
					array_map(
						function ( $weekday_index ) {
							return sprintf( 'weekday_%d', $weekday_index % 7 );
						},
						range( get_option( 'start_of_week' ), get_option( 'start_of_week' ) + 6 )
					),
					array_map(
						function ( $weekday_index ) {
							global $wp_locale;
							return $wp_locale->get_weekday( $weekday_index % 7 );
						},
						range( get_option( 'start_of_week' ), get_option( 'start_of_week' ) + 6 )
					)
				),
			),

			// TODO: find a way to introduce meta to the rules, problem: not translatable since it is
			// generated on run time with no prior definition
			// 'meta_query'            => array(),

			'connector'   => array(
				'title'     => esc_html__( 'Connector', 'stream' ),
				'type'      => 'select',
				'multiple'  => true,
				'operators' => $default_operators,
				'options'   => $connectors,
			),
			'context'     => array(
				'title'     => esc_html__( 'Context', 'stream' ),
				'type'      => 'select',
				'multiple'  => true,
				'operators' => $default_operators,
				'options'   => WP_Stream_Connectors::$term_labels['stream_context'],
			),
			'action'      => array(
				'title'     => esc_html__( 'Action', 'stream' ),
				'type'      => 'select',
				'multiple'  => true,
				'operators' => $default_operators,
				'options'   => WP_Stream_Connectors::$term_labels['stream_action'],
			),
		);

		// Connector-based triggers
		$args['special_types'] = array(
			'post' => array(
				'title'     => esc_html__( '- Post', 'stream' ),
				'type'      => 'text',
				'ajax'      => true,
				'connector' => 'posts',
				'operators' => $default_operators,
			),
			'post_title' => array(
				'title'     => esc_html__( '- Post: Title', 'stream' ),
				'type'      => 'text',
				'connector' => 'posts',
				'operators' => $text_operator,
			),
			'post_slug' => array(
				'title'     => esc_html__( '- Post: Slug', 'stream' ),
				'type'      => 'text',
				'connector' => 'posts',
				'operators' => $text_operator,
			),
			'post_content' => array(
				'title'     => esc_html__( '- Post: Content', 'stream' ),
				'type'      => 'text',
				'connector' => 'posts',
				'operators' => $text_operator,
			),
			'post_excerpt' => array(
				'title'     => esc_html__( '- Post: Excerpt', 'stream' ),
				'type'      => 'text',
				'connector' => 'posts',
				'operators' => $text_operator,
			),
			'post_author' => array(
				'title'     => esc_html__( '- Post: Author', 'stream' ),
				'type'      => 'text',
				'ajax'      => true,
				'connector' => 'posts',
				'operators' => $default_operators,
			),
			'post_status' => array(
				'title'     => esc_html__( '- Post: Status', 'stream' ),
				'type'      => 'select',
				'connector' => 'posts',
				'options'   => wp_list_pluck( $GLOBALS['wp_post_statuses'], 'label' ),
				'operators' => $default_operators,
			),
			'post_format' => array(
				'title'     => esc_html__( '- Post: Format', 'stream' ),
				'type'      => 'select',
				'connector' => 'posts',
				'options'   => get_post_format_strings(),
				'operators' => $default_operators,
			),
			'post_parent' => array(
				'title'     => esc_html__( '- Post: Parent', 'stream' ),
				'type'      => 'text',
				'ajax'      => true,
				'connector' => 'posts',
				'operators' => $default_operators,
			),
			'post_thumbnail' => array(
				'title'     => esc_html__( '- Post: Featured Image', 'stream' ),
				'type'      => 'select',
				'connector' => 'posts',
				'options'   => array(
					'0' => esc_html__( 'None', 'stream' ),
					'1' => esc_html__( 'Has one', 'stream' )
				),
				'operators' => $default_operators,
			),
			'post_comment_status' => array(
				'title'     => esc_html__( '- Post: Comment Status', 'stream' ),
				'type'      => 'select',
				'connector' => 'posts',
				'options'   => array(
					'open'   => esc_html__( 'Open', 'stream' ),
					'closed' => esc_html__( 'Closed', 'stream' )
				),
				'operators' => $default_operators,
			),
			'post_comment_count' => array(
				'title'     => esc_html__( '- Post: Comment Count', 'stream' ),
				'type'      => 'text',
				'connector' => 'posts',
				'operators' => $numeric_operators,
			),
			'user' => array(
				'title'     => esc_html__( '- User', 'stream' ),
				'type'      => 'text',
				'ajax'      => true,
				'connector' => 'users',
				'operators' => $default_operators,
			),
			'user_role' => array(
				'title'     => esc_html__( '- User: Role', 'stream' ),
				'type'      => 'select',
				'connector' => 'users',
				'options'   => $roles_arr,
				'operators' => $default_operators,
			),
			'tax' => array(
				'title'     => esc_html__( '- Taxonomy', 'stream' ),
				'type'      => 'text',
				'ajax'      => true,
				'connector' => 'taxonomies',
				'operators' => $default_operators,
			),
			'term' => array(
				'title'     => esc_html__( '- Term', 'stream' ),
				'type'      => 'text',
				'ajax'      => true,
				'connector' => 'taxonomies',
				'operators' => $default_operators,
			),
			'term_parent' => array(
				'title'     => esc_html__( '- Term: Parent', 'stream' ),
				'type'      => 'text',
				'ajax'      => true,
				'connector' => 'taxonomies',
				'operators' => $default_operators,
			),
		);

		$args['adapters'] = array();

		foreach ( WP_Stream_Notifications::$adapters as $name => $options ) {
			$args['adapters'][ $name ] = array(
				'title'  => $options['title'],
				'fields' => $options['class']::fields(),
				'hints'  => $options['class']::hints(),
			);
		}

		// Localization
		$args['i18n'] = array(
			'empty_triggers'        => esc_html__( 'You cannot save a rule without any triggers.', 'stream' ),
			'invalid_first_trigger' => esc_html__( 'You cannot save a rule with an empty first trigger.', 'stream' ),
			'ajax_error'            => esc_html__( 'There was an error submitting your request, please try again.', 'stream' ),
			'confirm_reset'         => esc_html__( 'Are you sure you want to reset occurrences for this rule? This cannot be undone.', 'stream' ),
		);

		global $post;

		if ( ( $meta = get_post_meta( $post->ID ) ) && isset( $meta['triggers'] ) ) {

			$args['meta'] = array(
				'triggers' => maybe_unserialize( $meta['triggers'][0] ),
				'groups'   => maybe_unserialize( $meta['groups'][0] ),
				'alerts'   => maybe_unserialize( $meta['alerts'][0] ),
			);
		}

		return apply_filters( 'wp_stream_notifications_js_args', $args );
	}

	/**
	 * Take an (associative) array and format it for select2 AJAX result parser
	 *
	 * @param  array  $data (associative) Data array
	 * @param  string $key  Key of the ID column, null if associative array
	 * @param  string $val  Key of the Title column, null if associative array
	 *
	 * @return array        Formatted array, [ { id: %, title: % }, .. ]
	 */
	public function format_json_for_select2( $data, $key = null, $val = null ) {
		$return = array();
		if ( is_null( $key ) && is_null( $val ) ) { // for flat associative array
			$keys = array_keys( $data );
			$vals = array_values( $data );
		} else {
			$keys = wp_list_pluck( $data, $key );
			$vals = wp_list_pluck( $data, $val );
		}
		foreach ( $keys as $idx => $key ) {
			$return[] = array(
				'id'   => $key,
				'text' => $vals[ $idx ],
			);
		}

		return $return;
	}

	/**
	 * Search for terms in a specific taxonomy
	 *
	 * @param string $search     Search keyword
	 * @param array  $taxonomies Taxonomies to search in
	 *
	 * @return array
	 */
	public function get_terms( $search, $taxonomies = array() ) {
		global $wpdb;
		$taxonomies = (array) $taxonomies;

		$sql = "SELECT tt.term_taxonomy_id id, t.name, t.slug, tt.taxonomy, tt.description
			FROM $wpdb->terms t
			JOIN $wpdb->term_taxonomy tt USING ( term_id )
			WHERE
			";

		if ( is_array( $search ) ) {
			$search = array_map( 'intval', $search );
			$where  = sprintf( 'tt.term_taxonomy_id IN ( %s )', implode( ', ', $search ) );
		} else {
			$where = '
				t.name LIKE %s
				OR
				t.slug LIKE %s
				OR
				tt.taxonomy LIKE %s
				OR
				tt.description LIKE %s
			';
			$where = $wpdb->prepare( $where, "%$search%", "%$search%", "%$search%", "%$search%" );
		}

		$sql .= $where;
		$results = $wpdb->get_results( $sql );

		$return = array();
		foreach ( $results as $result ) {
			$return[ $result->id ] = sprintf( '%s - %s', $result->name, $result->taxonomy );
		}

		return $return;
	}

	/**
	 * Custom WP_User_Query arg `search_in`
	 *
	 * This is to extend support beyond the default `search_columns`
	 * that are supported by WP_User_Query.
	 *
	 * @filter user_search_columns
	 *
	 * @return array
	 */
	public function define_search_in_arg( $search_columns, $search, $query ) {
		$search_in      = $query->get( 'search_in' );
		$search_columns = ! is_null( $search_in ) ? (array) $search_in : $search_columns;

		return $search_columns;
	}

	/**
	 * Change Post Title placeholder in post edit screen
	 *
	 * @filter enter_title_here
	 *
	 * @param $text
	 * @param $post
	 *
	 * @return string
	 */
	public function title_placeholder( $text, $post ) {
		if ( self::POSTTYPE === $post->post_type ) {
			$text = esc_html__( 'Enter Rule Title here', 'stream' );
		}

		return $text;
	}

	/**
	 * Apply list actions, and load our list-table object
	 *
	 * @action load-edit.php
	 *
	 * @return void
	 */
	public function load_list_table() {
		global $typenow;

		if ( self::POSTTYPE !== $typenow || WP_Stream::$api->is_restricted( true ) ) {
			return;
		}

		require_once WP_STREAM_NOTIFICATIONS_INC_DIR . 'class-wp-stream-notifications-list-table.php';
		WP_Stream_Notifications_List_Table::get_instance();
	}

}
