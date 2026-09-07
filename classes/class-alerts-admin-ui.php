<?php
/**
 * Alerts admin forms, AJAX, menu, and scripts.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class Alerts_Admin_UI
 *
 * @package WP_Stream
 */
class Alerts_Admin_UI {

	/**
	 * Class constructor.
	 *
	 * @param Plugin $plugin Instance of plugin object.
	 */
	public function __construct( public $plugin ) {
		$this->register_hooks();
	}

	/**
	 * Register menu, script, AJAX, and action-link hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Add custom post type to menu.
		add_action( 'wp_stream_admin_menu', array( $this, 'register_menu' ) );

		// Add scripts to post screens.
		add_action(
			'admin_enqueue_scripts',
			array(
				$this,
				'register_scripts',
			)
		);

		add_action(
			'network_admin_menu',
			array(
				$this,
				'change_menu_link_url',
			),
			99
		);

		add_action(
			'wp_ajax_load_alerts_settings',
			array(
				$this,
				'load_alerts_settings',
			)
		);
		add_action( 'wp_ajax_get_actions', array( $this, 'get_actions' ) );
		add_action(
			'wp_ajax_save_new_alert',
			array(
				$this,
				'save_new_alert',
			)
		);
		add_action(
			'wp_ajax_get_new_alert_triggers_notifications',
			array(
				$this,
				'get_new_alert_triggers_notifications',
			)
		);

		add_filter(
			'wp_stream_action_links_posts',
			array(
				$this,
				'change_alert_action_links',
			),
			11,
			2
		);
	}

	/**
	 * Register scripts for page load
	 *
	 * @action admin_enqueue_scripts
	 *
	 * @return void
	 */
	public function register_scripts() {
		$screen = get_current_screen();
		if ( 'edit-wp_stream_alerts' !== $screen->id ) {
			return;
		}

		$this->plugin->enqueue_asset(
			'alerts',
			array(
				$this->plugin->with_select2(),
				'inline-edit-post',
			),
			array(
				'any'             => __( 'Any', 'stream' ),
				'anyContext'      => __( 'Any Context', 'stream' ),
				'getActionsNonce' => wp_create_nonce( 'stream_get_actions' ),
			)
		);
	}

	/**
	 * Add custom post type to menu
	 *
	 * @action admin_menu
	 *
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			$this->plugin->admin->records_page_slug,
			__( 'Alerts', 'stream' ),
			__( 'Alerts', 'stream' ),
			Alerts::CAPABILITY,
			'edit.php?post_type=wp_stream_alerts'
		);
	}

	/**
	 * Modify the Stream > Alerts Network Admin Menu link.
	 *
	 * In self::register_menu(), the Alerts submenu item
	 * is essentially set to go to the Site's admin area.
	 *
	 * However, on the Network admin, we need to redirect
	 * it to the first site in the network, as this is
	 * where the true Network Alerts settings page is located.
	 *
	 * @action network_admin_menu
	 * @return bool
	 */
	public function change_menu_link_url() {
		global $submenu;

		$parent = 'wp_stream';
		$page   = 'edit.php?post_type=wp_stream_alerts';

		// If we're not on the Stream menu item, return.
		if ( ! isset( $submenu[ $parent ] ) ) {
			return false;
		}

		// Get the first existing Site in the Network.
		$sites = wp_stream_get_sites(
			array(
				'limit' => 5, // Limit the size of the query.
			)
		);

		$site_id = '1';

		// Function wp_get_sites() can return an empty array if the network is too large.
		if ( ! empty( $sites ) && ! empty( $sites[0]->blog_id ) ) {
			$site_id = $sites[0]->blog_id;
		}

		$new_url = get_admin_url( $site_id, $page );

		foreach ( $submenu[ $parent ] as $key => $value ) {
			// Set correct URL for the menu item.
			if ( $page === $value[2] ) {
				// This hack is not kosher, see the docblock for an explanation.
				$submenu[ $parent ][ $key ][2] = $new_url; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				break;
			}
		}

		return true;
	}

	/**
	 * Display Alert Type Meta Box
	 *
	 * @param \WP_Post|array $post Post object for current alert.
	 *
	 * @return void
	 */
	public function display_notification_box( $post = array() ) {
		$alert      = null;
		$alert_type = 'none';
		if ( is_object( $post ) ) {
			$alert = $this->plugin->alerts->get_alert( $post->ID );
			if ( false !== $alert ) {
				$alert_type = $alert->alert_type;
			}
		}
		$form = new Form_Generator();

		echo '<label>' . esc_html__( 'Alert me by', 'stream' ) . '</label>';
		$form->render_field(
			'select',
			array(
				'id'          => 'wp_stream_alert_type',
				'name'        => 'wp_stream_alert_type',
				'value'       => $alert_type,
				'options'     => $this->get_notification_values(),
				'placeholder' => __( 'No Alert', 'stream' ),
				'title'       => 'Alert Type:',
			)
		);

		echo '<div id="wp_stream_alert_type_form">';
		if ( is_object( $alert ) ) {
			$alert->get_alert_type_obj()->display_fields( $alert );
		} else {
			$this->plugin->alerts->alert_types['none']->display_fields( array() );
		}

		echo '</div>';
	}

	/**
	 * Returns settings form HTML for AJAX use
	 *
	 * @action wp_ajax_load_alerts_settings
	 *
	 * @return void
	 */
	public function load_alerts_settings() {
		if ( ! current_user_can( Alerts::CAPABILITY ) ) {
			wp_send_json_error(
				array(
					'message' => 'You do not have permission to do this.',
				)
			);
		}
		$alert   = array();
		$post_id = wp_stream_filter_input( INPUT_POST, 'post_id' );
		if ( ! empty( $post_id ) && 'new' !== $post_id ) {
			$alert = $this->plugin->alerts->get_alert( $post_id );
			if ( false === $alert ) {
				wp_send_json_error(
					array(
						'message' => 'Could not find alert.',
					)
				);
			}
		}

		$alert_type = wp_stream_filter_input( INPUT_POST, 'alert_type' );
		if ( empty( $alert_type ) ) {
			$alert_type = 'none';
		}
		if ( ! array_key_exists( $alert_type, $this->plugin->alerts->alert_types ) ) {
			wp_send_json_error(
				array(
					'message' => 'Could not find alert type.',
				)
			);
		}

		ob_start();
		$this->plugin->alerts->alert_types[ $alert_type ]->display_fields( $alert );
		$output = ob_get_contents();
		ob_end_clean();

		$data = array(
			'html' => $output,
		);
		wp_send_json_success( $data );
	}

	/**
	 * Display Trigger Meta Box
	 *
	 * @param \WP_Post|array $post Post object for current alert.
	 *
	 * @return void
	 */
	public function display_triggers_box( $post = array() ) {
		$alert = false;
		if ( is_object( $post ) ) {
			$alert = $this->plugin->alerts->get_alert( $post->ID );
		}
		if ( false === $alert ) {
			$alert = array();
		}

		$form = new Form_Generator();
		do_action( 'wp_stream_alert_trigger_form_display', $form, $alert );
		// @TODO use human readable text.
		echo '<label>' . esc_html__( 'Alert me when', 'stream' ) . '</label>';
		$form->render_fields();
		wp_nonce_field( 'save_alert', 'wp_stream_alerts_nonce' );

		if ( $post instanceof \WP_Post ) :
			/**
			 * These fields are required for the post to be saved, as the Admin AJAX inline_save action is fired.
			 *
			 * @see get_inline_data()
			 * @see wp_ajax_inline_save()
			 */
			?>
			<input type="hidden" name="_status" value="<?php echo esc_attr( get_post_status( $post->ID ) ); ?>" />
			<input type="hidden" name="jj" value="<?php echo esc_attr( mysql2date( 'd', $post->post_date, false ) ); ?>" />
			<input type="hidden" name="mm" value="<?php echo esc_attr( mysql2date( 'm', $post->post_date, false ) ); ?>" />
			<input type="hidden" name="aa" value="<?php echo esc_attr( mysql2date( 'Y', $post->post_date, false ) ); ?>" />
			<input type="hidden" name="hh" value="<?php echo esc_attr( mysql2date( 'H', $post->post_date, false ) ); ?>" />
			<input type="hidden" name="mn" value="<?php echo esc_attr( mysql2date( 'i', $post->post_date, false ) ); ?>" />
			<input type="hidden" name="ss" value="<?php echo esc_attr( mysql2date( 's', $post->post_date, false ) ); ?>" />
			<?php
		endif;
	}

	/**
	 * Display Submit Box
	 *
	 * @param \WP_Post $post Post object for current alert.
	 *
	 * @return void
	 */
	public function display_submit_box( $post ) {
		if ( empty( $post ) ) {
			return;
		}

		$post_status = $post->post_status;
		if ( 'auto-draft' === $post_status ) {
			$post_status = 'wp_stream_enabled';
		}
		?>
		<div class="submitbox" id="submitpost">
			<div id="minor-publishing">
				<div id="misc-publishing-actions">
					<div class="misc-pub-section misc-pub-post-status">
						<label for="wp_stream_alert_status"><?php esc_html_e( 'Status', 'stream' ); ?></label>
						<select name='wp_stream_alert_status' id='wp_stream_alert_status'>
							<option<?php selected( $post_status, 'wp_stream_enabled' ); ?>
									value='wp_stream_enabled'><?php esc_html_e( 'Enabled', 'stream' ); ?></option>
							<option<?php selected( $post_status, 'wp_stream_disabled' ); ?>
									value='wp_stream_disabled'><?php esc_html_e( 'Disabled', 'stream' ); ?></option>
						</select>
					</div>
				</div>
				<div class="clear"></div>
			</div>

			<div id="major-publishing-actions">
				<div id="delete-action">
					<?php
					if ( current_user_can( 'delete_post', $post->ID ) ) {
						if ( ! EMPTY_TRASH_DAYS ) {
							$delete_text = __( 'Delete Permanently', 'stream' );
						} else {
							$delete_text = __( 'Move to Trash', 'stream' );
						}
						?>
						<a class="submitdelete deletion" href="<?php echo get_delete_post_link( $post->ID ); ?>">
							<?php esc_html( $delete_text ); ?>
						</a>
						<?php
					}
					?>
				</div>
				<div id="publishing-action">
					<span class="spinner"></span>
					<?php submit_button( __( 'Save', 'stream' ), 'primary button-large', 'publish', false ); ?>
				</div>
				<div class="clear"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Display Status Box
	 *
	 * @return void
	 */
	public function display_status_box() {
		?>
		<div id="minor-publishing">
			<div id="misc-publishing-actions">
				<div class="misc-pub-section misc-pub-post-status">
					<label for="wp_stream_alert_status">
						<span class="title"><?php esc_html_e( 'Status:', 'stream' ); ?></span>
						<span class="input-text-wrap">
							<select name='wp_stream_alert_status' id='wp_stream_alert_status'>
								<option selected value='wp_stream_enabled'><?php esc_html_e( 'Enabled', 'stream' ); ?></option>
								<option value='wp_stream_disabled'><?php esc_html_e( 'Disabled', 'stream' ); ?></option>
							</select>
						</span>
					</label>
				</div>
			</div>
			<div class="clear"></div>
		</div>
		<?php
	}

	/**
	 * Return all notification values
	 *
	 * @return array
	 */
	public function get_notification_values() {
		$result = array();
		$names  = wp_list_pluck( $this->plugin->alerts->alert_types, 'name', 'slug' );
		foreach ( $names as $slug => $name ) {
			$result[ $slug ] = $name;
		}

		return $result;
	}

	/**
	 * Update actions dropdown options based on the connector selected.
	 */
	public function get_actions() {
		if ( ! current_user_can( Alerts::CAPABILITY ) ) {
			wp_send_json_error(
				array(
					'message' => 'You do not have permission to do this.',
				)
			);
		}

		check_ajax_referer( 'stream_get_actions', 'nonce' );

		$connector_name    = wp_stream_filter_input( INPUT_POST, 'connector' );
		$stream_connectors = wp_stream_get_instance()->connectors;
		if ( ! empty( $connector_name ) ) {
			if ( isset( $stream_connectors->connectors[ $connector_name ] ) ) {
				$connector = $stream_connectors->connectors[ $connector_name ];
				if ( method_exists( $connector, 'get_action_labels' ) ) {
					$actions = $connector->get_action_labels();
				}
			}
		} else {
			$actions = $stream_connectors->term_labels['stream_action'];
		}
		ksort( $actions );
		wp_send_json_success( $actions );
	}

	/**
	 * Save a new alert
	 */
	public function save_new_alert() {
		check_ajax_referer( 'save_alert', 'wp_stream_alerts_nonce' );

		if ( ! current_user_can( $this->plugin->admin->settings_cap ) ) {
			wp_die(
				esc_html__( "You don't have sufficient privileges to do this action.", 'stream' )
			);
		}

		$trigger_author                = wp_stream_filter_input( INPUT_POST, 'wp_stream_trigger_author' );
		$trigger_connector_and_context = wp_stream_filter_input( INPUT_POST, 'wp_stream_trigger_context' );
		if ( false !== strpos( $trigger_connector_and_context, '-' ) ) {
			// This is a connector with a context such as posts-post.
			$trigger_connector_and_context_split = explode( '-', $trigger_connector_and_context );
			$trigger_connector                   = $trigger_connector_and_context_split[0];
			$trigger_context                     = $trigger_connector_and_context_split[1];
		} elseif ( ! empty( $trigger_connector_and_context ) ) {
				// This is a parent connector with no dash such as posts.
				$trigger_connector = $trigger_connector_and_context;
				$trigger_context   = '';
		} else {
			// There is no connector or context.
			$trigger_connector = '';
			$trigger_context   = '';
		}

		$trigger_action = wp_stream_filter_input( INPUT_POST, 'wp_stream_trigger_action' );
		$alert_type     = wp_stream_filter_input( INPUT_POST, 'wp_stream_alert_type' );
		$alert_status   = wp_stream_filter_input( INPUT_POST, 'wp_stream_alert_status' );

		// Insert the post into the database.
		$item    = (object) array(
			'alert_type'   => $alert_type,
			'alert_meta'   => array(
				'trigger_author'    => $trigger_author,
				'trigger_connector' => $trigger_connector,
				'trigger_action'    => $trigger_action,
				'trigger_context'   => $trigger_context,
			),
			'alert_status' => $alert_status,
		);
		$alert   = new Alert( $item, $this->plugin );
		$title   = $alert->get_title();
		$post_id = wp_insert_post(
			array(
				'post_status' => $alert_status,
				'post_type'   => 'wp_stream_alerts',
				'post_title'  => $title,
			)
		);
		if ( empty( $post_id ) ) {
			wp_send_json_error();
		}
		add_post_meta( $post_id, 'alert_type', $alert_type );

		$alert_meta = array(
			'trigger_author'    => $trigger_author,
			'trigger_connector' => $trigger_connector,
			'trigger_action'    => $trigger_action,
			'trigger_context'   => $trigger_context,
		);
		$alert_meta = apply_filters( 'wp_stream_alerts_save_meta', $alert_meta, $alert_type );
		add_post_meta( $post_id, 'alert_meta', $alert_meta );
		wp_send_json_success(
			array(
				'success' => true,
			)
		);
	}

	/**
	 * Return HTML string of the Alert page controls.
	 */
	public function get_new_alert_triggers_notifications() {
		if ( ! current_user_can( $this->plugin->admin->settings_cap ) ) {
			wp_die(
				esc_html__( "You don't have sufficient privileges to do this action.", 'stream' )
			);
		}

		ob_start();
		?>
		<fieldset class="inline-edit-col inline-edit-wp_stream_alerts inline-edit-add-new-triggers">
			<legend class="inline-edit-legend">Add New</legend>
			<?php $this->display_triggers_box(); ?>
		</fieldset>
		<fieldset class="inline-edit-col inline-edit-wp_stream_alerts inline-edit-add-new-notifications">
			<?php $this->display_notification_box(); ?>
		</fieldset>
		<fieldset class="inline-edit-col inline-edit-wp_stream_alerts inline-edit-add-new-status">
			<?php $this->display_status_box(); ?>
		</fieldset>
		<?php
		$html = ob_get_clean();
		wp_send_json_success(
			array(
				'success' => true,
				'html'    => $html,
			)
		);
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_{connector}
	 *
	 * @param array  $links   Previous links registered.
	 * @param Record $record  Stream record.
	 *
	 * @return array Action links
	 */
	public function change_alert_action_links( $links, $record ) {
		$post = get_post( $record->object_id );

		if ( $post && Alerts::POST_TYPE === $post->post_type && $post->post_status === $record->get_meta( 'new_status', true ) ) {
			if ( 'trash' !== $post->post_status ) {
				$connector_posts = new \WP_Stream\Connector_Posts();
				$post_type_name  = $connector_posts->get_post_type_name( get_post_type( $post->ID ) );

				/* translators: %s: the post type singular name (e.g. "Post") */
				$links[ sprintf( esc_html_x( 'Edit %s', 'Post type singular name', 'stream' ), $post_type_name ) ] = admin_url( 'edit.php?post_type=wp_stream_alerts#post-' . $post->ID );
				unset( $links[ esc_html__( 'View', 'stream' ) ] );
			}
		}

		return $links;
	}
}
