<?php
/**
 * Email Alerts.
 *
 * Idea for future expansion: allow customization of email.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class Alert_Type_Email
 *
 * @package WP_Stream
 */
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
	 * Class Constructor
	 *
	 * @param Plugin $plugin Plugin object.
	 * @return void
	 */
	public function __construct( $plugin ) {
		parent::__construct( $plugin );
		$this->plugin = $plugin;
		if ( ! is_admin() ) {
			return;
		}
		add_filter(
			'wp_stream_alerts_save_meta',
			array(
				$this,
				'add_alert_meta',
			),
			10,
			2
		);
	}

	/**
	 * Sends an email to the given recipient.
	 *
	 * @param int   $record_id Record that triggered notification.
	 * @param array $recordarr Record details.
	 * @param Alert $alert Alert options.
	 * @return void
	 */
	public function alert( $record_id, $recordarr, $alert ) {
		$options = wp_parse_args(
			$alert->alert_meta,
			array(
				'email_recipient'   => '',
				'email_subject'     => '',
				'trigger_action'    => '',
				'trigger_connector' => '',
				'trigger_context'   => '',
			)
		);

		if ( empty( $options['email_recipient'] ) && empty( $options['email_subject'] ) ) {
			return;
		}

		// translators: Placeholder refers to the title of a site (e.g. "FooBar Website").
		$message = sprintf( __( 'A Stream Alert was triggered on %s.', 'stream' ), get_bloginfo( 'name' ) ) . "\n\n";

		$user_id = $recordarr['user_id'];
		$user    = get_user_by( 'id', $user_id );

		// translators: Placeholder refers to a username  (e.g. "administrator").
		$message .= sprintf( __( "User:\t%s", 'stream' ), $user->user_login ) . "\n";

		if ( ! empty( $alert->alert_meta['trigger_context'] ) ) {
			$context = $this->plugin->alerts->alert_triggers['context']->get_display_value( 'list_table', $alert );

			// translators: Placeholder refers to the context of the record (e.g. "Plugins").
			$message .= sprintf( __( "Context:\t%s", 'stream' ), $context ) . "\n";
		}
		if ( ! empty( $alert->alert_meta['trigger_action'] ) ) {
			$action = $this->plugin->alerts->alert_triggers['action']->get_display_value( 'list_table', $alert );

			// translators: Placeholder refers to the action of the record (e.g. "Installed").
			$message .= sprintf( __( "Action:\t%s", 'stream' ), $action ) . "\n";
		}

		$post = null;
		if ( isset( $recordarr['object_id'] ) ) {
			$post_id = $recordarr['object_id'];
			$post    = get_post( $post_id );
		}
		if ( is_object( $post ) && ! empty( $post ) ) {
			$post_type = get_post_type_object( $post->post_type );

			$message .= $post_type->labels->singular_name . ":\t" . $post->post_title . "\n\n";

			$edit_post_link = get_edit_post_link( $post->ID, 'raw' );

			// translators: Placeholder refers to the post type singular name (e.g. "Post").
			$message .= sprintf( __( 'Edit %s', 'stream' ), $post_type->labels->singular_name ) . "\n<$edit_post_link>\n";
		}

		$message .= "\n";

		$edit_alert_link = admin_url( 'edit.php?post_type=wp_stream_alerts#post-' . $alert->ID );
		$message        .= __( 'Edit Alert', 'stream' ) . "\n<$edit_alert_link>";

		wp_mail( $options['email_recipient'], $options['email_subject'], $message );
	}

	/**
	 * Displays a settings form for the alert type
	 *
	 * @param Alert $alert Alert object for the currently displayed alert.
	 * @return void
	 */
	public function display_fields( $alert ) {
		$alert_meta = array();
		if ( is_object( $alert ) ) {
			$alert_meta = $alert->alert_meta;
		}
		$options = wp_parse_args(
			$alert_meta,
			array(
				'email_recipient' => '',
				'email_subject'   => '',
			)
		);

		$form = new Form_Generator();
		echo '<span class="wp_stream_alert_type_description">' . esc_html__( 'Send a notification email to the recipient.', 'stream' ) . '</span>';
		echo '<label for="wp_stream_email_recipient"><span class="title">' . esc_html__( 'Recipient', 'stream' ) . '</span>';
		echo '<span class="input-text-wrap">';
		echo $form->render_field(
			'text',
			array(
				'name'  => 'wp_stream_email_recipient',
				'title' => esc_attr( __( 'Email Recipient', 'stream' ) ),
				'value' => $options['email_recipient'],
			)
		); // Xss ok.
		echo '</span></label>';

		echo '<label for="wp_stream_email_subject"><span class="title">' . esc_html__( 'Subject', 'stream' ) . '</span>';
		echo '<span class="input-text-wrap">';
		echo $form->render_field(
			'text',
			array(
				'name'  => 'wp_stream_email_subject',
				'title' => esc_attr( __( 'Email Subject', 'stream' ) ),
				'value' => $options['email_subject'],
			)
		); // Xss ok.
		echo '</span></label>';
	}

	/**
	 * Validates and saves form settings for later use.
	 *
	 * @param Alert $alert Alert object for the currently displayed alert.
	 * @return void
	 */
	public function save_fields( $alert ) {
		check_admin_referer( 'save_alert', 'wp_stream_alerts_nonce' );

		if ( isset( $_POST['wp_stream_email_recipient'] ) ) {
			$alert->alert_meta['email_recipient'] = sanitize_text_field( wp_unslash( $_POST['wp_stream_email_recipient'] ) );
		}

		if ( isset( $_POST['wp_stream_email_subject'] ) ) {
			$alert->alert_meta['email_subject'] = sanitize_text_field( wp_unslash( $_POST['wp_stream_email_subject'] ) );
		}
	}

	/**
	 * Add alert meta if this is a highlight alert
	 *
	 * @param array  $alert_meta The metadata to be inserted for this alert.
	 * @param string $alert_type The type of alert being added or updated.
	 *
	 * @return mixed
	 */
	public function add_alert_meta( $alert_meta, $alert_type ) {
		if ( $this->slug === $alert_type ) {
			$email_recipient = wp_stream_filter_input( INPUT_POST, 'wp_stream_email_recipient' );
			if ( ! empty( $email_recipient ) ) {
				$alert_meta['email_recipient'] = $email_recipient;
			}
			$email_subject = wp_stream_filter_input( INPUT_POST, 'wp_stream_email_subject' );
			if ( ! empty( $email_subject ) ) {
				$alert_meta['email_subject'] = $email_subject;
			}
		}

		return $alert_meta;
	}
}
