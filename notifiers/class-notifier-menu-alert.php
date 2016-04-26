<?php
namespace WP_Stream;

class Notifier_Menu_Alert extends Notifier {
	/**
	 * Notifier name
	 *
	 * @var string
	 */
	public $name = 'Menu Alert Notifier';

	/**
	 * Notifier slug
	 *
	 * @var string
	 */
	public $slug = 'menu-alert';

	public function __construct( $plugin ) {
		parent::__construct( $plugin );
		add_action( 'admin_bar_menu', array( $this, 'menu_alert' ), 99 );
	}

	public function notify( $record_id, $recordarr, $options ) {
		$this->add_notification( $recordarr['summary'] );
		return;
	}

	public function display_settings_form( $alert, $post ) {
		$options = wp_parse_args( $alert->alert_meta, array(
			'clear_immediate' => false,
		) );

		$form = new Form_Generator;
		$form->add_field( 'checkbox', array(
			'name'  => 'wp_stream_menu_alert_clear_immediate',
			'text'  => esc_attr( __( 'Clear alerts after seen.', 'stream' ) ),
			'value' => $options['clear_immediate'],
			'title' => __( 'Menu Bar', 'stream' ),
		) );

		echo $form->render_all(); // xss ok
	}

	public function process_settings_form( $alert, $post ) {
		check_admin_referer( 'save_post', 'wp_stream_alerts_nonce' );
		$alert->alert_meta['clear_immediate'] = ! empty( $_POST['wp_stream_menu_alert_clear_immediate'] );
	}

	public function menu_alert( $wp_admin_bar ) {
		$notifications = $this->get_notifications();
		if ( ! $notifications ) {
			return false;
		}

		$wp_admin_bar->add_node( array(
			'id' => 'wp_stream_alert_notify',
			'parent' => false,
			'title' => __( 'New Stream Alert', 'stream' ),
			'href' => '#',
			'meta' => array( 'class' => 'opposite' ),
		) );

		foreach ( $this->get_notifications() as $key => $message ) {
			$wp_admin_bar->add_node( array(
				'id'     => 'wp_stream_alert_notify_' . $key,
				'parent' => 'wp_stream_alert_notify',
				'title'  => esc_html( $message ),
				'href'   => '#',
				'meta'   => array( 'class' => 'opposite' ),
			) );
		}

		$this->clear_notifications();
	}

	public function get_notifications() {
		$current_user	= wp_get_current_user();
		$notifications = get_user_meta( $current_user->ID, $this->get_key(), false );
		return $notifications;
	}

	public function add_notification( $message ) {
		$current_user	= wp_get_current_user();
		add_user_meta( $current_user->ID, $this->get_key(), $message, false );
	}

	public function clear_notifications( $global = false ) {
		$current_user	= wp_get_current_user();
		delete_user_meta( $current_user->ID, $this->get_key(), $global );
	}

	public function get_key() {
		return 'wp_stream_alerts_menu_pending';
	}
}
