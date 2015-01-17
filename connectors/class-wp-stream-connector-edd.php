<?php

class WP_Stream_Connector_EDD extends WP_Stream_Connector {

	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public static $name = 'edd';

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
		'update_option',
		'add_option',
		'delete_option',
		'update_site_option',
		'add_site_option',
		'delete_site_option',
		'edd_pre_update_discount_status',
		'edd_generate_pdf',
		'edd_earnings_export',
		'edd_payment_export',
		'edd_email_export',
		'edd_downloads_history_export',
		'edd_import_settings',
		'edd_export_settings',
		'add_user_meta',
		'update_user_meta',
		'delete_user_meta',
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
	 * Tracking user meta updates related to this connector
	 *
	 * @var array
	 */
	public static $user_meta = array(
		'edd_user_public_key',
	);

	/**
	 * Flag status changes to not create duplicate entries
	 * @var bool
	 */
	public static $is_discount_status_change = false;

	/**
	 * Flag status changes to not create duplicate entries
	 * @var bool
	 */
	public static $is_payment_status_change = false;

	/**
	 * Check if plugin dependencies are satisfied and add an admin notice if not
	 *
	 * @return bool
	 */
	public static function is_dependency_satisfied() {
		if ( class_exists( 'Easy_Digital_Downloads' ) && defined( 'EDD_VERSION' ) && version_compare( EDD_VERSION, self::PLUGIN_MIN_VERSION, '>=' ) ) {
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
		return _x( 'Easy Digital Downloads', 'edd', 'stream' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public static function get_action_labels() {
		return array(
			'created'   => _x( 'Created', 'edd', 'stream' ),
			'updated'   => _x( 'Updated', 'edd', 'stream' ),
			'added'     => _x( 'Added', 'edd', 'stream' ),
			'deleted'   => _x( 'Deleted', 'edd', 'stream' ),
			'trashed'   => _x( 'Trashed', 'edd', 'stream' ),
			'untrashed' => _x( 'Restored', 'edd', 'stream' ),
			'generated' => _x( 'Generated', 'edd', 'stream' ),
			'imported'  => _x( 'Imported', 'edd', 'stream' ),
			'exported'  => _x( 'Exported', 'edd', 'stream' ),
			'revoked'   => _x( 'Revoked', 'edd', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public static function get_context_labels() {
		return array(
			'downloads'         => _x( 'Downloads', 'edd', 'stream' ),
			'download_category' => _x( 'Categories', 'edd', 'stream' ),
			'download_tag'      => _x( 'Tags', 'edd', 'stream' ),
			'discounts'         => _x( 'Discounts', 'edd', 'stream' ),
			'reports'           => _x( 'Reports', 'edd', 'stream' ),
			'api_keys'          => _x( 'API Keys', 'edd', 'stream' ),
			//'payments'        => _x( 'Payments', 'edd', 'stream' ),
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
		if ( in_array( $record->context, array( 'downloads' ) ) ) {
			$links = WP_Stream_Connector_Posts::action_links( $links, $record );
		} elseif ( in_array( $record->context, array( 'discounts' ) ) ) {
			$post_type_label = get_post_type_labels( get_post_type_object( 'edd_discount' ) )->singular_name;
			$base            = admin_url( 'edit.php?post_type=download&page=edd-discounts' );

			$links[ sprintf( __( 'Edit %s', 'stream' ), $post_type_label ) ] = add_query_arg(
				array(
					'edd-action' => 'edit_discount',
					'discount'   => $record->object_id,
				),
				$base
			);

			if ( 'active' === get_post( $record->object_id )->post_status ) {
				$links[ sprintf( __( 'Deactivate %s', 'stream' ), $post_type_label ) ] = add_query_arg(
					array(
						'edd-action' => 'deactivate_discount',
						'discount'   => $record->object_id,
					),
					$base
				);
			} else {
				$links[ sprintf( __( 'Activate %s', 'stream' ), $post_type_label ) ] = add_query_arg(
					array(
						'edd-action' => 'activate_discount',
						'discount'   => $record->object_id,
					),
					$base
				);
			}
		} elseif ( in_array( $record->context, array( 'download_category', 'download_tag' ) ) ) {
			$tax_label = get_taxonomy_labels( get_taxonomy( $record->context ) )->singular_name;
			$links[ sprintf( __( 'Edit %s', 'stream' ), $tax_label ) ] = get_edit_term_link( $record->object_id, wp_stream_get_meta( $record, 'taxonomy', true ) );
		} elseif ( 'api_keys' === $record->context ) {
			$user = new WP_User( $record->object_id );

			if ( apply_filters( 'edd_api_log_requests', true ) ) {
				$links[ __( 'View API Log', 'stream' ) ] = add_query_arg( array( 'view' => 'api_requests', 'post_type' => 'download', 'page' => 'edd-reports', 'tab' => 'logs', 's' => $user->user_email ), 'edit.php' );
			}

			$links[ __( 'Revoke', 'stream' ) ]  = add_query_arg( array( 'post_type' => 'download', 'user_id' => $record->object_id, 'edd_action' => 'process_api_key', 'edd_api_process' => 'revoke' ), 'edit.php' );
			$links[ __( 'Reissue', 'stream' ) ] = add_query_arg( array( 'post_type' => 'download', 'user_id' => $record->object_id, 'edd_action' => 'process_api_key', 'edd_api_process' => 'regenerate' ), 'edit.php' );
		}

		return $links;
	}

	public static function register() {
		parent::register();

		add_filter( 'wp_stream_log_data', array( __CLASS__, 'log_override' ) );

		self::$options = array(
			'edd_settings' => null,
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

		$replacement = str_replace( '-', '_', $option );

		if ( method_exists( __CLASS__, 'check_' . $replacement ) ) {
			call_user_func( array( __CLASS__, 'check_' . $replacement ), $old_value, $new_value );
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

	public static function check_edd_settings( $old_value, $new_value ) {
		$options = array();

		if ( ! is_array( $old_value ) || ! is_array( $new_value ) ) {
			return;
		}

		foreach ( self::get_changed_keys( $old_value, $new_value, 0 ) as $field_key => $field_value ) {
			$options[ $field_key ] = $field_value;
		}

		$settings = edd_get_registered_settings();

		foreach ( $options as $option => $option_value ) {
			$field = null;

			if ( 'banned_email' === $option ) {
				$field = array(
					'name' => _x( 'Banned emails', 'edd', 'stream' ),
				);
				$page = 'edd-tools';
				$tab  = 'general';
			} else {
				$page = 'edd-settings';

				foreach ( $settings as $tab => $fields ) {
					if ( isset( $fields[ $option ] ) ) {
						$field = $fields[ $option ];
						break;
					}
				}
			}

			if ( empty( $field ) ) {
				continue;
			}

			self::log(
				__( '"%s" setting updated', 'stream' ),
				array(
					'option_title' => $field['name'],
					'option'       => $option,
					'old_value'    => maybe_serialize( $old_value ),
					'value'        => maybe_serialize( $new_value ),
					'tab'          => $tab,
				),
				null,
				'settings',
				'updated'
			);
		}
	}

	/**
	 * Override connector log for our own Settings / Actions
	 *
	 * @param array $data
	 *
	 * @return array|bool
	 */
	public static function log_override( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		if ( 'posts' === $data['connector'] && 'download' === $data['context'] ) {
			// Download posts operations
			$data['context']  = 'downloads';
			$data['connector'] = self::$name;
		} elseif ( 'posts' === $data['connector'] && 'edd_discount' === $data['context'] ) {
			// Discount posts operations
			if ( self::$is_discount_status_change ) {
				return false;
			}

			if ( 'deleted' === $data['action'] ) {
				$data['message'] = __( '"%1s" discount deleted', 'stream' );
			}

			$data['context']  = 'discounts';
			$data['connector'] = self::$name;
		} elseif ( 'posts' === $data['connector'] && 'edd_payment' === $data['context'] ) {
			// Payment posts operations
			return false; // Do not track payments, they're well logged!
		} elseif ( 'posts' === $data['connector'] && 'edd_log' === $data['context'] ) {
			// Logging operations
			return false; // Do not track notes, because they're basically logs
		} elseif ( 'comments' === $data['connector'] && 'edd_payment' === $data['context'] ) {
			// Payment notes ( comments ) operations
			return false; // Do not track notes, because they're basically logs
		} elseif ( 'taxonomies' === $data['connector'] && 'download_category' === $data['context'] ) {
			$data['connector'] = self::$name;
		} elseif ( 'taxonomies' === $data['connector'] && 'download_tag' === $data['contexts'] ) {
			$data['connector'] = self::$name;
		} elseif ( 'taxonomies' === $data['connector'] && 'edd_log_type' === $data['contexts'] ) {
			return false;
		} elseif ( 'settings' === $data['connector'] && 'edd_settings' === $data['args']['option'] ) {
			return false;
		}

		return $data;
	}

	public static function callback_edd_pre_update_discount_status( $code_id, $new_status ) {
		self::$is_discount_status_change = true;

		self::log(
			sprintf(
				__( '"%1$s" discount %2$s', 'stream' ),
				get_post( $code_id )->post_title,
				'active' === $new_status ? __( 'activated', 'stream' ) : __( 'deactivated', 'stream' )
			),
			array(
				'post_id' => $code_id,
				'status'  => $new_status,
			),
			$code_id,
			'discounts',
			'updated'
		);
	}

	private static function callback_edd_generate_pdf() {
		self::report_generated( 'pdf' );
	}
	public static function callback_edd_earnings_export() {
		self::report_generated( 'earnings' );
	}
	public static function callback_edd_payment_export() {
		self::report_generated( 'payments' );
	}
	public static function callback_edd_email_export() {
		self::report_generated( 'emails' );
	}
	public static function callback_edd_downloads_history_export() {
		self::report_generated( 'download-history' );
	}

	private static function report_generated( $type ) {
		if ( 'pdf' === $type ) {
			$label = __( 'Sales and Earnings', 'stream' );
		} elseif ( 'earnings' ) {
			$label = __( 'Earnings', 'stream' );
		} elseif ( 'payments' ) {
			$label = __( 'Payments', 'stream' );
		} elseif ( 'emails' ) {
			$label = __( 'Emails', 'stream' );
		} elseif ( 'download-history' ) {
			$label = __( 'Download History', 'stream' );
		}

		self::log(
			sprintf(
				__( 'Generated %s report', 'stream' ),
				$label
			),
			array(
				'type' => $type,
			),
			null,
			'reports',
			'generated'
		);
	}

	public static function callback_edd_export_settings() {
		self::log(
			__( 'Exported Settings', 'stream' ),
			array(),
			null,
			'settings',
			'exported'
		);
	}

	public static function callback_edd_import_settings() {
		self::log(
			__( 'Imported Settings', 'stream' ),
			array(),
			null,
			'settings',
			'imported'
		);
	}

	public static function callback_update_user_meta( $meta_id, $object_id, $meta_key, $_meta_value ) {
		self::meta( $object_id, $meta_key, $_meta_value );
	}

	public static function callback_add_user_meta( $object_id, $meta_key, $_meta_value ) {
		self::meta( $object_id, $meta_key, $_meta_value, true );
	}

	public static function callback_delete_user_meta( $meta_id, $object_id, $meta_key, $_meta_value ) {
		self::meta( $object_id, $meta_key, null );
	}

	public static function meta( $object_id, $key, $value, $is_add = false ) {
		if ( ! in_array( $key, self::$user_meta ) ) {
			return false;
		}

		$key = str_replace( '-', '_', $key );

		if ( method_exists( __CLASS__, 'meta_' . $key ) ) {
			return call_user_func( array( __CLASS__, 'meta_' . $key ), $object_id, $value, $is_add );
		}
	}

	private static function meta_edd_user_public_key( $user_id, $value, $is_add = false ) {
		if ( is_null( $value ) ) {
			$action       = 'revoked';
			$action_title = __( 'revoked', 'stream' );
		} elseif ( $is_add ) {
			$action       = 'created';
			$action_title = __( 'created', 'stream' );
		} else {
			$action       = 'updated';
			$action_title = __( 'updated', 'stream' );
		}

		self::log(
			sprintf(
				__( 'User API Key %s', 'stream' ),
				$action_title
			),
			array(
				'meta_value' => $value,
			),
			$user_id,
			'api_keys',
			$action
		);
	}

}
