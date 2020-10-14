<?php
/**
 * Connector for Easy Digital Downloads
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Connector_EDD
 */
class Connector_EDD extends Connector {

	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public $name = 'edd';

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
	public $options = array();

	/**
	 * Tracking registered Settings, with overridden data
	 *
	 * @var array
	 */
	public $options_override = array();

	/**
	 * Tracking user meta updates related to this connector
	 *
	 * @var array
	 */
	public $user_meta = array(
		'edd_user_public_key',
	);

	/**
	 * Flag status changes to not create duplicate entries
	 *
	 * @var bool
	 */
	public $is_discount_status_change = false;

	/**
	 * Flag status changes to not create duplicate entries
	 *
	 * @var bool
	 */
	public $is_payment_status_change = false;

	/**
	 * Check if plugin dependencies are satisfied and add an admin notice if not
	 *
	 * @return bool
	 */
	public function is_dependency_satisfied() {
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
	public function get_label() {
		return esc_html_x( 'Easy Digital Downloads', 'edd', 'stream' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public function get_action_labels() {
		return array(
			'created'   => esc_html_x( 'Created', 'edd', 'stream' ),
			'updated'   => esc_html_x( 'Updated', 'edd', 'stream' ),
			'added'     => esc_html_x( 'Added', 'edd', 'stream' ),
			'deleted'   => esc_html_x( 'Deleted', 'edd', 'stream' ),
			'trashed'   => esc_html_x( 'Trashed', 'edd', 'stream' ),
			'untrashed' => esc_html_x( 'Restored', 'edd', 'stream' ),
			'generated' => esc_html_x( 'Generated', 'edd', 'stream' ),
			'imported'  => esc_html_x( 'Imported', 'edd', 'stream' ),
			'exported'  => esc_html_x( 'Exported', 'edd', 'stream' ),
			'revoked'   => esc_html_x( 'Revoked', 'edd', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public function get_context_labels() {
		return array(
			'downloads'         => esc_html_x( 'Downloads', 'edd', 'stream' ),
			'download_category' => esc_html_x( 'Categories', 'edd', 'stream' ),
			'download_tag'      => esc_html_x( 'Tags', 'edd', 'stream' ),
			'discounts'         => esc_html_x( 'Discounts', 'edd', 'stream' ),
			'reports'           => esc_html_x( 'Reports', 'edd', 'stream' ),
			'api_keys'          => esc_html_x( 'API Keys', 'edd', 'stream' ),
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
		if ( in_array( $record->context, array( 'downloads' ), true ) ) {
			$posts_connector = new Connector_Posts();
			$links           = $posts_connector->action_links( $links, $record );
		} elseif ( in_array( $record->context, array( 'discounts' ), true ) ) {
			$post_type_label = get_post_type_labels( get_post_type_object( 'edd_discount' ) )->singular_name;
			$base            = admin_url( 'edit.php?post_type=download&page=edd-discounts' );

			/* translators: %s: a post type (e.g. "Post") */
			$links[ sprintf( esc_html__( 'Edit %s', 'stream' ), $post_type_label ) ] = add_query_arg(
				array(
					'edd-action' => 'edit_discount',
					'discount'   => $record->object_id,
				),
				$base
			);

			if ( 'active' === get_post( $record->object_id )->post_status ) {
				/* translators: %s: a post type (e.g. "Post") */
				$links[ sprintf( esc_html__( 'Deactivate %s', 'stream' ), $post_type_label ) ] = add_query_arg(
					array(
						'edd-action' => 'deactivate_discount',
						'discount'   => $record->object_id,
					),
					$base
				);
			} else {
				/* translators: %s a post type (e.g. "Post") */
				$links[ sprintf( esc_html__( 'Activate %s', 'stream' ), $post_type_label ) ] = add_query_arg(
					array(
						'edd-action' => 'activate_discount',
						'discount'   => $record->object_id,
					),
					$base
				);
			}
		} elseif ( in_array(
			$record->context,
			array(
				'download_category',
				'download_tag',
			),
			true
		) ) {
			$tax_label = get_taxonomy_labels( get_taxonomy( $record->context ) )->singular_name;
			/* translators: %s a taxonomy (e.g. "Category") */
			$links[ sprintf( esc_html__( 'Edit %s', 'stream' ), $tax_label ) ] = get_edit_term_link( $record->object_id, $record->get_meta( 'taxonomy', true ) );
		} elseif ( 'api_keys' === $record->context ) {
			$user = new \WP_User( $record->object_id );

			if ( apply_filters( 'edd_api_log_requests', true ) ) {
				$links[ esc_html__( 'View API Log', 'stream' ) ] = add_query_arg(
					array(
						'view'      => 'api_requests',
						'post_type' => 'download',
						'page'      => 'edd-reports',
						'tab'       => 'logs',
						's'         => $user->user_email,
					),
					'edit.php'
				);
			}

			$links[ esc_html__( 'Revoke', 'stream' ) ]  = add_query_arg(
				array(
					'post_type'       => 'download',
					'user_id'         => $record->object_id,
					'edd_action'      => 'process_api_key',
					'edd_api_process' => 'revoke',
				),
				'edit.php'
			);
			$links[ esc_html__( 'Reissue', 'stream' ) ] = add_query_arg(
				array(
					'post_type'       => 'download',
					'user_id'         => $record->object_id,
					'edd_action'      => 'process_api_key',
					'edd_api_process' => 'regenerate',
				),
				'edit.php'
			);
		}

		return $links;
	}

	/**
	 * Register the connector
	 */
	public function register() {
		parent::register();

		add_filter( 'wp_stream_log_data', array( $this, 'log_override' ) );

		$this->options = array(
			'edd_settings' => null,
		);
	}

	/**
	 * Track EDD-specific option changes.
	 *
	 * @param string $option Option key.
	 * @param string $old    Old value.
	 * @param string $new    New value.
	 */
	public function callback_update_option( $option, $old, $new ) {
		$this->check( $option, $old, $new );
	}

	/**
	 * Track EDD-specific option creations.
	 *
	 * @param string $option Option key.
	 * @param string $val    Value.
	 */
	public function callback_add_option( $option, $val ) {
		$this->check( $option, null, $val );
	}

	/**
	 * Track EDD-specific option deletions.
	 *
	 * @param string $option Option key.
	 */
	public function callback_delete_option( $option ) {
		$this->check( $option, null, null );
	}

	/**
	 * Track EDD-specific site option changes
	 *
	 * @param string $option Option key.
	 * @param string $old    Old value.
	 * @param string $new    New value.
	 */
	public function callback_update_site_option( $option, $old, $new ) {
		$this->check( $option, $old, $new );
	}

	/**
	 * Track EDD-specific site option creations.
	 *
	 * @param string $option Option key.
	 * @param string $val    Value.
	 */
	public function callback_add_site_option( $option, $val ) {
		$this->check( $option, null, $val );
	}

	/**
	 * Track EDD-specific site option deletions.
	 *
	 * @param string $option Option key.
	 */
	public function callback_delete_site_option( $option ) {
		$this->check( $option, null, null );
	}

	/**
	 * Logs EDD-specific (site) option action.
	 *
	 * @param string $option     Option key.
	 * @param string $old_value  Old value.
	 * @param string $new_value  New value.
	 */
	public function check( $option, $old_value, $new_value ) {
		if ( ! array_key_exists( $option, $this->options ) ) {
			return;
		}

		$replacement = str_replace( '-', '_', $option );

		if ( method_exists( $this, 'check_' . $replacement ) ) {
			$method = "check_{$replacement}";
			$this->{$method}( $old_value, $new_value );
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
	 * Logs EDD setting changes.
	 *
	 * @param string $old_value  Old value.
	 * @param string $new_value  New value.
	 */
	public function check_edd_settings( $old_value, $new_value ) {
		$options = array();

		if ( ! is_array( $old_value ) || ! is_array( $new_value ) ) {
			return;
		}

		foreach ( $this->get_changed_keys( $old_value, $new_value, 0 ) as $field_key => $field_value ) {
			$options[ $field_key ] = $field_value;
		}

		// TODO: Check this exists first.
		$settings = \edd_get_registered_settings();

		foreach ( $options as $option => $option_value ) {
			$field = null;
			$tab   = null;

			if ( 'banned_email' === $option ) {
				$field = array(
					'name' => esc_html_x( 'Banned emails', 'edd', 'stream' ),
				);
				$tab   = 'general';
			} else {
				foreach ( $settings as $current_tab => $tab_sections ) {
					foreach ( $tab_sections as $section => $section_fields ) {
						if ( in_array( $option, array_keys( $section_fields ), true ) ) {
							$field = $section_fields[ $option ];
							$tab   = $current_tab;
							break;
						}
					}
				}
			}

			if ( empty( $field ) ) {
				continue;
			}

			$this->log(
				/* translators: %s: a setting title (e.g. "Language") */
				__( '"%s" setting updated', 'stream' ),
				array(
					'option_title' => $field['name'],
					'option'       => $option,
					'old_value'    => isset( $old_value[ $option ] ) ? $old_value[ $option ] : null,
					'value'        => isset( $new_value[ $option ] ) ? $new_value[ $option ] : null,
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
	 * @param array $data  Record data.
	 *
	 * @return array|bool
	 */
	public function log_override( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		if ( 'posts' === $data['connector'] && 'download' === $data['context'] ) {
			// Download posts operations.
			$data['context']   = 'downloads';
			$data['connector'] = $this->name;
		} elseif ( 'posts' === $data['connector'] && 'edd_discount' === $data['context'] ) {
			// Discount posts operations.
			if ( $this->is_discount_status_change ) {
				return false;
			}

			if ( 'deleted' === $data['action'] ) {
				/* translators: %s: a discount title (e.g. "Mother's Day") */
				$data['message'] = esc_html__( '"%s" discount deleted', 'stream' );
			}

			$data['context']   = 'discounts';
			$data['connector'] = $this->name;
		} elseif ( 'posts' === $data['connector'] && 'edd_payment' === $data['context'] ) {
			// Payment posts operations.
			return false; // Do not track payments, they're well logged!
		} elseif ( 'posts' === $data['connector'] && 'edd_log' === $data['context'] ) {
			// Logging operations.
			return false; // Do not track notes, because they're basically logs.
		} elseif ( 'comments' === $data['connector'] && 'edd_payment' === $data['context'] ) {
			// Payment notes ( comments ) operations.
			return false; // Do not track notes, because they're basically logs.
		} elseif ( 'taxonomies' === $data['connector'] && 'download_category' === $data['context'] ) {
			$data['connector'] = $this->name;
		} elseif ( 'taxonomies' === $data['connector'] && 'download_tag' === $data['context'] ) {
			$data['connector'] = $this->name;
		} elseif ( 'taxonomies' === $data['connector'] && 'edd_log_type' === $data['context'] ) {
			return false;
		} elseif ( 'settings' === $data['connector'] && 'edd_settings' === $data['args']['option'] ) {
			return false;
		}

		return $data;
	}

	/**
	 * Undocumented function
	 *
	 * @action edd_pre_update_discount_status
	 *
	 * @param int    $code_id     Post ID.
	 * @param string $new_status  Post status.
	 * @return void
	 */
	public function callback_edd_pre_update_discount_status( $code_id, $new_status ) {
		$this->is_discount_status_change = true;

		$this->log(
			sprintf(
				/* translators: %1$s: a discount title, %2$s: a status (e.g. "Mother's Day", "activated") */
				__( '"%1$s" discount %2$s', 'stream' ),
				get_post( $code_id )->post_title,
				'active' === $new_status ? esc_html__( 'activated', 'stream' ) : esc_html__( 'deactivated', 'stream' )
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
	/**
	 * Logs PDFs
	 *
	 * @action edd_generate_pdf
	 */
	private function callback_edd_generate_pdf() {
		$this->report_generated( 'pdf' );
	}

	/**
	 * Logs earning reports.
	 *
	 * @action edd_earnings_export
	 */
	public function callback_edd_earnings_export() {
		$this->report_generated( 'earnings' );
	}

	/**
	 * Logs payment reports.
	 *
	 * @action edd_payment_export
	 */
	public function callback_edd_payment_export() {
		$this->report_generated( 'payments' );
	}

	/**
	 * Logs email reports.
	 *
	 * @action edd_email_export
	 */
	public function callback_edd_email_export() {
		$this->report_generated( 'emails' );
	}

	/**
	 * Logs download history reports.
	 *
	 * @action edd_downloads_history_export
	 */
	public function callback_edd_downloads_history_export() {
		$this->report_generated( 'download-history' );
	}

	/**
	 * Logs generated reports.
	 *
	 * @param string $type  Report type.
	 */
	private function report_generated( $type ) {
		$label = '';

		if ( 'pdf' === $type ) {
			$label = esc_html__( 'Sales and Earnings', 'stream' );
		} elseif ( 'earnings' ) {
			$label = esc_html__( 'Earnings', 'stream' );
		} elseif ( 'payments' ) {
			$label = esc_html__( 'Payments', 'stream' );
		} elseif ( 'emails' ) {
			$label = esc_html__( 'Emails', 'stream' );
		} elseif ( 'download-history' ) {
			$label = esc_html__( 'Download History', 'stream' );
		}

		$this->log(
			sprintf(
				/* translators: %s: a report title (e.g. "Sales and Earnings") */
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

	/**
	 * Logs exported settings
	 *
	 * @action edd_export_settings
	 */
	public function callback_edd_export_settings() {
		$this->log(
			__( 'Exported Settings', 'stream' ),
			array(),
			null,
			'settings',
			'exported'
		);
	}

	/**
	 * Logs imported settings
	 *
	 * @action edd_import_settings
	 */
	public function callback_edd_import_settings() {
		$this->log(
			__( 'Imported Settings', 'stream' ),
			array(),
			null,
			'settings',
			'imported'
		);
	}

	/**
	 * Logs EDD-specific user meta changes.
	 *
	 * @action update_user_meta
	 *
	 * @param int    $meta_id      Meta ID.
	 * @param int    $object_id    Object ID.
	 * @param string $meta_key     Meta key.
	 * @param string $_meta_value  Meta value.
	 */
	public function callback_update_user_meta( $meta_id, $object_id, $meta_key, $_meta_value ) {
		unset( $meta_id );
		$this->meta( $object_id, $meta_key, $_meta_value );
	}

	/**
	 * Logs EDD-specific user meta creations.
	 *
	 * @action add_user_meta
	 *
	 * @param int    $object_id    Object ID.
	 * @param string $meta_key     Meta key.
	 * @param string $_meta_value  Meta value.
	 */
	public function callback_add_user_meta( $object_id, $meta_key, $_meta_value ) {
		$this->meta( $object_id, $meta_key, $_meta_value, true );
	}

	/**
	 * Logs EDD-specific user meta deletions.
	 *
	 * @action delete_user_meta
	 *
	 * @param int    $meta_id      Meta ID.
	 * @param int    $object_id    Object ID.
	 * @param string $meta_key     Meta key.
	 * @param string $_meta_value  Meta value.
	 */
	public function callback_delete_user_meta( $meta_id, $object_id, $meta_key, $_meta_value ) {
		$this->meta( $object_id, $meta_key, null );
	}

	/**
	 * Logs EDD-specific user meta activity.
	 *
	 * @param int    $object_id  Object ID.
	 * @param string $key        Meta key.
	 * @param string $value      Meta value.
	 * @param bool   $is_add     Is this a new meta?.
	 */
	public function meta( $object_id, $key, $value, $is_add = false ) {
		// For catching "edd_user_public_key" in newer versions of EDD.
		if ( in_array( $value, $this->user_meta, true ) ) {
			$key   = $value;
			$value = 1; // Probably, should avoid storing the api key.
		}

		if ( ! in_array( $key, $this->user_meta, true ) ) {
			return false;
		}

		$key = str_replace( '-', '_', $key );

		if ( ! method_exists( $this, 'meta_' . $key ) ) {
			return false;
		}

		$method = "meta_{$key}";
		return $this->{$method}( $object_id, $value, $is_add );
	}

	/**
	 * Logs change to User API key
	 *
	 * @param int    $user_id  User ID.
	 * @param string $value    API Key.
	 * @param bool   $is_add   Is this a new API key.
	 */
	private function meta_edd_user_public_key( $user_id, $value, $is_add = false ) {
		if ( is_null( $value ) ) {
			$action       = 'revoked';
			$action_title = esc_html__( 'revoked', 'stream' );
		} elseif ( $is_add ) {
			$action       = 'created';
			$action_title = esc_html__( 'created', 'stream' );
		} else {
			$action       = 'updated';
			$action_title = esc_html__( 'updated', 'stream' );
		}

		$this->log(
			sprintf(
				/* translators: %s: a status (e.g. "revoked") */
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
