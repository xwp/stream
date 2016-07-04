<?php
namespace WP_Stream;

class Alert_Type_Email extends Alert_Type {

	/**
	 * Alert type name
	 *
	 * @var string
	 */
	public $name = 'Send Email';

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
	 * @param array $alert Alert options.
	 * @return void
	 */
	public function alert( $record_id, $recordarr, $alert ) {
		$options = wp_parse_args( $alert->alert_meta, array(
			'email_recipient'   => '',
			'email_subject'     => '',
			'trigger_action'    => '',
			'trigger_connector' => '',
			'trigger_context'   => '',
		) );

		if ( empty( $options['email_recipient'] ) && empty( $options['email_subject'] ) ) {
			return;
		}

		$message = __( 'You\'ve received a Stream Alert.', 'stream' ) . "\n\n";
		$message .= sprintf( __( 'Action: %s', 'stream' ), $alert->alert_meta['trigger_action'] ) . "\n";
		$message .= sprintf( __( 'Connector: %s', 'stream' ), $alert->alert_meta['trigger_connector'] ) . "\n";
		if ( ! empty( $alert->alert_meta['trigger_context'] ) ) {
			$message .= sprintf( __( 'Context: %s', 'stream' ), $alert->alert_meta['trigger_context'] ) . "\n";
		}

		$user_id = $recordarr['user_id'];
		$user = get_user_by( 'id', $user_id );
		$message .= sprintf( __( 'Triggered By: %s', 'stream' ), $user->user_login ) . "\n";
		$message .= "\n";

		$post_id = $recordarr['object_id'];
		$post = get_post( $post_id );
		$post_type = get_post_type_object( $post->post_type );

		$message .= sprintf( __( 'The alert is in reference to the following %s:', 'stream' ), strtolower( $post_type->labels->singular_name ) ) . "\n\n";
		$message .= sprintf( __( 'ID: %s', 'stream' ), $post->ID ) . "\n";
		$message .= sprintf( __( 'Title: %s', 'stream' ), $post->post_title ) . "\n";
		$message .= sprintf( __( 'Last Updated: %s', 'stream' ), $post->post_modified ) . "\n";

		wp_mail( $options['email_recipient'], $options['email_subject'], $message );
	}

	/**
	 * Displays a settings form for the alert type
	 *
	 * @param Alert $alert Alert object for the currently displayed alert.
	 * @return void
	 */
	public function display_fields( $alert ) {
		$options = wp_parse_args( $alert->alert_meta, array(
			'email_recipient' => '',
			'email_subject'   => '',
		) );

		$form = new Form_Generator;

		echo '<p>Recipient:</p>';
		echo $form->render_field( 'text', array( // Xss ok.
			'name'    => 'wp_stream_email_recipient',
			'title'   => esc_attr( __( 'Email Recipient', 'stream' ) ),
			'value'   => $options['email_recipient'],
		) );

		echo '<p>Subject:</p>';
		echo $form->render_field( 'text', array( // Xss ok.
			'name'    => 'wp_stream_email_subject',
			'title'   => esc_attr( __( 'Email Subject', 'stream' ) ),
			'value'   => $options['email_subject'],
		) );
	}

	/**
	 * Validates and saves form settings for later use.
	 *
	 * @param Alert $alert Alert object for the currently displayed alert.
	 * @return void
	 */
	public function save_fields( $alert ) {
		check_admin_referer( 'save_post', 'wp_stream_alerts_nonce' );

		if ( ! empty( $_POST['wp_stream_email_recipient'] ) ) {
			$alert->alert_meta['email_recipient'] = $_POST['wp_stream_email_recipient'];
		}

		if ( ! empty( $_POST['wp_stream_email_subject'] ) ) {
			$alert->alert_meta['email_subject'] = $_POST['wp_stream_email_subject'];
		}
	}
}
