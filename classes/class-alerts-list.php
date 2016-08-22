<?php
/**
 * Listing of Alerts in the WP Admin.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class Alerts_List
 *
 * @package WP_Stream
 */
class Alerts_List {
	/**
	 * Hold the Plugin class
	 *
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * Class constructor.
	 *
	 * @param Plugin $plugin The main Plugin class.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		add_filter( 'bulk_actions-edit-wp_stream_alerts', array( $this, 'supress_bulk_actions' ), 10, 1 );
		add_filter( 'disable_months_dropdown', array( $this, 'supress_months_dropdown' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'supress_quick_edit' ), 10, 1 );

		// @todo Make more specific
		if ( is_admin() ) {
			add_filter( 'request', array( $this, 'parse_request' ), 10, 2 );
		}
		add_filter( 'views_edit-wp_stream_alerts', array( $this, 'manage_views' ) );

		add_filter( 'manage_wp_stream_alerts_posts_columns', array( $this, 'manage_columns' ) );
		add_action( 'manage_wp_stream_alerts_posts_custom_column', array( $this, 'column_data' ), 10, 2 );
		add_action( 'quick_edit_custom_box', array( $this, 'display_custom_quick_edit' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Default to wp_stream_enabled and wp_stream_disabled when querying for alerts
	 *
	 * @filter request
	 *
	 * @param array $query_vars Arguments for query to populate table.
	 * @return array
	 */
	function parse_request( $query_vars ) {
		$screen = get_current_screen();
		if ( 'edit-wp_stream_alerts' === $screen->id && Alerts::POST_TYPE === $query_vars['post_type'] && empty( $query_vars['post_status'] ) ) {
			$query_vars['post_status'] = array( 'wp_stream_enabled', 'wp_stream_disabled' );
		}
		return $query_vars;
	}

	/**
	 * Manage views on the alerts list view
	 *
	 * @filter views_edit-wp_stream_alerts
	 *
	 * @param array $views View links HTML.
	 * @return array
	 */
	function manage_views( $views ) {

		if ( array_key_exists( 'trash', $views ) ) {
			$trash = $views['trash'];
			unset( $views['trash'] );
			$views['trash'] = $trash;
		}

		return $views;
	}

	/**
	 * Manages columns on the alerts list view
	 *
	 * @filter manage_wp_stream_alerts_posts_columns
	 *
	 * @param array $columns Column id -> title array.
	 * @return array
	 */
	function manage_columns( $columns ) {
		$columns = array(
			'cb' => $columns['cb'],
			'alert_trigger' => __( 'Trigger', 'stream' ),
			'alert_type'    => __( 'Type', 'stream' ),
			'alert_status'  => __( 'Status', 'stream' ),
		);
		return $columns;
	}

	/**
	 * Fills in column data for custom columns.
	 *
	 * @action manage_wp_stream_alerts_posts_custom_column
	 *
	 * @param string $column_name Column name to show data for.
	 * @param int    $post_id The post being processed.
	 * @return array
	 */
	function column_data( $column_name, $post_id ) {

		$alert = $this->plugin->alerts->get_alert( $post_id );
		if ( ! $alert ) {
			return;
		}

		switch ( $column_name ) {
			case 'alert_trigger' :
				$values = array();
				foreach ( $this->plugin->alerts->alert_triggers as $trigger_type => $trigger_obj ) {
					$value = $trigger_obj->get_display_value( 'list_table', $alert );
					$values[] = '<span class="alert_trigger_value alert_trigger_' . esc_attr( $trigger_type ) . '">' . esc_html( $value ) . '</span>';
				}
				?>
				<div><?php echo wp_kses_post( join( '', $values ) ); ?></div>
				<div class="row-actions wp-stream-show-mobile">
					<?php echo wp_kses_post( $this->custom_column_actions( $post_id ) ); ?>
					<button type="button" class="toggle-row"><span class="screen-reader-text"><?php echo esc_html__( 'Show more details', 'stream' ); ?></span></button>
				</div>
				<?php
				break;
			case 'alert_type' :
				$alert_type = $alert->alert_type;
				if ( ! empty( $this->plugin->alerts->alert_types[ $alert_type ]->name ) ) {
					$alert_name = $this->plugin->alerts->alert_types[ $alert_type ]->name;
				} else {
					$alert_name = 'Untitled Alert';
				}
				?>
				<strong class="row-title"><?php echo esc_html( $alert_name ); ?></strong>
				<?php
				echo wp_kses_post( $this->custom_column_actions( $post_id ) );
				break;
			case 'alert_status' :
				$post_status_object = get_post_status_object( get_post_status( $post_id ) );
				if ( ! empty( $post_status_object ) ) {
					echo esc_html( $post_status_object->label );
				}
				break;
		}
	}

	/**
	 * Remove 'edit' action from bulk actions
	 *
	 * @filter bulk_actions-edit-wp_stream_alerts
	 *
	 * @param array $actions List of bulk actions available.
	 * @return array
	 */
	public function supress_bulk_actions( $actions ) {
		unset( $actions['edit'] );
		return $actions;
	}

	/**
	 * Remove quick edit action from inline edit actions
	 *
	 * @filter post_row_actions
	 *
	 * @param array $actions List of inline edit actions available.
	 * @return array
	 */
	function supress_quick_edit( $actions ) {
		if ( Alerts::POST_TYPE !== get_post_type() ) {
			return $actions;
		}
		unset( $actions['edit'] );
		unset( $actions['view'] );
		unset( $actions['trash'] );
		unset( $actions['inline hide-if-no-js'] );
		return $actions;
	}

	/**
	 * Remove months dropdown from Alerts list page
	 *
	 * @filter disable_months_dropdown
	 *
	 * @param bool   $status Status of months dropdown enabling.
	 * @param string $post_type Post type status is related to.
	 * @return bool
	 */
	public function supress_months_dropdown( $status, $post_type ) {
		if ( Alerts::POST_TYPE === $post_type ) {
			$status = true;
		}
		return $status;
	}

	/**
	 * Render custom Action links.
	 *
	 * @param int $post_id WP Post ID.
	 *
	 * @return mixed
	 */
	public function custom_column_actions( $post_id ) {
		ob_start();
		if ( ! empty( $_GET['post_status'] ) && 'trash' === $_GET['post_status'] ) {
			$bare_url         = '/wp-admin/post.php?post=' . $post_id . '&amp;action=untrash';
			$nonce_url        = wp_nonce_url( $bare_url, 'untrash-post_' . $post_id );
			$delete_url       = '/wp-admin/post.php?post=' . $post_id . '&amp;action=delete';
			$nonce_delete_url = wp_nonce_url( $delete_url, 'delete-post_' . $post_id );
			?>
			<div class="row-actions">
				<span class="untrash">
					<a href="<?php echo esc_url( $nonce_url ); ?>" class="untrash"><?php esc_html_e( 'Restore', 'stream' ); ?></a> |
				</span>
				<span class="delete">
					<a href="<?php echo esc_url( $nonce_delete_url ) ?>" class="submitdelete"><?php esc_html_e( 'Delete Permanently', 'stream' ); ?></a>
				</span>
			</div>
			<?php
		} else {
			$bare_url  = '/wp-admin/post.php?post=' . $post_id . '&amp;action=trash';
			$nonce_url = wp_nonce_url( $bare_url, 'trash-post_' . $post_id );
			?>
			<div class="row-actions">
				<span class="inline hide-if-no-js"><a href="#" class="editinline" aria-label="Quick edit “Hello world!” inline"><?php esc_html_e( 'Edit' ); ?></a>&nbsp;|&nbsp;</span>
				<span class="trash">
					<a href="<?php echo esc_url( $nonce_url ); ?>" class="submitdelete"><?php esc_html_e( 'Trash', 'stream' ); ?></a>
				</span>
				<?php // @todo remove after development. ?>
				&nbsp;|&nbsp;<?php edit_post_link( '[FULL POST]', '', '', $post_id, '' ); ?>
			</div>
		<?php
		}
		return ob_get_clean();
	}

	/**
	 * Display our custom Quick Edit pane.
	 */
	public function display_custom_quick_edit() {
		static $fired = false;
		if ( false !== $fired ) {
			return;
		}
		wp_nonce_field( plugin_basename( __FILE__ ), Alerts::POST_TYPE . '_edit_nonce' );

		$box_type = array(
			'triggers',
			'notification',
			'submit',
		);
		?>
		<legend class="inline-edit-legend"><?php esc_html_e( 'Edit', 'stream' ); ?></legend>
		<?php
		foreach ( $box_type as $type ) : // @todo remove inline styles. ?>
		<fieldset class="inline-edit-col inline-edit-<?php echo esc_attr( Alerts::POST_TYPE ); ?>">
			<div class="inline-edit-col">
				<label class="inline-edit-group">
					<?php
					$function_name = 'display_' . $type . '_box';
					call_user_func( array( $this->plugin->alerts, $function_name ), get_post() );
					?>
				</label>
			</div>
		</fieldset>
		<?php
		endforeach;
		$fired = true;

	}

	/**
	 * Enqueue scripts and styles.
	 *
	 * @param string $page WP Admin page.
	 */
	public function enqueue_scripts( $page ) {
		if ( 'edit.php' !== $page ) {
			return;
		}
		wp_register_script( 'wp-stream-alerts-list-js', $this->plugin->locations['url'] . 'ui/js/alerts-list.js', array( 'wp-stream-alerts', 'jquery' ) );
		wp_enqueue_script( 'wp-stream-alerts-list-js' );
		wp_register_style( 'wp-stream-alerts-list-css', $this->plugin->locations['url'] . 'ui/css/alerts-list.css' );
		wp_enqueue_style( 'wp-stream-alerts-list-css' );
		wp_enqueue_style( 'wp-stream-select2' );
	}
}
