<?php
/**
 * Settings class
 *
 * @author X-Team <x-team.com>
 * @author Shady Sharaf <shady@x-team.com>
 */
class WP_Stream_Settings {

	/**
	 * Settings key/identifier
	 */
	const KEY = 'wp_stream';

	/**
	 * Plugin settings
	 *
	 * @var array
	 */
	public static $options = array();

	/**
	 * Settings fields
	 *
	 * @var array
	 */
	public static $fields = array();

	/**
	 * Public constructor
	 *
	 * @return \WP_Stream_Settings
	 */
	public static function load() {

		// Parse field information gathering default values
		$defaults = self::get_defaults();

		/**
		 * Filter allows for modification of options
		 *
		 * @param  array  array of options
		 * @return array  updated array of options
		 */
		self::$options = apply_filters(
			'wp_stream_options',
			wp_parse_args(
				(array) get_option( self::KEY, array() ),
				$defaults
			)
		);

		// Register settings, and fields
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );

		// Check if we need to flush rewrites rules
		add_action( 'update_option_' . self::KEY, array( __CLASS__, 'updated_option_trigger_flush_rules' ), 10, 2 );

		// Remove records when records TTL is shortened
		add_action( 'update_option_' . self::KEY, array( __CLASS__, 'updated_option_ttl_remove_records' ), 10, 2 );

		add_filter( 'wp_stream_serialized_labels', array( __CLASS__, 'get_settings_translations' ) );
	}

	/**
	 * Return settings fields
	 *
	 * @return array Multidimensional array of fields
	 */
	public static function get_fields() {
		if ( empty( self::$fields ) ) {
			$fields = array(
				'general' => array(
					'title'  => __( 'General', 'stream' ),
					'fields' => array(
						array(
							'name'        => 'log_activity_for',
							'title'       => __( 'Log Activity for', 'stream' ),
							'type'        => 'multi_checkbox',
							'desc'        => __( 'Only the selected roles above will have their activity logged.', 'stream' ),
							'choices'     => self::get_roles(),
							'default'     => array_keys( self::get_roles() ),
						),
						array(
							'name'        => 'role_access',
							'title'       => __( 'Role Access', 'stream' ),
							'type'        => 'multi_checkbox',
							'desc'        => __( 'Users from the selected roles above will have permission to view Stream Records. However, only site Administrators can access Stream Settings.', 'stream' ),
							'choices'     => self::get_roles(),
							'default'     => array( 'administrator' ),
						),
						array(
							'name'        => 'private_feeds',
							'title'       => __( 'Private Feeds', 'stream' ),
							'type'        => 'checkbox',
							'desc'        => sprintf(
								__( 'Users from the selected roles above will be given a private key found in their %suser profile%s to access feeds of Stream Records securely.', 'stream' ),
								sprintf(
									'<a href="%s" title="%s">',
									admin_url( 'profile.php' ),
									esc_attr__( 'View Profile', 'stream' )
								),
								'</a>'
							),
							'after_field' => __( 'Enabled' ),
							'default'     => 0,
						),
						array(
							'name'        => 'records_ttl',
							'title'       => __( 'Keep Records for', 'stream' ),
							'type'        => 'number',
							'class'       => 'small-text',
							'desc'        => __( 'Maximum number of days to keep activity records. Leave blank to keep records forever.', 'stream' ),
							'default'     => 90,
							'after_field' => __( 'days', 'stream' ),
						),
						array(
							'name'        => 'delete_all_records',
							'title'       => __( 'Reset Stream Database', 'stream' ),
							'type'        => 'link',
							'href'        => add_query_arg(
								array(
									'action'          => 'wp_stream_reset',
									'wp_stream_nonce' => wp_create_nonce( 'stream_nonce' ),
								),
								admin_url( 'admin-ajax.php' )
							),
							'desc'        => __( 'Warning: Clicking this will delete all activity records from the database.', 'stream' ),
							'default'     => 0,
						),
					),
				),
				'exclude' => array(
					'title' => __( 'Exclude', 'stream' ),
					'fields' => array(
						array(
							'name'        => 'authors_and_roles',
							'title'       => __( 'Authors & Roles', 'stream' ),
							'type'        => 'user_and_role',
							'desc'        => __( 'No activity will be logged for these authors and roles.', 'stream' ),
						),
						array(
							'name'        => 'connectors',
							'title'       => __( 'Connectors', 'stream' ),
							'type'        => 'chosen',
							'desc'        => __( 'No activity will be logged for these connectors.', 'stream' ),
							'choices'     => array( __CLASS__, 'get_terms_labels' ),
							'param'       => 'connector',
							'default'     => array(),
						),
						array(
							'name'        => 'contexts',
							'title'       => __( 'Contexts', 'stream' ),
							'type'        => 'chosen',
							'desc'        => __( 'No activity will be logged for these contexts.', 'stream' ),
							'choices'     => array( __CLASS__, 'get_terms_labels' ),
							'param'       => 'context',
							'default'     => array(),
						),
						array(
							'name'        => 'actions',
							'title'       => __( 'Actions', 'stream' ),
							'type'        => 'chosen',
							'desc'        => __( 'No activity will be logged for these actions.', 'stream' ),
							'choices'     => array( __CLASS__, 'get_terms_labels' ),
							'param'       => 'action',
							'default'     => array(),
						),
						array(
							'name'        => 'ip_addresses',
							'title'       => __( 'IP Addresses', 'stream' ),
							'type'        => 'chosen',
							'desc'        => __( 'No activity will be logged for these IP addresses.', 'stream' ),
							'class'       => 'ip-addresses',
							'default'     => array(),
						),
					),
				),
			);
			/**
			 * Filter allows for modification of options fields
			 *
			 * @param  array  array of fields
			 * @return array  updated array of fields
			 */
			self::$fields = apply_filters( 'wp_stream_options_fields', $fields );
		}
		return self::$fields;
	}

	/**
	 * Iterate through registered fields and extract default values
	 *
	 * @return array Default option values
	 */
	public static function get_defaults() {
		$fields   = self::get_fields();
		$defaults = array();
		foreach ( $fields as $section_name => $section ) {
			foreach ( $section['fields'] as $field ) {
				$defaults[$section_name.'_'.$field['name']] = isset( $field['default'] )
					? $field['default']
					: null;
			}
		}
		return $defaults;
	}

	/**
	 * Registers settings fields and sections
	 *
	 * @return void
	 */
	public static function register_settings() {

		$sections = self::get_fields();

		register_setting( self::KEY, self::KEY );

		foreach ( $sections as $section_name => $section ) {
			add_settings_section(
				$section_name,
				null,
				'__return_false',
				self::KEY
			);

			foreach ( $section['fields'] as $field_idx => $field ) {
				if ( ! isset( $field['type'] ) ) { // No field type associated, skip, no GUI
					continue;
				}
				add_settings_field(
					$field['name'],
					$field['title'],
					( isset( $field['callback'] ) ? $field['callback'] : array( __CLASS__, 'output_field' ) ),
					self::KEY,
					$section_name,
					$field + array(
						'section'   => $section_name,
						'label_for' => sprintf( '%s_%s_%s', self::KEY, $section_name, $field['name'] ), // xss ok
					)
				);
			}
		}
	}

	/**
	 * Check if we have updated a settings that requires rewrite rules to be flushed
	 *
	 * @param array $old_value
	 * @param array $new_value
	 *
	 * @internal param $option
	 * @internal param string $option
	 * @action   updated_option
	 * @return void
	 */
	public static function updated_option_trigger_flush_rules( $old_value, $new_value ) {
		if ( is_array( $new_value ) && is_array( $old_value ) ) {
			$new_value = ( array_key_exists( 'general_private_feeds', $new_value ) ) ? $new_value['general_private_feeds'] : 0;
			$old_value = ( array_key_exists( 'general_private_feeds', $old_value ) ) ? $old_value['general_private_feeds'] : 0;
			if ( $new_value !== $old_value ) {
				delete_option( 'rewrite_rules' );
			}
		}
	}

	/**
	 * Compile HTML needed for displaying the field
	 *
	 * @param  array  $field  Field settings
	 * @return string         HTML to be displayed
	 */
	public static function render_field( $field ) {

		$output = null;

		$type          = isset( $field['type'] ) ? $field['type'] : null;
		$section       = isset( $field['section'] ) ? $field['section'] : null;
		$name          = isset( $field['name'] ) ? $field['name'] : null;
		$class         = isset( $field['class'] ) ? $field['class'] : null;
		$placeholder   = isset( $field['placeholder'] ) ? $field['placeholder'] : null;
		$description   = isset( $field['desc'] ) ? $field['desc'] : null;
		$href          = isset( $field['href'] ) ? $field['href'] : null;
		$after_field   = isset( $field['after_field'] ) ? $field['after_field'] : null;
		$title         = isset( $field['title'] ) ? $field['title'] : null;
		$current_value = self::$options[$section . '_' . $name];

		if ( is_callable( $current_value ) ) {
			$current_value = call_user_func( $current_value );
		}

		if ( ! $type || ! $section || ! $name ) {
			return;
		}

		if ( 'multi_checkbox' === $type
			&& ( empty( $field['choices'] ) || ! is_array( $field['choices'] ) )
		) {
			return;
		}

		switch ( $type ) {
			case 'text':
			case 'number':
				$output = sprintf(
					'<input type="%1$s" name="%2$s[%3$s_%4$s]" id="%2$s_%3$s_%4$s" class="%5$s" placeholder="%6$s" value="%7$s" /> %8$s',
					esc_attr( $type ),
					esc_attr( self::KEY ),
					esc_attr( $section ),
					esc_attr( $name ),
					esc_attr( $class ),
					esc_attr( $placeholder ),
					esc_attr( $current_value ),
					$after_field // xss ok
				);
				break;
			case 'checkbox':
				$output = sprintf(
					'<label><input type="checkbox" name="%1$s[%2$s_%3$s]" id="%1$s[%2$s_%3$s]" value="1" %4$s /> %5$s</label>',
					esc_attr( self::KEY ),
					esc_attr( $section ),
					esc_attr( $name ),
					checked( $current_value, 1, false ),
					$after_field // xss ok
				);
				break;
			case 'multi_checkbox':
				$output = sprintf(
					'<div id="%1$s[%2$s_%3$s]"><fieldset>',
					esc_attr( self::KEY ),
					esc_attr( $section ),
					esc_attr( $name )
				);
				// Fallback if nothing is selected
				$output .= sprintf(
					'<input type="hidden" name="%1$s[%2$s_%3$s][]" value="__placeholder__" />',
					esc_attr( self::KEY ),
					esc_attr( $section ),
					esc_attr( $name )
				);
				$current_value = (array) $current_value;
				$choices = $field['choices'];
				if ( is_callable( $choices ) ) {
					$choices = call_user_func( $choices );
				}
				foreach ( $choices as $value => $label ) {
					$output .= sprintf(
						'<label>%1$s <span>%2$s</span></label><br />',
						sprintf(
							'<input type="checkbox" name="%1$s[%2$s_%3$s][]" value="%4$s" %5$s />',
							esc_attr( self::KEY ),
							esc_attr( $section ),
							esc_attr( $name ),
							esc_attr( $value ),
							checked( in_array( $value, $current_value ), true, false )
						),
						esc_html( $label )
					);
				}
				$output .= '</fieldset></div>';
				break;
			case 'file':
				$output = sprintf(
					'<input type="file" name="%1$s[%2$s_%3$s]" id="%1$s_%2$s_%3$s" class="%4$s">',
					esc_attr( self::KEY ),
					esc_attr( $section ),
					esc_attr( $name ),
					esc_attr( $class )
				);
				break;
			case 'link':
				$output = sprintf(
					'<a id="%1$s_%2$s_%3$s" class="%4$s" href="%5$s">%6$s</a>',
					esc_attr( self::KEY ),
					esc_attr( $section ),
					esc_attr( $name ),
					esc_attr( $class ),
					esc_attr( $href ),
					esc_attr( $title )
				);
				break;
			case 'chosen' :
				if ( ! isset ( $current_value ) ){
					$current_value = array();
				}
				if ( ( $key = array_search( '__placeholder__', $current_value ) ) !== false ){
					unset( $current_value[ $key ] );
				}

				$data_values     = array();
				$selected_values = array();
				if ( isset( $field[ 'choices' ] ) ){
					$choices = $field[ 'choices' ];
					if ( is_callable( $choices ) ){
						$param   = ( isset( $field[ 'param' ] ) ) ? $field[ 'param' ] : null;
						$choices = call_user_func( $choices, $param );
					}
					foreach ( $choices as $key => $value ) {
						$data_values[ ] = array( 'id' => $key, 'text' => $value, );
						if ( in_array( $key, $current_value ) ){
							$selected_values[ ] = array( 'id' => $key, 'text' => $value, );
						}
					}
					$class .= 'with-source';
				} else {
					foreach ( $current_value as $value ) {
						if ( $value == '__placeholder__' ){
							continue;
						}
						$selected_values[ ] = array( 'id' => $value, 'text' => $value, );
					}
				}

				$output  = sprintf( '<div id="%1$s[%2$s_%3$s]">', esc_attr( self::KEY ), esc_attr( $section ), esc_attr( $name ) );
				$output .= sprintf( '<input type="hidden" data-values=\'%1$s\' data-selected=\'%2$s\' value="%3$s" class="chosen-select %4$s" data-select-placeholder="%5$s-%6$s-select-placeholder"  />', json_encode( $data_values ), json_encode( $selected_values ), esc_attr( implode( ',', $current_value ) ), $class, esc_attr( $section ), esc_attr( $name ) );
				// Fallback if nothing is selected
				$output .= sprintf( '<input type="hidden" name="%1$s[%2$s_%3$s][]" class="%2$s-%3$s-select-placeholder" value="__placeholder__" />', esc_attr( self::KEY ), esc_attr( $section ), esc_attr( $name ) );
				$output .= '</div>';
				break;
		}
		$output .= ! empty( $description ) ? sprintf( '<p class="description">%s</p>', $description /* xss ok */ ) : null;

		return $output;
	}

	/**
	 * Render Callback for post_types field
	 *
	 * @param array $field
	 *
	 * @internal param $args
	 * @return void
	 */
	public static function output_field( $field ) {
		$method = 'output_' . $field['name'];
		if ( method_exists( __CLASS__, $method ) ) {
			return call_user_func( array( __CLASS__, $method ), $field );
		}

		$output = self::render_field( $field );
		echo $output; // xss okay
	}

	/**
	 * Get an array of user roles
	 *
	 * @return array
	 */
	public static function get_roles() {
		$wp_roles = new WP_Roles();
		$roles    = array();

		foreach ( $wp_roles->get_names() as $role => $label ) {
			$roles[ $role ] = translate_user_role( $label );
		}

		return $roles;
	}

	/**
	 * Get an array of registered Connectors
	 *
	 * @return array
	 */
	public static function get_connectors() {
		return WP_Stream_Connectors::$term_labels['stream_connector'];
	}

	/**
	 * Get an array of registered Connectors
	 *
	 * @return array
	 */
	public static function get_default_connectors() {
		return array_keys( WP_Stream_Connectors::$term_labels['stream_connector'] );
	}

	/**
	 * Function will return all terms labels of given column
	 *
	 * @param $column string  Name of the column
	 * @return array
	 */
	public static function get_terms_labels( $column ){
		$return_labels = array();
		if ( isset ( WP_Stream_Connectors::$term_labels[ 'stream_' . $column ] ) ) {
			$return_labels = WP_Stream_Connectors::$term_labels[ 'stream_' . $column ];
			ksort( $return_labels );
		}
		return $return_labels;
	}
	/**
	 * Get an array of active Connectors
	 *
	 * @return array
	 */
	public static function get_active_connectors() {
		$excluded_connectors = self::get_excluded_connectors();
		$active_connectors   = array_intersect( $excluded_connectors, array_keys( self::get_terms_labels( 'connectors' ) ) );
		$active_connectors   = wp_list_filter(
			$active_connectors,
			array( '__placeholder__' ),
			'NOT'
		);

		return $active_connectors;
	}

	/**
	 * Get an array of excluded connectors
	 * @uses   WP_Stream_Settings::get_excluded_by_key
	 * @return array
	 */
	public static function get_excluded_connectors(){
		return self::get_excluded_by_key( 'connectors' );
	}

	/**
	 * Get an array of excluded contexts
	 * @uses   WP_Stream_Settings::get_excluded_by_key
	 * @return array
	 */
	public static function get_excluded_contexts(){
		return self::get_excluded_by_key( 'contexts' );
	}

	/**
	 * Get an array of excluded actions
	 * @uses   WP_Stream_Settings::get_excluded_by_key
	 * @return array
	 */
	public static function get_excluded_actions(){
		return self::get_excluded_by_key( 'actions' );
	}

	/**
	 * Get an array of excluded IP addresses
	 * @uses   WP_Stream_Settings::get_excluded_by_key
	 * @return array
	 */
	public static function get_excluded_ip_addresses(){
		return self::get_excluded_by_key( 'ip_addresses' );
	}


	public static function get_excluded_by_key( $key ){
		$option_name     = 'exclude_' . $key;
		$excluded_values = (isset(self::$options[$option_name]))?self::$options[$option_name] :array();
		if ( is_callable( $excluded_values ) ) {
			$excluded_values = call_user_func( $excluded_values );
		}
		$excluded_values = wp_list_filter(
			$excluded_values,
			array( '__placeholder__' ),
			'NOT'
		);

		return $excluded_values;
	}

	/**
	 * Get translations of serialized Stream settings
	 *
	 * @filter wp_stream_serialized_labels
	 * @return array Multidimensional array of fields
	 */
	public static function get_settings_translations( $labels ) {
		if ( ! isset( $labels[self::KEY] ) ) {
			$labels[self::KEY] = array();
		}

		foreach ( self::get_fields() as $section_slug => $section ) {
			foreach ( $section['fields'] as $field ) {
				$labels[self::KEY][sprintf( '%s_%s', $section_slug, $field['name'] )] = $field['title'];
			}
		}

		return $labels;
	}

	/**
	 * Remove records when records TTL is shortened
	 *
	 * @param array $old_value
	 * @param array $new_value
	 *
	 * @action update_option_wp_stream
	 * @return void
	 */
	public static function updated_option_ttl_remove_records( $old_value, $new_value ) {
		$ttl_before = isset( $old_value['general_records_ttl'] ) ? (int) $old_value['general_records_ttl'] : -1;
		$ttl_after  = isset( $new_value['general_records_ttl'] ) ? (int) $new_value['general_records_ttl'] : -1;

		if ( $ttl_after < $ttl_before ) {
			/**
			 * Action assists in purging when TTL is shortened
			 */
			do_action( 'wp_stream_auto_purge' );
		}
	}
}
