<?php
namespace WP_Stream;

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
	const PLUGIN_MIN_VERSION = '1.8.8';

	/**
	 * Actions registered for this connector
	 *
	 * @var array
	 */
	public $actions = array(
		'gform_after_save_form',
		'gform_pre_confirmation_save',
		'gform_pre_notification_save',
		'gform_notification_delete',
		'gform_confirmation_delete',
		'gform_notification_status',
		'gform_confirmation_status',
		'gform_form_status_change',
		'gform_form_reset_views',
		'gform_before_delete_form',
		'gform_form_trash',
		'gform_form_restore',
		'gform_form_duplicate',
		'gform_export_separator', // Export entries
		'gform_export_options', // Export forms
		'gform_import_form_xml_options', // Import
		'gform_delete_lead',
		'gform_insert_note',
		'gform_delete_note',
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
			'created'    => esc_html_x( 'Created', 'gravityforms', 'stream' ),
			'updated'    => esc_html_x( 'Updated', 'gravityforms', 'stream' ),
			'exported'   => esc_html_x( 'Exported', 'gravityforms', 'stream' ),
			'imported'   => esc_html_x( 'Imported', 'gravityforms', 'stream' ),
			'added'      => esc_html_x( 'Added', 'gravityforms', 'stream' ),
			'deleted'    => esc_html_x( 'Deleted', 'gravityforms', 'stream' ),
			'trashed'    => esc_html_x( 'Trashed', 'gravityforms', 'stream' ),
			'untrashed'  => esc_html_x( 'Restored', 'gravityforms', 'stream' ),
			'duplicated' => esc_html_x( 'Duplicated', 'gravityforms', 'stream' ),
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
	 * @param  array  $links     Previous links registered
	 * @param  object $record    Stream record
	 *
	 * @return array             Action links
	 */
	public function action_links( $links, $record ) {
		if ( 'forms' === $record->context ) {
			$links[ esc_html__( 'Edit', 'stream' ) ] = add_query_arg(
				array(
					'page' => 'gf_edit_forms',
					'id' => $record->object_id,
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
	 * @param array $form
	 * @param bool $is_new
	 */
	public function callback_gform_after_save_form( $form, $is_new ) {
		$title = $form['title'];
		$id    = $form['id'];

		$this->log(
			sprintf(
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
	 * @param array $confirmation
	 * @param array $form
	 * @param bool $is_new
	 *
	 * @return array
	 */
	public function callback_gform_pre_confirmation_save( $confirmation, $form, $is_new = true ) {
		if ( ! isset( $is_new ) ) {
			$is_new = false;
		}

		$this->log(
			sprintf(
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
	 * @param array $notification
	 * @param array $form
	 * @param bool $is_new
	 *
	 * @return array
	 */
	public function callback_gform_pre_notification_save( $notification, $form, $is_new = true ) {
		if ( ! isset( $is_new ) ) {
			$is_new = false;
		}

		$this->log(
			sprintf(
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
	 * @param array $notification
	 * @param array $form
	 */
	public function callback_gform_notification_delete( $notification, $form ) {
		$this->log(
			sprintf(
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
	 * @param array $confirmation
	 * @param array $form
	 */
	public function callback_gform_confirmation_delete( $confirmation, $form ) {
		$this->log(
			sprintf(
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
	 * @param array $confirmation
	 * @param array $form
	 * @param bool $is_active
	 */
	public function callback_gform_confirmation_status( $confirmation, $form, $is_active ) {
		$this->log(
			sprintf(
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
	 * Track status change of confirmations
	 *
	 * @param integer $id
	 */
	public function callback_gform_form_reset_views( $id ) {
		$form = $this->get_form( $id );

		$this->log(
			__( '"%s" form views reset', 'stream' ),
			array(
				'title'   => $form['title'],
				'form_id' => $form['id'],
			),
			$form['id'],
			'forms',
			'updated'
		);
	}

	/**
	 * Track status change of notifications
	 *
	 * @param array $notification
	 * @param array $form
	 * @param bool $is_active
	 */
	public function callback_gform_notification_status( $notification, $form, $is_active ) {
		$this->log(
			sprintf(
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
	 * Track status change of forms
	 *
	 * @param integer $id
	 * @param string $action
	 */
	public function callback_gform_form_status_change( $id, $action ) {
		$form    = $this->get_form( $id );
		$actions = array(
			'activated'   => esc_html__( 'Activated', 'stream' ),
			'deactivated' => esc_html__( 'Deactivated', 'stream' ),
			'trashed'     => esc_html__( 'Trashed', 'stream' ),
			'untrashed'   => esc_html__( 'Restored', 'stream' ),
		);

		$this->log(
			sprintf(
				__( '"%1$s" form %2$s', 'stream' ),
				$form['title'],
				$actions[ $action ]
			),
			array(
				'form_title' => $form['title'],
				'form_id'    => $id,
			),
			$form['id'],
			'forms',
			$action
		);
	}

	public function callback_update_option( $option, $old, $new ) {
		$this->check( $option, $old, $new );
	}

	public function callback_add_option( $option, $val ) {
		$this->check( $option, null, $val );
	}

	public function callback_delete_option( $option ) {
		$this->check( $option, null, null );
	}

	public function callback_update_site_option( $option, $old, $new ) {
		$this->check( $option, $old, $new );
	}

	public function callback_add_site_option( $option, $val ) {
		$this->check( $option, null, $val );
	}

	public function callback_delete_site_option( $option ) {
		$this->check( $option, null, null );
	}

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
				__( '"%s" setting updated', 'stream' ),
				compact( 'option_title', 'option', 'old_value', 'new_value' ),
				null,
				$context,
				isset( $data['action'] ) ? $data['action'] : 'updated'
			);
		}
	}

	public function check_rg_gforms_key( $old_value, $new_value ) {
		$is_update = ( $new_value && strlen( $new_value ) );
		$option    = 'rg_gforms_key';

		$this->log(
			sprintf(
				__( 'Gravity Forms license key %s', 'stream' ),
				$is_update ? esc_html__( 'updated', 'stream' ) : esc_html__( 'deleted', 'stream' )
			),
			compact( 'option', 'old_value', 'new_value' ),
			null,
			'settings',
			$is_update ? 'updated' : 'deleted'
		);
	}

	public function callback_gform_export_separator( $dummy, $form_id ) {
		$form = $this->get_form( $form_id );

		$this->log(
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

	public function callback_gform_import_form_xml_options( $dummy ) {
		$this->log(
			__( 'Import process started', 'stream' ),
			array(),
			null,
			'export',
			'imported'
		);

		return $dummy;
	}

	public function callback_gform_export_options( $dummy, $forms ) {
		$ids    = wp_list_pluck( $forms, 'id' );
		$titles = wp_list_pluck( $forms, 'title' );

		$this->log(
			__( 'Export process started for %d forms', 'stream' ),
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

	public function callback_gform_before_delete_form( $id ) {
		$form = $this->get_form( $id );

		$this->log(
			__( '"%s" form deleted', 'stream' ),
			array(
				'form_title' => $form['title'],
				'form_id'    => $id,
			),
			$form['id'],
			'forms',
			'deleted'
		);
	}

	public function callback_gform_form_duplicate( $id, $new_id ) {
		$form = $this->get_form( $id );
		$new  = $this->get_form( $new_id );

		$this->log(
			__( '"%1$s" form created as duplicate from "%2$s"', 'stream' ),
			array(
				'new_form_title' => $new['title'],
				'form_title'     => $form['title'],
				'form_id'        => $id,
				'new_id'         => $new_id,
			),
			$new_id,
			'forms',
			'duplicated'
		);
	}

	public function callback_gform_delete_lead( $lead_id ) {
		$lead = \GFFormsModel::get_lead( $lead_id );
		$form = $this->get_form( $lead['form_id'] );

		$this->log(
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

	public function callback_gform_insert_note( $note_id, $lead_id, $user_id, $user_name, $note, $note_type ) {
		$lead = \GFFormsModel::get_lead( $lead_id );
		$form = $this->get_form( $lead['form_id'] );

		$this->log(
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

	public function callback_gform_delete_note( $note_id, $lead_id ) {
		$lead = \GFFormsModel::get_lead( $lead_id );
		$form = $this->get_form( $lead['form_id'] );

		$this->log(
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

	public function callback_gform_update_status( $lead_id, $status, $prev = '' ) {
		$lead = \GFFormsModel::get_lead( $lead_id );
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

	public function callback_gform_update_is_read( $lead_id, $status ) {
		$lead = \GFFormsModel::get_lead( $lead_id );
		$form = $this->get_form( $lead['form_id'] );

		$this->log(
			sprintf(
				__( 'Lead #%1$d marked as %2$s on "%3$s" form', 'stream' ),
				$lead_id,
				$status ? esc_html__( 'read', 'stream' ) : esc_html__( 'unread', 'stream' ),
				$form['title']
			),
			array(
				'lead_id'    => $lead_id,
				'form_title' => $form['title'],
				'form_id'    => $form['id'],
				'status'     => $status,
			),
			$lead_id,
			'entries',
			'updated'
		);
	}

	public function callback_gform_update_is_starred( $lead_id, $status ) {
		$lead = \GFFormsModel::get_lead( $lead_id );
		$form = $this->get_form( $lead['form_id'] );

		$this->log(
			sprintf(
				__( 'Lead #%1$d %2$s on "%3$s" form', 'stream' ),
				$lead_id,
				$status ? esc_html__( 'starred', 'stream' ) : esc_html__( 'unstarred', 'stream' ),
				$form['title']
			),
			array(
				'lead_id'    => $lead_id,
				'form_title' => $form['title'],
				'form_id'    => $form['id'],
				'status'     => $status,
			),
			$lead_id,
			'entries',
			'updated'
		);
	}

	private function get_form( $form_id ) {
		return reset( \GFFormsModel::get_forms_by_id( $form_id ) );
	}
}
