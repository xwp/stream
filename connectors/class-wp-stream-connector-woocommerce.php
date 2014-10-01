<?php

class WP_Stream_Connector_Woocommerce extends WP_Stream_Connector {

	/**
	 * Context name
	 * @var string
	 */
	public static $name = 'woocommerce';

	/**
	 * Holds tracked plugin minimum version required
	 *
	 * @const string
	 */
	const PLUGIN_MIN_VERSION = '2.1.10';

	/**
	 * Actions registered for this context
	 * @var array
	 */
	public static $actions = array(
		'wp_stream_record_array',
		'updated_option',
		'transition_post_status',
		'deleted_post',
		'woocommerce_order_status_changed',
		'woocommerce_attribute_added',
		'woocommerce_attribute_updated',
		'woocommerce_attribute_deleted',
		'woocommerce_tax_rate_added',
		'woocommerce_tax_rate_updated',
		'woocommerce_tax_rate_deleted',
	);

	public static $taxonomies = array(
		'product_type',
		'product_cat',
		'product_tag',
		'product_shipping_class',
		'shop_order_status',
	);

	public static $post_types = array(
		'product',
		'product_variation',
		'shop_order',
		'shop_coupon',
	);

	private static $order_update_logged = false;

	private static $settings_pages = array();

	private static $settings = array();

	private static $custom_settings = array();

	public static function register() {
		parent::register();

		add_filter( 'wp_stream_posts_exclude_post_types', array( __CLASS__, 'exclude_order_post_types' ) );
		add_action( 'wp_stream_comments_exclude_comment_types', array( __CLASS__, 'exclude_order_comment_types' ) );

		self::get_woocommerce_settings_fields();
	}

	/**
	 * Check if plugin dependencies are satisfied and add an admin notice if not
	 *
	 * @return bool
	 */
	public static function is_dependency_satisfied() {
		global $woocommerce;

		if ( class_exists( 'WooCommerce' ) && version_compare( $woocommerce->version, self::PLUGIN_MIN_VERSION, '>=' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Return translated context label
	 *
	 * @return string Translated context label
	 */
	public static function get_label() {
		return _x( 'WooCommerce', 'woocommerce', 'stream' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public static function get_action_labels() {
		return array(
			'updated' => _x( 'Updated', 'woocommerce', 'stream' ),
			'created' => _x( 'Created', 'woocommerce', 'stream' ),
			'trashed' => _x( 'Trashed', 'woocommerce', 'stream' ),
			'deleted' => _x( 'Deleted', 'woocommerce', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public static function get_context_labels() {
		$context_labels = array();

		if ( class_exists( 'WP_Stream_Connector_Posts' ) ) {
			$context_labels = array_merge(
				$context_labels,
				WP_Stream_Connector_Posts::get_context_labels()
			);
		}

		$custom_context_labels = array(
			'attributes' => _x( 'Attributes', 'woocommerce', 'stream' ),
		);

		$context_labels = array_merge(
			$context_labels,
			$custom_context_labels,
			self::$settings_pages
		);

		return apply_filters( 'wp_stream_woocommerce_contexts', $context_labels );
	}

	/**
	 * Return settings used by WooCommerce that aren't registered
	 *
	 * @return array Custom settings with translated title and page
	 */
	public static function get_custom_settings() {
		$custom_settings = array(
			'woocommerce_frontend_css_colors' => array(
				'title'   => __( 'Frontend Styles', 'stream' ),
				'page'    => 'wc-settings',
				'tab'     => 'general',
				'section' => '',
				'type'    => __( 'setting', 'stream' ),
			),
			'woocommerce_default_gateway' => array(
				'title'   => __( 'Gateway Display Default', 'stream' ),
				'page'    => 'wc-settings',
				'tab'     => 'checkout',
				'section' => '',
				'type'    => __( 'setting', 'stream' ),
			),
			'woocommerce_gateway_order' => array(
				'title'   => __( 'Gateway Display Order', 'stream' ),
				'page'    => 'wc-settings',
				'tab'     => 'checkout',
				'section' => '',
				'type'    => __( 'setting', 'stream' ),
			),
			'woocommerce_default_shipping_method' => array(
				'title'   => __( 'Shipping Methods Default', 'stream' ),
				'page'    => 'wc-settings',
				'tab'     => 'shipping',
				'section' => '',
				'type'    => __( 'setting', 'stream' ),
			),
			'woocommerce_shipping_method_order' => array(
				'title'   => __( 'Shipping Methods Order', 'stream' ),
				'page'    => 'wc-settings',
				'tab'     => 'shipping',
				'section' => '',
				'type'    => __( 'setting', 'stream' ),
			),
			'shipping_debug_mode' => array(
				'title'   => __( 'Shipping Debug Mode', 'stream' ),
				'page'    => 'wc-status',
				'tab'     => 'tools',
				'section' => '',
				'type'    => __( 'tool', 'stream' ),
			),
			'template_debug_mode' => array(
				'title'   => __( 'Template Debug Mode', 'stream' ),
				'page'    => 'wc-status',
				'tab'     => 'tools',
				'section' => '',
				'type'    => __( 'tool', 'stream' ),
			),
			'uninstall_data' => array(
				'title'   => __( 'Remove post types on uninstall', 'stream' ),
				'page'    => 'wc-status',
				'tab'     => 'tools',
				'section' => '',
				'type'    => __( 'tool', 'stream' ),
			),
		);

		return apply_filters( 'wp_stream_woocommerce_custom_settings', $custom_settings );
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
		if ( in_array( $record->context, self::$post_types ) && get_post( $record->object_id ) ) {
			if ( $link = get_edit_post_link( $record->object_id ) ) {
				$post_type_name = WP_Stream_Connector_Posts::get_post_type_name( get_post_type( $record->object_id ) );
				$links[ sprintf( _x( 'Edit %s', 'Post type singular name', 'stream' ), $post_type_name ) ] = $link;
			}

			if ( post_type_exists( get_post_type( $record->object_id ) ) && $link = get_permalink( $record->object_id ) ) {
				$links[ __( 'View', 'stream' ) ] = $link;
			}
		}

		$context_labels = self::get_context_labels();
		$option_key     = wp_stream_get_meta( $record, 'option', true );
		$option_page    = wp_stream_get_meta( $record, 'page', true );
		$option_tab     = wp_stream_get_meta( $record, 'tab', true );
		$option_section = wp_stream_get_meta( $record, 'section', true );

		if ( $option_key && $option_tab ) {
			$text = sprintf( __( 'Edit WooCommerce %s', 'stream' ), $context_labels[ $record->context ] );;
			$url  = add_query_arg(
				array( 'page' => $option_page, 'tab' => $option_tab, 'section' => $option_section ),
				admin_url( 'admin.php' ) // Not self_admin_url here, as WooCommerce doesn't exist in Network Admin
			);

			$links[ $text ] = $url . '#wp-stream-highlight:' . $option_key;
		}

		return $links;
	}

	/**
	 * Prevent the Stream Posts connector from logging orders
	 * so that we can handle them differently here
	 *
	 * @filter wp_stream_posts_exclude_post_types
	 * @param  array $post_types Ignored post types
	 * @return array             Filtered post types
	 */
	public static function exclude_order_post_types( $post_types ) {
		$post_types[] = 'shop_order';

		return $post_types;
	}

	/**
	 * Prevent the Stream Comments connector from logging status
	 * change comments on orders
	 *
	 * @filter wp_stream_commnent_exclude_comment_types
	 * @param  array $comment_types Ignored post types
	 * @return array                Filtered post types
	 */
	public static function exclude_order_comment_types( $comment_types ) {
		$comment_types[] = 'order_note';

		return $comment_types;
	}

	/**
	 * Log Order major status changes ( creating / updating / trashing )
	 *
	 * @action transition_post_status
	 */
	public static function callback_transition_post_status( $new, $old, $post ) {
		// Only track orders
		if ( 'shop_order' !== $post->post_type ) {
			return;
		}

		// Don't track customer actions
		if ( ! is_admin() ) {
			return;
		}

		// Don't track minor status change actions
		if ( in_array( wp_stream_filter_input( INPUT_GET, 'action' ), array( 'mark_processing', 'mark_on-hold', 'mark_completed' ) ) || defined( 'DOING_AJAX' ) ) {
			return;
		}

		// Don't log updates when more than one happens at the same time
		if ( $post->ID === self::$order_update_logged ) {
			return;
		}

		if ( in_array( $new, array( 'auto-draft', 'draft', 'inherit' ) ) ) {
			return;
		} elseif ( 'auto-draft' === $old && 'publish' === $new ) {
			$message = _x(
				'%s created',
				'Order title',
				'stream'
			);
			$action  = 'created';
		} elseif ( 'trash' === $new ) {
			$message = _x(
				'%s trashed',
				'Order title',
				'stream'
			);
			$action  = 'trashed';
		} elseif ( 'trash' === $old && 'publish' === $new ) {
			$message = _x(
				'%s restored from the trash',
				'Order title',
				'stream'
			);
			$action  = 'untrashed';
		} else {
			$message = _x(
				'%s updated',
				'Order title',
				'stream'
			);
		}

		if ( empty( $action ) ) {
			$action = 'updated';
		}

		$order           = new WC_Order( $post->ID );
		$order_title     = __( 'Order number', 'stream' ) . ' ' . esc_html( $order->get_order_number() );
		$order_type_name = __( 'order', 'stream' );

		self::log(
			$message,
			array(
				'post_title'    => $order_title,
				'singular_name' => $order_type_name,
				'new_status'    => $new,
				'old_status'    => $old,
				'revision_id'   => null,
			),
			$post->ID,
			$post->post_type,
			$action
		);

		self::$order_update_logged = $post->ID;
	}

	/**
	 * Log order deletion
	 *
	 * @action deleted_post
	 */
	public static function callback_deleted_post( $post_id ) {
		$post = get_post( $post_id );

		// We check if post is an instance of WP_Post as it doesn't always resolve in unit testing
		if ( ! ( $post instanceof WP_Post ) || 'shop_order' !== $post->post_type ) {
			return;
		}

		// Ignore auto-drafts that are deleted by the system, see issue-293
		if ( 'auto-draft' === $post->post_status ) {
			return;
		}

		$order           = new WC_Order( $post->ID );
		$order_title     = __( 'Order number', 'stream' ) . ' ' . esc_html( $order->get_order_number() );
		$order_type_name = __( 'order', 'stream' );

		self::log(
			_x(
				'"%s" deleted from trash',
				'Order title',
				'stream'
			),
			array(
				'post_title'    => $order_title,
				'singular_name' => $order_type_name,
			),
			$post->ID,
			$post->post_type,
			'deleted'
		);
	}

	/**
	 * Log Order minor status changes ( pending / on-hold / failed / processing / completed / refunded / cancelled )
	 *
	 * @action woocommerce_order_status_changed
	 */
	public static function callback_woocommerce_order_status_changed( $order_id, $old, $new ) {
		// Don't track customer actions
		if ( ! is_admin() ) {
			return;
		}

		$old_status = get_term_by( 'slug', $old, 'shop_order_status' );
		$new_status = get_term_by( 'slug', $new, 'shop_order_status' );

		// Don't track new statuses
		if ( ! $old_status ) {
			return;
		}

		$message = _x(
			'%1$s status changed from %2$s to %3$s',
			'1. Order title, 2. Old status, 3. New status',
			'stream'
		);

		$order           = new WC_Order( $order_id );
		$order_title     = __( 'Order number', 'stream' ) . ' ' . esc_html( $order->get_order_number() );
		$order_type_name = __( 'order', 'stream' );
		$new_status_name = strtolower( $new_status->name );
		$old_status_name = strtolower( $old_status->name );

		self::log(
			$message,
			array(
				'post_title'      => $order_title,
				'old_status_name' => $old_status_name,
				'new_status_name' => $new_status_name,
				'singular_name'   => $order_type_name,
				'new_status'      => $new,
				'old_status'      => $old,
				'revision_id'     => null,
			),
			$order_id,
			'shop_order',
			$new_status_name
		);
	}

	/**
	 * Log adding a product attribute
	 *
	 * @action woocommerce_attribute_added
	 */
	public static function callback_woocommerce_attribute_added( $attribute_id, $attribute ) {
		self::log(
			_x(
				'"%s" product attribute created',
				'Term name',
				'stream'
			),
			$attribute,
			$attribute_id,
			'attributes',
			'created'
		);
	}

	/**
	 * Log updating a product attribute
	 *
	 * @action woocommerce_attribute_updated
	 */
	public static function callback_woocommerce_attribute_updated( $attribute_id, $attribute ) {
		self::log(
			_x(
				'"%s" product attribute updated',
				'Term name',
				'stream'
			),
			$attribute,
			$attribute_id,
			'attributes',
			'updated'
		);
	}

	/**
	 * Log deleting a product attribute
	 *
	 * @action woocommerce_attribute_updated
	 */
	public static function callback_woocommerce_attribute_deleted( $attribute_id, $attribute_name ) {
		self::log(
			_x(
				'"%s" product attribute deleted',
				'Term name',
				'stream'
			),
			array(
				'attribute_name' => $attribute_name,
			),
			$attribute_id,
			'attributes',
			'deleted'
		);
	}

	/**
	 * Log adding a tax rate
	 *
	 * @action woocommerce_tax_rate_added
	 */
	public static function callback_woocommerce_tax_rate_added( $tax_rate_id, $tax_rate ) {
		self::log(
			_x(
				'"%4$s" tax rate created',
				'Tax rate name',
				'stream'
			),
			$tax_rate,
			$tax_rate_id,
			'tax',
			'created'
		);
	}

	/**
	 * Log updating a tax rate
	 *
	 * @action woocommerce_tax_rate_updated
	 */
	public static function callback_woocommerce_tax_rate_updated( $tax_rate_id, $tax_rate ) {
		self::log(
			_x(
				'"%4$s" tax rate updated',
				'Tax rate name',
				'stream'
			),
			$tax_rate,
			$tax_rate_id,
			'tax',
			'updated'
		);
	}

	/**
	 * Log deleting a tax rate
	 *
	 * @action woocommerce_tax_rate_updated
	 */
	public static function callback_woocommerce_tax_rate_deleted( $tax_rate_id ) {
		global $wpdb;

		$tax_rate_name = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT tax_rate_name FROM {$wpdb->prefix}woocommerce_tax_rates
				WHERE tax_rate_id = %s
				",
				$tax_rate_id
			)
		);

		self::log(
			_x(
				'"%s" tax rate deleted',
				'Tax rate name',
				'stream'
			),
			array(
				'tax_rate_name' => $tax_rate_name,
			),
			$tax_rate_id,
			'tax',
			'deleted'
		);
	}

	/**
	 * Filter records and take-over our precious data
	 *
	 * @filter wp_stream_record_array
	 *
	 * @param  array $recordarr Record data to be inserted
	 *
	 * @return array            Filtered record data
	 */
	public static function callback_wp_stream_record_array( $recordarr ) {
		foreach ( $recordarr as $key => $record ) {
			// Change connector::posts records
			if ( 'posts' === $record['connector'] && in_array( $record['context'], self::$post_types ) ) {
				$recordarr[ $key ]['connector'] = self::$name;
			} elseif ( 'taxonomies' === $record['connector'] && in_array( $record['context'], self::$taxonomies ) ) {
				$recordarr[ $key ]['connector'] = self::$name;
			} elseif ( 'settings' === $record['connector'] ) {
				$option = isset( $record['meta']['option_key'] ) ? $record['meta']['option_key'] : false;

				if ( $option && isset( self::$settings[ $option ] ) ) {
					return false;
				}
			}
		}

		return $recordarr;
	}

	public static function callback_updated_option( $option_key, $old_value, $value ) {
		$options = array( $option_key );

		if ( is_array( $old_value ) || is_array( $value ) ) {
			foreach ( self::get_changed_keys( $old_value, $value ) as $field_key ) {
				$options[] = $field_key;
			}
		}

		foreach ( $options as $option ) {
			if ( ! array_key_exists( $option, self::$settings ) ) {
				continue;
			}

			self::log(
				__( '"%1$s" %2$s updated', 'stream' ),
				array(
					'label'     => self::$settings[ $option ]['title'],
					'type'      => self::$settings[ $option ]['type'],
					'page'      => self::$settings[ $option ]['page'],
					'tab'       => self::$settings[ $option ]['tab'],
					'section'   => self::$settings[ $option ]['section'],
					'option'    => $option,
					// Prevent fatal error when saving option as array
					'old_value' => maybe_serialize( $old_value ),
					'value'     => maybe_serialize( $value ),
				),
				null,
				self::$settings[ $option ]['tab'],
				'updated'
			);
		}
	}

	public static function get_woocommerce_settings_fields() {
		if ( ! defined( 'WC_VERSION' ) || ! class_exists( 'WC_Admin_Settings' ) ) {
			return;
		}

		if ( ! empty( self::$settings ) ) {
			return self::$settings;
		}

		$settings_cache_key = 'stream_connector_woocommerce_settings_' . sanitize_key( WC_VERSION );

		if ( $settings_transient = get_transient( $settings_cache_key ) ) {
			$settings       = $settings_transient['settings'];
			$settings_pages = $settings_transient['settings_pages'];
		} else {
			global $woocommerce;

			$settings       = array();
			$settings_pages = array();

			foreach ( WC_Admin_Settings::get_settings_pages() as $page ) {
				// Get ID / Label of the page, since they're protected, by hacking into
				// the callback filter for 'woocommerce_settings_tabs_array'
				$info       = $page->add_settings_page( array() );
				$page_id    = key( $info );
				$page_label = current( $info );
				$sections   = $page->get_sections();

				if ( empty( $sections ) ) {
					$sections[''] = $page_label;
				}

				$settings_pages[ $page_id ] = $page_label;

				// Remove non-fields ( sections, titles and whatever )
				$fields = array();

				foreach ( $sections as $section_key => $section_label ) {
					$_fields = array_filter(
						$page->get_settings( $section_key ),
						function( $item ) {
							return isset( $item['id'] ) && ( ! in_array( $item['type'], array( 'title', 'sectionend' ) ) );
						}
					);

					foreach ( $_fields as $field ) {
						$title = isset( $field['title'] ) ? $field['title'] : $field['desc'];
						$fields[ $field['id'] ] = array(
							'title'   => $title,
							'page'    => 'wc-settings',
							'tab'     => $page_id,
							'section' => $section_key,
							'type'    => __( 'setting', 'stream' ),
						);
					}
				}

				// Store fields in the global array to be searched later
				$settings = array_merge( $settings, $fields );
			}

			// Provide additional context for each of the settings pages
			array_walk( $settings_pages, function( &$value ) {
				$value .= ' ' . __( 'Settings', 'stream' );
			});

			// Load Payment Gateway Settings
			$payment_gateway_settings = array();
			$payment_gateways         = $woocommerce->payment_gateways();

			foreach ( $payment_gateways->payment_gateways as $section_key => $payment_gateway ) {
				$title = $payment_gateway->title;
				$key   = $payment_gateway->plugin_id . $payment_gateway->id . '_settings';

				$payment_gateway_settings[ $key ] = array(
					'title'   => $title,
					'page'    => 'wc-settings',
					'tab'     => 'checkout',
					'section' => strtolower( $section_key ),
					'type'    => __( 'payment gateway', 'stream' ),
				);
			}

			$settings = array_merge( $settings, $payment_gateway_settings );

			// Load Shipping Method Settings
			$shipping_method_settings = array();
			$shipping_methods         = $woocommerce->shipping();

			foreach ( $shipping_methods->shipping_methods as $section_key => $shipping_method ) {
				$title = $shipping_method->title;
				$key   = $shipping_method->plugin_id . $shipping_method->id . '_settings';

				$shipping_method_settings[ $key ] = array(
					'title'   => $title,
					'page'    => 'wc-settings',
					'tab'     => 'shipping',
					'section' => strtolower( $section_key ),
					'type'    => __( 'shipping method', 'stream' ),
				);
			}

			$settings = array_merge( $settings, $shipping_method_settings );

			// Load Email Settings
			$email_settings = array();
			$emails         = $woocommerce->mailer();

			foreach ( $emails->emails as $section_key => $email ) {
				$title = $email->title;
				$key   = $email->plugin_id . $email->id . '_settings';

				$email_settings[ $key ] = array(
					'title'   => $title,
					'page'    => 'wc-settings',
					'tab'     => 'email',
					'section' => strtolower( $section_key ),
					'type'    => __( 'email', 'stream' ),
				);
			}

			$settings = array_merge( $settings, $email_settings );

			// Tools page
			$tools_page = array(
				'tools' => __( 'Tools', 'stream' )
			);

			$settings_pages = array_merge( $settings_pages, $tools_page );

			// Cache the results
			$settings_cache = array(
				'settings'       => $settings,
				'settings_pages' => $settings_pages,
			);

			set_transient( $settings_cache_key, $settings_cache, MINUTE_IN_SECONDS * 60 * 6 );
		}

		$custom_settings      = self::get_custom_settings();
		self::$settings       = array_merge( $settings, $custom_settings );
		self::$settings_pages = $settings_pages;

		return self::$settings;
	}

}
