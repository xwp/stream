<?php
namespace WP_Stream;

class Notifier_Emailer extends Notifier {

	/**
	 * Notifier name
	 *
	 * @var string
	 */
	public $name = 'Email Notifier';

	/**
	 * Notifier slug
	 *
	 * @var string
	 */
	public $slug = 'email';

	/**
	 * Sends an email to the given recipient.
	 *
	 * @param int   $record_id Record that triggered notification.
	 * @param array $recordarr Record details.
	 * @param array $options Alert options.
	 * @return void
	 */
	public function notify( $record_id, $recordarr, $options ) {
		$options = wp_parse_args( $alert->alert_meta, array(
			'email_recipient' => '',
			'email_subject'   => '',
		) );

		if ( empty( $options['email_recipient'] ) && empty( $options['email_subject'] ) ) {
			return;
		}

		wp_email( $options['email_recipient'], $options['email_subject'], 'This is a test email.' );
	}

	/**
	 * Displays a settings form for the alert type
	 *
	 * @param Alert   $alert Alert object for the currently displayed alert.
	 * @param WP_Post $post Post object representing the current alert.
	 * @return void
	 */
	public function display_settings_form( $alert, $post ) {
		$options = wp_parse_args( $alert->alert_meta, array(
			'email_recipient' => '',
			'email_subject'   => '',
		) );

		$form = new Form_Generator;
		$form->add_field( 'text', array(
			'name'    => 'wp_stream_email_recipient',
			'title'   => esc_attr( __( 'Email Recipient', 'stream' ) ),
			'value'   => $options['email_recipient'],
		) );

		$form->add_field( 'text', array(
			'name'    => 'wp_stream_email_subject',
			'title'   => esc_attr( __( 'Email Subject', 'stream' ) ),
			'value'   => $options['email_subject'],
		) );

		echo $form->render_all(); // xss ok
	}

	/**
	 * Validates and saves form settings for later use.
	 *
	 * @param Alert   $alert Alert object for the currently displayed alert.
	 * @param WP_Post $post Post object representing the current alert.
	 * @return void
	 */
	public function process_settings_form( $alert, $post ) {
		check_admin_referer( 'save_post', 'wp_stream_alerts_nonce' );

		if ( ! empty( $_POST['wp_stream_email_recipient'] ) ) {
			$alert->alert_meta = $_POST['wp_stream_email_recipient'];
		}

		if ( ! empty( $_POST['wp_stream_email_subject'] ) ) {
			$alert->alert_meta = $_POST['wp_stream_email_subject'];
		}
	}
}
