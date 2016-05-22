<?php
namespace WP_Stream;

class Alert_Type_Email extends Alert_Type {

	/**
	 * Alert type name
	 *
	 * @var string
	 */
	public $name = 'Email';

	/**
	 * Alert type slug
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
	public function alert( $record_id, $recordarr, $options ) {
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

		echo '<p>Recipient:</p>';
		echo $form->render_field( 'text', array( // xss ok
			'name'    => 'wp_stream_email_recipient',
			'title'   => esc_attr( __( 'Email Recipient', 'stream' ) ),
			'value'   => $options['email_recipient'],
		) );

		echo '<p>Subject:</p>';
		echo $form->render_field( 'text', array( // xss ok
			'name'    => 'wp_stream_email_subject',
			'title'   => esc_attr( __( 'Email Subject', 'stream' ) ),
			'value'   => $options['email_subject'],
		) );
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
			$alert->alert_meta['email_recipient'] = $_POST['wp_stream_email_recipient'];
		}

		if ( ! empty( $_POST['wp_stream_email_subject'] ) ) {
			$alert->alert_meta['email_subject'] = $_POST['wp_stream_email_subject'];
		}
	}
}
