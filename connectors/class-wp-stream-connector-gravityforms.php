<?php

class WP_Stream_Connector_GravityForms extends WP_Stream_Connector {

	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public static $name = 'gravityforms';

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
	public static $actions = array(
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
	public static $options = array();

	/**
	 * Tracking registered Settings, with overridden data
	 *
	 * @var array
	 */
	public static $options_override = array();

	/**
	 * Check if plugin dependencies are satisfied and add an admin notice if not
	 *
	 * @return bool
	 */
	public static function is_dependency_satisfied() {
		if ( class_exists( 'GFForms' ) && version_compare( GFCommon::$version, self::PLUGIN_MIN_VERSION, '>=' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Return translated connector label
	 *
	 * @return string Translated connector label
	 */
	public static function get_label() {
		return _x( 'Gravity Forms', 'gravityforms', 'stream' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public static function get_action_labels() {
		return array(
			'created'    => _x( 'Created', 'gravityforms', 'stream' ),
			'updated'    => _x( 'Updated', 'gravityforms', 'stream' ),
			'exported'   => _x( 'Exported', 'gravityforms', 'stream' ),
			'imported'   => _x( 'Imported', 'gravityforms', 'stream' ),
			'added'      => _x( 'Added', 'gravityforms', 'stream' ),
			'deleted'    => _x( 'Deleted', 'gravityforms', 'stream' ),
			'trashed'    => _x( 'Trashed', 'gravityforms', 'stream' ),
			'untrashed'  => _x( 'Restored', 'gravityforms', 'stream' ),
			'duplicated' => _x( 'Duplicated', 'gravityforms', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public static function get_context_labels() {
		return array(
			'forms'    => _x( 'Forms', 'gravityforms', 'stream' ),
			'settings' => _x( 'Settings', 'gravityforms', 'stream' ),
			'export'   => _x( 'Import/Export', 'gravityforms', 'stream' ),
			'entries'  => _x( 'Entries', 'gravityforms', 'stream' ),
			'notes'    => _x( 'Notes', 'gravityforms', 'stream' ),
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
	public static function action_links( $links, $record ) {
		if ( 'forms' === $record->context ) {
			$links[ __( 'Edit', 'stream' ) ] = add_query_arg(
				array(
					'page' => 'gf_edit_forms',
					'id' => $record->object_id,
				),
				admin_url( 'admin.php' )
			);
		} elseif ( 'entries' === $record->context ) {
			$links[ __( 'View', 'stream' ) ] = add_query_arg(
				array(
					'page' => 'gf_entries',
					'view' => 'entry',
					'lid' => $record->object_id,
					'id' => wp_stream_get_meta( $record, 'form_id', true ),
				),
				admin_url( 'admin.php' )
			);
		} elseif ( 'notes' === $record->context ) {
			$links[ __( 'View', 'stream' ) ] = add_query_arg(
				array(
					'page' => 'gf_entries',
					'view' => 'entry',
					'lid' => wp_stream_get_meta( $record, 'lead_id', true ),
					'id' => wp_stream_get_meta( $record, 'form_id', true ),
				),
				admin_url( 'admin.php' )
			);
		} elseif ( 'settings' === $record->context ) {
			$links[ __( 'Edit Settings', 'stream' ) ] = add_query_arg(
				array(
					'page' => 'gf_settings',
				),
				admin_url( 'admin.php' )
			);
		}

		return $links;
	}

	public static function register() {
		parent::register();

		self::$options = array(
			'rg_gforms_disable_css'         => array(
				'label' => _x( 'Output CSS', 'gravityforms', 'stream' ),
			),
			'rg_gforms_enable_html5'        => array(
				'label' => _x( 'Output HTML5', 'gravityforms', 'stream' ),
			),
			'gform_enable_noconflict'       => array(
				'label' => _x( 'No-Conflict Mode', 'gravityforms', 'stream' ),
			),
			'rg_gforms_currency'            => array(
				'label' => _x( 'Currency', 'gravityforms', 'stream' ),
			),
			'rg_gforms_captcha_public_key'  => array(
				'label' => _x( 'reCAPTCHA Public Key', 'gravityforms', 'stream' ),
			),
			'rg_gforms_captcha_private_key' => array(
				'label' => _x( 'reCAPTCHA Private Key', 'gravityforms', 'stream' ),
			),
			'rg_gforms_key'                 => null,
		);
	}

	/**
	 * Track Create/Update actions on Forms
	 *
	 * @param $form
	 * @param $is_new
	 */
	public static function callback_gform_after_save_form( $form, $is_new ) {
		$title = $form['title'];
		$id    = $form['id'];

		self::log(
			sprintf(
				__( '"%1$s" form %2$s', 'stream' ),
				$title,
				$is_new ? __( 'created', 'stream' ) : __( 'updated', 'stream' )
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
	 * @param $confirmation
	 * @param $form
	 * @param bool $is_new
	 *
	 * @return mixed
	 */
	public static function callback_gform_pre_confirmation_save( $confirmation, $form, $is_new = true ) {
		if ( ! isset( $is_new ) ) {
			$is_new = false;
		}

		self::log(
			sprintf(
				__( '"%1$s" confirmation %2$s for "%3$s"', 'stream' ),
				$confirmation['name'],
				$is_new ? __( 'created', 'stream' ) : __( 'updated', 'stream' ),
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
	 * @param $notification
	 * @param $form
	 * @param bool $is_new
	 *
	 * @return mixed
	 */
	public static function callback_gform_pre_notification_save( $notification, $form, $is_new = true ) {
		if ( ! isset( $is_new ) ) {
			$is_new = false;
		}

		self::log(
			sprintf(
				__( '"%1$s" notification %2$s for "%3$s"', 'stream' ),
				$notification['name'],
				$is_new ? __( 'created', 'stream' ) : __( 'updated', 'stream' ),
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
	 * @param $notification
	 * @param $form
	 */
	public static function callback_gform_notification_delete( $notification, $form ) {
		self::log(
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
	 * @param $confirmation
	 * @param $form
	 */
	public static function callback_gform_confirmation_delete( $confirmation, $form ) {
		self::log(
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
	 * @param $confirmation
	 * @param $form
	 * @param $is_active
	 */
	public static function callback_gform_confirmation_status( $confirmation, $form, $is_active ) {
		self::log(
			sprintf(
				__( '"%1$s" confirmation %2$s from "%3$s"', 'stream' ),
				$confirmation['name'],
				$is_active ? __( 'activated', 'stream' ) : __( 'deactivated', 'stream' ),
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
	 * @param $id
	 */
	public static function callback_gform_form_reset_views( $id ) {
		$form = self::get_form( $id );

		self::log(
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
	 * @param $notification
	 * @param $form
	 * @param $is_active
	 */
	public static function callback_gform_notification_status( $notification, $form, $is_active ) {
		self::log(
			sprintf(
				__( '"%1$s" notification %2$s from "%3$s"', 'stream' ),
				$notification['name'],
				$is_active ? __( 'activated', 'stream' ) : __( 'deactivated', 'stream' ),
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
	 * @param $id
	 * @param $action
	 */
	public static function callback_gform_form_status_change( $id, $action ) {
		$form    = self::get_form( $id );
		$actions = array(
			'activated'   => __( 'Activated', 'stream' ),
			'deactivated' => __( 'Deactivated', 'stream' ),
			'trashed'     => __( 'Trashed', 'stream' ),
			'untrashed'   => __( 'Restored', 'stream' ),
		);

		self::log(
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

	public static function callback_update_option( $option, $old, $new ) {
		self::check( $option, $old, $new );
	}

	public static function callback_add_option( $option, $val ) {
		self::check( $option, null, $val );
	}

	public static function callback_delete_option( $option ) {
		self::check( $option, null, null );
	}

	public static function callback_update_site_option( $option, $old, $new ) {
		self::check( $option, $old, $new );
	}

	public static function callback_add_site_option( $option, $val ) {
		self::check( $option, null, $val );
	}

	public static function callback_delete_site_option( $option ) {
		self::check( $option, null, null );
	}

	public static function check( $option, $old_value, $new_value ) {
		if ( ! array_key_exists( $option, self::$options ) ) {
			return;
		}

		if ( is_null( self::$options[ $option ] ) ) {
			call_user_func( array( __CLASS__, 'check_' . str_replace( '-', '_', $option ) ), $old_value, $new_value );
		} else {
			$data         = self::$options[ $option ];
			$option_title = $data['label'];
			$context      = isset( $data['context'] ) ? $data['context'] : 'settings';

			self::log(
				__( '"%s" setting updated', 'stream' ),
				compact( 'option_title', 'option', 'old_value', 'new_value' ),
				null,
				$context,
				isset( $data['action'] ) ? $data['action'] : 'updated'
			);
		}
	}

	public static function check_rg_gforms_key( $old_value, $new_value ) {
		$is_update = ( $new_value && strlen( $new_value ) );
		$option    = 'rg_gforms_key';

		self::log(
			sprintf(
				__( 'Gravity Forms license key %s', 'stream' ),
				$is_update ? __( 'updated', 'stream' ) : __( 'deleted', 'stream' )
			),
			compact( 'option', 'old_value', 'new_value' ),
			null,
			'settings',
			$is_update ? 'updated' : 'deleted'
		);
	}

	public static function callback_gform_export_separator( $dummy, $form_id ) {
		$form = self::get_form( $form_id );

		self::log(
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

	public static function callback_gform_import_form_xml_options( $dummy ) {
		self::log(
			__( 'Import process started', 'stream' ),
			array(),
			null,
			'export',
			'imported'
		);

		return $dummy;
	}

	public static function callback_gform_export_options( $dummy, $forms ) {
		$ids    = wp_list_pluck( $forms, 'id' );
		$titles = wp_list_pluck( $forms, 'title' );

		self::log(
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

	public static function callback_gform_before_delete_form( $id ) {
		$form = self::get_form( $id );

		self::log(
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

	public static function callback_gform_form_duplicate( $id, $new_id ) {
		$form = self::get_form( $id );
		$new  = self::get_form( $new_id );

		self::log(
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

	public static function callback_gform_delete_lead( $lead_id ) {
		$lead = GFFormsModel::get_lead( $lead_id );
		$form = self::get_form( $lead['form_id'] );

		self::log(
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

	public static function callback_gform_insert_note( $note_id, $lead_id, $user_id, $user_name, $note, $note_type ) {
		$lead = GFFormsModel::get_lead( $lead_id );
		$form = self::get_form( $lead['form_id'] );

		self::log(
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

	public static function callback_gform_delete_note( $note_id, $lead_id ) {
		$lead = GFFormsModel::get_lead( $lead_id );
		$form = self::get_form( $lead['form_id'] );

		self::log(
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

	public static function callback_gform_update_status( $lead_id, $status, $prev = '' ) {
		$lead = GFFormsModel::get_lead( $lead_id );
		$form = self::get_form( $lead['form_id'] );

		if ( 'active' === $status && 'trash' === $prev ) {
			$status = 'restore';
		}

		$actions = array(
			'active'  => __( 'activated', 'stream' ),
			'spam'    => __( 'marked as spam', 'stream' ),
			'trash'   => __( 'trashed', 'stream' ),
			'restore' => __( 'restored', 'stream' ),
		);

		if ( ! isset( $actions[ $status ] ) ) {
			return;
		}

		self::log(
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

	public static function callback_gform_update_is_read( $lead_id, $status ) {
		$lead = GFFormsModel::get_lead( $lead_id );
		$form = self::get_form( $lead['form_id'] );

		self::log(
			sprintf(
				__( 'Lead #%1$d marked as %2$s on "%3$s" form', 'stream' ),
				$lead_id,
				$status ? __( 'read', 'stream' ) : __( 'unread', 'stream' ),
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

	public static function callback_gform_update_is_starred( $lead_id, $status ) {
		$lead = GFFormsModel::get_lead( $lead_id );
		$form = self::get_form( $lead['form_id'] );

		self::log(
			sprintf(
				__( 'Lead #%1$d %2$s on "%3$s" form', 'stream' ),
				$lead_id,
				$status ? __( 'starred', 'stream' ) : __( 'unstarred', 'stream' ),
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

	private static function get_form( $form_id ) {
		return reset( GFFormsModel::get_forms_by_id( $form_id ) );
	}

}
