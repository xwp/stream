<?php
/**
 * Connector for Gravity Forms
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Connector_GravityForms
 */
class Connector_GravityForms extends Connector {
	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public $name = 'gravityforms';

	/**
	 * Holds tracked plugin minimum version required
	 *
	 * @const string
	 */
	const PLUGIN_MIN_VERSION = '1.9.14';

	/**
	 * Actions registered for this connector
	 *
	 * @var array
	 */
	public $actions = array(
		'gform_after_save_form',
		'gform_pre_confirmation_save',
		'gform_pre_notification_save',
		'gform_pre_notification_deleted',
		'gform_pre_confirmation_deleted',
		'gform_before_delete_form',
		'gform_post_form_trashed',
		'gform_post_form_restored',
		'gform_post_form_activated',
		'gform_post_form_deactivated',
		'gform_post_form_duplicated',
		'gform_post_form_views_deleted',
		'gform_post_export_entries',
		'gform_forms_post_import',
		'gform_delete_lead',
		'gform_post_note_added',
		'gform_pre_note_deleted',
		'gform_update_status',
		'gform_update_is_read',
		'gform_update_is_starred',
		'update_option',
		'add_option',
		'delete_option',
		'update_site_option',
		'add_site_option',
		'delete_site_option',
	);

	/**
	 * Tracked option keys
	 *
	 * @var array
	 */
	public $options = array();

	/**
	 * Tracking registered Settings, with overridden data
	 *
	 * @var array
	 */
	public $options_override = array();

	/**
	 * Check if plugin dependencies are satisfied and add an admin notice if not
	 *
	 * @return bool
	 */
	public function is_dependency_satisfied() {
		if ( class_exists( 'GFForms' ) && version_compare( \GFCommon::$version, self::PLUGIN_MIN_VERSION, '>=' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Return translated connector label
	 *
	 * @return string Translated connector label
	 */
	public function get_label() {
		return esc_html_x( 'Gravity Forms', 'gravityforms', 'stream' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public function get_action_labels() {
		return array(
			'created'       => esc_html_x( 'Created', 'gravityforms', 'stream' ),
			'updated'       => esc_html_x( 'Updated', 'gravityforms', 'stream' ),
			'exported'      => esc_html_x( 'Exported', 'gravityforms', 'stream' ),
			'imported'      => esc_html_x( 'Imported', 'gravityforms', 'stream' ),
			'added'         => esc_html_x( 'Added', 'gravityforms', 'stream' ),
			'deleted'       => esc_html_x( 'Deleted', 'gravityforms', 'stream' ),
			'trashed'       => esc_html_x( 'Trashed', 'gravityforms', 'stream' ),
			'untrashed'     => esc_html_x( 'Restored', 'gravityforms', 'stream' ),
			'duplicated'    => esc_html_x( 'Duplicated', 'gravityforms', 'stream' ),
			'activated'     => esc_html_x( 'Activated', 'gravityforms', 'stream' ),
			'deactivated'   => esc_html_x( 'Deactivated', 'gravityforms', 'stream' ),
			'views_deleted' => esc_html_x( 'Views Reset', 'gravityforms', 'stream' ),
			'starred'       => esc_html_x( 'Starred', 'gravityforms', 'stream' ),
			'unstarred'     => esc_html_x( 'Unstarred', 'gravityforms', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public function get_context_labels() {
		return array(
			'forms'    => esc_html_x( 'Forms', 'gravityforms', 'stream' ),
			'settings' => esc_html_x( 'Settings', 'gravityforms', 'stream' ),
			'export'   => esc_html_x( 'Import/Export', 'gravityforms', 'stream' ),
			'entries'  => esc_html_x( 'Entries', 'gravityforms', 'stream' ),
			'notes'    => esc_html_x( 'Notes', 'gravityforms', 'stream' ),
		);
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_{connector}
	 *
	 * @param  array  $links   Previous links registered.
	 * @param  object $record  Stream record.
	 *
	 * @return array             Action links
	 */
	public function action_links( $links, $record ) {
		if ( 'forms' === $record->context ) {
			$links[ esc_html__( 'Edit', 'stream' ) ] = add_query_arg(
				array(
					'page' => 'gf_edit_forms',
					'id'   => $record->object_id,
				),
				admin_url( 'admin.php' )
			);
		} elseif ( 'entries' === $record->context ) {
			$links[ esc_html__( 'View', 'stream' ) ] = add_query_arg(
				array(
					'page' => 'gf_entries',
					'view' => 'entry',
					'lid'  => $record->object_id,
					'id'   => $record->get_meta( 'form_id', true ),
				),
				admin_url( 'admin.php' )
			);
		} elseif ( 'notes' === $record->context ) {
			$links[ esc_html__( 'View', 'stream' ) ] = add_query_arg(
				array(
					'page' => 'gf_entries',
					'view' => 'entry',
					'lid'  => $record->get_meta( 'lead_id', true ),
					'id'   => $record->get_meta( 'form_id', true ),
				),
				admin_url( 'admin.php' )
			);
		} elseif ( 'settings' === $record->context ) {
			$links[ esc_html__( 'Edit Settings', 'stream' ) ] = add_query_arg(
				array(
					'page' => 'gf_settings',
				),
				admin_url( 'admin.php' )
			);
		}

		return $links;
	}

	/**
	 * Register all context hooks
	 */
	public function register() {
		parent::register();

		$this->options = array(
			'rg_gforms_disable_css'         => array(
				'label' => esc_html_x( 'Output CSS', 'gravityforms', 'stream' ),
			),
			'rg_gforms_enable_html5'        => array(
				'label' => esc_html_x( 'Output HTML5', 'gravityforms', 'stream' ),
			),
			'gform_enable_noconflict'       => array(
				'label' => esc_html_x( 'No-Conflict Mode', 'gravityforms', 'stream' ),
			),
			'rg_gforms_currency'            => array(
				'label' => esc_html_x( 'Currency', 'gravityforms', 'stream' ),
			),
			'rg_gforms_captcha_public_key'  => array(
				'label' => esc_html_x( 'reCAPTCHA Public Key', 'gravityforms', 'stream' ),
			),
			'rg_gforms_captcha_private_key' => array(
				'label' => esc_html_x( 'reCAPTCHA Private Key', 'gravityforms', 'stream' ),
			),
			'rg_gforms_key'                 => null,
		);
	}

	/**
	 * Track Create/Update actions on Forms
	 *
	 * @param array $form    Form data.
	 * @param bool  $is_new  Is this a new form?.
	 */
	public function callback_gform_after_save_form( $form, $is_new ) {
		$title = $form['title'];
		$id    = $form['id'];

		$this->log(
			sprintf(
				/* translators: %1$s a form title, %2$s a status (e.g. "Contact Form", "created") */
				__( '"%1$s" form %2$s', 'stream' ),
				$title,
				$is_new ? esc_html__( 'created', 'stream' ) : esc_html__( 'updated', 'stream' )
			),
			array(
				'action' => $is_new,
				'id'     => $id,
				'title'  => $title,
			),
			$id,
			'forms',
			$is_new ? 'created' : 'updated'
		);
	}

	/**
	 * Track saving form confirmations
	 *
	 * @param array $confirmation  Confirmation data.
	 * @param array $form          Form data.
	 * @param bool  $is_new        Is this a new form?.
	 */
	public function callback_gform_pre_confirmation_save( $confirmation, $form, $is_new = true ) {
		if ( ! isset( $is_new ) ) {
			$is_new = false;
		}

		$this->log(
			sprintf(
				/* translators: %1$s: a confirmation name, %2$s: a status, %3$s: a form title (e.g. "Email", "created", "Contact Form") */
				__( '"%1$s" confirmation %2$s for "%3$s"', 'stream' ),
				$confirmation['name'],
				$is_new ? esc_html__( 'created', 'stream' ) : esc_html__( 'updated', 'stream' ),
				$form['title']
			),
			array(
				'is_new'  => $is_new,
				'form_id' => $form['id'],
			),
			$form['id'],
			'forms',
			'updated'
		);

		return $confirmation;
	}

	/**
	 * Track saving form notifications
	 *
	 * @param array $notification  Notification data.
	 * @param array $form          Form data.
	 * @param bool  $is_new        Is this a new form?.
	 * @return array
	 */
	public function callback_gform_pre_notification_save( $notification, $form, $is_new = true ) {
		if ( ! isset( $is_new ) ) {
			$is_new = false;
		}

		$this->log(
			sprintf(
				/* translators: %1$s: a notification name, %2$s: a status, %3$s: a form title (e.g. "Email", "created", "Contact Form") */
				__( '"%1$s" notification %2$s for "%3$s"', 'stream' ),
				$notification['name'],
				$is_new ? esc_html__( 'created', 'stream' ) : esc_html__( 'updated', 'stream' ),
				$form['title']
			),
			array(
				'is_update' => $is_new,
				'form_id'   => $form['id'],
			),
			$form['id'],
			'forms',
			'updated'
		);

		return $notification;
	}

	/**
	 * Track deletion of notifications
	 *
	 * @param array $notification  Notification data.
	 * @param array $form          Form data.
	 */
	public function callback_gform_pre_notification_deleted( $notification, $form ) {
		$this->log(
			sprintf(
				/* translators: %1$s: a notification name, %2$s: a form title (e.g. "Email", "Contact Form") */
				__( '"%1$s" notification deleted from "%2$s"', 'stream' ),
				$notification['name'],
				$form['title']
			),
			array(
				'form_id'      => $form['id'],
				'notification' => $notification,
			),
			$form['id'],
			'forms',
			'updated'
		);
	}

	/**
	 * Track deletion of confirmations
	 *
	 * @param array $confirmation  Confirmation data.
	 * @param array $form          Form data.
	 */
	public function callback_gform_pre_confirmation_deleted( $confirmation, $form ) {
		$this->log(
			sprintf(
				/* translators: %1$s: a confirmation name, %2$s: a form title (e.g. "Email", "Contact Form") */
				__( '"%1$s" confirmation deleted from "%2$s"', 'stream' ),
				$confirmation['name'],
				$form['title']
			),
			array(
				'form_id'      => $form['id'],
				'confirmation' => $confirmation,
			),
			$form['id'],
			'forms',
			'updated'
		);
	}

	/**
	 * Track status change of confirmations
	 *
	 * @param array $confirmation  Confirmation data.
	 * @param array $form          Form data.
	 * @param bool  $is_active     Is this form active?.
	 */
	public function callback_gform_confirmation_status( $confirmation, $form, $is_active ) {
		$this->log(
			sprintf(
				/* translators: %1$s: a confirmation name, %2$s: a status, %3$s: a form title (e.g. "Email", "activated", "Contact Form") */
				__( '"%1$s" confirmation %2$s from "%3$s"', 'stream' ),
				$confirmation['name'],
				$is_active ? esc_html__( 'activated', 'stream' ) : esc_html__( 'deactivated', 'stream' ),
				$form['title']
			),
			array(
				'form_id'      => $form['id'],
				'confirmation' => $confirmation,
				'is_active'    => $is_active,
			),
			null,
			'forms',
			'updated'
		);
	}

	/**
	 * Track status change of notifications
	 *
	 * @param array $notification  Notification data.
	 * @param array $form          Form data.
	 * @param bool  $is_active     Is this form active?.
	 */
	public function callback_gform_notification_status( $notification, $form, $is_active ) {
		$this->log(
			sprintf(
				/* translators: %1$s: a notification name, %2$s: a status, %3$s: a form title (e.g. "Email", "activated", "Contact Form") */
				__( '"%1$s" notification %2$s from "%3$s"', 'stream' ),
				$notification['name'],
				$is_active ? esc_html__( 'activated', 'stream' ) : esc_html__( 'deactivated', 'stream' ),
				$form['title']
			),
			array(
				'form_id'      => $form['id'],
				'notification' => $notification,
				'is_active'    => $is_active,
			),
			$form['id'],
			'forms',
			'updated'
		);
	}

	/**
	 * Track GravityForms-specific option changes.
	 *
	 * @param string $option Option key.
	 * @param string $old    Old value.
	 * @param string $new    New value.
	 */
	public function callback_update_option( $option, $old, $new ) {
		$this->check( $option, $old, $new );
	}

	/**
	 * Track GravityForms-specific option creations.
	 *
	 * @param string $option Option key.
	 * @param string $val    Value.
	 */
	public function callback_add_option( $option, $val ) {
		$this->check( $option, null, $val );
	}

	/**
	 * Track GravityForms-specific option deletions.
	 *
	 * @param string $option Option key.
	 */
	public function callback_delete_option( $option ) {
		$this->check( $option, null, null );
	}

	/**
	 * Track GravityForms-specific site option changes
	 *
	 * @param string $option Option key.
	 * @param string $old    Old value.
	 * @param string $new    New value.
	 */
	public function callback_update_site_option( $option, $old, $new ) {
		$this->check( $option, $old, $new );
	}

	/**
	 * Track GravityForms-specific site option creations.
	 *
	 * @param string $option Option key.
	 * @param string $val    Value.
	 */
	public function callback_add_site_option( $option, $val ) {
		$this->check( $option, null, $val );
	}

	/**
	 * Track GravityForms-specific site option deletions.
	 *
	 * @param string $option Option key.
	 */
	public function callback_delete_site_option( $option ) {
		$this->check( $option, null, null );
	}

	/**
	 * Logs GravityForms-specific (site) option activity.
	 *
	 * @param string $option     Option key.
	 * @param string $old_value  Old value.
	 * @param string $new_value  New value.
	 */
	public function check( $option, $old_value, $new_value ) {
		if ( ! array_key_exists( $option, $this->options ) ) {
			return;
		}

		if ( is_null( $this->options[ $option ] ) ) {
			call_user_func( array( $this, 'check_' . str_replace( '-', '_', $option ) ), $old_value, $new_value );
		} else {
			$data         = $this->options[ $option ];
			$option_title = $data['label'];
			$context      = isset( $data['context'] ) ? $data['context'] : 'settings';

			$this->log(
				/* translators: %s: a setting title (e.g. "Language") */
				__( '"%s" setting updated', 'stream' ),
				compact( 'option_title', 'option', 'old_value', 'new_value' ),
				null,
				$context,
				isset( $data['action'] ) ? $data['action'] : 'updated'
			);
		}
	}

	/**
	 * Log GravityForm license key changes
	 *
	 * @param string $old_value  Old license key.
	 * @param string $new_value  New license key.
	 * @return void
	 */
	public function check_rg_gforms_key( $old_value, $new_value ) {
		$is_update = ( $new_value && strlen( $new_value ) );
		$option    = 'rg_gforms_key';

		$this->log(
			sprintf(
				/* translators: %s: a status (e.g. "updated") */
				__( 'Gravity Forms license key %s', 'stream' ),
				$is_update ? esc_html__( 'updated', 'stream' ) : esc_html__( 'deleted', 'stream' )
			),
			compact( 'option', 'old_value', 'new_value' ),
			null,
			'settings',
			$is_update ? 'updated' : 'deleted'
		);
	}

	/**
	 * Logs form entry exports.
	 *
	 * @action gform_post_export_entries
	 *
	 * @param object $form        Form data.
	 * @param string $start_date  Form start date.
	 * @param string $end_date    Form completion date.
	 * @param array  $fields      Form fields data.
	 */
	public function callback_gform_post_export_entries( $form, $start_date, $end_date, $fields ) {
		unset( $fields );
		$this->log(
			/* translators: %s: a form title (e.g. "Contact Form") */
			__( '"%s" form entries exported', 'stream' ),
			array(
				'form_title' => $form['title'],
				'form_id'    => $form['id'],
				'start_date' => empty( $start_date ) ? null : $start_date,
				'end_date'   => empty( $end_date ) ? null : $end_date,
			),
			$form['id'],
			'export',
			'exported'
		);
	}

	/**
	 * Logs form imports.
	 *
	 * @action gform_forms_post_import
	 *
	 * @param array $forms  List of form data.
	 */
	public function callback_gform_forms_post_import( $forms ) {
		$forms_total  = count( $forms );
		$forms_ids    = wp_list_pluck( $forms, 'id' );
		$forms_titles = wp_list_pluck( $forms, 'title' );

		$this->log(
			/* translators: %d: a number of forms (e.g. "42") */
			_n( '%d form imported', '%d forms imported', $forms_total, 'stream' ),
			array(
				'count'  => $forms_total,
				'ids'    => $forms_ids,
				'titles' => $forms_titles,
			),
			null,
			'export',
			'imported'
		);
	}

	/**
	 * Logs form exports
	 *
	 * @action gform_export_separator
	 *
	 * @param string $dummy    Unused.
	 * @param int    $form_id  Form ID.
	 */
	public function callback_gform_export_separator( $dummy, $form_id ) {
		$form = $this->get_form( $form_id );

		$this->log(
			/* translators: %s: a form title (e.g. "Contact Form") */
			__( '"%s" form exported', 'stream' ),
			array(
				'form_title' => $form['title'],
				'form_id'    => $form_id,
			),
			$form_id,
			'export',
			'exported'
		);

		return $dummy;
	}

	/**
	 * Log bulk form exports
	 *
	 * @param string $dummy  Unused.
	 * @param array  $forms  Form data.
	 */
	public function callback_gform_export_options( $dummy, $forms ) {
		$ids    = wp_list_pluck( $forms, 'id' );
		$titles = wp_list_pluck( $forms, 'title' );

		$this->log(
			/* translators: %d: a number of forms (e.g. "42") */
			_n( 'Export process started for %d form', 'Export process started for %d forms', count( $forms ), 'stream' ),
			array(
				'count'  => count( $forms ),
				'ids'    => $ids,
				'titles' => $titles,
			),
			null,
			'export',
			'imported'
		);

		return $dummy;
	}

	/**
	 * Logs lead deletions.
	 *
	 * @action gform_delete_lead
	 *
	 * @param int $lead_id  Lead ID.
	 * @return void
	 */
	public function callback_gform_delete_lead( $lead_id ) {
		$lead = $this->get_lead( $lead_id );
		$form = $this->get_form( $lead['form_id'] );

		$this->log(
			/* translators: %1$d: to an ID, %2$s: a form title (e.g. "42", "Contact Form") */
			__( 'Lead #%1$d from "%2$s" deleted', 'stream' ),
			array(
				'lead_id'    => $lead_id,
				'form_title' => $form['title'],
				'form_id'    => $form['id'],
			),
			$lead_id,
			'entries',
			'deleted'
		);
	}

	/**
	 * Logs note creation on lead.
	 *
	 * @action gform_post_note_added
	 *
	 * @param int    $note_id    Note ID.
	 * @param int    $lead_id    Lead ID.
	 * @param int    $user_id    User ID of note author.
	 * @param string $user_name  Username of note author.
	 * @param string $note       Note object.
	 * @param string $note_type  Note type.
	 */
	public function callback_gform_post_note_added( $note_id, $lead_id, $user_id, $user_name, $note, $note_type ) {
		unset( $user_id );
		unset( $user_name );
		unset( $note );
		unset( $note_type );

		$lead = \GFFormsModel::get_lead( $lead_id );
		$form = $this->get_form( $lead['form_id'] );

		$this->log(
			/* translators: %1$d: an ID, %2$d: another ID, %3$s: a form title (e.g. "42", "7", "Contact Form") */
			__( 'Note #%1$d added to lead #%2$d on "%3$s" form', 'stream' ),
			array(
				'note_id'    => $note_id,
				'lead_id'    => $lead_id,
				'form_title' => $form['title'],
				'form_id'    => $form['id'],
			),
			$note_id,
			'notes',
			'added'
		);
	}

	/**
	 * Logs note deletion
	 *
	 * @action gform_pre_note_deleted
	 *
	 * @param int $note_id  Note ID.
	 * @param int $lead_id  Lead ID.
	 */
	public function callback_gform_pre_note_deleted( $note_id, $lead_id ) {
		$lead = $this->get_lead( $lead_id );
		$form = $this->get_form( $lead['form_id'] );

		$this->log(
			/* translators: %2$d an ID, another ID, and a form title (e.g. "42", "7", "Contact Form") */
			__( 'Note #%1$d deleted from lead #%2$d on "%3$s" form', 'stream' ),
			array(
				'note_id'    => $note_id,
				'lead_id'    => $lead_id,
				'form_title' => $form['title'],
				'form_id'    => $form['id'],
			),
			$note_id,
			'notes',
			'deleted'
		);
	}

	/**
	 * Logs form status updates.
	 *
	 * @action gform_update_status
	 *
	 * @param int    $lead_id  Lead ID.
	 * @param string $status   New form status.
	 * @param string $prev     Old form status.
	 */
	public function callback_gform_update_status( $lead_id, $status, $prev = '' ) {
		$lead = $this->get_lead( $lead_id );
		$form = $this->get_form( $lead['form_id'] );

		if ( 'active' === $status && 'trash' === $prev ) {
			$status = 'restore';
		}

		$actions = array(
			'active'  => esc_html__( 'activated', 'stream' ),
			'spam'    => esc_html__( 'marked as spam', 'stream' ),
			'trash'   => esc_html__( 'trashed', 'stream' ),
			'restore' => esc_html__( 'restored', 'stream' ),
		);

		if ( ! isset( $actions[ $status ] ) ) {
			return;
		}

		$this->log(
			sprintf(
				/* translators: %1$d: an ID, %2$s: a status, %3$s: a form title (e.g. "42", "activated", "Contact Form") */
				__( 'Lead #%1$d %2$s on "%3$s" form', 'stream' ),
				$lead_id,
				$actions[ $status ],
				$form['title']
			),
			array(
				'lead_id'    => $lead_id,
				'form_title' => $form['title'],
				'form_id'    => $form['id'],
				'status'     => $status,
				'prev'       => $prev,
			),
			$lead_id,
			'entries',
			$status
		);
	}

	/**
	 * Callback fired when an entry is read/unread
	 *
	 * @action update_is_read
	 *
	 * @param int    $lead_id  Lead ID.
	 * @param string $status   Status.
	 */
	public function callback_gform_update_is_read( $lead_id, $status ) {
		$lead   = $this->get_lead( $lead_id );
		$form   = $this->get_form( $lead['form_id'] );
		$status = ( ! empty( $status ) ) ? esc_html__( 'read', 'stream' ) : esc_html__( 'unread', 'stream' );

		$this->log(
			sprintf(
				/* translators: %1$d: a lead ID, %2$s: a status, %3$s: a form ID, %4$s: a form title (e.g. "42", "unread", "Contact Form") */
				__( 'Entry #%1$d marked as %2$s on form #%3$d ("%4$s")', 'stream' ),
				$lead_id,
				$status,
				$form['id'],
				$form['title']
			),
			array(
				'lead_id'     => $lead_id,
				'lead_status' => $status,
				'form_id'     => $form['id'],
				'form_title'  => $form['title'],
			),
			$lead_id,
			'entries',
			'updated'
		);
	}

	/**
	 * Callback fired when an entry is starred/unstarred
	 *
	 * @action gform_update_is_starred
	 *
	 * @param int $lead_id  Lead ID.
	 * @param int $status   Status.
	 */
	public function callback_gform_update_is_starred( $lead_id, $status ) {
		$lead   = $this->get_lead( $lead_id );
		$form   = $this->get_form( $lead['form_id'] );
		$status = ( ! empty( $status ) ) ? esc_html__( 'starred', 'stream' ) : esc_html__( 'unstarred', 'stream' );
		$action = $status;

		$this->log(
			sprintf(
				/* translators: %1$d: an ID, %2$s: a status, %3$d: a form title (e.g. "42", "starred", "Contact Form") */
				__( 'Entry #%1$d %2$s on form #%3$d ("%4$s")', 'stream' ),
				$lead_id,
				$status,
				$form['id'],
				$form['title']
			),
			array(
				'lead_id'     => $lead_id,
				'lead_status' => $status,
				'form_id'     => $form['id'],
				'form_title'  => $form['title'],
			),
			$lead_id,
			'entries',
			$action
		);
	}

	/**
	 * Callback fired when a form is deleted
	 *
	 * @action gform_before_delete_form
	 *
	 * @param int $form_id  Form ID.
	 */
	public function callback_gform_before_delete_form( $form_id ) {
		$this->log_form_action( $form_id, 'deleted' );
	}

	/**
	 * Callback fired when a form is trashed
	 *
	 * @action gform_post_form_trashed
	 *
	 * @param int $form_id  Form ID.
	 */
	public function callback_gform_post_form_trashed( $form_id ) {
		$this->log_form_action( $form_id, 'trashed' );
	}

	/**
	 * Callback fired when a form is restored
	 *
	 * @action gform_post_form_restored
	 *
	 * @param int $form_id  Form ID.
	 */
	public function callback_gform_post_form_restored( $form_id ) {
		$this->log_form_action( $form_id, 'untrashed' );
	}

	/**
	 * Callback fired when a form is activated
	 *
	 * @action gform_post_form_activated
	 *
	 * @param int $form_id  Form ID.
	 */
	public function callback_gform_post_form_activated( $form_id ) {
		$this->log_form_action( $form_id, 'activated' );
	}

	/**
	 * Callback fired when a form is deactivated
	 *
	 * @action gform_post_form_deactivated
	 *
	 * @param  int $form_id  Form ID.
	 */
	public function callback_gform_post_form_deactivated( $form_id ) {
		$this->log_form_action( $form_id, 'deactivated' );
	}

	/**
	 * Callback fired when a form is duplicated
	 *
	 * @action gform_post_form_duplicated
	 *
	 * @param  int $form_id  Form ID.
	 */
	public function callback_gform_post_form_duplicated( $form_id ) {
		$this->log_form_action( $form_id, 'duplicated' );
	}

	/**
	 * Callback fired when a form's views are reset
	 *
	 * @action gform_post_form_views_deleted
	 *
	 * @param int $form_id  Form ID.
	 */
	public function callback_gform_post_form_views_deleted( $form_id ) {
		$this->log_form_action( $form_id, 'views_deleted' );
	}

	/**
	 * Track status change of forms
	 *
	 * @param int    $form_id  Form ID.
	 * @param string $action   Action.
	 */
	public function log_form_action( $form_id, $action ) {
		$form = $this->get_form( $form_id );

		if ( empty( $form ) ) {
			return;
		}

		$actions = array(
			'activated'     => esc_html__( 'Activated', 'stream' ),
			'deactivated'   => esc_html__( 'Deactivated', 'stream' ),
			'trashed'       => esc_html__( 'Trashed', 'stream' ),
			'untrashed'     => esc_html__( 'Restored', 'stream' ),
			'duplicated'    => esc_html__( 'Duplicated', 'stream' ),
			'deleted'       => esc_html__( 'Deleted', 'stream' ),
			'views_deleted' => esc_html__( 'Views Reset', 'stream' ),
		);

		$this->log(
			sprintf(
				/* translators: %1$d: an ID, %2$s: a form title, %3$s: a status (e.g. "42", "Contact Form", "Activated") */
				__( 'Form #%1$d ("%2$s") %3$s', 'stream' ),
				$form_id,
				$form['title'],
				strtolower( $actions[ $action ] )
			),
			array(
				'form_id'     => $form_id,
				'form_title'  => $form['title'],
				'form_status' => strtolower( $action ),
			),
			$form['id'],
			'forms',
			$action
		);
	}

	/**
	 * Helper function to get a single entry
	 *
	 * @param int $lead_id  Lead ID.
	 */
	private function get_lead( $lead_id ) {
		return \GFFormsModel::get_lead( $lead_id );
	}

	/**
	 * Helper function to get a single form
	 *
	 * @param int $form_id  Form ID.
	 */
	private function get_form( $form_id ) {
		return \GFFormsModel::get_form_meta( $form_id );
	}
}
