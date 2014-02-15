<?php

class WP_Stream_Notifications_Form
{

	function __construct() {
		// AJAX end point for form auto completion
		add_action( 'wp_ajax_stream_notification_endpoint', array( $this, 'form_ajax_ep' ) );
		add_action( 'wp_ajax_stream-notifications-reset-occ', array( $this, 'ajax_reset_occ' ) );

		// Enqueue our form scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 11 );
	}

	public function load() {

		// Control screen layout
		add_screen_option( 'layout_columns', array( 'max' => 2, 'default' => 2 ) );

		// Register metaboxes
		add_meta_box(
			'triggers',
			__( 'Triggers', 'stream-notifications' ),
			array( $this, 'metabox_triggers' ),
			WP_Stream_Notifications::$screen_id,
			'normal'
		);
		add_meta_box(
			'alerts',
			__( 'Alerts', 'stream-notifications' ),
			array( $this, 'metabox_alerts' ),
			WP_Stream_Notifications::$screen_id,
			'normal'
		);
		add_meta_box(
			'submitdiv',
			__( 'Save', 'stream-notifications' ),
			array( $this, 'metabox_save' ),
			WP_Stream_Notifications::$screen_id,
			'side'
		);
		add_meta_box(
			'data-tags',
			__( 'Data Tags', 'stream-notifications' ),
			array( $this, 'metabox_data_tags' ),
			WP_Stream_Notifications::$screen_id,
			'side'
		);
	}

	/**
	 * Enqueue our scripts, in our own page only
	 *
	 * @action admin_enqueue_scripts
	 * @param  string $hook Current admin page slug
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
		if (
			$hook != WP_Stream_Notifications::$screen_id
			||
			filter_input( INPUT_GET, 'view' ) != 'rule'
			) {
			return;
		}

		$view = filter_input( INPUT_GET, 'view', FILTER_DEFAULT, array( 'options' => array( 'default' => 'list' ) ) );

		if ( $view == 'rule' ) {
			wp_enqueue_script( 'dashboard' );
			wp_enqueue_style( 'select2' );
			wp_enqueue_script( 'select2' );
			wp_enqueue_script( 'underscore' );
			wp_enqueue_style( 'jquery-ui' );
			wp_enqueue_script( 'jquery-ui-datepicker' );
			wp_enqueue_script( 'jquery-ui-accordion' );
			wp_enqueue_style( 'stream-notifications-form', WP_STREAM_NOTIFICATIONS_URL . '/ui/css/form.css' );
			wp_enqueue_script( 'stream-notifications-form', WP_STREAM_NOTIFICATIONS_URL . '/ui/js/form.js', array( 'underscore', 'select2' ) );
			wp_localize_script( 'stream-notifications-form', 'stream_notifications', $this->get_js_options() );
		}
	}


	/**
	 * Callback for form AJAX operations
	 *
	 * @action wp_ajax_stream_notifications_endpoint
	 * @return void
	 */
	public function form_ajax_ep() {
		// BIG @TODO: Make the request context-aware,
		// ie: get other rules ( maybe in the same group only ? ), so an author
		// query would check if there is a author_role rule available to limit
		// the results according to it

		$type      = filter_input( INPUT_POST, 'type' );
		$is_single = filter_input( INPUT_POST, 'single' );
		$query     = filter_input( INPUT_POST, 'q' );
		$args      = filter_input( INPUT_POST, 'args' );

		if ( $is_single ) {
			switch ( $type ) {
				case 'author':
					$user_ids = explode( ',', $query );
					$user_query = new WP_User_Query(
						array(
							'include' => $user_ids,
							'fields'  => array( 'ID', 'user_email', 'display_name' ),
						)
					);
					if ( $user_query->results ) {
						$data = $this->format_json_for_select2(
							$user_query->results,
							'ID',
							'display_name'
						);
					} else {
						$data = array();
					}
					break;
				case 'action':
					$actions = WP_Stream_Connectors::$term_labels['stream_action'];
					$values  = explode( ',', $query );
					$actions = array_intersect_key( $actions, array_flip( $values ) );
					$data    = $this->format_json_for_select2( $actions );
					break;
			}
		} else {
			switch ( $type ) {
				case 'author':
					add_action( 'pre_user_query', array( $this, 'fix_user_query_display_name' ) );
					$users = get_users( array( 'search' => '*' . $query . '*' ) );
					remove_action( 'pre_user_query', array( $this, 'fix_user_query_display_name' ) );
					$data = $this->format_json_for_select2( $users, 'ID', 'display_name' );
					break;
				case 'action':
					$actions = WP_Stream_Connectors::$term_labels['stream_action'];
					$actions = preg_grep( sprintf( '/%s/i', $query ), $actions );
					$data    = $this->format_json_for_select2( $actions );
					break;
			}
		}

		// Add gravatar for authors
		if ( $type == 'author' && get_option( 'show_avatars' ) ) {
			foreach ( $data as $i => $item ) {
				if ( $avatar = get_avatar( $item['id'], 20 ) ) {
					$item['avatar'] = $avatar;
				}
				$data[$i] = $item;
			}
		}

		if ( ! empty( $data )  ) {
			wp_send_json_success( $data );
		} else {
			wp_send_json_error();
		}
	}

	/**
	 * Take an (associative) array and format it for select2 AJAX result parser
	 * @param  array  $data (associative) Data array
	 * @param  string $key  Key of the ID column, null if associative array
	 * @param  string $val  Key of the Title column, null if associative array
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
				'text' => $vals[$idx],
			);
		}
		return $return;
	}

	public function fix_user_query_display_name( $query ) {
		global $wpdb;
		$search = $query->query_vars['search'];
		if ( empty( $search ) ) {
			return;
		}
		$search = str_replace( '*', '', $search );
		$query->query_where .= $wpdb->prepare( " OR $wpdb->users.display_name LIKE %s", '%' . like_escape( $search ) . '%' );
	}

	public function ajax_reset_occ() {
		$id = filter_input( INPUT_GET, 'id' );
		$nonce = filter_input( INPUT_GET, 'wp_stream_nonce' );

		if ( ! wp_verify_nonce( $nonce, 'reset-occ_' . $id ) ) {
			wp_send_json_error( __( 'Invalid nonce', 'domain' ) );
		}

		if ( empty( $id ) || (int) $id != $id ) {
			wp_send_json_error( __( 'Invalid record ID', 'domain' ) );
		}

		update_stream_meta( $id, 'occurrences', 0 );
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

		$roles     = $wp_roles->roles;
		$roles_arr = array_combine( array_keys( $roles ), wp_list_pluck( $roles, 'name' ) );

		$default_operators = array(
			'='   => __( 'is', 'stream-notifications' ),
			'!='  => __( 'is not', 'stream-notifications' ),
			'in'  => __( 'in', 'stream-notifications' ),
			'!in' => __( 'not in', 'stream-notifications' ),
		);

		$args['types'] = array(
			'search' => array(
				'title'     => __( 'Summary', 'stream-notifications' ),
				'type'      => 'text',
				'operators' => array(
					'='         => __( 'is', 'stream-notifications' ),
					'!='        => __( 'is not', 'stream-notifications' ),
					'contains'  => __( 'contains', 'stream-notifications' ),
					'!contains' => __( 'does not contain', 'stream-notifications' ),
					'regex'     => __( 'regex', 'stream-notifications' ),
				),
			),
			'object_id' => array(
				'title'     => __( 'Object ID', 'stream-notifications' ),
				'type'      => 'text',
				'tags'      => true,
				'operators' => $default_operators,
			),

			'author_role' => array(
				'title'     => __( 'Author Role', 'stream-notifications' ),
				'type'      => 'select',
				'multiple'  => true,
				'operators' => $default_operators,
				'options' => $roles_arr,
			),

			'author' => array(
				'title'     => __( 'Author', 'stream-notifications' ),
				'type'      => 'text',
				'ajax'      => true,
				'operators' => $default_operators,
			),

			'ip' => array(
				'title'     => __( 'IP', 'stream-notifications' ),
				'type'      => 'text',
				'tags'      => true,
				'operators' => $default_operators,
			),

			'date' => array(
				'title'     => __( 'Date', 'stream-notifications' ),
				'type'      => 'date',
				'operators' => array(
					'='  => __( 'is on', 'stream-notifications' ),
					'!=' => __( 'is not on', 'stream-notifications' ),
					'<'  => __( 'is before', 'stream-notifications' ),
					'<=' => __( 'is on or before', 'stream-notifications' ),
					'>'  => __( 'is after', 'stream-notifications' ),
					'>=' => __( 'is on or after', 'stream-notifications' ),
				),
			),

			// TODO: find a way to introduce meta to the rules, problem: not translatable since it is
			// generated on run time with no prior definition
			// 'meta_query'            => array(),

			'connector' => array(
				'title'     => __( 'Connector', 'stream-notifications' ),
				'type'      => 'select',
				'operators' => $default_operators,
				'options' => WP_Stream_Connectors::$term_labels['stream_connector'],
			),
			'context' => array(
				'title'     => __( 'Context', 'stream-notifications' ),
				'type'      => 'text',
				'ajax'      => true,
				'operators' => $default_operators,
			),
			'action' => array(
				'title'     => __( 'Action', 'stream-notifications' ),
				'type'      => 'text',
				'ajax'      => true,
				'operators' => $default_operators,
			),
		);

		$args['adapters'] = array();

		foreach ( WP_Stream_Notifications::$adapters as $name => $options ) {
			$args['adapters'][$name] = array(
				'title'  => $options['title'],
				'fields' => $options['class']::fields(),
				'hints'  => $options['class']::hints(),
			);
		}

		// Localization
		$args['i18n'] = array(
			'empty_triggers' => __( 'A rule must contain at least one trigger to be saved.', 'stream-notifications' ),
			'ajax_error'     => __( 'There was an error submitting your request, please try again.', 'stream-notifications' ),
			'confirm_reset'  => __( 'Are you sure you want to reset occurrences for this rule? This cannot be undone.', 'stream-notifications' ),
		);

		return apply_filters( 'stream_notification_js_args', $args );
	}

	public function metabox_triggers() {
		?>
		<a class="add-trigger button button-secondary" href="#add-trigger" data-group="0"><?php esc_html_e( '+ Add Trigger', 'stream-notifications' ) ?></a>
		<a class="add-trigger-group button button-primary" href="#add-trigger-group" data-group="0"><?php esc_html_e( '+ Add Group', 'stream-notifications' ) ?></a>
		<div class="group" rel="0"></div>
		<?php
	}

	public function metabox_alerts() {
		?>
		<a class="add-alert button button-secondary" href="#add-alert"><?php esc_html_e( '+ Add Alert', 'stream-notifications' ) ?></a>
		<?php
	}

	public function metabox_save( $rule ) {
		$reset_link = add_query_arg(
			array(
				'action'          => 'stream-notifications-reset-occ',
				'id'              => absint( $rule->ID ),
				'wp_stream_nonce' => wp_create_nonce( 'reset-occ_' . absint( $rule->ID ) ),
			),
			admin_url( 'admin-ajax.php' )
		);
		?>
		<div class="submitbox" id="submitpost">
			<div id="minor-publishing">
				<div id="misc-publishing-actions">
					<div class="misc-pub-section misc-pub-post-status">
						<label for="notification_visibility">
							<input type="checkbox" name="visibility" id="notification_visibility" value="active" <?php $rule->exists() && checked( $rule->visibility, 'active' ) ?>>
							<?php esc_html_e( 'Active', 'stream-notifications' ) ?>
						</label>
					</div>
					<?php if ( $rule->exists() ): ?>
					<div class="misc-pub-section">
						<?php $occ = get_stream_meta( $rule->ID, 'occurrences', true ) ?>
						<div class="occurrences">
							<p>
								<?php
								echo sprintf(
									_n(
										'This rule has occurred %1$s time.',
										'This rule has occurred %1$s times.',
										$occ,
										'stream-notifications'
									),
									sprintf( '<strong>%d</strong>', $occ ? $occ : 0 )
								)
								?>
							</p>
							<p>
							<a href="<?php echo esc_url( $reset_link ) ?>" class="button button-secondary reset-occ">
								<?php esc_html_e( 'Reset Count', 'stream-notifications' ) ?>
							</a>
							</p>
						</div>
					</div>
					<?php endif ?>
				</div>
			</div>

			<div id="major-publishing-actions">
				<?php if ( $rule->exists() ) : ?>
					<div id="delete-action">
						<?php
						$delete_link = add_query_arg(
							array(
								'page'            => WP_Stream_Notifications::NOTIFICATIONS_PAGE_SLUG,
								'action'          => 'delete',
								'id'              => absint( $rule->ID ),
								'wp_stream_nonce' => wp_create_nonce( 'delete-record_' . absint( $rule->ID ) ),
							),
							admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE )
						);
						?>
						<a class="submitdelete deletion" href="<?php echo esc_url( $delete_link ) ?>">
							<?php esc_html_e( 'Delete Permanently', 'stream-notifications' ) ?>
						</a>
					</div>
				<?php endif; ?>

				<div id="publishing-action">
					<span class="spinner"></span>
					<input type="submit" name="publish" id="publish" class="button button-primary button-large" value="<?php $rule->exists() ? esc_attr_e( 'Update', 'stream-notifications' ) : esc_attr_e( 'Save', 'stream-notifications' ) ?>" accesskey="s">
				</div>
				<div class="clear"></div>
			</div>
		</div>
		<?php
	}

	public function metabox_data_tags() {
		?>
		<div id="data-tag-glossary">
			<header><?php _e( 'General', 'stream-notifications' ) ?></header>
			<div class="dt-list">
				<dl>
					<dt><code>%%summary%%</code></dt>
					<dd><?php _e( 'Summary message of the triggered record', 'stream-notifications' ) ?></dd>
					<dt><code>%%object_id%%</code></dt>
					<dd><?php _e( 'Object ID of triggered record', 'stream-notifications' ) ?></dd>
					<dt><code>%%created%%</code></dt>
					<dd><?php _e( 'Timestamp of triggered record', 'stream-notifications' ) ?></dd>
					<dt><code>%%ip%%</code></dt>
					<dd><?php _e( 'IP of the person who authored the triggered record', 'stream-notifications' ) ?></dd>
				</dl>
			</div>
			<header><?php _e( 'Object', 'stream-notifications' ) ?></header>
			<div class="dt-list">
				<dl>
					<dt><code>%%object.post_title%%</code></dt>
					<dd><?php _e( 'Post title of the record post', 'stream-notifications' ) ?></dd>
				</dl>
			</div>
			<header><?php _e( 'Author', 'stream-notifications' ) ?></header>
			<div class="dt-list">
				<dl>
					<dt><code>%%author.user_login%%</code></dt>
					<dd><?php _e( 'Username of the record author', 'stream-notifications' ) ?></dd>
					<dt><code>%%author.user_email%%</code></dt>
					<dd><?php _e( 'Email of the record author', 'stream-notifications' ) ?></dd>
					<dt><code>%%author.display_name%%</code></dt>
					<dd><?php _e( 'Display name of the record author', 'stream-notifications' ) ?></dd>
				</dl>
			</div>
			<div class="dt-list">
				<header><?php _e( 'Meta', 'stream-notifications' ) ?></header>
				<dl>
				</dl>
			</div>
		</div>
		<?php
	}
}